# F3 Stripe reconciliation (local implementation; disabled by default)

## Scope and operation

`stripe:reconcile` runs only when `stripe_reconciliation.enabled` is true.
The scheduler calls it every five minutes with `withoutOverlapping(10)`.
No Stripe Session creation, expiration, payment, or refund POST is performed by this command.
There is no idempotency-key rotation, including after F2's 23-hour boundary.
The six new Order fields describe scheduling/observations, never financial state.

The command compares the previous next-check timestamp and claim identity to claim a due Order.
Each claim receives a fresh UUID in `stripe_reconcile_claim`; `stripe_reconcile_next_at` only controls expiry.
The exact UUID is passed through inspect into StripeStateApplier::recovered. After attempt/Return/Order
locks, before any binding, stock, status, refund, late marker or outbox write, the fresh Order must
still own that UUID and next_at must be strictly later than Laravel now(). Equality is expired.
Ownership is checked at entry under Order lock; that lock prevents reclaim/invalidation until the
short business transaction commits. The lease is never extended after HTTP. Expired or invalidated
workers return lease_expired/claim_lost without business or operational writes. Completion also
compares the exact UUID and unexpired lease, then clears it atomically. Equal expiry timestamps
cannot cause ABA. A commit followed by lost completion is recovered through idempotent guards;
the aggregate outcome completion_not_owned does not claim that business work rolled back.
An administrative refund schedules a prompt check and clears the old UUID before HTTP.
A claim expires after 240 seconds by default; interrupted work becomes eligible again.
A stale worker cannot overwrite a newer operational claim. Business effects remain protected
by row locks and terminal-state guards even if two workers reach the same Order.
A run has a 180-second deadline, 100 enumeration GET allowance (minimum four), one bounded
1014-read validation allowance, and a 50-Order batch. See remediation 4 below for the budget semantics.
Stripe GET timeouts are 3 seconds to connect / 10 seconds per request, with SDK retries disabled;
retry is handled by the durable scheduler. One in-flight request can exceed the run deadline.
Keep the lease longer than runtime + maximum in-flight request duration and lock/DB overhead.

Due rows are ordered by next check and ID. Unchecked rows are checked first, then scheduled away.
Monitor initial historical backfill and queue age: configured throughput is finite and this
implementation does not promise bounded recovery latency under sustained overload.
An administrative refund POST queues its Order for a prompt GET check before dispatch, including
when its response is lost. Stable paid/refunded/expired rows are revisited weekly to discover out-of-band refunds.
Waiting/unknown/incomplete work returns after five minutes. Manual cases return daily.
Errors back off 5m / 15m / 1h / 6h / 24h plus deterministic ID jitter; they are never abandoned.
Manual results and eight-or-more consecutive errors produce safe-ID warnings, not raw payload logs.
Command output aggregates outcomes; production alert routing still requires operator configuration.

## Discovery and validation

A known Session is retrieved by ID. An unknown Session requires durable F2 attempt parameters.
Session discovery pages through a bounded creation window: first initiation minus 60 seconds
through first initiation + 23 hours + 60 seconds (bounded by current time).
A known PI further restricts the list. No metadata/UUID/idempotency-key server-side filter is assumed.
A matching attempt or Order metadata ID identifies candidates; full validation is still required.
Zero candidates keeps the order unresolved, incomplete scans retain their cursor, and multiple
candidates require manual review. A resumed discovery completes a second pass over the same frozen
window and parameters. Both ordered identity/correlation digests must match. A complete current-run
verification then checks uniqueness again before a fresh Session GET and locked application validation. A checkpoint alone never
authorizes effects. Legacy orders without durable F2 correlation are manual-only.

