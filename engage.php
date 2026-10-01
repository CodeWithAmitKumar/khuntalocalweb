<?php
/**
 * KhuntaLocal — Engagement endpoint (JSON): like / save / share.
 * Consumed by assets/js/app.js. CSRF token is sent via the X-CSRF-Token header.
 */
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_post()) {
    json_response(false, 'Method not allowed.', [], [], 405);
}
if (!csrf_verify($_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null))) {
    json_response(false, 'Your session expired. Please refresh.', [], [], 419);
}

$action = input('action');
$newsId = (int) input('news_id', '0');

$news = $newsId > 0 ? fetch("SELECT id, status FROM news WHERE id = ? LIMIT 1", [$newsId]) : null;
if (!$news || $news['status'] !== 'published') {
    json_response(false, 'Story not available.', [], [], 404);
}

$uid = auth_user_id();

switch ($action) {
    case 'like':
        if (!$uid) {
            json_response(false, 'Please log in to like stories.', ['login' => true], [], 401);
        }
        if (!rate_limit_hit('like', rate_limit_key($uid), 60, 600)) {
            json_response(false, 'Too many actions. Slow down a little.', [], [], 429);
        }
        $r = engage_toggle_like($newsId, $uid);
        json_response(true, $r['liked'] ? 'Liked' : 'Like removed', $r);
        break;

    case 'save':
        if (!$uid) {
            json_response(false, 'Please log in to save stories.', ['login' => true], [], 401);
        }
        $r = engage_toggle_save($newsId, $uid);
        json_response(true, $r['saved'] ? 'Saved' : 'Removed from saved', $r);
        break;

    case 'share':
        if (!rate_limit_hit('share', rate_limit_key($uid), 60, 600)) {
            json_response(true, 'ok', ['count' => (int) fetch_column('SELECT share_count FROM news WHERE id = ?', [$newsId], 0)]);
        }
        $r = engage_record_share($newsId, $uid, input('channel'));
        json_response(true, 'Shared', $r);
        break;

    default:
        json_response(false, 'Unknown action.', [], [], 400);
}
