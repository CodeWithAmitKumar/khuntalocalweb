<?php
/**
 * KhuntaLocal — Authentication (registration, login, sessions).
 *
 * Passwords use password_hash()/password_verify(). The session id is
 * regenerated on privilege change (login) to prevent fixation.
 */

declare(strict_types=1);

if (!function_exists('auth_user_id')) {
    function auth_user_id(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }
}

if (!function_exists('is_logged_in')) {
    function is_logged_in(): bool
    {
        return current_user() !== null;
    }
}

if (!function_exists('current_user')) {
    /**
     * Load the authenticated user row (cached per request). Returns null for
     * guests or if the account is no longer active.
     *
     * @return array<string,mixed>|null
     */
    function current_user(): ?array
    {
        static $loaded = false;
        static $user   = null;

        if ($loaded) {
            return $user;
        }
        $loaded = true;

        $id = auth_user_id();
        if (!$id) {
            return $user = null;
        }
        try {
            $row = fetch(
                'SELECT * FROM users WHERE id = ? LIMIT 1',
                [$id]
            );
        } catch (Throwable $ex) {
            return $user = null;
        }
        if (!$row || ($row['status'] ?? '') !== 'active') {
            // Account gone/suspended — drop the stale session pointer.
            unset($_SESSION['user_id']);
            return $user = null;
        }
        return $user = $row;
    }
}

if (!function_exists('register_user')) {
    /**
     * Create a member/reporter account. Expects already-validated input.
     * Returns the new user id. Assigns the 'member' role.
     *
     * @param array{name:string,email:string,password:string,username?:string,
     *              phone?:?string,location_id?:?int,language_code?:string} $data
     */
    function register_user(array $data): int
    {
        return db_transaction(function () use ($data): int {
            $username = $data['username'] ?? '';
            if ($username === '') {
                $base     = slugify($data['name'] ?: explode('@', $data['email'])[0], 60);
                $username = unique_slug($base, static function (string $u): bool {
                    return (bool) fetch_column('SELECT 1 FROM users WHERE username = ? LIMIT 1', [$u]);
                }, 70);
            }

            $userId = db_insert('users', [
                'name'          => $data['name'],
                'username'      => $username,
                'email'         => strtolower($data['email']),
                'phone'         => $data['phone'] ?? null,
                'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
                'location_id'   => $data['location_id'] ?? null,
                'language_code' => $data['language_code'] ?? 'en',
                'status'        => 'active',
                'reporter_since'=> date('Y-m-d'),
            ]);

            // Assign the member (reporter) role.
            $memberRoleId = (int) fetch_column("SELECT id FROM roles WHERE slug = 'member' LIMIT 1");
            if ($memberRoleId) {
                db_run(
                    'INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)',
                    [$userId, $memberRoleId]
                );
            }

            return $userId;
        });
    }
}

if (!function_exists('login_user_session')) {
    /**
     * Mark a user as logged in: regenerate the session id and store the pointer.
     */
    function login_user_session(int $userId): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['user_id']    = $userId;
        $_SESSION['login_time'] = time();

        try {
            db_update('users', ['last_login_at' => date('Y-m-d H:i:s')], ['id' => $userId]);
        } catch (Throwable $ex) {
            error_log('last_login update failed: ' . $ex->getMessage());
        }
    }
}

if (!function_exists('attempt_login')) {
    /**
     * Verify credentials. On success, starts the authenticated session and
     * returns the user row. On failure returns null. Records attempts for
     * throttling.
     *
     * @return array<string,mixed>|null
     */
    function attempt_login(string $email, string $password): ?array
    {
        $email = strtolower(trim($email));

        $user = fetch('SELECT * FROM users WHERE email = ? LIMIT 1', [$email]);

        if (!$user || !password_verify($password, (string) $user['password_hash'])) {
            login_record_attempt($email, false);
            return null;
        }

        if (($user['status'] ?? '') !== 'active') {
            login_record_attempt($email, false);
            return null;
        }

        // Rehash if the algorithm/cost has since changed.
        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
            try {
                db_update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], ['id' => $user['id']]);
            } catch (Throwable $ex) {
                error_log('password rehash failed: ' . $ex->getMessage());
            }
        }

        login_record_attempt($email, true);
        login_clear_attempts($email);
        login_user_session((int) $user['id']);

        return $user;
    }
}

if (!function_exists('logout')) {
    function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies') && session_status() === PHP_SESSION_ACTIVE) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                [
                    'expires'  => time() - 42000,
                    'path'     => $params['path'],
                    'domain'   => $params['domain'],
                    'secure'   => $params['secure'],
                    'httponly' => $params['httponly'],
                    'samesite' => $params['samesite'] ?? 'Lax',
                ]
            );
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
}

if (!function_exists('require_login')) {
    /**
     * Gate a page behind authentication. Remembers the intended URL so the user
     * returns there after logging in.
     */
    function require_login(): array
    {
        $user = current_user();
        if ($user === null) {
            if (!is_post()) {
                $_SESSION['_intended'] = $_SERVER['REQUEST_URI'] ?? '/';
            }
            flash_set('info', 'Please log in to continue.');
            redirect('login.php');
        }
        return $user;
    }
}

if (!function_exists('intended_url')) {
    /** Pop the remembered post-login destination (default: homepage). */
    function intended_url(string $default = ''): string
    {
        $to = $_SESSION['_intended'] ?? $default;
        unset($_SESSION['_intended']);
        // Only allow same-site relative paths.
        if (is_string($to) && $to !== '' && $to[0] === '/' && !str_starts_with($to, '//')) {
            return ltrim($to, '/');
        }
        return $default;
    }
}
