<?php
/**
 * KhuntaLocal — Engagement (like / save / share) + news reports.
 *
 * Counters on the news row are kept in sync. Basic abuse protection: likes and
 * saves are unique per user; shares are lightly deduplicated per IP per hour.
 */

declare(strict_types=1);

if (!function_exists('user_has_liked')) {
    function user_has_liked(int $newsId, int $userId): bool
    {
        return (bool) fetch_column('SELECT 1 FROM likes WHERE news_id = ? AND user_id = ? LIMIT 1', [$newsId, $userId]);
    }
}

if (!function_exists('user_has_saved')) {
    function user_has_saved(int $newsId, int $userId): bool
    {
        return (bool) fetch_column('SELECT 1 FROM saved_news WHERE news_id = ? AND user_id = ? LIMIT 1', [$newsId, $userId]);
    }
}

if (!function_exists('engage_toggle_like')) {
    /** @return array{liked:bool,count:int} */
    function engage_toggle_like(int $newsId, int $userId): array
    {
        return db_transaction(function () use ($newsId, $userId): array {
            $liked = user_has_liked($newsId, $userId);
            if ($liked) {
                db_run('DELETE FROM likes WHERE news_id = ? AND user_id = ?', [$newsId, $userId]);
            } else {
                // INSERT IGNORE guards the unique(news_id,user_id) against races.
                db_run('INSERT IGNORE INTO likes (news_id, user_id) VALUES (?, ?)', [$newsId, $userId]);
            }
            $count = (int) fetch_column('SELECT COUNT(*) FROM likes WHERE news_id = ?', [$newsId], 0);
            db_update('news', ['like_count' => $count], ['id' => $newsId]);
            return ['liked' => !$liked, 'count' => $count];
        });
    }
}

if (!function_exists('engage_toggle_save')) {
    /** @return array{saved:bool} */
    function engage_toggle_save(int $newsId, int $userId): array
    {
        $saved = user_has_saved($newsId, $userId);
        if ($saved) {
            db_run('DELETE FROM saved_news WHERE news_id = ? AND user_id = ?', [$newsId, $userId]);
        } else {
            db_run('INSERT IGNORE INTO saved_news (news_id, user_id) VALUES (?, ?)', [$newsId, $userId]);
        }
        return ['saved' => !$saved];
    }
}

if (!function_exists('engage_record_share')) {
    /** @return array{count:int} */
    function engage_record_share(int $newsId, ?int $userId, string $channel = ''): array
    {
        $hash = ip_hash();
        // Dedupe: one counted share per IP per news per hour.
        $recent = fetch_column(
            'SELECT 1 FROM shares WHERE news_id = ? AND ip_hash = ? AND created_at > (NOW() - INTERVAL 1 HOUR) LIMIT 1',
            [$newsId, $hash]
        );
        db_insert('shares', [
            'news_id' => $newsId,
            'user_id' => $userId,
            'channel' => $channel !== '' ? mb_substr($channel, 0, 40) : null,
            'ip_hash' => $hash,
        ]);
        if (!$recent) {
            db_run('UPDATE news SET share_count = share_count + 1 WHERE id = ?', [$newsId]);
        }
        $count = (int) fetch_column('SELECT share_count FROM news WHERE id = ?', [$newsId], 0);
        return ['count' => $count];
    }
}

if (!function_exists('report_news')) {
    /**
     * File a report against a published news item. Deduped per user while open.
     */
    function report_news(int $newsId, ?int $userId, string $reason, string $note = ''): bool
    {
        $valid = ['false_information', 'duplicate', 'offensive', 'copyright', 'spam', 'wrong_information', 'other'];
        if (!in_array($reason, $valid, true)) {
            return false;
        }
        $news = fetch('SELECT id FROM news WHERE id = ? LIMIT 1', [$newsId]);
        if (!$news) {
            return false;
        }
        if ($userId) {
            $dup = fetch("SELECT id FROM news_reports WHERE news_id = ? AND user_id = ? AND status IN ('open','reviewing') LIMIT 1", [$newsId, $userId]);
            if ($dup) {
                return true; // already reported — treat as success, no duplicate
            }
        }
        db_insert('news_reports', [
            'news_id' => $newsId,
            'user_id' => $userId,
            'reason'  => $reason,
            'note'    => $note !== '' ? mb_substr($note, 0, 1000) : null,
            'ip_hash' => ip_hash(),
        ]);
        notify_staff('report.review', 'news.reported', 'A story was reported', 'Reason: ' . $reason, ['news_id' => $newsId]);
        return true;
    }
}

if (!function_exists('report_set_status')) {
    /** Moderator action on a news report. */
    function report_set_status(int $reportId, string $status, int $moderatorId): bool
    {
        if (!in_array($status, ['open', 'reviewing', 'resolved', 'dismissed'], true)) {
            return false;
        }
        $r = fetch('SELECT id, news_id, status FROM news_reports WHERE id = ? LIMIT 1', [$reportId]);
        if (!$r) {
            return false;
        }
        $data = ['status' => $status];
        if (in_array($status, ['resolved', 'dismissed'], true)) {
            $data['resolved_by'] = $moderatorId;
            $data['resolved_at'] = date('Y-m-d H:i:s');
        }
        db_update('news_reports', $data, ['id' => $reportId]);
        audit_log('ADMIN_REVIEWED_REPORT', [
            'entity_type' => 'news_report', 'entity_id' => $reportId, 'news_id' => (int) $r['news_id'],
            'old_status' => (string) $r['status'], 'new_status' => $status, 'admin_id' => $moderatorId,
        ]);
        return true;
    }
}

if (!function_exists('reason_label')) {
    function reason_label(string $reason): string
    {
        $map = [
            'false_information' => 'False information',
            'duplicate'         => 'Duplicate',
            'offensive'         => 'Offensive content',
            'copyright'         => 'Copyright issue',
            'spam'              => 'Spam',
            'wrong_information' => 'Wrong information',
            'other'             => 'Other',
        ];
        return $map[$reason] ?? ucfirst(str_replace('_', ' ', $reason));
    }
}
