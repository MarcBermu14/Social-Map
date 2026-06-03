<?php
require_once __DIR__ . '/../config/db.php';
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

$pubId  = (int)($body['pub_id'] ?? 0);
$reason = $body['reason'] ?? '';
$desc   = mb_substr(strip_tags(trim($body['description'] ?? '')), 0, 500);
$valid  = ['spam', 'false_info', 'inappropriate', 'other'];

if (!$pubId || !in_array($reason, $valid, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Parámetros inválidos']);
    exit;
}

// No se puede reportar la propia publicación
$own = $db->prepare('SELECT user_id FROM publications WHERE id = ? AND status = "active"');
$own->execute([$pubId]);
$row = $own->fetch();
if (!$row) { http_response_code(404); echo json_encode(['success' => false, 'error' => 'Publicación no encontrada']); exit; }
if ((int)$row['user_id'] === $userId) { http_response_code(400); echo json_encode(['success' => false, 'error' => 'No puedes reportar tu propia publicación']); exit; }

try {
    $db->prepare(
        'INSERT INTO publication_reports (reporter_id, publication_id, reason, description)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE reason = VALUES(reason), description = VALUES(description), status = "pending"'
    )->execute([$userId, $pubId, $reason, $desc]);
    echo json_encode(['success' => true, 'message' => 'Reporte enviado. Lo revisaremos pronto.']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error al guardar el reporte']);
}
