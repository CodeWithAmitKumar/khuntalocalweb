# cron/

Scheduled background jobs.

**Status: planned for Phase 4.** They will be idempotent and transaction-guarded
so they are safe to run repeatedly and never publish the same news twice:

```
cron/verification-check.php   Run automated checks on pending news.
cron/auto-publish.php         Apply the configured auto-publish policy (never on a
                              bare timer — always re-runs final safety checks).
cron/notification-worker.php  Deliver queued notifications.
cron/cleanup.php              Expire breaking flags, archive expired news, prune.
```
