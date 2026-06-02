<?php
require_once dirname(__DIR__) . '/config/db.php';
requireLogin();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$db = getDB();
$userId = (int)$_SESSION['user_id'];
$body = json_decode(file_get_contents('php://input'), true) ?? [];
const ALLOWED_REPORT_REASONS = ['spam', 'offensive', 'inappropriate', 'other'];
const SQLSTATE_INTEGRITY_CONSTRAINT = '23000';
const MAX_REPORT_DESCRIPTION_LENGTH = 500;
const PUBLICATION_STATUS_ACTIVE = 'active';

$pubId = (int)($body['pub_id'] ?? 0);
$reason = $body['reason'] ?? '';
$desc = mb_substr(strip_tags(trim($body['description'] ?? '')), 0, MAX_REPORT_DESCRIPTION_LENGTH);

if (!$pubId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Parámetros inválidos']);
    exit;
}
if (!in_array($reason, ALLOWED_REPORT_REASONS, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Motivo de reporte inválido']);
    exit;
}

$chk = $db->prepare("SELECT user_id FROM publications WHERE id = ? AND status = ?");
$chk->execute([$pubId, PUBLICATION_STATUS_ACTIVE]);
$pub = $chk->fetch();

if (!$pub) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Publicación no encontrada']);
    exit;
}

if ((int)$pub['user_id'] === $userId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No puedes reportar tu propia publicación']);
    exit;
}

try {
    $db->prepare(
        "INSERT INTO publication_reports (reporter_id, publication_id, reason, description)
         VALUES (?, ?, ?, ?)"
    )->execute([$userId, $pubId, $reason, $desc ?: null]);
} catch (PDOException $e) {
    if ($e->getCode() === SQLSTATE_INTEGRITY_CONSTRAINT) {
        echo json_encode(['success' => false, 'error' => 'Ya has reportado esta publicación']);
        exit;
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error al procesar el reporte']);
    exit;
}

echo json_encode(['success' => true, 'message' => 'Reporte enviado. Lo revisaremos pronto.']);
