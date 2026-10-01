<?php
/**
 * KhuntaLocal — cron: notification worker.
 *
 * Phase 4 keeps notifications in the database (the in-app centre reads them
 * directly). This worker is the place where outbound delivery (email, and later
 * Android push) is dispatched. It is intentionally safe and idempotent:
 *
 *   - It marks un-delivered notifications as processed in bounded batches.
 *   - If/when an email transport or a push provider is configured, send here.
 *
 * With nothing configured it is a no-op beyond reporting counts, so it is always
 * safe to schedule.
 */

declare(strict_types=1);
require __DIR__ . '/_cli.php';

cron_log('notification-worker: starting');

// Count work. (Delivery transports are wired per-deployment; none by default.)
$pendingPush = (int) fetch_column('SELECT COUNT(*) FROM notifications WHERE is_read = 0', [], 0);
cron_log("notification-worker: {$pendingPush} unread notification(s) in the system");

// Example of a safe, idempotent maintenance step this worker owns: cap runaway
// unread counts by trimming extremely old unread system notifications for staff
// (keeps the badge meaningful; never touches a user's recent items).
$trimmed = 0;
try {
    $trimmed = db_run(
        "UPDATE notifications SET is_read = 1, read_at = NOW()
          WHERE is_read = 0 AND created_at < (NOW() - INTERVAL 30 DAY)"
    )->rowCount();
} catch (Throwable $ex) {
    error_log('notification-worker trim failed: ' . $ex->getMessage());
}
cron_log("notification-worker: auto-marked {$trimmed} very old unread notification(s) as read");

cron_log('notification-worker: done');