Cursors retain the scan window, last ID, candidate IDs, and only allowlisted refund correlation /
financial fields. They do not retain customer payloads or payment URLs. Cursors are checkpointed
when a run yields or catches an exception. A hard crash can replay the current run's pages;
it cannot apply an incomplete scan. The context hash binds Order/owner/Session/PI, monetary values,
item IDs/products/names/prices/quantities, attempt/token/owner/request hash/start time, frozen
parameters, configured mode, credential identity hash and page size. Discovery retains its original
inclusive creation window and PI filter. No credential is stored in the cursor.
A changed context clears the cursor and requires manual review, including a change during GETs that
is detected under application locks. Version-3 refund checkpoints bind the Session and Charge financial
identity. Enumeration progresses over bounded runs. Finalization refreshes every Refund by ID in the
current run and verifies the entire current ordered list and mutable values. A changed list requires
manual review within the original global cap. Fresh Session/PI/Charge
reads and the validated refund sum are required before effects; cached statuses alone never authorize
them. Consumed cursors are discarded on business rollback, permitting an automatic technical retry.
Scans are globally limited to 1,000 pages, 24 hours and 1,000 refunds per Order. Changed context,
old cursor versions or exhausted global limits require manual handling; larger-than-run scans can
progress across runs without applying partial results.

Under locks, validation checks configured livemode, object kinds/IDs, attempt UUID, Order/owner,
request hash, client reference, canonical frozen-parameter fingerprint, mode/status, RON,
integer cents, exact local item names/prices/quantities and shipping against frozen parameters,
Order total, Session amount, Session -> PI ID, PI status/amount/received amount/currency and
existing local ID compatibility/other-Order collisions. All GETs use the same configured Stripe
account credential as checkout; do not change accounts or modes without reviewing old records.
An open Session's URL can be repaired only for HTTPS hosts in `checkout_hosts` (default checkout.stripe.com).

For a succeeded PI, fetch its latest Charge and every refund page before a paid receipt is created.
Validate Charge ID/PI/context/currency/amount/paid flag, refund ID/Charge/PI/context/currency/status/
positive amount, and that succeeded refund amounts equal Charge.amount_refunded.
Return metadata must identify the same Order and a real Return, with the recorded refund amount
and a compatible refund ID. Every automatic Return path validates explicit ReturnItems under locks:
same OrderItem/Order, existing referenced Product, positive integer quantity, exact original unit price
and line amount, aggregate active/refunded Return claims no greater than ordered quantity, compatible
owner/method/currency/status and Return amount. Shipping may be allocated once to a withdrawal
completing all withdrawn quantities. Empty/orphaned/foreign/excessive positions are manual-only.
ReturnItems have no separate product_id: Product identity comes exclusively from their OrderItem FK.
A non-null but historically incorrect Product FK cannot be reconstructed from old checkout data;
this guard verifies the recorded association, not historical provenance. A known full Order refund ID must occur in the snapshot.
Unclassified partial refunds, mixed Order/Return refunds, incompatible IDs and unsafe legacy data
require manual review. Multiple safely identified Return refunds are applied individually.

## State application and P1 guard

`StripeStateApplier` is shared by webhooks, refund POST responses, and GET reconciliation.
Refund guards read current rows under locks: attempt -> Return (when relevant) -> Order.
Recovery with multiple Returns locks their IDs in ascending order before the Order.
Every F3 common application path pre-acquires all Order Product locks in ascending ID order before
calling legacy restoration helpers. Current ReturnItem locking reads serialize quantities with the
OrderItem lock used by Return creation. The legacy empty-Return all-items fallback remains intact
outside automatic Stripe application; F3 never invokes it for an unsafe Return. Unsafe application
preserves stock/status/refund completion and emits no false completed receipt; it records manual
attention with existing Order/Return/PI correlation for operators.
Completed refunds never regress to processing/failed or stale commercial/payment states.
Failed refunds cannot regress to processing; succeeded remains the strongest authoritative result.
Order refunds cannot restore stock through a completed Return: active/refunded Returns force manual review.
Stock markers and F1 unique event keys keep restoration and receipt recording idempotent.
All local commercial writes and outbox writes roll back together on failure.

