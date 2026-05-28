<?php
require_once __DIR__ . '/../../config/db.php';
requireLogin();
header('Content-Type: application/json; charset=utf-8');

$db     = getDB();
$userId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$body       = json_decode(file_get_contents('php://input'), true) ?? [];
$targetType = $body['target_type'] ?? '';
$targetId   = (int)($body['target_id'] ?? 0);

if (!in_array($targetType, ['post', 'comment'], true) || !$targetId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Parámetros inválidos']);
    exit;
}

// Verify target exists and is active
if ($targetType === 'post') {
    $chk = $db->prepare("SELECT id FROM event_forum_posts WHERE id = ? AND status = 'active'");
} else {
    $chk = $db->prepare("SELECT id FROM event_forum_comments WHERE id = ? AND status = 'active'");
}
$chk->execute([$targetId]);
if (!$chk->fetch()) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Contenido no encontrado']);
    exit;
}

// Toggle like
$existing = $db->prepare(
    "SELECT id FROM event_forum_likes WHERE user_id = ? AND target_type = ? AND target_id = ?"
);
$existing->execute([$userId, $targetType, $targetId]);
$liked = (bool)$existing->fetch();

$db->beginTransaction();
try {
    $table = $targetType === 'post' ? 'event_forum_posts' : 'event_forum_comments';

    if ($liked) {
        $db->prepare(
            "DELETE FROM event_forum_likes WHERE user_id = ? AND target_type = ? AND target_id = ?"
        )->execute([$userId, $targetType, $targetId]);
        $db->prepare("UPDATE {$table} SET like_count = GREATEST(0, like_count - 1) WHERE id = ?")
           ->execute([$targetId]);
        $newLiked = false;
    } else {
        $db->prepare(
            "INSERT INTO event_forum_likes (user_id, target_type, target_id) VALUES (?, ?, ?)"
        )->execute([$userId, $targetType, $targetId]);
        $db->prepare("UPDATE {$table} SET like_count = like_count + 1 WHERE id = ?")
           ->execute([$targetId]);
        $newLiked = true;
    }

    // Get updated count
    $count = $db->prepare("SELECT like_count FROM {$table} WHERE id = ?");
    $count->execute([$targetId]);
    $likeCount = (int)$count->fetchColumn();

    $db->commit();
} catch (PDOException $e) {
    $db->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno']);
    exit;
}

echo json_encode([
    'success'    => true,
    'liked'      => $newLiked,
    'like_count' => $likeCount,
]);
