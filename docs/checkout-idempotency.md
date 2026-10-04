# F2: checkout idempotency

The browser must obtain a server-issued `checkout_token` from GET /checkout.
Missing, unknown, foreign-user, retired unused and expired unused tokens are
rejected. There is no unprotected fallback for old forms or direct POST clients.

`checkout_attempts.token` is unique. Issuing a form locks the user row and reuses
the active attempt for the same logical session and normalized cart quantities.
The session scope survives session-ID regeneration. Cart edits/clearing retire
active attempts; cancelled/failed orders allow a fresh form. Consumed old tokens
always resolve to their original order, even after clearing/expiry, and never
create another order. Reusing a consumed token with different validated customer
or payment data is rejected. Separate sessions or deliberate new cart generations
are separate purchase intents, not globally deduplicated identical purchases.

Consumption locks the attempt. Order creation, stock reservation, COD email
receipt and linking the order/request hash commit together. `order_id` is unique
as well. OrderService's product locks and F1 receipt keys remain unchanged. A DB
failure rolls everything back, leaving the same unused token retryable.

## Stripe boundary

The order/stock reservation is committed first. Exact Stripe Checkout parameters
and the first initiation timestamp are committed before the network call. The
next transaction locks the attempt and the order, checks their current state,
and persists the Stripe session ID and URL together. The API uses
`novelion-checkout-{attempt UUID}` as its idempotency key. Concurrent retries
serialize; after success they reuse the stored session without another create.
Paid/cancelled/failed/refunded orders return their existing result, not a new
Checkout session. Payment confirmation stays exclusively in validated webhooks;
there is no new payment table or separate payment-intent creation.

An API timeout or failure saving the local response does NOT prove Stripe failed.
The application keeps the pending order and reserved stock. A retry reuses the
durable parameters and key. It never automatically calls markAsFailed on that
ambiguous exception. Existing Stripe expiry/failure webhook and explicit customer
cancellation flows remain available.

Stripe may prune idempotency keys after at least 24 hours:
https://docs.stripe.com/api/idempotent_requests
This implementation stops session creation/resumption 23 hours after first
initiation, conservatively failing closed. It does not rotate the Stripe key or
automatically release stock in an ambiguous case. Such pending orders require
provider reconciliation before cancellation/replacement. A definitive provider
configuration/validation error also leaves the order pending until resolved or
explicitly cancelled; this favors avoiding duplicate paid orders over silently
abandoning an externally ambiguous request.

The network call holds row locks. Configure finite Stripe/CLI/request timeouts
and account for MySQL lock waits; a rejected concurrent request can be retried
with the same token. DB deadlocks are not swallowed. No automatic transaction
retry is added around the external API call.

## Deployment requirements (not executed)

1. Review and test on isolated MySQL/InnoDB, including concurrent requests.
2. Apply only the additive `2026_10_04_000001_create_checkout_attempts_table.php`
   migration before enabling the new code. Existing order tables are untouched.
3. Switch the entire release (controller, form, services, model). Old open forms
   without tokens must reload; do not leave a mixed release serving requests.
4. Smoke-test COD, Stripe and retry behavior and monitor pending reservations.

No packages, new environment variables, scheduler or queue worker are required.
The migration is compatible with old code; reverting code reintroduces F2.
Never drop the attempt table while this release is running or while its receipts
are needed for recovery. It contains frozen Stripe parameters, checkout URLs and
hashes; retain/access it as application data and do not put raw URLs in logs.
User deletion nulls the attempt owner rather than blocking account deletion.
Deleting an associated order is restricted to preserve replay integrity.

There is no historical backfill: pre-F2 pending orders have no attempt receipt.
Reconcile them before inviting a fresh checkout for the same purchase.
Explicit cancellation/late-payment handling, cross-session deliberate purchases
and abandoned-reservation cleanup remain existing/separate concerns; do not
assume idempotency alone solves them.

## Tests and limits

Regression tests cover form refresh, duplicate POST, replay after cart clearing,
token ownership/mismatch/expiry, lifecycle after cart edits, SQL rollback after
order/stock/COD receipt creation, lost Stripe response, local response-save
rollback, frozen parameters, 23-hour safety boundary, paid webhook replay and
cancelled-order replay. Two worker processes also exercise simultaneous COD and
Stripe attempts against a generated shared SQLite fixture with a shared fake
Stripe idempotency store. SQLite busy failures are retried by the test worker to
model browser retries. This does not assert MySQL row-lock/deadlock semantics.
No production database or real Stripe API is used.

## A1 recovery of an externally created, locally unbound Session

Before the external call, the frozen request now includes the attempt UUID as
client_reference_id and metadata, order/user IDs, request hash, and a SHA-256
fingerprint of the request (excluding the fingerprint itself). These metadata
values are strings, matching Stripe's metadata representation. No email or other
personal contact data is added. The fingerprint is a consistency check, not an
authentication secret: Stripe webhook signature verification remains required.

If the strict existing Order/Session-ID lookup fails, recovery locks the attempt
and then the Order, in the same order as checkout. On InnoDB these locking reads
wait for the checkout transaction and read the current committed state following
its commit/rollback. Recovery requires all frozen identity metadata to match,
the same owner and request hash, payment mode, terminal status appropriate to the
event, RON currency, exact frozen and current order/item totals, and compatible
Session/PaymentIntent IDs. It cannot replace another bound Session. Paid events
also require a nonempty compatible PaymentIntent, not bound to another Order.
The binding commits before existing transactional stock/payment/outbox handling;
a processing failure returns an error so Stripe can retry with the binding kept.
An expired Session restores stock once; successful payment uses the same lookup.
No browser retry is necessary. Existing bound legacy sessions retain their
original lookup and payment validation behavior.

Previously created attempts lacking these metadata cannot be backfilled into an
already-created external Session by this code. Reconcile any such pending local
attempts before activation. No additional migration/configuration is introduced
by A1 beyond the F2 migration. A2 (HTTP under locks) and A3 (generic error after
23 hours) remain unchanged. Cross-process webhook-versus-checkout timing on
MySQL/InnoDB remains a required isolated pre-deployment check; SQLite regressions
prove SQL rollback/retry outcomes, not InnoDB concurrency semantics.
