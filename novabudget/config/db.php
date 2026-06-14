<?php
// ─────────────────────────────────────────
// config/db.php — Database configuration
// Edit DB_* constants to match your server
// ─────────────────────────────────────────

// ── AUTO-DETECT BASE PATH ─────────────────────────────────────────────────────
// Works for XAMPP at localhost/novabudget/  →  APP_BASE = '/novabudget'
// Works for root install at localhost/      →  APP_BASE = ''
// Works for production at domain.com/       →  APP_BASE = ''
if (!defined('APP_BASE')) {
    $docRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? getcwd()));
    $appRoot = str_replace('\\', '/', realpath(__DIR__ . '/..'));
    $base    = str_replace($docRoot, '', $appRoot);
    define('APP_BASE', rtrim($base, '/'));
}

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '5432');
define('DB_NAME', getenv('DB_NAME') ?: 'novabudget_final');
define('DB_USER', getenv('DB_USER') ?: 'postgres');
define('DB_PASS', getenv('DB_PASS') ?: '2007');

define('APP_NAME',    'NovaBudget');
define('APP_VERSION', '2.0.0');
define('APP_URL',     getenv('APP_URL') ?: 'http://localhost');

// Upload settings
define('UPLOAD_DIR',      __DIR__ . '/../assets/uploads/');
define('UPLOAD_MAX_MB',   5);
define('ALLOWED_TYPES',   ['image/jpeg','image/png','image/webp','application/pdf']);

// Session lifetime (30 days in seconds)
define('SESSION_LIFETIME', 60 * 60 * 24 * 30);

/**
 * Returns a singleton PDO instance.
 * Throws PDOException on failure — caught in auth.php bootstrap.
 */
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s;options=--client_encoding=UTF8',
            DB_HOST, DB_PORT, DB_NAME
        );
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_PERSISTENT         => false,
        ]);
    }
    return $pdo;
}

/**
 * Execute a prepared statement and return all rows.
 */
function dbQuery(string $sql, array $params = []): array {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Execute a prepared statement and return one row.
 */
function dbQueryOne(string $sql, array $params = []): ?array {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Execute INSERT/UPDATE/DELETE and return affected rows.
 */
function dbExec(string $sql, array $params = []): int {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

/**
 * Execute INSERT and return the last inserted ID (PostgreSQL: use RETURNING).
 */
function dbInsert(string $sql, array $params = []): ?string {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row ? array_values($row)[0] : null;
}
