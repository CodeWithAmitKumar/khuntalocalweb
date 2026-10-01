<?php
/**
 * KhuntaLocal — CSRF protection.
 *
 * A per-session token is embedded in every state-changing form and verified on
 * submission with a constant-time comparison.
 */

declare(strict_types=1);

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }
}

if (!function_exists('csrf_field')) {
    /** Hidden input for inclusion in forms. */
    function csrf_field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e_attr(csrf_token()) . '">';
    }
}

if (!function_exists('csrf_verify')) {
    /**
     * Verify a submitted token against the session token.
     */
    function csrf_verify(?string $token): bool
    {
        if (!is_string($token) || $token === '' || empty($_SESSION['_csrf'])) {
            return false;
        }
        return hash_equals($_SESSION['_csrf'], $token);
    }
}

if (!function_exists('csrf_check')) {
    /**
     * Guard a POST request. On failure: JSON error for AJAX, otherwise 419
     * page. Call at the top of every POST handler.
     */
    function csrf_check(): void
    {
        $token = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        if (!csrf_verify($token)) {
            if (function_exists('wants_json') && wants_json()) {
                json_response(false, 'Your session has expired. Please refresh and try again.', [], [], 419);
            }
            http_response_code(419);
            exit('Invalid or expired form token. Please go back, refresh the page and try again.');
        }
    }
}
