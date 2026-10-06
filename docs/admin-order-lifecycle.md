# F4: Admin order lifecycle and inventory

## Scope

Local implementation only. No database migration is required. No changes to the F3 enable flag, StripeService, reconciliation claims, or checkout
idempotency. Remediation R1 minimally changes StripeStateApplier refund completion
so financial refunds cannot imply physical receipt of dispatched goods. Generic Admin order creation is disabled because it does not create
items/totals through OrderService and would bypass inventory reservation.

## Transitions

Order status and payment status are read-only on the edit form. Separate confirmed
actions call AdminOrderLifecycle, using the current locked database state:

| Action | Allowed state | Result |
| --- | --- | --- |
| Process | new/pending | processing |
| Ship | processing | shipped, shipped_at recorded once |
| Deliver | shipped | delivered, delivered_at recorded once |
| Confirm cash collection | active cash order, pending/paid payment, no unresolved/completed refund or restored stock | paid; fulfilment status unchanged |
| Cancel unpaid | new/pending/processing, payment not paid, no unresolved refund or active return | cancelled; cash pending becomes failed; stock restored once |
| Repeat cancellation | cancelled with otherwise eligible state | no extra stock or business receipt |
| Full Stripe refund | paid Stripe before dispatch, no return, no restored stock | durable initiated intent, then existing Stripe refund mechanism |

Stripe orders require confirmed paid status to progress through fulfilment.
Cash orders may be fulfilled while payment is pending, and collection may be
recorded later without resetting delivered status. Repeating the same fulfilment
or cash collection operation is a no-op, provided no incompatible refund/return
state has appeared. Backwards transitions, skipped stages, cancelled reactivation,
manual Stripe payment confirmation, and arbitrary financial form writes are denied.
Legacy `new` is accepted as equivalent to pending only for the initial transition.
Dispatched goods use the existing returns workflow, not whole-order cancellation.

The full-refund button remains on ViewOrder. The edit form no longer triggers an
external refund as an incidental effect of Save. The refund intent is committed
before HTTP, so shipping either wins first (refund rejected) or loses to the intent
(shipping rejected). HTTP remains outside database transactions. A lost reply
leaves initiated intent durable; retry uses StripeService's existing idempotency
key. Never automatically clear an uncertain refund. A known failed refund requires
operator review; F4 does not invent a retry/reset policy for existing Stripe IDs.

## Locking

New Admin actions acquire checkout_attempt (if present), then order. The refund
intent uses that same order, commits, then performs HTTP. Existing processor
application uses attempt -> return (when present) -> order -> sorted products.
OrderService::cancel acquires order before reading payment, then product locks
in ascending ID order for restoration. It never acquires attempt/return locks
after order. Order-only operations use a suffix of the established lock order.
Return stock restoration also sorts product locks and validates restoration bounds.
Order status, stock marker, inventory increments and durable email receipts share
one transaction. Failure rolls them back together. No SMTP is sent by new actions.

Admin product editing locks only the product row; it does not request order locks.
The Livewire originalStock property is Locked (client cannot alter its baseline).
If submitted stock equals baseline, it is excluded from UPDATE: editing another
field does not undo reservations. If the user changes stock, current stock must
still equal baseline under the product lock, otherwise the entire save is rejected.
After a successful save, the displayed stock and baseline are refreshed. This is
a comparison of quantity, not a timestamp/version column; net-zero intervening
movements do not cause rejection because they do not alter available quantity.

## Commercial invariants

- Stock: integer 0..2147483647, matching the signed database integer column.
- Purchase cost: >=0. Selling price: >0. Sale price: null or >0.
- Monetary input: at most two decimal places and <=99999999.99 (DECIMAL(10,2)).
- No new rule requires sale_price < selling_price: existing code does not enforce
  that relationship and bases the reduction badge on historical reference price.
