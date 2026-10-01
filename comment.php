<?php
/**
 * KhuntaLocal — Comment actions (POST): add a comment/reply, or report one.
 */
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

if (!is_post()) {
    redirect('');
}
$user = require_login();
csrf_check();

$action = input('action', 'add');
$newsId = (int) input('news_id', '0');

$news = $newsId > 0 ? fetch("SELECT id, slug, status FROM news WHERE id = ? LIMIT 1", [$newsId]) : null;
if (!$news || $news['status'] !== 'published') {
    flash_set('error', 'This story is not available for comments.');
    redirect('');
}
$back = 'news/' . $news['slug'] . '#comments';

if ($action === 'report') {
    if (!rate_limit_hit('comment_report', rate_limit_key((int) $user['id']), 20, 3600)) {
        flash_set('error', 'Too many reports. Please try again later.');
        redirect($back);
    }
    $commentId = (int) input('comment_id', '0');
    $reason    = input('reason', 'other');
    comment_report($commentId, (int) $user['id'], $reason, input('note'));
    flash_set('success', 'Thank you. Our moderators will review this comment.');
    redirect($back);
}

// Default: add a comment / reply.
if (!rate_limit_hit('comment_add', rate_limit_key((int) $user['id']), 15, 600)) {
    flash_set('error', 'You are commenting too quickly. Please wait a moment.');
    redirect($back);
}

$body     = trim((string) ($_POST['body'] ?? ''));
$parentId = (int) input('parent_id', '0') ?: null;

$v = new Validator($_POST);
$v->required('body', 'Comment')->min('body', 2)->max('body', 5000);
if ($v->fails()) {
    flash_set('error', $v->firstError() ?? 'Please write a comment.');
    redirect($back);
}

$res = comment_add($newsId, (int) $user['id'], $body, $parentId);
if ($res['status'] === 'approved') {
    flash_set('success', 'Your comment was posted.');
} else {
    flash_set('info', 'Thanks! Your comment will appear after moderation.');
}
redirect($back);
