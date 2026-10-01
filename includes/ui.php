<?php
/**
 * KhuntaLocal — UI render helpers (news card + flash messages).
 *
 * Kept as functions so loops over many articles stay clean and every card is
 * rendered identically across the homepage, categories, search and profiles.
 */

declare(strict_types=1);

if (!function_exists('render_flash')) {
    /** Render and clear any pending flash messages as Bootstrap alerts. */
    function render_flash(): void
    {
        $flashes = flash_all();
        if (!$flashes) {
            return;
        }
        $map = [
            'success' => 'alert-success',
            'error'   => 'alert-danger',
            'danger'  => 'alert-danger',
            'warning' => 'alert-warning',
            'info'    => 'alert-info',
        ];
        echo '<div class="kl-flash-wrap">';
        foreach ($flashes as $f) {
            $cls = $map[$f['type']] ?? 'alert-info';
            echo '<div class="alert ' . $cls . ' kl-flash d-flex align-items-start gap-2" role="alert" data-autodismiss>'
                . '<div class="flex-grow-1">' . e($f['message']) . '</div>'
                . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>'
                . '</div>';
        }
        echo '</div>';
    }
}

if (!function_exists('news_cover_tag')) {
    /** Render the media area of a card (image or themed placeholder). */
    function news_cover_tag(array $n): string
    {
        $cover = $n['cover_thumb'] ?? ($n['cover_path'] ?? null);
        if (!empty($cover)) {
            return '<img src="' . e_attr(upload_url($cover)) . '" alt="' . e_attr($n['title'] ?? '') . '" loading="lazy">';
        }
        $icon = $n['category_icon'] ?? '📰';
        return '<div class="kl-card__placeholder"><span>' . e($icon) . '</span></div>';
    }
}

if (!function_exists('render_news_card')) {
    /**
     * Render one news card.
     *
     * @param array<string,mixed> $n     joined news row (see news_select_base)
     * @param array{feature?:bool,class?:string} $opts
     */
    function render_news_card(array $n, array $opts = []): void
    {
        $isFeature = !empty($opts['feature']);
        $extraCls  = $opts['class'] ?? ($isFeature ? 'col-12' : 'col-12 col-sm-6 col-lg-4');
        $url       = news_url((string) $n['slug']);

        $breakingActive = !empty($n['is_breaking'])
            && (empty($n['breaking_expires_at']) || strtotime((string) $n['breaking_expires_at']) > time());

        echo '<div class="' . e_attr($extraCls) . '">';
        echo '<article class="kl-card' . ($isFeature ? ' kl-feature' : '') . '">';

        // Media
        echo '<a class="kl-card__media" href="' . e_attr($url) . '" aria-label="' . e_attr($n['title'] ?? '') . '">';
        echo news_cover_tag($n);
        echo '<div class="position-absolute top-0 start-0 p-2 d-flex gap-1">';
        if ($breakingActive) {
            echo '<span class="kl-badge kl-badge--breaking">● Breaking</span>';
        } elseif (($n['priority'] ?? '') === 'urgent') {
            echo '<span class="kl-badge kl-badge--urgent">Urgent</span>';
        } elseif (($n['priority'] ?? '') === 'featured' || !empty($n['is_featured'])) {
            echo '<span class="kl-badge kl-badge--featured">★ Featured</span>';
        }
        echo '</div>';
        echo '</a>';

        // Body
        echo '<div class="kl-card__body">';
        echo '<div class="d-flex align-items-center gap-2 flex-wrap">';
        if (!empty($n['category_name'])) {
            echo '<a href="' . e_attr(category_url((string) $n['category_slug'])) . '" class="kl-badge kl-badge--category">'
                . e(($n['category_icon'] ? $n['category_icon'] . ' ' : '') . $n['category_name']) . '</a>';
        }
        if (($n['status'] ?? '') === 'published') {
            echo '<span class="kl-badge kl-badge--verified" title="Reviewed by KhuntaLocal">✓ Verified</span>';
        }
        echo '</div>';

        echo '<a href="' . e_attr($url) . '" class="kl-card__title">' . e($n['title'] ?? '') . '</a>';

        $excerpt = $n['summary'] ?? '';
        if ($excerpt === '') {
            $excerpt = str_excerpt($n['body'] ?? '', 140);
        }
        echo '<p class="kl-card__excerpt">' . e($excerpt) . '</p>';

        // Meta
        echo '<div class="kl-card__meta">';
        if (!empty($n['location_name'])) {
            echo '<span>📍 ' . e($n['location_name']) . '</span><span class="dot"></span>';
        }
        echo '<span>' . e(time_ago($n['published_at'] ?? $n['created_at'] ?? '')) . '</span>';
        if (!empty($n['author_name'])) {
            echo '<span class="dot"></span><span>By ' . e($n['author_name']) . '</span>';
        }
        echo '<span class="dot"></span><span>👁 ' . e(format_count((int) ($n['view_count'] ?? 0))) . '</span>';
        echo '</div>';

        echo '</div>';   // body
        echo '</article>';
        echo '</div>';
    }
}