- Zero selling/sale price is rejected: checkout is a paid-goods flow; free-product
  handling is not implemented. Use null to remove a promotional price. Confirm
  this scope if zero-price goods are intended before accepting the change.
- Raw input is validated before Eloquent decimal casts can round it.
- OrderService revalidates persisted product values under lock, checks quantity
  aggregation and computes bounded subtotal in cents before multiplication.
- Empty orders, invalid shipping amounts and totals outside the monetary column
  capacity are rejected before reservation. Restoration validates positive integer
  quantities, current stock and the resulting stock bound for orders and returns.

Model events protect ordinary Eloquent saves; raw SQL can bypass them. Checkout
validation and restoration guards do not rely on Admin UI validation. No existing
development/production rows were cleaned up or changed as part of this work.

## Test scope and limitations

AdminOrderLifecycleTest covers terminal cancellation, repeats, both serial orders
of payment versus cancellation, the read boundary, stale form spoofing, rollback,
cash fulfilment/collection, Stripe restrictions, intent fencing and lost replies.
The beforeExecuting hook is a deterministic same-connection simulation; its write
rolls back with the rejected transaction. It is NOT proof of cross-connection
commit visibility. A separate stale-object test commits payment before cancellation.
AdminProductStockTest uses actual OrderService checkout between loading and saving
a Livewire product page. It covers implicit preservation, explicit conflict,
subsequent valid editing, model/UI/service validation and large-total rejection.
OrderAdminRefundTest retains the actual mocked Stripe response and stale edit
regression, now via the explicit refund action. BankRefundTransactionTest adds
restoration-overflow rollback and retry to its existing atomicity coverage.

SQLite does not implement MySQL SELECT FOR UPDATE row locks. The suite proves
application guards, deterministic interleavings, rollback and regression behavior;
it does not prove InnoDB waiting, isolation or absence of real deadlocks.

## Recommended MySQL/InnoDB gate before commit

Run only on a disposable local test schema, with a dedicated account that has no
access to development or production databases. Require APP_ENV=testing, a loopback
host, an explicitly confirmed disposable database name, InnoDB tables and the same
isolation level as deployment. Mock all Stripe HTTP and mail, keep F3 OFF. Do not
point the existing UsesCommittedDatabase suite at MySQL: it intentionally requires
SQLite :memory:. Prepare a separate two-process harness with explicit barriers,
independent connections and a finite lock-wait timeout; do not rely on sleeps.

Gate scenarios and required assertions:

1. Hold order lock while committing payment; start cancellation behind it. After
   release, cancellation rejects, paid/processing remain, stock remains reserved.
2. Hold cancellation before commit; start payment behind it. After release, cancelled
   remains terminal, stock restores once; late processor handling follows F1-F3.
3. Two cancellations contend for one order: exactly one restoration and receipt.
4. Two distinct customers buy the last unit: only one order reserves it; stock >=0.
5. Checkout holds product lock while stale Admin Save waits: unchanged field keeps
   the reduced stock; explicit adjustment rejects; no partial metadata write.
6. Admin adjustment commits first, then checkout: checkout sees current quantity.
7. Two orders/returns touch products in inverse item order: lock acquisition is
   ascending product ID and both finish or retry without partial business effects.
8. Refund intent versus shipping, both orders of acquisition: either shipping wins
   and no refund HTTP occurs, or intent wins and shipping is denied. Lost refund
   response never re-enables fulfilment; retry has the same idempotency key.

Record database version/isolation, barriers reached, observed lock waits, process
exit codes, final quantities/statuses/markers and receipt counts. Any unexpected
deadlock, lost update, duplicate restore or invariant violation blocks commit.
This gate is a recommendation/specification; it has not been executed here.


## Remediation R1

- Partial product form refreshes update the inventory baseline only when they also
  replace the stock field. Explicit stock refresh intentionally discards that field's
  unsaved adjustment. Metadata-only refresh preserves both the old field and baseline.
