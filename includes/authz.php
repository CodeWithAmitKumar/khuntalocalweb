<?php
/**
 * KhuntaLocal — Authorization (role-based access control).
 *
 * Roles and permissions live in the database (roles, permissions,
 * role_permissions, user_roles). super_admin implicitly has every permission.
 * Every admin/reporter action must call require_permission() / can().
 */

declare(strict_types=1);

if (!function_exists('user_roles')) {
    /**
     * Role slugs for a user (cached per request).
     *
     * @return array<int,string>
     */
    function user_roles(?int $userId = null): array
    {
        static $cache = [];
        $userId = $userId ?? auth_user_id();
        if (!$userId) {
            return [];
        }
        if (isset($cache[$userId])) {
            return $cache[$userId];
        }
        try {
            $rows = fetch_all(
                'SELECT r.slug FROM user_roles ur
                   JOIN roles r ON r.id = ur.role_id
                  WHERE ur.user_id = ?',
                [$userId]
            );
        } catch (Throwable $ex) {
            return $cache[$userId] = [];
        }
        return $cache[$userId] = array_column($rows, 'slug');
    }
}

if (!function_exists('user_permissions')) {
    /**
     * Permission slugs for a user (cached). super_admin => ['*'].
     *
     * @return array<int,string>
     */
    function user_permissions(?int $userId = null): array
    {
        static $cache = [];
        $userId = $userId ?? auth_user_id();
        if (!$userId) {
            return [];
        }
        if (isset($cache[$userId])) {
            return $cache[$userId];
        }

        if (in_array('super_admin', user_roles($userId), true)) {
            return $cache[$userId] = ['*'];
        }

        try {
            $rows = fetch_all(
                'SELECT DISTINCT p.slug
                   FROM user_roles ur
                   JOIN role_permissions rp ON rp.role_id = ur.role_id
                   JOIN permissions p ON p.id = rp.permission_id
                  WHERE ur.user_id = ?',
                [$userId]
            );
        } catch (Throwable $ex) {
            return $cache[$userId] = [];
        }
        return $cache[$userId] = array_column($rows, 'slug');
    }
}

if (!function_exists('has_role')) {
    function has_role(string $roleSlug, ?int $userId = null): bool
    {
        return in_array($roleSlug, user_roles($userId), true);
    }
}

if (!function_exists('is_staff')) {
    /** True for any back-office role (not a plain member). */
    function is_staff(?int $userId = null): bool
    {
        foreach (user_roles($userId) as $slug) {
            if (in_array($slug, ['verification_admin', 'content_admin', 'moderator', 'super_admin'], true)) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('can')) {
    /**
     * Does the (current) user hold a permission? super_admin => always true.
     */
    function can(string $permission, ?int $userId = null): bool
    {
        $perms = user_permissions($userId);
        return in_array('*', $perms, true) || in_array($permission, $perms, true);
    }
}

if (!function_exists('require_permission')) {
    /**
     * Hard gate: must be logged in AND hold the permission, else 403.
     */
    function require_permission(string $permission): array
    {
        $user = require_login();
        if (!can($permission, (int) $user['id'])) {
            if (function_exists('wants_json') && wants_json()) {
                json_response(false, 'You do not have permission to perform this action.', [], [], 403);
            }
            http_response_code(403);
            exit('403 — You do not have permission to access this page.');
        }
        return $user;
    }
}

if (!function_exists('require_role')) {
    function require_role(string $roleSlug): array
    {
        $user = require_login();
        if (!has_role($roleSlug, (int) $user['id']) && !has_role('super_admin', (int) $user['id'])) {
            http_response_code(403);
            exit('403 — You do not have permission to access this page.');
        }
        return $user;
    }
}
