<?php
// logout.php — Secure logout
require_once __DIR__ . '/config/auth.php';

if (!empty($_SESSION['user_id'])) {
    try {
        // Delete session from DB
        dbExec(
            'DELETE FROM sessions WHERE user_id = ?',
            [$_SESSION['user_id']]
        );
        // Log activity
        dbExec(
            'INSERT INTO audit_log (user_id, action, ip_address) VALUES (?, ?, ?)',
            [$_SESSION['user_id'], 'logout', $_SERVER['REMOTE_ADDR'] ?? '']
        );
    } catch (Exception $e) {
        error_log('Logout DB error: ' . $e->getMessage());
    }
}

// Clear remember-me cookie
if (isset($_COOKIE['nb_token'])) {
    setcookie('nb_token', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
}

// Destroy session completely
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}
session_destroy();

header('Location: ' . APP_BASE . '/login.php?logged_out=1');
exit;
