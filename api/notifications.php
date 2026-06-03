<?php
require_once __DIR__ . '/../config/db.php';
requireLogin();
header('Content-Type: application/json; charset=utf-8');

$db     = getDB();
$userId = (int)$_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

function jsonOk(array $data = []): void {
    echo json_encode(['success' => true] + $data);
    exit;
}

function jsonErr(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

if ($method === 'GET') {
    $markRead = isset($_GET['mark_read']) && $_GET['mark_read'] === '1';

    if ($markRead) {
        $db->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0')
           ->execute([$userId]);
    }

    $countStmt = $db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $countStmt->execute([$userId]);
    $unreadCount = (int)$countStmt->fetchColumn();

    $stmt = $db->prepare('
        SELECT n.id, n.title, n.body, n.url, n.is_read, n.created_at,
               u.username AS actor_username, u.full_name AS actor_name, u.avatar AS actor_avatar
        FROM notifications n
        LEFT JOIN users u ON u.id = n.actor_id
        WHERE n.user_id = ?
        ORDER BY n.created_at DESC
        LIMIT 30
    ');
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll();

    jsonOk([
        'notifications' => $rows,
        'unread_count' => $unreadCount,
    ]);
}

if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $body['action'] ?? '';

    if ($action === 'delete') {
        $id = (int)($body['id'] ?? 0);
        if (!$id) jsonErr('id requerido');
        $db->prepare('DELETE FROM notifications WHERE id = ? AND user_id = ?')
           ->execute([$id, $userId]);
        jsonOk();
    }

    jsonErr('Accion no valida', 400);
}

jsonErr('Metodo no permitido', 405);
