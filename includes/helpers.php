<?php
/**
 * KhuntaLocal — General helpers (escaping, URLs, slugs, time, JSON).
 * No database access here; safe to use everywhere.
 */

declare(strict_types=1);

/* ---------------------------------------------------------------------------
 * Config access
 * ------------------------------------------------------------------------- */
if (!function_exists('config')) {
    /**
     * Dot-path config reader: config('app.url'), config('db.host').
     */
    function config(string $path, $default = null)
    {
        $cfg = $GLOBALS['kl_config'] ?? [];
        foreach (explode('.', $path) as $segment) {
            if (is_array($cfg) && array_key_exists($segment, $cfg)) {
                $cfg = $cfg[$segment];
            } else {
                return $default;
            }
        }
        return $cfg;
    }
}

/* ---------------------------------------------------------------------------
 * Output escaping
 * ------------------------------------------------------------------------- */
if (!function_exists('e')) {
    /**
     * Escape a value for safe HTML output. Use on EVERYTHING printed.
     */
    function e($value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('e_attr')) {
    function e_attr($value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

/* ---------------------------------------------------------------------------
 * URLs & assets
 * ------------------------------------------------------------------------- */
if (!function_exists('base_url')) {
    function base_url(string $path = ''): string
    {
        $base = rtrim((string) config('app.url', ''), '/');
        $path = ltrim($path, '/');
        return $path === '' ? $base . '/' : $base . '/' . $path;
    }
}

if (!function_exists('url')) {
    /** Alias of base_url() for readability in views. */
    function url(string $path = ''): string
    {
        return base_url($path);
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return base_url('assets/' . ltrim($path, '/'));
    }
}

if (!function_exists('news_url')) {
    function news_url(string $slug): string
    {
        return base_url('news/' . $slug);
    }
}

if (!function_exists('category_url')) {
    function category_url(string $slug): string
    {
        return base_url('category/' . $slug);
    }
}

if (!function_exists('reporter_url')) {
    function reporter_url(string $username): string
    {
        return base_url('reporter/' . $username);
    }
}

if (!function_exists('upload_url')) {
    /** Public URL for a stored upload path (relative to the uploads dir). */
    function upload_url(?string $relative): string
    {
        if (!$relative) {
            return '';
        }
        return base_url(trim((string) config('uploads.url', '/uploads'), '/') . '/' . ltrim($relative, '/'));
    }
}

/* ---------------------------------------------------------------------------
 * Redirects
 * ------------------------------------------------------------------------- */
if (!function_exists('redirect')) {
    /**
     * Redirect to an app path (or absolute URL) and stop execution.
     */
    function redirect(string $to, int $status = 302): void
    {
        $location = preg_match('#^https?://#i', $to) ? $to : base_url($to);
        header('Location: ' . $location, true, $status);
        exit;
    }
}

/* ---------------------------------------------------------------------------
 * Request helpers
 * ------------------------------------------------------------------------- */
if (!function_exists('is_post')) {
    function is_post(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }
}

if (!function_exists('input')) {
    /** Read + trim a request value (GET or POST). */
    function input(string $key, $default = ''): string
    {
        $val = $_POST[$key] ?? $_GET[$key] ?? $default;
        return is_string($val) ? trim($val) : (string) $val;
    }
}

if (!function_exists('request_ip')) {
    function request_ip(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }
}

if (!function_exists('ip_hash')) {
    /** One-way hash of the client IP (so we never store raw IPs for analytics). */
    function ip_hash(?string $ip = null): string
    {
        $ip = $ip ?? request_ip();
        return hash('sha256', $ip . '|khuntalocal');
    }
}

if (!function_exists('user_agent')) {
    function user_agent(): string
    {
        return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }
}

/* ---------------------------------------------------------------------------
 * Strings: slugs, excerpts, tokens, UUID
 * ------------------------------------------------------------------------- */
if (!function_exists('slugify')) {
    /**
     * Make a URL-safe slug. Falls back gracefully for non-ASCII (e.g. Odia)
     * titles so we never produce an empty slug.
     */
    function slugify(string $text, int $maxLen = 200): string
    {
        $text = trim($text);

        // Transliterate to ASCII where the intl/iconv tooling allows.
        $ascii = $text;
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
            if ($converted !== false) {
                $ascii = $converted;
            }
        }

        $slug = strtolower($ascii);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        if ($slug === '') {
            // Non-latin script: build a short stable token instead.
            $slug = 'n-' . substr(hash('sha1', $text), 0, 10);
        }

        if (strlen($slug) > $maxLen) {
            $slug = rtrim(substr($slug, 0, $maxLen), '-');
        }
        return $slug;
    }
}

if (!function_exists('unique_slug')) {
    /**
     * Ensure a slug is unique using a caller-supplied existence check.
     *
     * @param callable $exists  fn(string $slug): bool  — true if slug is taken
     */
    function unique_slug(string $base, callable $exists, int $maxLen = 200): string
    {
        $base = slugify($base, $maxLen);
        if (!$exists($base)) {
            return $base;
        }
        $i = 2;
        do {
            $suffix    = '-' . $i;
            $candidate = substr($base, 0, $maxLen - strlen($suffix)) . $suffix;
            $i++;
        } while ($exists($candidate) && $i < 10000);

        return $candidate;
    }
}

if (!function_exists('str_excerpt')) {
    function str_excerpt(?string $text, int $len = 160): string
    {
        $text = trim(preg_replace('/\s+/', ' ', (string) $text) ?? '');
        if (function_exists('mb_strlen')) {
            if (mb_strlen($text) <= $len) {
                return $text;
            }
            return rtrim(mb_substr($text, 0, $len - 1)) . '…';
        }
        return strlen($text) <= $len ? $text : rtrim(substr($text, 0, $len - 1)) . '…';
    }
}

if (!function_exists('random_token')) {
    function random_token(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }
}

if (!function_exists('uuid4')) {
    function uuid4(): string
    {
        $data    = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40); // version 4
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80); // variant
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

/* ---------------------------------------------------------------------------
 * Time
 * ------------------------------------------------------------------------- */
if (!function_exists('time_ago')) {
    /**
     * Human-friendly relative time, e.g. "5 min ago", "2 days ago".
     * Accepts a datetime string or a unix timestamp.
     */
    function time_ago($when, ?int $now = null): string
    {
        $ts  = is_numeric($when) ? (int) $when : strtotime((string) $when);
        if ($ts === false || $ts === 0) {
            return '';
        }
        $now  = $now ?? time();
        $diff = $now - $ts;

        if ($diff < 0) {
            return 'just now';
        }
        if ($diff < 60) {
            return 'just now';
        }

        $units = [
            31536000 => 'year',
            2592000  => 'month',
            604800   => 'week',
            86400    => 'day',
            3600     => 'hour',
            60       => 'min',
        ];
        foreach ($units as $secs => $label) {
            if ($diff >= $secs) {
                $count = (int) floor($diff / $secs);
                if ($label === 'min') {
                    return $count . ' min ago';
                }
                return $count . ' ' . $label . ($count > 1 ? 's' : '') . ' ago';
            }
        }
        return 'just now';
    }
}

if (!function_exists('format_count')) {
    /** Compact number: 1200 -> 1.2k */
    function format_count(int $n): string
    {
        if ($n < 1000) {
            return (string) $n;
        }
        if ($n < 1000000) {
            return rtrim(rtrim(number_format($n / 1000, 1), '0'), '.') . 'k';
        }
        return rtrim(rtrim(number_format($n / 1000000, 1), '0'), '.') . 'M';
    }
}

/* ---------------------------------------------------------------------------
 * JSON responses (AJAX + future REST API share this shape)
 * ------------------------------------------------------------------------- */
if (!function_exists('json_response')) {
    /**
     * Emit a consistent JSON envelope and stop.
     *
     *   { "success": bool, "message": string, "data": {...}, "errors": {...} }
     */
    function json_response(bool $success, string $message = '', array $data = [], array $errors = [], int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        $payload = ['success' => $success, 'message' => $message];
        if (!$success && $errors) {
            $payload['errors'] = $errors;
        }
        $payload['data'] = (object) $data;
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (!function_exists('wants_json')) {
    /** True if the request prefers a JSON response (AJAX / API). */
    function wants_json(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $xrw    = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        return stripos($accept, 'application/json') !== false
            || strtolower($xrw) === 'xmlhttprequest';
    }
}