- Cash collection permits requested/approved/received/rejected returns without a
  refund in flight or completed. It locks existing returns in ID order before the
  order, matching bank-refund serialization. A refunded return, restored stock, or
  initiated/processing/completed refund requires manual clarification instead of
  fabricating a new paid state. Collection never changes delivery status or stock.
- Full Stripe financial refunds keep shipped/delivered status and timestamps,
  record refunded/completed/refunded_at, and leave stock unchanged. Dispatch
  timestamps also fence legacy inconsistent statuses. Unclassified active returns
  likewise prohibit full-order restoration. The result is manual_review and the
  order detail page explains the physical-return check even with F3 disabled.
- Webhook and recovery share this completion rule. Repeated observations preserve
  timestamps and durable receipt uniqueness. Failed observations cannot undo a
  completed refund. Before dispatch, legitimate full refunds still cancel and
  release the reservation once.
- A received, validated ReturnRequest with explicit refund correlation continues
  through the existing per-return restoration marker. No new automatic matching,
  refund issuance, or physical-return workflow is invented. An unclassified manual
  Stripe refund remains manual until the existing correlation/receipt requirements
  are satisfied; receiving money alone never satisfies them.

Review #2 must precede the MySQL gate. Add independent-process gate scenarios for
cash collection versus bank refund (return -> order -> products), unclassified
financial refund versus received-return application, and replay through F3 after
an F3-OFF webhook. Assert no duplicate stock/receipts and preserved dispatch history.
Include partial stock refresh and two stale Admin forms in the UI regression gate.


## Remediation R2

Order::hasDispatchHistory is the shared rule: shipped/delivered status OR any
shipped_at/delivered_at timestamp. Cancellation, reservation release, failed-payment
handling, Stripe full-refund application, Admin eligibility and the physical-return
notice use this rule. Legacy processing rows with dispatch history cannot release
stock through cancellation or direct OrderService::restoreStock. Received and
validated ReturnRequest restoration remains distinct from reservation release.

A narrowly scoped exception records actual delivery after a completed full Stripe
refund: status must be shipped and shipped_at must exist; order stock must not have
been restored. It sets delivered/delivered_at only. A repeated delivered action
with the same shipping history and existing delivered_at is a no-op. Processing or
cancelled refunded orders and shipped rows without shipping evidence are rejected.
Payment/refund fields, stock and manual-review fields are unchanged; the Admin
physical-return notice stays visible. The normal customer return endpoint becomes
available, subject to its existing ownership, deadline and quantity rules.

Return requests validate canonical positive integer item IDs before processing.
Aliases such as 01/1, fractional IDs and overflow IDs are rejected. Positive selected
quantities are sorted numerically, then order-owned positions are fetched under one
orderBy(id)->lockForUpdate query and processed in that order. Missing/foreign IDs
reject the whole transaction; quantity failures roll back all return positions.
No automatic transaction retry was added: the existing closure stores uploaded
photos, which are not rolled back by SQL. Retrying that entire closure could repeat
filesystem effects. The SQL transaction still rolls back on conflict; MySQL gate
must observe rollback and the caller's explicit retry. SQL ORDER BY is not itself
proof of the optimizer's physical lock acquisition order; inspect actual InnoDB
waits/execution plans in the gate.

Additional mandatory gate cases after independent Review #3:
- processing + delivered_at -> financial refund -> cancel/direct release: zero restore;
- processing + shipped_at -> financial refund -> cancel/direct release: zero restore;
- shipped -> external full refund -> actual delivered: unchanged financial/stock state,
  one delivery timestamp/receipt, visible physical-return notice, normal return request;
- concurrent ReturnRequest submissions with inverse order-item input ordering, including
  quantity contention and injected deadlock/timeout: no partial request or duplicate quantity.

Keep all R1 gate cases, real MySQL/InnoDB, disposable local schema, independent
connections/processes and deterministic barriers. F3 default remains OFF. This gate
has not been executed during remediation.
