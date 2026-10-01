<?php
/**
 * KhuntaLocal — cron: housekeeping.
 *
 * Idempotent maintenance:
 *   - clear expired breaking flags
 *   - archive published stories whose expiry has passed
 *   - prune old analytics/throttle rows and resolved reports
 * Safe to run repeatedly.
 */

declare(strict_types=1);
require __DIR__ . '/_cli.php';

cron_log('cleanup: starting');

try {
    $n = db_run(
        "UPDATE news SET is_breaking = 0
          WHERE is_breaking = 1 AND breaking_expires_at IS NOT NULL AND breaking_expires_at <= NOW()"
    )->rowCount();
    cron_log("cleanup: cleared {$n} expired breaking flag(s)");

    $n = db_run(
        "UPDATE news SET status = 'expired'
          WHERE status = 'published' AND expires_at IS NOT NULL AND expires_at <= NOW()"
    )->rowCount();
    cron_log("cleanup: expired {$n} story/stories");

    $n = db_run('DELETE FROM news_views WHERE viewed_at < (NOW() - INTERVAL 180 DAY)')->rowCount();
    cron_log("cleanup: pruned {$n} old view row(s)");

    $n = db_run('DELETE FROM login_attempts WHERE created_at < (NOW() - INTERVAL 7 DAY)')->rowCount();
    cron_log("cleanup: pruned {$n} old login attempt(s)");

    $n = db_run('DELETE FROM rate_limits WHERE window_start < (NOW() - INTERVAL 2 DAY)')->rowCount();
    cron_log("cleanup: pruned {$n} stale rate-limit row(s)");

    $n = db_run("DELETE FROM shares WHERE created_at < (NOW() - INTERVAL 365 DAY)")->rowCount();
    cron_log("cleanup: pruned {$n} old share row(s)");
} catch (Throwable $ex) {
    error_log('cleanup failed: ' . $ex->getMessage());
    cron_log('cleanup: ERROR — ' . $ex->getMessage());
}

cron_log('cleanup: done');
