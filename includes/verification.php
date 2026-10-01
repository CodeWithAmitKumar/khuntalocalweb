<?php
/**
 * KhuntaLocal — Verification & workflow service.
 *
 * Phase 2 provides:
 *   - fetching a full news row by id for review
 *   - a basic verification "assistant" (required fields, source presence,
 *     reporter history, media, spam hints) for HUMAN reviewers
 *   - a lightweight similar-story / possible-duplicate finder
 *   - transactional status transitions (approve / reject / request info /
 *     schedule / start review) that write audit + verification logs and notify
 *     the reporter
 *
 * The automated engine (external evidence, richer scoring, auto-publish) is
 * expanded in Phase 4. Nothing here claims a story is absolutely true — it
 * surfaces evidence and concerns for a person to decide.
 */

declare(strict_types=1);

if (!function_exists('news_get')) {
    /** Full news row (any status) by id, with joined display fields. */
    function news_get(int $id): ?array
    {
        return fetch(news_select_base() . ' WHERE n.id = ? LIMIT 1', [$id]);
    }
}

if (!function_exists('reporter_history')) {
    /**
     * Internal operational stats for a reporter (NOT a public credibility score).
     *
     * @return array{total:int,published:int,approved:int,rejected:int,
     *               needs_information:int,pending:int,reports:int}
     */
    function reporter_history(int $userId): array
    {
        $out = ['total' => 0, 'published' => 0, 'approved' => 0, 'rejected' => 0,
                'needs_information' => 0, 'pending' => 0, 'under_review' => 0, 'reports' => 0];
        foreach (fetch_all('SELECT status, COUNT(*) c FROM news WHERE user_id = ? GROUP BY status', [$userId]) as $r) {
            $out[$r['status']] = (int) $r['c'];
            $out['total'] += (int) $r['c'];
        }
        $out['reports'] = (int) fetch_column(
            'SELECT COUNT(*) FROM news_reports nr JOIN news n ON n.id = nr.news_id WHERE n.user_id = ?',
            [$userId],
            0
        );
        return $out;
    }
}

if (!function_exists('kl_keywords')) {
    /**
     * Extract significant lowercase keywords from text (drops stopwords and
     * very short tokens). Used for similarity.
     *
     * @return array<int,string>
     */
    function kl_keywords(string $text): array
    {
        static $stop = null;
        if ($stop === null) {
            $stop = array_flip(explode(' ',
                'the a an and or but of to in on at for with from by is are was were be been being '
                . 'this that these those it its as into near over under out up down new news khunta '
                . 'local report said says will has have had not no yes we you they he she his her '
                . 'about after before during between across begins begin started start'));
        }
        $text  = mb_strtolower($text);
        $parts = preg_split('/[^a-z0-9]+/', $text) ?: [];
        $words = [];
        foreach ($parts as $w) {
            if (mb_strlen($w) >= 4 && !isset($stop[$w])) {
                $words[$w] = true;
            }
        }
        return array_keys($words);
    }
}

if (!function_exists('verification_find_similar')) {
    /**
     * Find possibly-similar / duplicate stories for a human to compare.
     * Rough Jaccard overlap of title+summary keywords; returns rows with a
     * match level. This assists review — it does not decide anything.
     *
     * @return array<int,array<string,mixed>>  each row + 'match_level','match_score'
     */
    function verification_find_similar(array $news, int $limit = 6): array
    {
        $keywords = kl_keywords(($news['title'] ?? '') . ' ' . ($news['summary'] ?? ''));
        if (!$keywords) {
            return [];
        }

        // Candidate pool: recent items in the same category or sharing a keyword
        // in the title. Bounded for performance.
        $candidates = fetch_all(
            news_select_base()
            . ' WHERE n.id <> ? AND (n.category_id = ? OR n.title LIKE ?)
                ORDER BY n.created_at DESC LIMIT 80',
            [
                (int) $news['id'],
                (int) ($news['category_id'] ?? 0),
                '%' . str_replace(['%', '_'], ['\%', '\_'], $keywords[0]) . '%',
            ]
        );

        $kwSet   = array_flip($keywords);
        $scored  = [];
        foreach ($candidates as $c) {
            $ck = kl_keywords(($c['title'] ?? '') . ' ' . ($c['summary'] ?? ''));
            if (!$ck) {
                continue;
            }
            $inter = 0;
            foreach ($ck as $w) {
                if (isset($kwSet[$w])) {
                    $inter++;
                }
            }
            $union = count($kwSet) + count($ck) - $inter;
            $score = $union > 0 ? $inter / $union : 0.0;
            if ($score <= 0) {
                continue;
            }
            $c['match_score'] = round($score, 3);
            $c['match_level'] = $score >= 0.5 ? 'high' : ($score >= 0.25 ? 'medium' : 'low');
            $scored[] = $c;
        }
        usort($scored, static fn($a, $b) => $b['match_score'] <=> $a['match_score']);
        return array_slice($scored, 0, $limit);
    }
}

