<?php
/**
 * KhuntaLocal — Pure-logic self-test (no database required).
 *
 * Exercises the framework-free helpers so core logic is proven even in
 * environments without a MySQL server. Run:
 *
 *     php scripts/selftest.php
 *
 * Exits non-zero if any assertion fails.
 */

declare(strict_types=1);

// Minimal environment so helpers that read config don't fatally error.
$GLOBALS['kl_config'] = [
    'app'     => ['url' => 'https://example.test', 'name' => 'KhuntaLocal', 'locale' => 'en'],
    'uploads' => ['url' => '/uploads'],
];
$_SESSION = [];
$_SERVER['REMOTE_ADDR'] = '203.0.113.9';

require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/validation.php';
require __DIR__ . '/../includes/csrf.php';

$passed = 0;
$failed = 0;

function check(string $name, bool $cond): void
{
    global $passed, $failed;
    if ($cond) {
        $passed++;
        echo "  \033[32m✓\033[0m {$name}\n";
    } else {
        $failed++;
        echo "  \033[31m✗ {$name}\033[0m\n";
    }
}

echo "KhuntaLocal self-test\n=====================\n\n";

/* ---- escaping ---------------------------------------------------------- */
echo "Escaping:\n";
check('e() escapes angle brackets', e('<b>x</b>') === '&lt;b&gt;x&lt;/b&gt;');
check('e() escapes quotes',        e('a"b\'c') === 'a&quot;b&#039;c');
check('e() handles null',          e(null) === '');

/* ---- slugify ----------------------------------------------------------- */
echo "\nslugify():\n";
check('basic title',   slugify('Road construction begins near Khunta market') === 'road-construction-begins-near-khunta-market');
check('trims symbols', slugify('Hello, World!!!') === 'hello-world');
check('collapses dashes', slugify('a---b   c') === 'a-b-c');
check('non-latin fallback not empty', slugify('ଓଡ଼ିଆ ଖବର') !== '');
check('respects max length', strlen(slugify(str_repeat('word ', 100), 50)) <= 50);

/* ---- unique_slug ------------------------------------------------------- */
echo "\nunique_slug():\n";
$taken = ['news', 'news-2'];
$exists = static fn(string $s): bool => in_array($s, $taken, true);
check('returns base when free', unique_slug('Fresh Title', $exists) === 'fresh-title');
check('appends suffix on clash', unique_slug('News', $exists) === 'news-3');

/* ---- excerpt / counts / time ------------------------------------------ */
echo "\nformatting:\n";
check('str_excerpt shortens', mb_strlen(str_excerpt(str_repeat('x', 300), 50)) <= 51);
check('str_excerpt keeps short', str_excerpt('short', 50) === 'short');
check('format_count small', format_count(950) === '950');
check('format_count thousands', format_count(1200) === '1.2k');
check('format_count millions', format_count(2000000) === '2M');
check('time_ago minutes', time_ago(time() - 300) === '5 min ago');
check('time_ago hours', time_ago(time() - 7200) === '2 hours ago');
check('time_ago just now', time_ago(time() - 5) === 'just now');

/* ---- uuid -------------------------------------------------------------- */
echo "\nuuid4():\n";
$uuid = uuid4();
check('uuid v4 format', (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuid));
check('uuid unique-ish', uuid4() !== uuid4());

/* ---- ip_hash ----------------------------------------------------------- */
echo "\nip_hash():\n";
check('ip_hash is sha256 hex', (bool) preg_match('/^[0-9a-f]{64}$/', ip_hash()));
check('ip_hash stable', ip_hash('1.2.3.4') === ip_hash('1.2.3.4'));
check('ip_hash differs', ip_hash('1.2.3.4') !== ip_hash('5.6.7.8'));

/* ---- CSRF -------------------------------------------------------------- */
echo "\nCSRF:\n";
$t = csrf_token();
check('token is 64 hex chars', (bool) preg_match('/^[0-9a-f]{64}$/', $t));
check('token stable per session', csrf_token() === $t);
check('verify accepts correct', csrf_verify($t) === true);
check('verify rejects wrong', csrf_verify('deadbeef') === false);
check('verify rejects empty', csrf_verify('') === false);

/* ---- password hashing -------------------------------------------------- */
echo "\npassword hashing:\n";
$hash = password_hash('Secret@123', PASSWORD_DEFAULT);
check('verify matches', password_verify('Secret@123', $hash) === true);
check('verify rejects wrong', password_verify('wrong', $hash) === false);

/* ---- Validator --------------------------------------------------------- */
echo "\nValidator:\n";
$v = new Validator(['name' => '', 'email' => 'bad', 'pw' => '123', 'pw2' => '124']);
$v->required('name')->email('email')->min('pw', 8)->matches('pw2', 'pw');
check('fails on bad input', $v->fails() === true);
check('name required error', isset($v->errors()['name']));
check('email error', isset($v->errors()['email']));
check('min length error', isset($v->errors()['pw']));
check('match error', isset($v->errors()['pw2']));

$v2 = new Validator(['name' => 'Amit', 'email' => 'a@b.com', 'lang' => 'en']);
$v2->required('name')->email('email')->in('lang', ['od', 'en', 'hi']);
check('passes on good input', $v2->passes() === true);

$v3 = new Validator(['u' => 'https://example.com', 'bad' => 'notaurl']);
$v3->url('u');
check('valid url passes', $v3->passes() === true);
$v4 = new Validator(['bad' => 'notaurl']);
$v4->url('bad');
check('invalid url fails', $v4->fails() === true);

/* ---- summary ----------------------------------------------------------- */
echo "\n=====================\n";
echo "Passed: {$passed}   Failed: {$failed}\n";
exit($failed === 0 ? 0 : 1);
