<?php
/**
 * KhuntaLocal — Flash messages + old-input repopulation.
 *
 * One-time messages survive exactly one redirect. Old input lets a form refill
 * its fields after a validation failure without echoing passwords.
 */

declare(strict_types=1);

if (!function_exists('flash_set')) {
    /**
     * @param string $type  success | error | info | warning
     */
    function flash_set(string $type, string $message): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }
}

if (!function_exists('flash_all')) {
    /**
     * Return and clear all pending flash messages.
     *
     * @return array<int,array{type:string,message:string}>
     */
    function flash_all(): array
    {
        $flashes = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $flashes;
    }
}

if (!function_exists('flash_has')) {
    function flash_has(): bool
    {
        return !empty($_SESSION['_flash']);
    }
}

/* ---------------------------------------------------------------------------
 * Old input
 * ------------------------------------------------------------------------- */
if (!function_exists('old_flash')) {
    /**
     * Stash input so a redirected-back form can repopulate. Sensitive keys are
     * never stored.
     *
     * @param array<string,mixed> $input
     */
    function old_flash(array $input): void
    {
        unset($input['password'], $input['password_confirm'], $input['_csrf']);
        $_SESSION['_old'] = $input;
    }
}

if (!function_exists('old_load')) {
    /** Move stashed old input into a request-scoped store. */
    function old_load(): void
    {
        $GLOBALS['_kl_old'] = $_SESSION['_old'] ?? [];
        unset($_SESSION['_old']);
    }
}

if (!function_exists('old')) {
    /**
     * Read an old-input value (after old_load()), falling back to a default.
     */
    function old(string $key, string $default = ''): string
    {
        $store = $GLOBALS['_kl_old'] ?? [];
        $val   = $store[$key] ?? $default;
        return is_string($val) ? $val : (string) $val;
    }
}
