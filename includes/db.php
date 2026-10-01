<?php
/**
 * KhuntaLocal — Database layer (PDO, MySQL, utf8mb4).
 *
 * Every query in the application goes through these helpers, and every piece of
 * user-controlled input is passed as a bound parameter — never concatenated
 * into SQL.
 */

declare(strict_types=1);

if (!function_exists('db')) {
    /**
     * Return the shared PDO connection (lazily created).
     */
    function db(): PDO
    {
        static $pdo = null;
        if ($pdo instanceof PDO) {
            return $pdo;
        }

        $c = config('db');
        $charset = $c['charset'] ?? 'utf8mb4';
        if (!empty($c['socket'])) {
            // Unix-socket connection (common on shared hosting / local servers).
            $dsn = sprintf('mysql:unix_socket=%s;dbname=%s;charset=%s', $c['socket'], $c['database'], $charset);
        } else {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $c['host'],
                (int) ($c['port'] ?? 3306),
                $c['database'],
                $charset
            );
        }

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Use real prepared statements (defence in depth against injection).
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ];

        try {
            $pdo = new PDO($dsn, $c['username'], $c['password'] ?? '', $options);
        } catch (PDOException $ex) {
            // Never leak credentials / DSN to the browser.
            error_log('KhuntaLocal DB connection failed: ' . $ex->getMessage());
            http_response_code(500);
            if (defined('KL_DEBUG') && KL_DEBUG) {
                exit('Database connection failed: ' . $ex->getMessage());
            }
            exit('The service is temporarily unavailable. Please try again later.');
        }

        return $pdo;
    }
}

if (!function_exists('db_run')) {
    /**
     * Prepare + execute a statement with bound params. Returns the statement.
     *
     * @param array<string|int,mixed> $params
     */
    function db_run(string $sql, array $params = []): PDOStatement
    {
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
}

if (!function_exists('fetch')) {
    /**
     * Fetch a single row (associative) or null.
     *
     * @return array<string,mixed>|null
     */
    function fetch(string $sql, array $params = []): ?array
    {
        $row = db_run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }
}

if (!function_exists('fetch_all')) {
    /**
     * Fetch all rows.
     *
     * @return array<int,array<string,mixed>>
     */
    function fetch_all(string $sql, array $params = []): array
    {
        return db_run($sql, $params)->fetchAll();
    }
}

if (!function_exists('fetch_column')) {
    /**
     * Fetch a single scalar value (first column of first row).
     */
    function fetch_column(string $sql, array $params = [], $default = null)
    {
        $val = db_run($sql, $params)->fetchColumn();
        return $val === false ? $default : $val;
    }
}

if (!function_exists('db_insert')) {
    /**
     * Insert an associative array into a table. Returns the new id.
     *
     * Column names come from code (never user input); values are bound.
     *
     * @param array<string,mixed> $data
     */
    function db_insert(string $table, array $data): int
    {
        $cols         = array_keys($data);
        $placeholders = array_map(static fn($c) => ':' . $c, $cols);
        $quotedCols   = array_map(static fn($c) => '`' . $c . '`', $cols);

        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $table,
            implode(', ', $quotedCols),
            implode(', ', $placeholders)
        );

        $params = [];
        foreach ($data as $k => $v) {
            $params[':' . $k] = $v;
        }
        db_run($sql, $params);
        return (int) db()->lastInsertId();
    }
}

if (!function_exists('db_update')) {
    /**
     * Update rows matching a simple equality WHERE map. Returns affected rows.
     *
     * @param array<string,mixed> $data   columns to set
     * @param array<string,mixed> $where  equality conditions (ANDed)
     */
    function db_update(string $table, array $data, array $where): int
    {
        if (!$data || !$where) {
            throw new InvalidArgumentException('db_update requires data and where.');
        }
        $set    = [];
        $params = [];
        foreach ($data as $k => $v) {
            $set[]              = '`' . $k . '` = :set_' . $k;
            $params[':set_' . $k] = $v;
        }
        $cond = [];
        foreach ($where as $k => $v) {
            $cond[]               = '`' . $k . '` = :w_' . $k;
            $params[':w_' . $k]   = $v;
        }
        $sql = sprintf(
            'UPDATE `%s` SET %s WHERE %s',
            $table,
            implode(', ', $set),
            implode(' AND ', $cond)
        );
        return db_run($sql, $params)->rowCount();
    }
}

if (!function_exists('db_transaction')) {
    /**
     * Run a callback inside a transaction; commits on success, rolls back on
     * any exception (which is re-thrown).
     *
     * @param callable $fn  fn(PDO $pdo): mixed
     * @return mixed
     */
    function db_transaction(callable $fn)
    {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $result = $fn($pdo);
            $pdo->commit();
            return $result;
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $ex;
        }
    }
}
