# F1: transactional email delivery

Business requests record delivery intents in `transactional_emails`. No SMTP or
invoice rendering executes from checkout, payment/refund webhooks, Order hooks,
ReturnRequest hooks, or order recovery. COD creation and payment confirmation
record their intent in the business transaction. Stripe refund state, stock
restoration and notification intents also commit together, after the Stripe API
call. A rollback discards the intent; delivery rejects invocation inside an open
business transaction. Existing stock restoration timestamps remain the
idempotency guard.

Laravel's scheduled `emails:deliver` command drains the table. This uses existing
database, scheduler, mailables and SMTP configuration, with no queue worker,
Redis or new packages. It deliberately avoids dispatching a queued mailable:
`sync` queues could still expose requests to mail exceptions, and a database
queue requires a worker whose production availability is unknown.

## Separately authorized deployment requirements

This change has not been deployed. Before enabling the changed code, apply the
additive `2026_10_03_000001_create_transactional_emails_table.php` migration using
the normal deployment procedure. No existing business tables/data are changed
by it. Do not drop the delivery table while the new code is running.

Configure or verify a cPanel cron invoking Laravel `schedule:run` **every minute**
from the project root, with the hosting account's PHP CLI and production
configuration. Example with placeholders to replace:

```cron
* * * * * cd /absolute/project/path && /absolute/php/path artisan schedule:run
```

Keep cron output and monitor errors. No new environment variable is required;
the existing mail configuration must be correct. Console-generated email links
use the existing APP_URL. The scheduler overlap lock uses the configured Laravel
cache; verify its persistent lock support (the repository defaults to database
cache and already includes cache/cache_locks migrations). All drainers must use
the same primary database. MySQL/MariaDB InnoDB row locks serialize delivery of
the same intent; SQLite tests verify functional behavior, not concurrent InnoDB
locking. Configure a finite SMTP/socket timeout and CLI run budget appropriate
to the host. The current SMTP config's null timeout defers to the PHP/provider
default; a slow provider must not cause an indefinitely running cron.

## Monitoring and recovery

```sh
php artisan emails:deliver --status
php artisan emails:deliver --limit=50
php artisan emails:deliver --retry=123
```

`--status` sends nothing. It reports pending, failed and deliveries overdue by
ten minutes relative to their next eligible attempt, returning exit code 1 if
failed/overdue work exists. Monitor this independently of the scheduler so a
missing cron is detected. Also alert on growing pending counts and command/cron
errors. There is no new admin UI and no automatic monitoring service installed
by this change.

Each intent has at most five automatic attempts, with delays of 60, 300, 900 and
3600 seconds, plus cron latency. Failures retain attempts, next_attempt_at,
failed_at and last_error_type. Logs contain delivery ID, kind, attempt and
exception class, never the raw exception message, recipient, body or SMTP
credentials. Inspect provider diagnostics separately without exposing secrets.
After fixing the cause, `--retry=ID` resets only a failed, unsent intent and
drains the due batch. It refuses already sent intents. It does not re-run a
payment, refund or stock operation. `--limit` accepts 1–500 (default 50).
Laravel `failed_jobs` and `queue:retry` are not used for these notifications.

The table stores references and minimal refund state/amount snapshots, rather
than email bodies or addresses. Rows marked sent remain as deduplication
receipts. Do not casually delete receipts: a later replay could recreate a
notification. Foreign keys restrict deleting referenced orders/returns;
retention/cleanup must account for these receipts in a future separate change.

## Replay, reconciliation and remaining limits

Unique event keys deduplicate order notifications by order/kind and refund
notifications by order-or-return/status. Replayed paid webhooks ensure the same
intent exists; they never reset sent/failed receipts. Completed refund replays
still invoke the existing idempotent stock recovery. A validated completed
order refund also repairs payment/status steps which the old synchronous email
hook could interrupt. These are F1 recovery steps, not new refund API calls.

No backfill of historical mail is run. Existing paid orders without receipts
may receive one confirmation if Stripe replays a valid event after deployment,
even if the old code already sent that email; historical successful sends have
no durable receipt to distinguish them. Reconcile historical affected refunds
through the existing validated webhook/admin process after deployment; do not
assume the migration repairs them automatically.

SMTP cannot guarantee exactly-once delivery: acceptance followed by a lost
acknowledgement, process death or DB receipt-commit failure can lead to a retry
of an email already accepted by the provider. Ordinary replay, successful-send
retry and overlapping drainers are deduplicated, but this ambiguous external
delivery window remains. Check provider acceptance before manual recovery in
such cases. An email failure never writes order/payment/refund/stock state.
Database outages can still prevent persistence; they are distinct from SMTP
failures, and are not silently ignored.

Refund state/amount is snapshotted. Other mailable data and recipient are loaded
from the current order, so delayed messages can reflect subsequently changed
details. A long outage can deliver older status notifications late. Existing
distinct cancellation and refund messages, and separate order/return refund
messages for a full return, retain their previous semantics. Return-request
acknowledgement, authentication mail and newsletter delivery are outside F1 and
remain unchanged.