if (!function_exists('render_card_skeletons')) {
    /** Render N skeleton placeholder cards (loading state). */
    function render_card_skeletons(int $count = 3, string $colClass = 'col-12 col-sm-6 col-lg-4'): void
    {
        for ($i = 0; $i < $count; $i++) {
            echo '<div class="' . e_attr($colClass) . '"><div class="kl-card">'
                . '<div class="kl-skeleton" style="aspect-ratio:16/9"></div>'
                . '<div class="kl-card__body">'
                . '<div class="kl-skeleton mb-2" style="height:14px;width:40%"></div>'
                . '<div class="kl-skeleton mb-2" style="height:18px;width:90%"></div>'
                . '<div class="kl-skeleton" style="height:14px;width:70%"></div>'
                . '</div></div></div>';
        }
    }
}

if (!function_exists('render_comment')) {
    /**
     * Render one approved comment (and its replies, one level deep).
     *
     * @param array<string,mixed> $c
     */
    function render_comment(array $c, int $newsId, bool $isReply = false): void
    {
        $id      = (int) $c['id'];
        $cls     = $isReply ? 'ms-4 ms-md-5' : '';
        echo '<div class="kl-card p-3 ' . $cls . '" style="box-shadow:none">';

        // Header
        echo '<div class="d-flex align-items-center gap-2 mb-1">';
        echo '<span class="kl-logo-mark" style="width:30px;height:30px;font-size:.8rem;background:var(--kl-primary-light);color:var(--kl-primary-dark)">'
            . e(mb_substr((string) ($c['author_name'] ?? '?'), 0, 1)) . '</span>';
        echo '<div><a class="fw-semibold text-reset" href="' . e_attr(reporter_url((string) $c['author_username'])) . '">'
            . e((string) $c['author_name']) . '</a>'
            . '<div class="small text-muted-2">' . e(time_ago((string) $c['created_at'])) . '</div></div>';
        echo '</div>';

        // Body
        echo '<p class="mb-2" style="white-space:pre-wrap">' . e((string) $c['body']) . '</p>';

        // Actions (logged in only)
        if (is_logged_in()) {
            echo '<div class="d-flex gap-3 small">';
            if (!$isReply) {
                echo '<button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" data-reply-toggle="' . $id . '">Reply</button>';
            }
            echo '<button type="button" class="btn btn-link btn-sm p-0 text-decoration-none text-muted-2" data-report-toggle="' . $id . '">Report</button>';
            echo '</div>';

            // Reply form (hidden)
            if (!$isReply) {
                echo '<form method="post" action="' . e_attr(base_url('comment.php')) . '" class="mt-2 d-none" data-reply-form="' . $id . '">'
                    . csrf_field()
                    . '<input type="hidden" name="news_id" value="' . $newsId . '">'
                    . '<input type="hidden" name="action" value="add">'
                    . '<input type="hidden" name="parent_id" value="' . $id . '">'
                    . '<textarea class="form-control form-control-sm mb-2" name="body" rows="2" required maxlength="5000" placeholder="Write a reply…"></textarea>'
                    . '<button class="btn btn-emerald btn-sm" type="submit">Reply</button>'
                    . '</form>';
            }

            // Report form (hidden)
            echo '<form method="post" action="' . e_attr(base_url('comment.php')) . '" class="mt-2 d-none" data-report-form="' . $id . '">'
                . csrf_field()
                . '<input type="hidden" name="news_id" value="' . $newsId . '">'
                . '<input type="hidden" name="action" value="report">'
                . '<input type="hidden" name="comment_id" value="' . $id . '">'
                . '<div class="input-group input-group-sm">'
                . '<select class="form-select" name="reason">'
                . '<option value="offensive">Offensive</option>'
                . '<option value="spam">Spam</option>'
                . '<option value="false_information">False information</option>'
                . '<option value="other">Other</option>'
                . '</select>'
                . '<button class="btn btn-outline-danger" type="submit">Report</button>'
                . '</div></form>';
        }

        // Replies
        if (!empty($c['replies'])) {
            echo '<div class="d-grid gap-2 mt-3">';
            foreach ($c['replies'] as $r) {
                render_comment($r, $newsId, true);
            }
            echo '</div>';
        }

        echo '</div>';
    }
}