if (!function_exists('verification_basic_checks')) {
    /**
     * Produce a list of human-readable checks for the verification panel.
     *
     * @param array<string,mixed> $news
     * @param array<int,array<string,mixed>> $sources
     * @param array<int,array<string,mixed>> $similar
     * @return array{checks:array<int,array{label:string,status:string,detail:string}>,
     *               concerns:array<int,string>}
     */
    function verification_basic_checks(array $news, array $sources, array $similar): array
    {
        $checks   = [];
        $concerns = [];

        // Required fields.
        $missing = [];
        foreach (['title' => 'headline', 'body' => 'description', 'category_id' => 'category', 'location_id' => 'location'] as $field => $label) {
            if (empty($news[$field])) {
                $missing[] = $label;
            }
        }
        $checks[] = [
            'label'  => 'Required fields',
            'status' => $missing ? 'fail' : 'pass',
            'detail' => $missing ? 'Missing: ' . implode(', ', $missing) : 'All required fields present.',
        ];
        if ($missing) {
            $concerns[] = 'Submission is missing: ' . implode(', ', $missing) . '.';
        }

        // Duplicate / similar.
        $topLevel = $similar[0]['match_level'] ?? null;
        $checks[] = [
            'label'  => 'Duplicate / similar stories',
            'status' => $topLevel === 'high' ? 'warn' : ($topLevel ? 'info' : 'pass'),
            'detail' => $similar
                ? (count($similar) . ' possibly related — strongest match: ' . strtoupper((string) $topLevel))
                : 'No similar stories found.',
        ];
        if ($topLevel === 'high') {
            $concerns[] = 'A strongly similar story already exists — check for duplication.';
        }

        // Source / reference.
        $hasUrl = false;
        foreach ($sources as $s) {
            if (!empty($s['url'])) { $hasUrl = true; break; }
        }
        $checks[] = [
            'label'  => 'Source / references',
            'status' => $sources ? ($hasUrl ? 'pass' : 'info') : 'info',
            'detail' => $sources
                ? (count($sources) . ' provided' . ($hasUrl ? ', including a link.' : ' (no link).'))
                : 'No external source provided (common for eyewitness reports).',
        ];

        // Reporter history.
        $hist = reporter_history((int) $news['user_id']);
        $histStatus = 'pass';
        if ($hist['total'] <= 1) {
            $histStatus = 'info';
        }
        if ($hist['rejected'] >= 3 && $hist['rejected'] > $hist['published']) {
            $histStatus = 'warn';
            $concerns[] = 'Reporter has a relatively high number of rejected submissions.';
        }
        $checks[] = [
            'label'  => 'Reporter history',
            'status' => $histStatus,
            'detail' => sprintf('%d published, %d rejected, %d total.', $hist['published'], $hist['rejected'], $hist['total']),
        ];

        // Media.
        $hasCover = !empty($news['cover_path']);
        $checks[] = [
            'label'  => 'Media',
            'status' => $hasCover ? 'pass' : 'info',
            'detail' => $hasCover ? 'Cover image attached.' : 'No image attached.',
        ];

        // Spam hints.
        $body      = (string) ($news['body'] ?? '');
        $linkCount = preg_match_all('#https?://#i', $body);
        $shouting  = preg_match_all('/[A-Z]{6,}/', (string) $news['title']);
        $spam      = ($linkCount >= 5) || ($shouting >= 2);
        $checks[]  = [
            'label'  => 'Spam indicators',
            'status' => $spam ? 'warn' : 'pass',
            'detail' => $spam ? 'Possible spam signals (many links or shouting).' : 'No obvious spam signals.',
        ];
        if ($spam) {
            $concerns[] = 'Content shows possible spam signals — review carefully.';
        }

        // Open user reports on this item.
        $openReports = (int) fetch_column(
            "SELECT COUNT(*) FROM news_reports WHERE news_id = ? AND status IN ('open','reviewing')",
            [(int) $news['id']],
            0
        );
        if ($openReports > 0) {
            $checks[] = ['label' => 'User reports', 'status' => 'warn', 'detail' => $openReports . ' open report(s).'];
            $concerns[] = $openReports . ' user report(s) are open against this story.';
        } else {
            $checks[] = ['label' => 'User reports', 'status' => 'pass', 'detail' => 'No open reports.'];
        }

        return ['checks' => $checks, 'concerns' => $concerns];
    }
}

/* ===========================================================================
 * Status transitions (transactional, audited, idempotent).
 * ======================================================================== */

