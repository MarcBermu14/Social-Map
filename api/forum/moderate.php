<?php
require_once __DIR__ . '/../../config/db.php';
requireLogin();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$db     = getDB();
$userId = (int)$_SESSION['user_id'];
$me     = currentUser();

// Only platinum users (admins) can use this endpoint
if (($me['plan'] ?? 'free') !== 'platinum') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Sin permisos de moderación']);
    exit;
}

$body       = json_decode(file_get_contents('php://input'), true) ?? [];
$action     = $body['action'] ?? '';
$targetType = $body['target_type'] ?? 'post';
$targetId   = (int)($body['target_id'] ?? 0);

if (!$targetId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'target_id requerido']);
    exit;
}

$validActions = ['pin', 'unpin', 'hide', 'delete'];
if (!in_array($action, $validActions, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Acción inválida']);
    exit;
}

$db->beginTransaction();
try {
    switch ($action) {
        case 'pin':
        case 'unpin':
            if ($targetType !== 'post') {
                $db->rollBack();
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Solo se pueden fijar posts']);
                exit;
            }
            $pinVal = $action === 'pin' ? 1 : 0;
            $db->prepare("UPDATE event_forum_posts SET is_pinned = ? WHERE id = ?")
               ->execute([$pinVal, $targetId]);
            break;

        case 'hide':
            $table = $targetType === 'post' ? 'event_forum_posts' : 'event_forum_comments';
            $db->prepare("UPDATE {$table} SET status = 'hidden' WHERE id = ?")
               ->execute([$targetId]);
            break;

        case 'delete':
            $table = $targetType === 'post' ? 'event_forum_posts' : 'event_forum_comments';
            $db->prepare("UPDATE {$table} SET status = 'deleted' WHERE id = ?")
               ->execute([$targetId]);

            if ($targetType === 'post') {
                $imgs = $db->prepare("SELECT filename FROM event_forum_images WHERE post_id = ?");
                $imgs->execute([$targetId]);
                foreach ($imgs->fetchAll() as $img) {
                    $path = __DIR__ . '/../../uploads/forum/' . $img['filename'];
                    if (file_exists($path)) @unlink($path);
                }
            }
            break;
    }

    // Log action
    $db->prepare(
        "INSERT INTO event_forum_mod_log (mod_id, action, target_type, target_id, reason)
         VALUES (?, ?, ?, ?, ?)"
    )->execute([$userId, $action, $targetType, $targetId, $body['reason'] ?? null]);

    $db->commit();
} catch (PDOException $e) {
    $db->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno']);
    exit;
}

echo json_encode(['success' => true, 'action' => $action]);