if (!function_exists('render_pagination')) {
    /**
     * Render Bootstrap pagination. $query is the base query string params to
     * preserve (without the page key).
     *
     * @param array<string,string|int> $query
     */
    function render_pagination(int $page, int $totalPages, string $path, array $query = []): void
    {
        if ($totalPages <= 1) {
            return;
        }
        $mk = static function (int $p) use ($path, $query): string {
            $query['page'] = $p;
            return e_attr(base_url($path) . '?' . http_build_query($query));
        };
        $page = max(1, min($page, $totalPages));
        $start = max(1, $page - 2);
        $end   = min($totalPages, $page + 2);

        echo '<nav aria-label="Pagination" class="mt-4"><ul class="pagination justify-content-center">';
        echo '<li class="page-item ' . ($page <= 1 ? 'disabled' : '') . '">'
            . '<a class="page-link" href="' . $mk(max(1, $page - 1)) . '">Previous</a></li>';
        if ($start > 1) {
            echo '<li class="page-item"><a class="page-link" href="' . $mk(1) . '">1</a></li>';
            if ($start > 2) { echo '<li class="page-item disabled"><span class="page-link">…</span></li>'; }
        }
        for ($p = $start; $p <= $end; $p++) {
            echo '<li class="page-item ' . ($p === $page ? 'active' : '') . '">'
                . '<a class="page-link" href="' . $mk($p) . '">' . $p . '</a></li>';
        }
        if ($end < $totalPages) {
            if ($end < $totalPages - 1) { echo '<li class="page-item disabled"><span class="page-link">…</span></li>'; }
            echo '<li class="page-item"><a class="page-link" href="' . $mk($totalPages) . '">' . $totalPages . '</a></li>';
        }
        echo '<li class="page-item ' . ($page >= $totalPages ? 'disabled' : '') . '">'
            . '<a class="page-link" href="' . $mk(min($totalPages, $page + 1)) . '">Next</a></li>';
        echo '</ul></nav>';
    }
}

if (!function_exists('render_empty_state')) {
    function render_empty_state(string $title, string $message = '', string $icon = '📭'): void
    {
        echo '<div class="kl-empty">'
            . '<div class="kl-empty__icon">' . e($icon) . '</div>'
            . '<h5>' . e($title) . '</h5>'
            . ($message !== '' ? '<p class="mb-0">' . e($message) . '</p>' : '')
            . '</div>';
    }
}
