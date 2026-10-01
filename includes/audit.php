<?php
/**
 * KhuntaLocal — Audit logging.
 *
 * Records privileged/administrative actions for accountability. Used lightly in
 * Phase 1 (e.g. profile changes) and heavily by admin/verification in later
 * phases. Also exposes a helper to append to the per-news verification log.
 */

declare(strict_types=1);

if (!function_exists('audit_log')) {
    /**
     * Append an entry to admin_logs.
     *
     * @param array{
     *   entity_type?:?string, entity_id?:?int, news_id?:?int,
     *   old_status?:?string, new_status?:?string, reason?:?string, admin_id?:?int
     * } $context
     */
    function audit_log(string $action, array $context = []): void
    {
        try {
            db_insert('admin_logs', [
                'admin_id'    => $context['admin_id'] ?? auth_user_id(),
                'action'      => $action,
                'entity_type' => $context['entity_type'] ?? null,
                'entity_id'   => $context['entity_id'] ?? null,
                'news_id'     => $context['news_id'] ?? null,
                'old_status'  => $context['old_status'] ?? null,
                'new_status'  => $context['new_status'] ?? null,
                'reason'      => $context['reason'] ?? null,
                'ip_address'  => request_ip(),
                'user_agent'  => user_agent(),
            ]);
        } catch (Throwable $ex) {
            error_log('audit_log failed (' . $action . '): ' . $ex->getMessage());
        }
    }
}

if (!function_exists('verification_log')) {
    /**
     * Append to news_verification_logs (the per-article workflow trail).
     *
     * @param array{
     *   actor_type?:string, admin_id?:?int, old_status?:?string,
     *   new_status?:?string, note?:?string, data?:?array
     * } $context
     */
    function verification_log(int $newsId, string $action, array $context = []): void
    {
        try {
            db_insert('news_verification_logs', [
                'news_id'    => $newsId,
                'actor_type' => $context['actor_type'] ?? 'system',
                'admin_id'   => $context['admin_id'] ?? auth_user_id(),
                'action'     => $action,
                'old_status' => $context['old_status'] ?? null,
                'new_status' => $context['new_status'] ?? null,
                'note'       => $context['note'] ?? null,
                'data_json'  => isset($context['data']) ? json_encode($context['data'], JSON_UNESCAPED_UNICODE) : null,
            ]);
        } catch (Throwable $ex) {
            error_log('verification_log failed (' . $action . '): ' . $ex->getMessage());
        }
    }
}
