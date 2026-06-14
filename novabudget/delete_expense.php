<?php
// delete_expense.php — POST-only expense deletion
require_once __DIR__ . '/config/auth.php';
requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_BASE . '/dashboard.php');
    exit;
}
verifyCsrf();

$userId   = currentUserId();
$id       = postStr('id');
$redirect = postStr('redirect') ?: '/dashboard.php';

// Whitelist redirects to prevent open redirect
$allowedRedirects = ['/dashboard.php', '/calendar.php', '/reports.php'];
if (!in_array($redirect, $allowedRedirects, true)) {
    $redirect = '/dashboard.php';
}

if (!$id || !isValidUuid($id)) {
    flashSet('error', 'Invalid expense ID.');
    header('Location: ' . $redirect);
    exit;
}

try {
    // Verify ownership before deleting
    $expense = dbQueryOne(
        'SELECT id, receipt_url FROM expenses WHERE id = ? AND user_id = ?',
        [$id, $userId]
    );

    if (!$expense) {
        flashSet('error', 'Expense not found or access denied.');
        header('Location: ' . $redirect);
        exit;
    }

    // Delete receipt file if it exists
    if ($expense['receipt_url']) {
        $filePath = __DIR__ . '/' . $expense['receipt_url'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    dbExec('DELETE FROM expenses WHERE id = ? AND user_id = ?', [$id, $userId]);
    dbExec(
        'INSERT INTO audit_log (user_id, action, table_name, record_id) VALUES (?,?,?,?::uuid)',
        [$userId, 'delete', 'expenses', $id]
    );

    flashSet('success', 'Expense deleted.');
} catch (Exception $e) {
    error_log('Delete expense error: ' . $e->getMessage());
    flashSet('error', 'Failed to delete expense. Please try again.');
}

header('Location: ' . $redirect);
exit;
