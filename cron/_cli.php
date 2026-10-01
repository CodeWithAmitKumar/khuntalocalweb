<?php
/**
 * KhuntaLocal — shared cron bootstrap + guard.
 *
 * Each cron script starts with:  require __DIR__ . '/_cli.php';
 *
 * Run from the command line (recommended), e.g.:
 *   php /path/to/cron/auto-publish.php
 *
 * Or trigger over HTTP with the configured secret:
 *   curl "https://example.com/cron/auto-publish.php?token=YOUR_CRON_SECRET"
 * (set `cron_secret` / CRON_SECRET in config; HTTP without it is forbidden.)
 */

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    $secret = (string) config('cron_secret', '');
    $token  = (string) ($_GET['token'] ?? '');
    if ($secret === '' || !hash_equals($secret, $token)) {
        http_response_code(403);
        exit("Forbidden: cron scripts require CLI or a valid ?token.\n");
    }
    header('Content-Type: text/plain; charset=utf-8');
}

if (!function_exists('cron_log')) {
    function cron_log(string $message): void
    {
        echo '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
    }
}
