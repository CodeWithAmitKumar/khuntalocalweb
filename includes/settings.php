<?php
/**
 * KhuntaLocal — Settings (DB key/value with typed casting + request cache).
 *
 * Settings are seeded in the DB and editable by admins (Phase 2). Reads are
 * cached per request. Config file values act as fallbacks before the DB has a
 * value.
 */

declare(strict_types=1);

if (!function_exists('settings_all')) {
    /**
     * Load all settings once per request, cast to native types.
     *
     * @return array<string,mixed>
     */
    function settings_all(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $cache = [];
        try {
            $rows = fetch_all('SELECT `key`, `value`, `type` FROM settings');
        } catch (Throwable $ex) {
            // DB not ready yet — behave as if there are no stored settings.
            return $cache;
        }
        foreach ($rows as $row) {
            $cache[$row['key']] = settings_cast($row['value'], $row['type']);
        }
        return $cache;
    }
}

if (!function_exists('settings_cast')) {
    function settings_cast($value, string $type)
    {
        switch ($type) {
            case 'int':
                return (int) $value;
            case 'bool':
                return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
            case 'json':
                $decoded = json_decode((string) $value, true);
                return $decoded === null ? [] : $decoded;
            default:
                return (string) $value;
        }
    }
}

if (!function_exists('setting')) {
    /**
     * Read one setting with a fallback default.
     */
    function setting(string $key, $default = null)
    {
        $all = settings_all();
        return array_key_exists($key, $all) ? $all[$key] : $default;
    }
}

if (!function_exists('setting_bool')) {
    function setting_bool(string $key, bool $default = false): bool
    {
        $v = setting($key, $default);
        if (is_bool($v)) {
            return $v;
        }
        return in_array(strtolower((string) $v), ['1', 'true', 'yes', 'on'], true);
    }
}

if (!function_exists('setting_int')) {
    function setting_int(string $key, int $default = 0): int
    {
        return (int) setting($key, $default);
    }
}

if (!function_exists('site_name')) {
    function site_name(): string
    {
        return (string) setting('site_name', config('app.name', 'KhuntaLocal'));
    }
}
