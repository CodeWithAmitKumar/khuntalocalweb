<?php
/**
 * KhuntaLocal — News repository.
 *
 * Shared, parameterised queries used by the homepage, category pages, article
 * pages and profile. ORDER BY uses a whitelist (never user input), and every
 * value is bound.
 */

declare(strict_types=1);

if (!function_exists('news_select_base')) {
    /** Common SELECT + JOINs that populate a news card / article. */
    function news_select_base(): string
    {
        return '
            SELECT n.id, n.uuid, n.slug, n.title, n.summary, n.body, n.status,
                   n.priority, n.is_breaking, n.is_featured, n.breaking_expires_at,
                   n.language_code, n.risk_level, n.seo_title, n.meta_description,
                   n.canonical_url, n.view_count, n.like_count, n.share_count,
                   n.comment_count, n.published_at, n.created_at, n.updated_at,
                   n.user_id, n.category_id, n.location_id,
                   c.name AS category_name, c.slug AS category_slug,
                   c.color AS category_color, c.icon AS category_icon,
                   l.name AS location_name, l.slug AS location_slug,
                   u.name AS author_name, u.username AS author_username,
                   u.avatar AS author_avatar,
                   m.path AS cover_path, m.thumb_path AS cover_thumb
              FROM news n
              LEFT JOIN categories c ON c.id = n.category_id
              LEFT JOIN locations  l ON l.id = n.location_id
              LEFT JOIN users      u ON u.id = n.user_id
              LEFT JOIN news_media m ON m.id = n.cover_media_id
        ';
    }
}

if (!function_exists('news_order_clause')) {
    /** Whitelisted ORDER BY — the key comes from user input, the SQL does not. */
    function news_order_clause(string $sort): string
    {
        switch ($sort) {
            case 'oldest':      return 'n.published_at ASC';
            case 'most_viewed': return 'n.view_count DESC, n.published_at DESC';
            case 'most_shared': return 'n.share_count DESC, n.published_at DESC';
            case 'trending':    return '(n.view_count + n.share_count * 3 + n.like_count * 2) DESC, n.published_at DESC';
            case 'latest':
            default:            return 'n.published_at DESC';
        }
    }
}

if (!function_exists('news_fetch_published')) {
    /**
     * Fetch published articles for cards / listings.
     *
     * @param array{category_id?:int,location_id?:int,language?:string,
     *              search?:string,is_breaking?:bool,is_featured?:bool,
     *              trending_days?:int,exclude_id?:int} $filters
     * @return array<int,array<string,mixed>>
     */
    function news_fetch_published(array $filters = [], string $sort = 'latest', int $limit = 12, int $offset = 0): array
    {
        [$where, $params] = news_published_where($filters);
        $sql = news_select_base()
            . ' WHERE ' . implode(' AND ', $where)
            . ' ORDER BY ' . news_order_clause($sort)
            . ' LIMIT ' . max(1, min(100, $limit))
            . ' OFFSET ' . max(0, $offset);
        return fetch_all($sql, $params);
    }
}

if (!function_exists('news_count_published')) {
    function news_count_published(array $filters = []): int
    {
        [$where, $params] = news_published_where($filters);
        $sql = 'SELECT COUNT(*) FROM news n WHERE ' . implode(' AND ', $where);
        return (int) fetch_column($sql, $params, 0);
    }
}

