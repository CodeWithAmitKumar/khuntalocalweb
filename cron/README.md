# cron/

Scheduled background jobs. **Phase 4 — implemented.** All are idempotent and
transaction-guarded, so they are safe to run repeatedly and never publish the
same news twice.

```
cron/_cli.php                 Shared bootstrap + guard (CLI, or token-guarded HTTP).
cron/verification-check.php   Re-runs the automated engine on queued items and
                              escalates items past the max review window.
cron/auto-publish.php         Applies the auto-publish policy (never on a bare
                              timer — re-runs final checks) and publishes due
                              scheduled stories. Logs AUTO_PUBLISH_DECISION.
cron/notification-worker.php  Notification delivery hook + safe upkeep.
cron/cleanup.php              Expire breaking flags, archive expired news, prune
                              old views / login attempts / rate limits / shares.
```

See the project README (“Cron setup”) for crontab examples. Run via CLI, or over
HTTPS with `?token=CRON_SECRET` when a secret is configured.