GET recovery pays only complete/paid + succeeded PI + validated Charge/refund snapshot.
Processing/action/capture/confirmation/payment-method states keep stock reserved.
Expired/unpaid without a PI, or with canceled PI, releases stock; completed + canceled PI also releases.
A retryable requires_payment_method PI is not evidence of permanent asynchronous failure.
Current local paid/refunded state produces terminal_local_state for unpaid/failure observations,
not expired_or_canceled or async_failed. Financial terminal guards remain independent of claims.
For complete/unpaid + requires_payment_method, GET/list Events searches for a definitive
checkout.session.async_payment_failed snapshot (same account, livemode, Session/attempt/owner/
metadata/amount/PI, integer timestamp in the attempt window). Webhook and F3 share the same failure
policy: current Session must still be complete/unpaid and PI requires_payment_method, with zero
received amount and a failed latest Charge matching last_payment_error.charge. The event must be
strictly later than that Charge's creation timestamp. Equal timestamps, absent error/Charge correlation
or other ambiguity require manual review; timestamp ordering alone does not prove causality.
Charge retrieval is followed by another PI GET to detect progress during validation. These reads
occur outside transactions, followed by locked local context/terminal-state revalidation. A current
processing/action/confirmation/capture PI waits; a validated current paid/succeeded state wins.
A fresh retryable case without proof waits;
after 24 hours from initiation, absent proof escalates to async_failure_manual_review with stock
still reserved. Invalid/ambiguous proof is manual_review. Age alone never cancels or releases stock.
The Events scan is bounded to the last 29 days because Stripe retains listable events for 30 days;
older or missing/version-incompatible evidence requires manual handling. Existing paid/refunded
state wins over older failure evidence. Signature verification authenticates the event but does not
by itself authorize cancellation. Missing optional Stripe error correlation stays conservative.
A late paid canceled Order gets its PI and late-payment marker, remains canceled, and requires review.
A full Order refund cancels and restores Order stock. A Return refund restores only Return quantities;
a fully refunded Charge through Returns marks payment refunded while preserving delivery status.

Webhook/admin sources do not require a reconciliation claim. Administrative refund scheduling
invalidates the old UUID before its POST; the stale reconciler sees that under Order lock. Webhook
financial transitions still use current locked terminal guards. F2 retry/binding changes are checked
against the fresh context before reconciliation application; no F2 code was changed.

A verified webhook must also pass the common strict context validator for both Event envelope and
business object. Missing/null/nonboolean/mismatched livemode is rejected before binding or effects.
For a legitimate payload mismatch the endpoint acknowledges with HTTP 200 and
ignored=incompatible_context, logging only event ID and event type. An absent/invalid canonical
local mode or mode/key disagreement returns HTTP 503 without effects, allowing delivery retry.
An invalid signature remains HTTP 400. Async validation HTTP failures also return 503; an authentic
but ambiguous commercial failure records manual_review without cancellation. Refund/Session/Charge shared application
guards and Charge refund entries use the same context policy, including admin responses.

Ordinary paid/expired webhooks retain F2's separate durable missing-Session binding transaction before
business application: a later stock/outbox rollback does not lose the external Session association.
Async-failure binding now occurs only after authoritative GET validation, atomically with its effects.
GET reconciliation performs validated binding and business effects atomically.

## HTTP/locking boundary and remaining gate

All F3 reads and administrative refund POSTs reject DB::transactionLevel() > 0 before dispatch.
Tests use a fake Stripe SDK transport that also rejects any HTTP under a transaction.
There is one deliberately unchanged preexisting exception: F2 CheckoutAttempts::stripeSession
still makes its idempotent Session-create POST under attempt/Order locks. F3 never calls that path.
This document does not claim that every preexisting Stripe operation runs at transaction level zero.

SQLite tests demonstrate controlled ordering, stale snapshots, rollback, duplicate receipts,
API failures, pagination/resume, lease expiry, and stale-worker operational completion.
They do not demonstrate InnoDB row locks, real simultaneous processes, deadlocks, or process kill
mid-commit. These remain a separate MySQL/InnoDB gate, including canonical JSON key reordering,
checkout/webhook/reconciler races, Return -> Order interactions with R1, and worker crash/lease expiry.
R1 bank-refund and other legacy helpers still acquire Products in item iteration order and can
participate in cross-Order deadlocks. They were deliberately not refactored here. InnoDB must verify
Return creation/item locks, F3/R1/F1 lock interactions and bounded transaction retries.
This remediation made no Stripe test/live account API requests; API behavior uses local SDK fakes.
Public Stripe documentation was read separately.
This does not add durable refund-initiation attempts or extend Stripe refund idempotency-key
retention. Do not blindly repeat an ambiguous refund POST after key retention may have elapsed;
GET reconciliation/manual verification must establish the external result first.
Manual review describes incomplete local business application: a refund may already exist externally,
including when an administrative POST response fails Return validation. It does not mean that Stripe
has not refunded the customer. Do not use local incomplete status alone to initiate another refund.
F1 receipts are deduplicated; SMTP exactly-once delivery is not guaranteed after acknowledgement loss.

