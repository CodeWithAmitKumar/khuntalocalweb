<?php
/**
 * KhuntaLocal — Application bootstrap.
 *
 * Every entry point (public page, admin page, API endpoint, cron script)
 * includes this file ONCE. It loads configuration, prepares the environment,
 * wires the core library and starts a secure session.
 *
 *     require __DIR__ . '/includes/bootstrap.php';   // from repo root
 */

declare(strict_types=1);

if (defined('KL_BOOTSTRAPPED')) {
    return;
}
define('KL_BOOTSTRAPPED', true);

/* ---------------------------------------------------------------------------
 * Paths
 * ------------------------------------------------------------------------- */
define('KL_ROOT', dirname(__DIR__));
define('KL_INCLUDES', KL_ROOT . '/includes');
define('KL_CONFIG_FILE', KL_ROOT . '/config/config.php');

/* ---------------------------------------------------------------------------
 * Configuration
 * ------------------------------------------------------------------------- */
if (!is_file(KL_CONFIG_FILE)) {
    http_response_code(500);
    exit(
        "KhuntaLocal is not configured yet.\n\n" .
        "Copy config/config.sample.php to config/config.php and set your " .
        "database credentials.\n"
    );
}

/** @var array $GLOBALS['kl_config'] */
$GLOBALS['kl_config'] = require KL_CONFIG_FILE;

/* ---------------------------------------------------------------------------
 * Environment: timezone + error reporting
 * ------------------------------------------------------------------------- */
date_default_timezone_set($GLOBALS['kl_config']['app']['timezone'] ?? 'Asia/Kolkata');

$isDebug = (bool) ($GLOBALS['kl_config']['app']['debug'] ?? false)
    || (($GLOBALS['kl_config']['app']['env'] ?? 'production') === 'development');

if ($isDebug) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
    ini_set('display_errors', '0');
}
define('KL_DEBUG', $isDebug);

/* ---------------------------------------------------------------------------
 * Core library — order matters (later files may use earlier helpers).
 * ------------------------------------------------------------------------- */
require KL_INCLUDES . '/helpers.php';
require KL_INCLUDES . '/db.php';
require KL_INCLUDES . '/settings.php';
require KL_INCLUDES . '/csrf.php';
require KL_INCLUDES . '/flash.php';
require KL_INCLUDES . '/validation.php';
require KL_INCLUDES . '/ratelimit.php';
require KL_INCLUDES . '/auth.php';
require KL_INCLUDES . '/authz.php';
require KL_INCLUDES . '/audit.php';
require KL_INCLUDES . '/notifications.php';
require KL_INCLUDES . '/upload.php';
require KL_INCLUDES . '/news.php';
require KL_INCLUDES . '/verification.php';
require KL_INCLUDES . '/seo.php';
require KL_INCLUDES . '/ui.php';

/* ---------------------------------------------------------------------------
 * Secure session — skip on CLI (cron / self-tests).
 * ------------------------------------------------------------------------- */
if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    $cfg      = $GLOBALS['kl_config']['session'];
    $secure   = $cfg['secure'];
    if ($secure === null) {
        // Auto-detect HTTPS (handles common reverse-proxy headers too).
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
            || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);
    }

    session_name($cfg['name']);
    session_set_cookie_params([
        'lifetime' => (int) $cfg['lifetime'],
        'path'     => '/',
        'domain'   => '',
        'secure'   => (bool) $secure,
        'httponly' => true,
        'samesite' => $cfg['samesite'] ?? 'Lax',
    ]);
    session_start();
}