if (!function_exists('news_start_review')) {
    /** Claim a pending item for review (pending -> under_review). */
    function news_start_review(int $newsId, int $adminId): bool
    {
        return db_transaction(function () use ($newsId, $adminId): bool {
            $row = fetch('SELECT id, status FROM news WHERE id = ? FOR UPDATE', [$newsId]);
            if (!$row || $row['status'] !== 'pending') {
                return false;
            }
            db_update('news', [
                'status'      => 'under_review',
                'reviewed_by' => $adminId,
            ], ['id' => $newsId]);
            verification_log($newsId, 'UNDER_REVIEW', [
                'actor_type' => 'admin', 'admin_id' => $adminId,
                'old_status' => 'pending', 'new_status' => 'under_review',
            ]);
            return true;
        });
    }
}

if (!function_exists('news_approve')) {
    /** Approve & publish. Idempotent: no-op if already published. */
    function news_approve(int $newsId, int $adminId): bool
    {
        $ok = db_transaction(function () use ($newsId, $adminId): bool {
            $row = fetch('SELECT id, status, user_id, title FROM news WHERE id = ? FOR UPDATE', [$newsId]);
            if (!$row) {
                return false;
            }
            if ($row['status'] === 'published') {
                return false; // already published — never publish twice
            }
            $old = (string) $row['status'];
            db_update('news', [
                'status'       => 'published',
                'published_at' => date('Y-m-d H:i:s'),
                'reviewed_at'  => date('Y-m-d H:i:s'),
                'reviewed_by'  => $adminId,
            ], ['id' => $newsId]);

            audit_log('ADMIN_APPROVED_NEWS', [
                'entity_type' => 'news', 'entity_id' => $newsId, 'news_id' => $newsId,
                'old_status' => $old, 'new_status' => 'published', 'admin_id' => $adminId,
            ]);
            verification_log($newsId, 'APPROVED', [
                'actor_type' => 'admin', 'admin_id' => $adminId,
                'old_status' => $old, 'new_status' => 'published',
                'note' => 'Approved and published.',
            ]);
            $GLOBALS['_kl_tx_news'] = $row; // pass to post-commit notify
            return true;
        });
        if ($ok) {
            $row = $GLOBALS['_kl_tx_news'];
            notify((int) $row['user_id'], 'news.published', 'Your story is now published',
                '“' . str_excerpt((string) $row['title'], 80) . '” has been reviewed and published.',
                ['news_id' => $newsId]);
        }
        return $ok;
    }
}

if (!function_exists('news_reject')) {
    /** Reject with a required reason. */
    function news_reject(int $newsId, int $adminId, string $reason): bool
    {
        $reason = trim($reason);
        if ($reason === '') {
            return false;
        }
        $ok = db_transaction(function () use ($newsId, $adminId, $reason): bool {
            $row = fetch('SELECT id, status, user_id, title FROM news WHERE id = ? FOR UPDATE', [$newsId]);
            if (!$row || $row['status'] === 'rejected') {
                return false;
            }
            $old = (string) $row['status'];
            db_update('news', [
                'status'           => 'rejected',
                'rejection_reason' => mb_substr($reason, 0, 500),
                'reviewed_at'      => date('Y-m-d H:i:s'),
                'reviewed_by'      => $adminId,
            ], ['id' => $newsId]);
            audit_log('ADMIN_REJECTED_NEWS', [
                'entity_type' => 'news', 'entity_id' => $newsId, 'news_id' => $newsId,
                'old_status' => $old, 'new_status' => 'rejected', 'reason' => $reason, 'admin_id' => $adminId,
            ]);
            verification_log($newsId, 'REJECTED', [
                'actor_type' => 'admin', 'admin_id' => $adminId,
                'old_status' => $old, 'new_status' => 'rejected', 'note' => $reason,
            ]);
            $GLOBALS['_kl_tx_news'] = $row;
            return true;
        });
        if ($ok) {
            $row = $GLOBALS['_kl_tx_news'];
            notify((int) $row['user_id'], 'news.rejected', 'Your story was not approved',
                'Reason: ' . $reason, ['news_id' => $newsId]);
        }
        return $ok;
    }
}