## Future rollout (not performed here)

1. Adversarial review, then isolated MySQL gate before release authorization.
2. Apply `2026_10_04_000002_add_stripe_reconciliation_to_orders.php` before enabling the command;
   the deployed F2 migration stays unchanged. This unpublished migration includes the UUID claim column.
   Leave the columns on application rollback.
3. Activation is strict: only an explicit true value enables reconciliation (Laravel env true or
   the exact string true); absent/invalid/off/yes/1 stays disabled. Command and scheduler require
   actual boolean true even if configuration is overridden. Keep STRIPE_RECONCILIATION_ENABLED false until account/mode, timeouts/lease, cache scheduler locks,
   cPanel cron, backfill throughput, and log alert routing are verified.
4. BEFORE installing this code/configuration cache, provision STRIPE_MODE as exactly live or test,
   coherent with STRIPE_SECRET and STRIPE_WEBHOOK_SECRET. There is no default or PHP truthiness:
   absent/null/empty/boolean/false/true/0/1/off/unknown modes fail closed. The explicit mode is the
   Stripe-wide source for checkout, webhook, refunds and F3; the standard sk_/rk_ key prefix is only
   a consistency check, never a mode fallback. Unsupported credentials fail closed. Legacy
   STRIPE_RECONCILIATION_LIVEMODE is unused. F3 disabled cannot change valid webhook mode.
   Missing mode gives 503, so provisioning remains a deploy prerequisite. Review custom checkout
   hosts if used. No environment was read or edited during remediation.
5. Enable only under a separate production authorization. Watch counts and unknown/manual queue age.
6. Disable the scheduler flag to stop reconciliation; preserve all F2 attempts and financial records.

This implementation has no dry-run mode. Do not mistake the enabled command for a read-only audit:
its Stripe operations are GET-only, but its validated local business application writes to the DB.

## Remediation 4: persistent checkpoints are progress, not business proof

Cursor version 3 uses the existing JSON column; no migration is added. Any cursor loaded from
the DB is cross-run by definition, including API/technical exceptions, budget/deadline yields,
process restart and saved phase transitions. Version 2 and malformed cursors are cleared with
manual_review before HTTP, then a later scheduled run may scan fresh. They are never resaved
as the same poisoned structure. Completion still requires the current claim UUID and live lease.

Schema validation requires a 64-character context hash, integer pages in [0, scan_max_pages),
an integer started_at within the configured age bound and not in the future, and a known phase.
Unknown fields are rejected, including unknown scan_type. Only after may be null. Optional
resumed/complete fields are booleans; identifiers have the appropriate cs_/pi_/ch_/re_/evt_ prefix.
Discovery requires after, a list of at most one match, digest, and integer ordered from/to window;
expected_digest is optional. Snapshot requires session_id. Refund phases require session_id,
charge_id/state, after, an ID-keyed allowlisted refund map (maximum 1000), and an independent
ordered refund_ids list with exactly the same ID set. Refresh/confirm also require a head ID list,
refresh_ids equal to refund_ids, and a bounded integer index. Map/object key reordering does not
alter pagination order; mutable-object comparison uses the existing canonical JSON fingerprint.
Async requires session_id/after/from/to; async_proof requires an event proof with event ID,
failure type, created integer, boolean mode and a same-Session object. Context mismatches are
manual_review. A nested discovery_proof has the same context and discovery schema; deeper nesting
is rejected. Mutable commercial values still pass the existing fresh remote and locked local validators.

An unbound Order retains its discovery proof through snapshot/refund/async phase changes. A saved
completed proof restarts verification over the frozen window. Incremental two-pass traversal and
digests establish progress and detect ID/correlation changes, not immutable status or metadata.
After those passes complete, the whole window is listed again in the current PHP run (limit 100),
with exact ordered ID/correlation digest and unique candidate comparison. The candidate Session,
PI and Charge are then retrieved fresh. An exception at any step cannot make snapshot a shortcut.

