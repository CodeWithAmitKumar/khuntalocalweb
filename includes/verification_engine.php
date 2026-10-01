<?php
/**
 * KhuntaLocal — Automated verification engine (Phase 4).
 *
 * Runs a full set of checks over a submission, computes a RISK LEVEL and a
 * CRITICAL flag, and persists a structured result to news_verification. It is a
 * decision-support tool for human reviewers and the auto-publish policy — it
 * never asserts that a story is true, and never shows an unexplained "% true".
 *
 * External fact-check / search APIs are pluggable and OFF by default (no key in
 * config => the evidence step reports "not configured" and the engine degrades
 * gracefully). Credentials come from config/env, never hard-coded.
 */

declare(strict_types=1);

if (!function_exists('verification_engine_run')) {
    /**
     * Run all checks for a news item and (by default) persist the result.
     *
     * @param array{persist?:bool,actor_type?:string,admin_id?:?int} $opts
     * @return array{
     *   risk_level:string, critical:bool, score:int,
     *   checks:array<int,array{key:string,label:string,status:string,detail:string}>,
     *   concerns:array<int,string>, evidence:array<string,mixed>, duplicate_news_id:?int
     * }
     */
    function verification_engine_run(int $newsId, array $opts = []): array
    {
        $news = news_get($newsId);
        if (!$news) {
            return ['risk_level' => 'unknown', 'critical' => true, 'score' => 0,
                    'checks' => [], 'concerns' => ['News item not found.'], 'evidence' => [], 'duplicate_news_id' => null];
        }

        $sources = news_sources_for($newsId);
        $images  = news_media_for($newsId, 'image');
        $videos  = news_media_for($newsId, 'video');
        $similar = verification_find_similar($news, 6);
        $hist    = reporter_history((int) $news['user_id']);

        $checks   = [];
        $concerns = [];
        $score    = 0;        // higher = riskier
        $critical = false;
        $dupId    = null;

        $add = static function (string $key, string $label, string $status, string $detail) use (&$checks) {
            $checks[] = ['key' => $key, 'label' => $label, 'status' => $status, 'detail' => $detail];
        };

        /* 1. Required fields -------------------------------------------------- */
        $missing = [];
        foreach (['title' => 'headline', 'body' => 'description', 'category_id' => 'category', 'location_id' => 'location'] as $f => $lbl) {
            if (empty($news[$f])) { $missing[] = $lbl; }
        }
        if ($missing) {
            $critical = true;
            $score   += 100;
            $concerns[] = 'Missing required fields: ' . implode(', ', $missing) . '.';
            $add('required_fields', 'Required fields', 'fail', 'Missing: ' . implode(', ', $missing));
        } else {
            $add('required_fields', 'Required fields', 'pass', 'All required fields present.');
        }

        /* 2. Body length / quality ------------------------------------------- */
        $bodyLen = mb_strlen(trim((string) $news['body']));
        if ($bodyLen < 60) {
            $score += 15;
            $concerns[] = 'The description is very short (' . $bodyLen . ' chars).';
            $add('content_length', 'Content length', 'warn', $bodyLen . ' characters — quite short.');
        } else {
            $add('content_length', 'Content length', 'pass', $bodyLen . ' characters.');
        }

        /* 3. Duplicate / similar --------------------------------------------- */
        if ($similar) {
            $top = $similar[0];
            $dupId = (int) $top['id'];
            if ($top['match_level'] === 'high') {
                $score += 40;
                $concerns[] = 'A strongly similar story already exists (#' . $dupId . ') — possible duplicate.';
                $add('duplicate', 'Duplicate / similar', 'warn', count($similar) . ' related; strongest: HIGH (#' . $dupId . ')');
            } else {
                $add('duplicate', 'Duplicate / similar', 'info', count($similar) . ' related; strongest: ' . strtoupper((string) $top['match_level']));
            }
        } else {
            $add('duplicate', 'Duplicate / similar', 'pass', 'No similar stories found.');
        }

        /* 4. Sources / references -------------------------------------------- */
        $hasUrl = false;
        foreach ($sources as $s) { if (!empty($s['url'])) { $hasUrl = true; break; } }
        if ($sources) {
            $add('sources', 'Source / references', $hasUrl ? 'pass' : 'info',
                count($sources) . ' provided' . ($hasUrl ? ', including a link.' : ' (no link).'));
        } else {
            $score += 8;
            $add('sources', 'Source / references', 'info', 'No external source (common for eyewitness reports).');
        }

        /* 5. Reporter history ------------------------------------------------- */
        if ($hist['total'] <= 1) {
            $score += 10;
            $add('reporter', 'Reporter history', 'info', 'First-time or new reporter.');
        } elseif ($hist['rejected'] >= 3 && $hist['rejected'] > $hist['published']) {
            $score += 20;
            $concerns[] = 'Reporter has a high number of rejected submissions.';
            $add('reporter', 'Reporter history', 'warn', sprintf('%d published, %d rejected.', $hist['published'], $hist['rejected']));
        } else {
            $add('reporter', 'Reporter history', 'pass', sprintf('%d published, %d rejected.', $hist['published'], $hist['rejected']));
        }

        /* 6. Submission timestamp -------------------------------------------- */
        $subTs = !empty($news['submitted_at']) ? strtotime((string) $news['submitted_at']) : 0;
        if ($subTs && $subTs > time() + 3600) {
            $score += 10;
            $concerns[] = 'Submission timestamp is in the future.';
            $add('timestamp', 'Submission time', 'warn', 'Timestamp is in the future.');
        } else {
            $add('timestamp', 'Submission time', 'pass', $subTs ? date('d M Y, H:i', $subTs) : 'n/a');
        }

        /* 7. Location consistency -------------------------------------------- */
        $add('location', 'Location', !empty($news['location_name']) ? 'pass' : 'info',
            !empty($news['location_name']) ? (string) $news['location_name'] : 'No location set.');

        /* 8. Media metadata --------------------------------------------------- */
        if ($images || $videos) {
            $detail = count($images) . ' image(s), ' . count($videos) . ' video(s).';
            // Best-effort EXIF read on the first image (never fatal).
            $exifNote = '';
            if ($images) {
                $path = rtrim((string) config('uploads.path', ''), '/') . '/' . ltrim((string) $images[0]['path'], '/');
                if (function_exists('exif_read_data') && is_file($path)) {
                    $exif = @exif_read_data($path);
                    if ($exif && !empty($exif['DateTimeOriginal'])) {
                        $exifNote = ' EXIF date: ' . $exif['DateTimeOriginal'];
                    }
                }
            }
            $add('media', 'Media', 'pass', $detail . $exifNote);
        } else {
            $add('media', 'Media', 'info', 'No media attached.');
        }

        /* 9. Spam indicators -------------------------------------------------- */
        $body      = (string) $news['body'];
        $linkCount = (int) preg_match_all('#https?://#i', $body);
        $shouting  = (int) preg_match_all('/[A-Z]{6,}/', (string) $news['title']);
        $repeat    = (bool) preg_match('/(.)\1{6,}/', $body);
        if ($linkCount >= 5 || $shouting >= 2 || $repeat) {
            $score += 25;
            $concerns[] = 'Possible spam signals (many links, shouting, or repeated characters).';
            $add('spam', 'Spam indicators', 'warn', "links={$linkCount}, shouting={$shouting}" . ($repeat ? ', repeats' : ''));
        } else {
            $add('spam', 'Spam indicators', 'pass', 'No obvious spam signals.');
        }

        /* 10. Repeated submissions ------------------------------------------- */
        $dupSubs = (int) fetch_column(
            'SELECT COUNT(*) FROM news WHERE user_id = ? AND id <> ? AND title = ? AND created_at > (NOW() - INTERVAL 1 DAY)',
            [(int) $news['user_id'], $newsId, (string) $news['title']],
            0
        );
        if ($dupSubs > 0) {
            $score += 30;
            $concerns[] = 'The reporter submitted the same headline ' . $dupSubs . ' time(s) in the last 24h.';
            $add('repeat_submissions', 'Repeated submissions', 'warn', $dupSubs . ' identical title(s) in 24h.');
        } else {
            $add('repeat_submissions', 'Repeated submissions', 'pass', 'No repeated submissions.');
        }

        /* 11. User reports --------------------------------------------------- */
        $openReports = (int) fetch_column("SELECT COUNT(*) FROM news_reports WHERE news_id = ? AND status IN ('open','reviewing')", [$newsId], 0);
        if ($openReports > 0) {
            $score += 35;
            $critical = true;
            $concerns[] = $openReports . ' open user report(s) against this story.';
            $add('user_reports', 'User reports', 'warn', $openReports . ' open report(s).');
        } else {
            $add('user_reports', 'User reports', 'pass', 'No open reports.');
        }

        /* 12. External evidence (pluggable, off by default) ------------------ */
        $evidence = verification_external_evidence($news);
        $add('external_evidence', 'External evidence',
            $evidence['status'], $evidence['detail']);

        /* ---- Risk level ---------------------------------------------------- */
        if ($critical || $score >= 60) {
            $risk = 'high';
        } elseif ($score >= 25) {
            $risk = 'medium';
        } else {
            $risk = 'low';
        }

        $result = [
            'risk_level'        => $risk,
            'critical'          => $critical,
            'score'             => $score,
            'checks'            => $checks,
            'concerns'          => $concerns,
            'evidence'          => $evidence,
            'duplicate_news_id' => $dupId,
        ];

        if (($opts['persist'] ?? true) === true) {
            verification_engine_persist($newsId, $result, $opts);
        }
        return $result;
    }
}

