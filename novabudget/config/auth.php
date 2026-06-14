<?php
// ─────────────────────────────────────────
// config/auth.php — Auth helpers & guards
// Include at the top of every PHP page
// ─────────────────────────────────────────
require_once __DIR__ . '/db.php';

// Secure session setup
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly',  '1');
    ini_set('session.cookie_samesite',  'Strict');
    ini_set('session.use_strict_mode',  '1');
    ini_set('session.gc_maxlifetime',   (string) SESSION_LIFETIME);
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', '1');
    }
    session_name('NOVABUDGET_SESS');
    session_start();
}

// ── Auth Guards ────────────────────────────

/**
 * Redirect to login if user is not authenticated.
 */
function requireAuth(): array {
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . APP_BASE . '/login.php');
        exit;
    }
    return $_SESSION['user_data'] ?? [];
}

/**
 * Redirect to dashboard if user IS already logged in.
 */
function requireGuest(): void {
    if (!empty($_SESSION['user_id'])) {
        header('Location: ' . APP_BASE . '/dashboard.php');
        exit;
    }
}

/**
 * Return currently logged-in user ID.
 */
function currentUserId(): string {
    return $_SESSION['user_id'] ?? '';
}

/**
 * Return currently logged-in user data array.
 */
function currentUser(): array {
    return $_SESSION['user_data'] ?? [];
}

// ── CSRF ───────────────────────────────────

/**
 * Generate or return existing CSRF token.
 */
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Output a hidden CSRF input field.
 */
function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken()) . '">';
}

/**
 * Validate CSRF token; die on failure.
 */
function verifyCsrf(): void {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals(csrfToken(), $token)) {
        http_response_code(403);
        die(json_encode(['error' => 'Invalid CSRF token']));
    }
}

// ── Input helpers ──────────────────────────

function postStr(string $key, string $default = ''): string {
    return trim((string)($_POST[$key] ?? $default));
}

function postFloat(string $key, float $default = 0.0): float {
    return (float)($_POST[$key] ?? $default);
}

function postInt(string $key, int $default = 0): int {
    return (int)($_POST[$key] ?? $default);
}

function getStr(string $key, string $default = ''): string {
    return trim((string)($_GET[$key] ?? $default));
}

function getInt(string $key, int $default = 0): int {
    return (int)($_GET[$key] ?? $default);
}

function h(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function isValidEmail(string $email): bool {
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

function isValidUuid(string $uuid): bool {
    return (bool) preg_match(
        '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
        strtolower($uuid)
    );
}

// ── Flash messages ─────────────────────────

function flashSet(string $type, string $msg): void {
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function flashGet(): array {
    $msgs = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $msgs;
}

// ── Rate limiting (simple IP-based) ────────

function checkRateLimit(string $key, int $maxAttempts = 5, int $windowSec = 60): bool {
    $ip  = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rKey = "rl_{$key}_{$ip}";
    if (empty($_SESSION[$rKey])) {
        $_SESSION[$rKey] = ['count' => 0, 'reset_at' => time() + $windowSec];
    }
    if (time() > $_SESSION[$rKey]['reset_at']) {
        $_SESSION[$rKey] = ['count' => 0, 'reset_at' => time() + $windowSec];
    }
    $_SESSION[$rKey]['count']++;
    return $_SESSION[$rKey]['count'] <= $maxAttempts;
}

// ── File upload helper ──────────────────────

function handleUpload(string $field): ?string {
    if (empty($_FILES[$field]['tmp_name'])) return null;
    $file = $_FILES[$field];
    if ($file['error'] !== UPLOAD_ERR_OK) return null;
    if ($file['size'] > UPLOAD_MAX_MB * 1024 * 1024) return null;
    $mime = mime_content_type($file['tmp_name']);
    if (!in_array($mime, ALLOWED_TYPES, true)) return null;
    $ext  = pathinfo($file['name'], PATHINFO_EXTENSION);
    $name = bin2hex(random_bytes(16)) . '.' . strtolower($ext);
    $dest = UPLOAD_DIR . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) return null;
    return 'assets/uploads/' . $name;
}

// ── JSON response helper ────────────────────

function jsonResponse(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