if (!function_exists('news_request_info')) {
    /** Ask the reporter for more information (-> needs_information). */
    function news_request_info(int $newsId, int $adminId, string $note): bool
    {
        $note = trim($note);
        if ($note === '') {
            return false;
        }
        $ok = db_transaction(function () use ($newsId, $adminId, $note): bool {
            $row = fetch('SELECT id, status, user_id, title FROM news WHERE id = ? FOR UPDATE', [$newsId]);
            if (!$row || in_array($row['status'], ['published', 'rejected'], true)) {
                return false;
            }
            $old = (string) $row['status'];
            db_update('news', [
                'status'           => 'needs_information',
                'rejection_reason' => mb_substr($note, 0, 500), // reused as the latest reviewer note
                'reviewed_at'      => date('Y-m-d H:i:s'),
                'reviewed_by'      => $adminId,
            ], ['id' => $newsId]);
            audit_log('ADMIN_REQUESTED_INFO', [
                'entity_type' => 'news', 'entity_id' => $newsId, 'news_id' => $newsId,
                'old_status' => $old, 'new_status' => 'needs_information', 'reason' => $note, 'admin_id' => $adminId,
            ]);
            verification_log($newsId, 'NEEDS_INFORMATION', [
                'actor_type' => 'admin', 'admin_id' => $adminId,
                'old_status' => $old, 'new_status' => 'needs_information', 'note' => $note,
            ]);
            $GLOBALS['_kl_tx_news'] = $row;
            return true;
        });
        if ($ok) {
            $row = $GLOBALS['_kl_tx_news'];
            notify((int) $row['user_id'], 'news.needs_information', 'More information requested',
                $note, ['news_id' => $newsId]);
        }
        return $ok;
    }
}

if (!function_exists('news_schedule')) {
    /** Schedule for future publication (cron publishes it in Phase 4). */
    function news_schedule(int $newsId, int $adminId, string $datetime): bool
    {
        $ts = strtotime($datetime);
        if ($ts === false) {
            return false;
        }
        $ok = db_transaction(function () use ($newsId, $adminId, $ts): bool {
            $row = fetch('SELECT id, status, user_id, title FROM news WHERE id = ? FOR UPDATE', [$newsId]);
            if (!$row || $row['status'] === 'published') {
                return false;
            }
            $old = (string) $row['status'];
            db_update('news', [
                'status'       => 'scheduled',
                'scheduled_at' => date('Y-m-d H:i:s', $ts),
                'reviewed_at'  => date('Y-m-d H:i:s'),
                'reviewed_by'  => $adminId,
            ], ['id' => $newsId]);
            audit_log('ADMIN_SCHEDULED_NEWS', [
                'entity_type' => 'news', 'entity_id' => $newsId, 'news_id' => $newsId,
                'old_status' => $old, 'new_status' => 'scheduled', 'admin_id' => $adminId,
            ]);
            verification_log($newsId, 'SCHEDULED', [
                'actor_type' => 'admin', 'admin_id' => $adminId,
                'old_status' => $old, 'new_status' => 'scheduled',
                'note' => 'Scheduled for ' . date('Y-m-d H:i', $ts),
            ]);
            $GLOBALS['_kl_tx_news'] = $row;
            return true;
        });
        if ($ok) {
            $row = $GLOBALS['_kl_tx_news'];
            notify((int) $row['user_id'], 'news.scheduled', 'Your story was scheduled',
                'It will be published on ' . date('d M Y, H:i', $ts) . '.', ['news_id' => $newsId]);
        }
        return $ok;
    }
}

if (!function_exists('verification_timeline')) {
    /** Audit history for the verify page. @return array<int,array<string,mixed>> */
    function verification_timeline(int $newsId): array
    {
        return fetch_all(
            'SELECT vl.*, u.name AS admin_name
               FROM news_verification_logs vl
               LEFT JOIN users u ON u.id = vl.admin_id
              WHERE vl.news_id = ? ORDER BY vl.created_at DESC, vl.id DESC',
            [$newsId]
        );
    }
}

if (!function_exists('review_timer')) {
    /**
     * Compute review-window status from submitted_at + settings.
     *
     * @return array{elapsed_min:int,min_min:int,max_min:int,state:string,label:string}
     */
    function review_timer(?string $submittedAt): array
    {
        $minM = setting_int('verification_min_minutes', (int) config('verification.min_review_minutes', 60));
        $maxM = setting_int('verification_max_minutes', (int) config('verification.max_review_minutes', 120));
        if (!$submittedAt) {
            return ['elapsed_min' => 0, 'min_min' => $minM, 'max_min' => $maxM, 'state' => 'new', 'label' => 'Just now'];
        }
        $elapsed = max(0, (int) floor((time() - strtotime($submittedAt)) / 60));
        if ($elapsed < $minM) {
            $state = 'within';   // still inside minimum window
        } elseif ($elapsed < $maxM) {
            $state = 'due';      // past minimum, within maximum
        } else {
            $state = 'overdue';  // past maximum review window
        }
        $label = $elapsed < 60 ? ($elapsed . ' min') : (floor($elapsed / 60) . 'h ' . ($elapsed % 60) . 'm');
        return ['elapsed_min' => $elapsed, 'min_min' => $minM, 'max_min' => $maxM, 'state' => $state, 'label' => $label];
    }
}
