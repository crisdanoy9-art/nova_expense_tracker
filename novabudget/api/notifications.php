<?php
// api/notifications.php — Notifications endpoint
require_once __DIR__ . '/../config/auth.php';
requireAuth();

header('Content-Type: application/json');
$userId = currentUserId();

// Count unread
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['count'])) {
    $n = dbQueryOne('SELECT COUNT(*) AS n FROM notifications WHERE user_id=? AND is_read=FALSE', [$userId]);
    echo json_encode(['unread' => (int)$n['n']]);
    exit;
}

// List notifications
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $rows = dbQuery(
        "SELECT id, type, title, message, is_read,
                TO_CHAR(created_at, 'Mon DD, YYYY HH24:MI') AS created_at
         FROM notifications WHERE user_id=?
         ORDER BY created_at DESC LIMIT 20",
        [$userId]
    );
    echo json_encode(['notifications' => $rows]);
    exit;
}

// Handle POST actions (mark read)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body   = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $body['action'] ?? '';

    if ($action === 'read' && !empty($body['id'])) {
        $id = $body['id'];
        if (isValidUuid($id)) {
            dbExec('UPDATE notifications SET is_read=TRUE WHERE id=?::uuid AND user_id=?', [$id, $userId]);
        }
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'read_all') {
        dbExec('UPDATE notifications SET is_read=TRUE WHERE user_id=?', [$userId]);
        echo json_encode(['ok' => true]);
        exit;
    }
}

http_response_code(400);
echo json_encode(['error' => 'Bad request']);
