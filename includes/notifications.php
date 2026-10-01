<?php
/**
 * KhuntaLocal — Notifications (DB-backed).
 *
 * Phase 1 records notifications (e.g. "news submitted"); the full in-app
 * notification centre + Android push API arrive in later phases. The storage
 * and helper are in place now so the workflow writes real data from day one.
 */

declare(strict_types=1);

if (!function_exists('notify')) {
    /**
     * Create a notification for a user.
     *
     * @param array{news_id?:?int,data?:?array} $context
     */
    function notify(int $userId, string $type, string $title, string $body = '', array $context = []): void
    {
        try {
            db_insert('notifications', [
                'user_id'   => $userId,
                'type'      => $type,
                'title'     => $title,
                'body'      => $body !== '' ? $body : null,
                'news_id'   => $context['news_id'] ?? null,
                'data_json' => isset($context['data']) ? json_encode($context['data'], JSON_UNESCAPED_UNICODE) : null,
            ]);
        } catch (Throwable $ex) {
            error_log('notify failed (' . $type . '): ' . $ex->getMessage());
        }
    }
}

if (!function_exists('notify_staff')) {
    /**
     * Notify every holder of a given permission (e.g. new submission -> verifiers).
     * Used lightly in Phase 1; expanded in the admin phases.
     */
    function notify_staff(string $permissionSlug, string $type, string $title, string $body = '', array $context = []): void
    {
        try {
            $rows = fetch_all(
                'SELECT DISTINCT ur.user_id
                   FROM user_roles ur
                   JOIN role_permissions rp ON rp.role_id = ur.role_id
                   JOIN permissions p ON p.id = rp.permission_id
                  WHERE p.slug = ?',
                [$permissionSlug]
            );
            foreach ($rows as $r) {
                notify((int) $r['user_id'], $type, $title, $body, $context);
            }
        } catch (Throwable $ex) {
            error_log('notify_staff failed (' . $type . '): ' . $ex->getMessage());
        }
    }
}

if (!function_exists('unread_notification_count')) {
    function unread_notification_count(int $userId): int
    {
        try {
            return (int) fetch_column(
                'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0',
                [$userId]
            );
        } catch (Throwable $ex) {
            return 0;
        }
    }
}
