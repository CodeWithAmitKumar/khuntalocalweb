<?php
/**
 * KhuntaLocal — Rate limiting + login-attempt throttling (DB-backed).
 *
 * Generic fixed-window limiter stored in `rate_limits`, plus helpers for login
 * lockout stored in `login_attempts`.
 */

declare(strict_types=1);

if (!function_exists('rate_limit_hit')) {
    /**
     * Register a hit for ($action,$key) within a time window and report whether
     * the caller is now over the limit.
     *
     * @return bool  true if ALLOWED, false if the limit is exceeded
     */
    function rate_limit_hit(string $action, string $key, int $max, int $windowSecs): bool
    {
        try {
            $row = fetch(
                'SELECT id, hits, UNIX_TIMESTAMP(window_start) AS started
                   FROM rate_limits WHERE action = ? AND bucket_key = ? LIMIT 1',
                [$action, $key]
            );

            $now = time();
            if (!$row) {
                db_run(
                    'INSERT INTO rate_limits (action, bucket_key, hits, window_start)
                     VALUES (?, ?, 1, NOW())',
                    [$action, $key]
                );
                return true;
            }

            // Window expired -> reset.
            if (($now - (int) $row['started']) >= $windowSecs) {
                db_run(
                    'UPDATE rate_limits SET hits = 1, window_start = NOW() WHERE id = ?',
                    [$row['id']]
                );
                return true;
            }

            if ((int) $row['hits'] >= $max) {
                return false;
            }

            db_run('UPDATE rate_limits SET hits = hits + 1 WHERE id = ?', [$row['id']]);
            return true;
        } catch (Throwable $ex) {
            // Fail open on infrastructure errors — never block legitimate users
            // because the limiter table is unavailable, but log it.
            error_log('rate_limit_hit failed: ' . $ex->getMessage());
            return true;
        }
    }
}

if (!function_exists('rate_limit_key')) {
    /** Default bucket key: IP (+ optional user id). */
    function rate_limit_key(?int $userId = null): string
    {
        return $userId ? ('u' . $userId) : ('ip' . ip_hash());
    }
}

/* ---------------------------------------------------------------------------
 * Login attempts / lockout
 * ------------------------------------------------------------------------- */
if (!function_exists('login_record_attempt')) {
    function login_record_attempt(string $identifier, bool $success): void
    {
        try {
            db_run(
                'INSERT INTO login_attempts (identifier, ip_address, successful)
                 VALUES (?, ?, ?)',
                [mb_substr($identifier, 0, 190), request_ip(), $success ? 1 : 0]
            );
        } catch (Throwable $ex) {
            error_log('login_record_attempt failed: ' . $ex->getMessage());
        }
    }
}

if (!function_exists('login_is_locked')) {
    /**
     * True if this identifier/IP has had too many failed attempts recently.
     */
    function login_is_locked(string $identifier): bool
    {
        $max     = (int) config('security.login_max_attempts', 5);
        $lockout = (int) config('security.login_lockout_secs', 900);
        try {
            $failures = (int) fetch_column(
                'SELECT COUNT(*) FROM login_attempts
                  WHERE successful = 0
                    AND (identifier = ? OR ip_address = ?)
                    AND created_at > (NOW() - INTERVAL ? SECOND)',
                [mb_substr($identifier, 0, 190), request_ip(), $lockout]
            );
        } catch (Throwable $ex) {
            error_log('login_is_locked failed: ' . $ex->getMessage());
            return false; // fail open
        }
        return $failures >= $max;
    }
}

if (!function_exists('login_clear_attempts')) {
    /** Clear failed attempts after a successful login. */
    function login_clear_attempts(string $identifier): void
    {
        try {
            db_run(
                'DELETE FROM login_attempts
                  WHERE successful = 0 AND (identifier = ? OR ip_address = ?)',
                [mb_substr($identifier, 0, 190), request_ip()]
            );
        } catch (Throwable $ex) {
            error_log('login_clear_attempts failed: ' . $ex->getMessage());
        }
    }
}