if (!function_exists('verification_external_evidence')) {
    /**
     * Pluggable external evidence lookup. Returns a check-shaped array. Performs
     * NO network calls unless an API key is configured (none by default).
     *
     * @return array{status:string,detail:string,results:array}
     */
    function verification_external_evidence(array $news): array
    {
        $key = (string) config('external.factcheck_api_key', '');
        if ($key === '') {
            return ['status' => 'info', 'detail' => 'External fact-check API not configured.', 'results' => []];
        }
        // A real integration would query the configured provider here, guarding
        // network errors. Kept intentionally inert so no key => no calls.
        return ['status' => 'info', 'detail' => 'External lookup configured (no provider wired in this build).', 'results' => []];
    }
}

if (!function_exists('verification_engine_persist')) {
    /** Upsert the engine result into news_verification + news.risk_level + log. */
    function verification_engine_persist(int $newsId, array $result, array $opts = []): void
    {
        try {
            db_run(
                'INSERT INTO news_verification
                    (news_id, risk_level, checks_json, concerns_json, evidence_json, duplicate_news_id, last_checked_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE
                    risk_level = VALUES(risk_level),
                    checks_json = VALUES(checks_json),
                    concerns_json = VALUES(concerns_json),
                    evidence_json = VALUES(evidence_json),
                    duplicate_news_id = VALUES(duplicate_news_id),
                    last_checked_at = NOW()',
                [
                    $newsId,
                    $result['risk_level'],
                    json_encode($result['checks'], JSON_UNESCAPED_UNICODE),
                    json_encode($result['concerns'], JSON_UNESCAPED_UNICODE),
                    json_encode($result['evidence'], JSON_UNESCAPED_UNICODE),
                    $result['duplicate_news_id'],
                ]
            );
            db_update('news', ['risk_level' => $result['risk_level']], ['id' => $newsId]);

            verification_log($newsId, 'AUTO_CHECK', [
                'actor_type' => $opts['actor_type'] ?? 'system',
                'admin_id'   => $opts['admin_id'] ?? null,
                'note'       => 'Automated checks: risk=' . $result['risk_level']
                    . ', score=' . $result['score']
                    . ($result['critical'] ? ', CRITICAL' : ''),
                'data'       => ['risk' => $result['risk_level'], 'score' => $result['score'], 'critical' => $result['critical']],
            ]);
        } catch (Throwable $ex) {
            error_log('verification_engine_persist failed: ' . $ex->getMessage());
        }
    }
}

if (!function_exists('verification_latest')) {
    /** Load the persisted verification row (decoded) for display. */
    function verification_latest(int $newsId): ?array
    {
        $row = fetch('SELECT * FROM news_verification WHERE news_id = ? LIMIT 1', [$newsId]);
        if (!$row) {
            return null;
        }
        $row['checks']   = $row['checks_json']   ? json_decode((string) $row['checks_json'], true)   : [];
        $row['concerns'] = $row['concerns_json'] ? json_decode((string) $row['concerns_json'], true) : [];
        $row['evidence'] = $row['evidence_json'] ? json_decode((string) $row['evidence_json'], true) : [];
        return $row;
    }
}
