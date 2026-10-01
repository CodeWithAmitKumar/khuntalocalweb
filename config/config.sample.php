<?php
/**
 * KhuntaLocal — Configuration template.
 *
 * Copy this file to config/config.php and adjust the values for your server.
 * config/config.php is git-ignored so your real credentials never get committed.
 *
 * Every value may be overridden by an environment variable (recommended for
 * production / shared hosting panels that support env vars). The getenv()
 * fallbacks below are used when the variable is not set.
 */

declare(strict_types=1);

/* ---------------------------------------------------------------------------
 * Small helper so we can read env vars with a typed fallback.
 * ------------------------------------------------------------------------- */
if (!function_exists('kl_env')) {
    function kl_env(string $key, $default = null)
    {
        $val = getenv($key);
        if ($val === false || $val === '') {
            return $default;
        }
        // Normalise common boolean-ish strings.
        $lower = strtolower($val);
        if (in_array($lower, ['true', '1', 'yes', 'on'], true))  return true;
        if (in_array($lower, ['false', '0', 'no', 'off'], true)) return false;
        return $val;
    }
}

return [

    /* -------------------------------------------------------------------
     * Application
     * ----------------------------------------------------------------- */
    'app' => [
        'name'     => kl_env('APP_NAME', 'KhuntaLocal'),
        'env'      => kl_env('APP_ENV', 'production'), // 'production' | 'development'
        'debug'    => kl_env('APP_DEBUG', false),
        // Public base URL with NO trailing slash, e.g. https://khuntalocal.com
        // or http://localhost:8000 during local development.
        'url'      => kl_env('APP_URL', 'http://localhost:8000'),
        'timezone' => kl_env('APP_TIMEZONE', 'Asia/Kolkata'),
        'locale'   => kl_env('APP_LOCALE', 'en'),
    ],

    /* -------------------------------------------------------------------
     * Database (MySQL / MariaDB, utf8mb4)
     * ----------------------------------------------------------------- */
    'db' => [
        'driver'   => 'mysql',
        'host'     => kl_env('DB_HOST', '127.0.0.1'),
        'port'     => (int) kl_env('DB_PORT', 3306),
        'database' => kl_env('DB_NAME', 'khuntalocal'),
        'username' => kl_env('DB_USER', 'root'),
        'password' => kl_env('DB_PASS', ''),
        'charset'  => 'utf8mb4',
        // Optional: connect via a Unix socket instead of host/port. When set,
        // host/port are ignored. Handy on some shared hosts and for local dev.
        'socket'   => kl_env('DB_SOCKET', ''),
    ],

    /* -------------------------------------------------------------------
     * Sessions & security
     * ----------------------------------------------------------------- */
    'session' => [
        'name'      => kl_env('SESSION_NAME', 'KHUNTALOCALSESS'),
        'lifetime'  => (int) kl_env('SESSION_LIFETIME', 60 * 60 * 24 * 7), // 7 days
        'samesite'  => 'Lax',
        // Leave 'secure' null to auto-detect HTTPS; set true to force.
        'secure'    => kl_env('SESSION_SECURE', null),
    ],

    'security' => [
        // Login throttling.
        'login_max_attempts' => (int) kl_env('LOGIN_MAX_ATTEMPTS', 5),
        'login_lockout_secs' => (int) kl_env('LOGIN_LOCKOUT_SECS', 900), // 15 min
    ],

    /* -------------------------------------------------------------------
     * Uploads
     * ----------------------------------------------------------------- */
    'uploads' => [
        // Absolute path on disk. Must be writable by the web server.
        'path'          => dirname(__DIR__) . '/uploads',
        // Public URL path (relative to app url) where uploads are served.
        'url'           => '/uploads',
        // Limits (bytes). These are also mirrored in DB settings so admins
        // can tune them; the config value is the hard server-side ceiling.
        'max_image_size' => (int) kl_env('MAX_IMAGE_SIZE', 5 * 1024 * 1024),   // 5 MB
        'max_video_size' => (int) kl_env('MAX_VIDEO_SIZE', 50 * 1024 * 1024),  // 50 MB (Phase 3)
        'image_exts'     => ['jpg', 'jpeg', 'png', 'webp'],
        'image_mimes'    => ['image/jpeg', 'image/png', 'image/webp'],
        'video_exts'     => ['mp4', 'webm'],
        'video_mimes'    => ['video/mp4', 'video/webm'],
    ],

    /* -------------------------------------------------------------------
     * Verification workflow defaults (also stored in DB settings; these are
     * the fallbacks used before/without DB settings).
     * ----------------------------------------------------------------- */
    'verification' => [
        'min_review_minutes' => (int) kl_env('VERIFY_MIN_MINUTES', 60),  // 1 hour
        'max_review_minutes' => (int) kl_env('VERIFY_MAX_MINUTES', 120), // 2 hours
        // Conservative defaults — auto publish is OFF by default (Phase 4).
        'auto_publish'            => kl_env('AUTO_PUBLISH', false),
        'auto_publish_low_risk'   => kl_env('AUTO_PUBLISH_LOW_RISK_ONLY', true),
    ],

    /* -------------------------------------------------------------------
     * Optional external fact-check / search APIs (Phase 4+).
     * NEVER hard-code real keys here — set them via environment variables.
     * Leave empty to disable; the verification service degrades gracefully.
     * ----------------------------------------------------------------- */
    'external' => [
        'factcheck_api_key' => kl_env('FACTCHECK_API_KEY', ''),
        'search_api_key'    => kl_env('SEARCH_API_KEY', ''),
    ],

    /* -------------------------------------------------------------------
     * Secret used to authorise CLI cron endpoints when triggered over HTTP
     * (Phase 4). Set a long random value in production.
     * ----------------------------------------------------------------- */
    'cron_secret' => kl_env('CRON_SECRET', ''),
];
