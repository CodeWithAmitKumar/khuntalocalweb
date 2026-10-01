<?php
/**
 * KhuntaLocal — Report a news story (POST). Login required (abuse protection).
 */
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

if (!is_post()) {
    redirect('');
}
$user = require_login();
csrf_check();

$newsId = (int) input('news_id', '0');
$news   = $newsId > 0 ? fetch('SELECT id, slug, status FROM news WHERE id = ? LIMIT 1', [$newsId]) : null;
if (!$news) {
    flash_set('error', 'Story not found.');
    redirect('');
}
$back = 'news/' . $news['slug'];

if (!rate_limit_hit('news_report', rate_limit_key((int) $user['id']), 15, 3600)) {
    flash_set('error', 'Too many reports submitted. Please try again later.');
    redirect($back);
}

$reason = input('reason');
$note   = input('note');

$valid = ['false_information', 'duplicate', 'offensive', 'copyright', 'spam', 'wrong_information', 'other'];
if (!in_array($reason, $valid, true)) {
    flash_set('error', 'Please choose a valid reason.');
    redirect($back);
}

if (report_news($newsId, (int) $user['id'], $reason, $note)) {
    flash_set('success', 'Thank you for the report. Our moderators will review it.');
} else {
    flash_set('error', 'Could not submit the report. Please try again.');
}
redirect($back);
