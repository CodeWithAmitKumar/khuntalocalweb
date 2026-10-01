<?php
/**
 * KhuntaLocal — Comments (add / list / report / moderate).
 *
 * Moderation statuses: pending, approved, hidden, deleted.
 * When the `comment_moderation` setting is on, new comments start as pending;
 * otherwise they are auto-approved. comment_count on news tracks APPROVED only.
 */

declare(strict_types=1);

if (!function_exists('comment_recount')) {
    /** Recompute the visible (approved) comment count for a news item. */
    function comment_recount(int $newsId): void
    {
        $n = (int) fetch_column("SELECT COUNT(*) FROM comments WHERE news_id = ? AND status = 'approved'", [$newsId], 0);
        db_update('news', ['comment_count' => $n], ['id' => $newsId]);
    }
}

if (!function_exists('comment_add')) {
    /**
     * Add a comment or reply. Returns ['id'=>int,'status'=>string].
     *
     * @return array{id:int,status:string}
     */
    function comment_add(int $newsId, int $userId, string $body, ?int $parentId = null): array
    {
        $body = trim($body);
        $moderate = setting_bool('comment_moderation', true);
        $status   = $moderate ? 'pending' : 'approved';

        // Validate parent belongs to the same news and is visible.
        if ($parentId) {
            $parent = fetch('SELECT id, news_id, status, parent_id FROM comments WHERE id = ? LIMIT 1', [$parentId]);
            if (!$parent || (int) $parent['news_id'] !== $newsId || $parent['status'] !== 'approved') {
                $parentId = null; // ignore invalid parent -> top-level
            } elseif ($parent['parent_id']) {
                $parentId = (int) $parent['parent_id']; // flatten to one reply level
            }
        }

        $id = db_insert('comments', [
            'news_id'   => $newsId,
            'user_id'   => $userId,
            'parent_id' => $parentId,
            'body'      => $body,
            'status'    => $status,
        ]);

        if ($status === 'approved') {
            comment_recount($newsId);
            // Notify the reporter of a new comment (not on their own comment).
            $authorId = (int) fetch_column('SELECT user_id FROM news WHERE id = ?', [$newsId], 0);
            if ($authorId && $authorId !== $userId) {
                notify($authorId, 'comment.new', 'New comment on your story', str_excerpt($body, 120), ['news_id' => $newsId]);
            }
        }
        return ['id' => $id, 'status' => $status];
    }
}

if (!function_exists('comments_for_news')) {
    /**
     * Approved comments for an article, threaded one level deep.
     * @return array<int,array<string,mixed>>  each top-level comment with 'replies'
     */
    function comments_for_news(int $newsId): array
    {
        $rows = fetch_all(
            "SELECT c.id, c.parent_id, c.body, c.created_at, c.user_id,
                    u.name AS author_name, u.username AS author_username, u.avatar AS author_avatar
               FROM comments c JOIN users u ON u.id = c.user_id
              WHERE c.news_id = ? AND c.status = 'approved'
              ORDER BY c.created_at ASC",
            [$newsId]
        );
        $top = [];
        $byId = [];
        foreach ($rows as $r) {
            $r['replies'] = [];
            $byId[(int) $r['id']] = $r;
        }
        foreach ($byId as $id => $r) {
            if ($r['parent_id'] && isset($byId[(int) $r['parent_id']])) {
                $byId[(int) $r['parent_id']]['replies'][] = &$byId[$id];
            }
        }
        foreach ($byId as $id => &$r) {
            if (!$r['parent_id'] || !isset($byId[(int) $r['parent_id']])) {
                $top[] = &$r;
            }
        }
        unset($r);
        return $top;
    }
}

if (!function_exists('comment_report')) {
    /** File a report against a comment. */
    function comment_report(int $commentId, int $userId, string $reason, string $note = ''): bool
    {
        $exists = fetch('SELECT id FROM comments WHERE id = ? LIMIT 1', [$commentId]);
        if (!$exists) {
            return false;
        }
        // One open report per user per comment.
        $dup = fetch("SELECT id FROM comment_reports WHERE comment_id = ? AND user_id = ? AND status IN ('open','reviewing') LIMIT 1", [$commentId, $userId]);
        if ($dup) {
            return true;
        }
        db_insert('comment_reports', [
            'comment_id' => $commentId,
            'user_id'    => $userId,
            'reason'     => mb_substr($reason, 0, 50),
            'note'       => $note !== '' ? mb_substr($note, 0, 500) : null,
        ]);
        notify_staff('comment.moderate', 'comment.reported', 'A comment was reported', str_excerpt($reason, 120));
        return true;
    }
}

if (!function_exists('comment_set_status')) {
    /** Moderator action: approve / hide / delete a comment. */
    function comment_set_status(int $commentId, string $status, int $moderatorId): bool
    {
        if (!in_array($status, ['approved', 'hidden', 'deleted', 'pending'], true)) {
            return false;
        }
        $c = fetch('SELECT id, news_id, status FROM comments WHERE id = ? LIMIT 1', [$commentId]);
        if (!$c) {
            return false;
        }
        db_update('comments', ['status' => $status], ['id' => $commentId]);
        comment_recount((int) $c['news_id']);

        // Resolve any open reports when hidden/deleted/approved.
        if (in_array($status, ['hidden', 'deleted', 'approved'], true)) {
            db_run("UPDATE comment_reports SET status = 'resolved' WHERE comment_id = ? AND status IN ('open','reviewing')", [$commentId]);
        }
        audit_log('ADMIN_MODERATED_COMMENT', [
            'entity_type' => 'comment', 'entity_id' => $commentId,
            'old_status' => (string) $c['status'], 'new_status' => $status, 'admin_id' => $moderatorId,
        ]);
        return true;
    }
}

if (!function_exists('comment_moderation_list')) {
    /**
     * Comments for the moderation screen (pending or reported), newest first.
     * @return array<int,array<string,mixed>>
     */
    function comment_moderation_list(string $scope = 'pending', int $limit = 50): array
    {
        if ($scope === 'reported') {
            return fetch_all(
                "SELECT c.*, u.name AS author_name, n.title AS news_title, n.slug AS news_slug,
                        (SELECT COUNT(*) FROM comment_reports cr WHERE cr.comment_id = c.id AND cr.status IN ('open','reviewing')) AS open_reports
                   FROM comments c
                   JOIN users u ON u.id = c.user_id
                   JOIN news  n ON n.id = c.news_id
                  WHERE EXISTS (SELECT 1 FROM comment_reports cr WHERE cr.comment_id = c.id AND cr.status IN ('open','reviewing'))
                  ORDER BY c.created_at DESC LIMIT " . (int) $limit
            );
        }
        return fetch_all(
            "SELECT c.*, u.name AS author_name, n.title AS news_title, n.slug AS news_slug, 0 AS open_reports
               FROM comments c
               JOIN users u ON u.id = c.user_id
               JOIN news  n ON n.id = c.news_id
              WHERE c.status = 'pending'
              ORDER BY c.created_at DESC LIMIT " . (int) $limit
        );
    }
}
