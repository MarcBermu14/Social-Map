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
$body   = json_decode(file_get_contents('php://input'), true) ?? [];

$targetType = $body['target_type'] ?? '';
$targetId   = (int)($body['target_id'] ?? 0);
$reason     = $body['reason'] ?? '';
$desc       = mb_substr(strip_tags(trim($body['description'] ?? '')), 0, 500);

$allowedTypes   = ['post', 'comment'];
$allowedReasons = ['spam', 'offensive', 'inappropriate', 'other'];

if (!in_array($targetType, $allowedTypes, true) || !$targetId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Parámetros inválidos']);
    exit;
}
if (!in_array($reason, $allowedReasons, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Motivo de reporte inválido']);
    exit;
}

// Verify target exists
if ($targetType === 'post') {
    $chk = $db->prepare("SELECT user_id FROM event_forum_posts WHERE id = ? AND status = 'active'");
} else {
    $chk = $db->prepare("SELECT user_id FROM event_forum_comments WHERE id = ? AND status = 'active'");
}
$chk->execute([$targetId]);
$target = $chk->fetch();
if (!$target) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Contenido no encontrado']);
    exit;
}

// Cannot report own content
if ($target['user_id'] == $userId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No puedes reportar tu propio contenido']);
    exit;
}

// Check for duplicate report
try {
    $db->prepare(
        "INSERT INTO event_forum_reports (reporter_id, target_type, target_id, reason, description)
         VALUES (?, ?, ?, ?, ?)"
    )->execute([$userId, $targetType, $targetId, $reason, $desc ?: null]);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        echo json_encode(['success' => false, 'error' => 'Ya has reportado este contenido']);
        exit;
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error al procesar el reporte']);
    exit;
}

echo json_encode(['success' => true, 'message' => 'Reporte enviado. Lo revisaremos pronto.']);