if (!function_exists('news_published_where')) {
    /**
     * Build the shared WHERE for "visible to the public" plus optional filters.
     *
     * @return array{0:array<int,string>,1:array<int,mixed>}
     */
    function news_published_where(array $filters): array
    {
        $where  = ["n.status = 'published'", 'n.published_at <= NOW()'];
        $params = [];

        if (!empty($filters['category_id'])) {
            $where[]  = 'n.category_id = ?';
            $params[] = (int) $filters['category_id'];
        }
        if (!empty($filters['location_id'])) {
            $where[]  = 'n.location_id = ?';
            $params[] = (int) $filters['location_id'];
        }
        if (!empty($filters['language'])) {
            $where[]  = 'n.language_code = ?';
            $params[] = (string) $filters['language'];
        }
        if (!empty($filters['user_id'])) {
            $where[]  = 'n.user_id = ?';
            $params[] = (int) $filters['user_id'];
        }
        if (!empty($filters['is_breaking'])) {
            $where[] = 'n.is_breaking = 1 AND (n.breaking_expires_at IS NULL OR n.breaking_expires_at > NOW())';
        }
        if (!empty($filters['is_featured'])) {
            $where[] = 'n.is_featured = 1';
        }
        if (!empty($filters['has_video'])) {
            $where[] = "EXISTS (SELECT 1 FROM news_media m WHERE m.news_id = n.id AND m.type = 'video')";
        }
        if (!empty($filters['has_gallery'])) {
            $where[] = "EXISTS (SELECT 1 FROM news_media m WHERE m.news_id = n.id AND m.type = 'image')";
        }
        if (!empty($filters['date_from'])) {
            $where[]  = 'n.published_at >= ?';
            $params[] = (string) $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[]  = 'n.published_at <= ?';
            $params[] = (string) $filters['date_to'] . ' 23:59:59';
        }
        if (!empty($filters['trending_days'])) {
            $where[]  = 'n.published_at > (NOW() - INTERVAL ? DAY)';
            $params[] = (int) $filters['trending_days'];
        }
        if (!empty($filters['exclude_id'])) {
            $where[]  = 'n.id <> ?';
            $params[] = (int) $filters['exclude_id'];
        }
        if (!empty($filters['search'])) {
            // Simple, safe LIKE search (full-text search UI arrives in Phase 3).
            $where[]  = '(n.title LIKE ? OR n.summary LIKE ? OR n.body LIKE ?)';
            $like     = '%' . str_replace(['%', '_'], ['\%', '\_'], (string) $filters['search']) . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        return [$where, $params];
    }
}

if (!function_exists('news_get_by_slug')) {
    /**
     * Full article by slug. Published articles are visible to everyone; the
     * author and staff may preview their own non-published items.
     *
     * @return array<string,mixed>|null
     */
    function news_get_by_slug(string $slug): ?array
    {
        $row = fetch(news_select_base() . ' WHERE n.slug = ? LIMIT 1', [$slug]);
        if (!$row) {
            return null;
        }
        $isPublic = $row['status'] === 'published'
            && (empty($row['published_at']) || strtotime((string) $row['published_at']) <= time());
        if ($isPublic) {
            return $row;
        }
        $uid = auth_user_id();
        if ($uid && ((int) $row['user_id'] === $uid || (function_exists('is_staff') && is_staff()))) {
            return $row;
        }
        return null;
    }
}

if (!function_exists('news_record_view')) {
    /**
     * Count a view with light abuse protection: one counted view per article
     * per IP per hour.
     */
    function news_record_view(int $newsId): void
    {
        try {
            $hash = ip_hash();
            $seen = fetch_column(
                'SELECT 1 FROM news_views
                  WHERE news_id = ? AND ip_hash = ? AND viewed_at > (NOW() - INTERVAL 1 HOUR)
                  LIMIT 1',
                [$newsId, $hash]
            );
            db_insert('news_views', [
                'news_id'    => $newsId,
                'user_id'    => auth_user_id(),
                'ip_hash'    => $hash,
                'user_agent' => user_agent(),
            ]);
            if (!$seen) {
                db_run('UPDATE news SET view_count = view_count + 1 WHERE id = ?', [$newsId]);
            }
        } catch (Throwable $ex) {
            error_log('news_record_view failed: ' . $ex->getMessage());
        }
    }
}

if (!function_exists('news_sources_for')) {
    /** @return array<int,array<string,mixed>> */
    function news_sources_for(int $newsId): array
    {
        return fetch_all('SELECT * FROM news_sources WHERE news_id = ? ORDER BY id', [$newsId]);
    }
}

if (!function_exists('news_media_for')) {
    /**
     * Media for an article, optionally filtered by type ('image'|'video').
     * @return array<int,array<string,mixed>>
     */
    function news_media_for(int $newsId, ?string $type = null): array
    {
        if ($type !== null) {
            return fetch_all(
                'SELECT * FROM news_media WHERE news_id = ? AND type = ? ORDER BY sort_order, id',
                [$newsId, $type]
            );
        }
        return fetch_all('SELECT * FROM news_media WHERE news_id = ? ORDER BY sort_order, id', [$newsId]);
    }
}

/* ---------------------------------------------------------------------------
 * Reference data (categories / locations / languages) — cached per request.
 * ------------------------------------------------------------------------- */
if (!function_exists('active_categories')) {
    function active_categories(): array
    {
        static $c = null;
        if ($c !== null) {
            return $c;
        }
        try {
            return $c = fetch_all('SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order, name');
        } catch (Throwable $ex) {
            return $c = [];
        }
    }
}

if (!function_exists('active_locations')) {
    function active_locations(): array
    {
        static $c = null;
        if ($c !== null) {
            return $c;
        }
        try {
            return $c = fetch_all('SELECT * FROM locations WHERE is_active = 1 ORDER BY sort_order, name');
        } catch (Throwable $ex) {
            return $c = [];
        }
    }
}

if (!function_exists('active_languages')) {
    function active_languages(): array
    {
        static $c = null;
        if ($c !== null) {
            return $c;
        }
        try {
            return $c = fetch_all('SELECT * FROM languages WHERE is_active = 1 ORDER BY sort_order, name');
        } catch (Throwable $ex) {
            return $c = [];
        }
    }
}

if (!function_exists('status_label')) {
    /** Human label + badge style for a news status. */
    function status_label(string $status): array
    {
        $map = [
            'draft'             => ['Draft', 'secondary'],
            'pending'           => ['Pending', 'warning'],
            'under_review'      => ['Under review', 'info'],
            'needs_information' => ['Needs information', 'warning'],
            'approved'          => ['Approved', 'success'],
            'scheduled'         => ['Scheduled', 'info'],
            'published'         => ['Published', 'success'],
            'rejected'          => ['Rejected', 'danger'],
            'expired'           => ['Expired', 'secondary'],
            'archived'          => ['Archived', 'secondary'],
        ];
        return $map[$status] ?? [ucfirst($status), 'secondary'];
    }
}