For a resumed refund scan, every saved refund ID is freshly retrieved in the current run. A complete
current list traversal (limit 100) must have the same ordered IDs and allowlisted mutable values as
those GETs. Association, amount, currency or status changes therefore cause fresh selection or
manual_review, never cached Return selection. A failed GET retains enumeration progress; the next
run starts fresh retrieval from index zero. A completed refresh saved before list confirmation is
also restarted on load. Budget/deadline exhaustion during final verification is bounded manual_review
rather than applying a partially fresh batch. Business rollback still discards the consumed cursor.

The enumeration read_budget remains 100 by default (minimum 4). A separate validation_read_budget
defaults to 1014 and is clamped to [4, 2014]. It is added at most once per whole reconciliation run,
when complete enumeration needs coherent current-run verification; it is not renewed per Order,
page, exception or retry. Total dispatch is bounded by initial enumeration allowance plus this one
validation allowance, the unchanged runtime/deadline, page cap, 1000-refund cap and claim lease.
The allowance supports 1000 relevant Refund GETs plus list verification at limit 100; actual calls
are made only for the observed batch. It does not preallocate API requests or extend the lease.
If a configured validation allowance cannot cover the batch, manual_review is the safe outcome.
This additional allowance is necessary to avoid repeatedly yielding a partly fresh batch forever.
It increases the maximum per-run read count: operators must assess throughput before enabling F3.

Mode policy, async failure policy, local claim/lease fencing, business locks and monetary application
remain unchanged. Historical async event proof is checked against freshly retrieved current Session,
PI, failed Charge and PI recheck by the existing conservative policy; missing proof is structurally
rejected. All new lists and GETs remain outside transactions. Stripe is not atomically locked during
remote observation: concurrent changes after the final read remain an external consistency limit.

Stripe API references checked for this fix: [Events list and 30-day retention](https://docs.stripe.com/api/events/list),
[async failure event](https://docs.stripe.com/api/events/types#event_types-checkout.session.async_payment_failed).
Reading documentation made no calls to the Stripe account API.

## Remediation 5: bounded finalization and ambiguous Return refunds

`read_budget` is the enumeration allowance per whole run (effective minimum 4).
`validation_read_budget` is added once per whole run, at the first mandatory finalization
stage (effective range 4–2014, default 1014). Unused enumeration reads remain available.
There is no fixed minimum relationship between the two budgets that covers every history:
current-run discovery pages, Session/PI/Charge reads, refund enumeration and, for a resumed
refund batch, every Refund GET and confirm-list page all consume reads. A limit of 100
on verification lists does not guarantee that Stripe returns a full page.

The invariant is: after the validation allowance has been opened, an incomplete inspection
must resolve to `manual_review` with a cleared cursor, never persist `scan_incomplete`.
This also covers deadline exhaustion and later Orders sharing that run's remaining allowance.
Before finalization, ordinary enumeration checkpoints still advance across runs. API exceptions
retain the existing technical-error policy and mandatory fresh revalidation on retry.
No cached discovery or refund observation can authorize application. No default increase,
new lock order, HTTP under locks, or claim-fencing exception is introduced.

Insufficient budgets are accepted but require manual handling; they do not repeatedly replay
the same checkpoint until TTL/page cap. Existing manual-result warning, zero technical failure
count and `manual_seconds` scheduling apply (one day by default). A later scheduled scan can
start fresh, but operators must resolve the budget/history issue for automatic completion.
This is a conservative escalation, not a promise that every history automatically completes.

For eight discovery pages at page_size=1 and read_budget=4, two incremental passes take four
runs. If current-run verification fits one list response and there are zero refunds, finalization
then needs five reads: discovery list, Session GET, PI GET, Charge GET, empty refund list.
A validation allowance of 4 yields manual_review on run four (20 total fake calls), with no
binding, status, stock or receipt changes. Allowances of 5, 6 and 1014 complete paid in four
runs (21 calls). These are fixture measurements, not Stripe rate-limit estimates.

Under the existing business locks, all Return refund identities are checked before any binding
or commercial mutation. Two distinct Refund IDs for the same Return are `manual_review`,
regardless of status or list ordering. Nothing in that batch is applied, no receipt is emitted,
and the consumed cursor is cleared. Identical retries retain the same manual classification
without technical failure escalation. Ordinary transaction rollback remains in place for
failures during application. A single Refund whose current metadata changes from A to B still
processes only B after current-run revalidation; this is covered by positive fixture controls.
