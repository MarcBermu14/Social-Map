<?php
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/config/db.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autenticado']);
    exit;
}

$data  = json_decode(file_get_contents('php://input'), true);
$pubId = (int)($data['pub_id'] ?? 0);
$uid   = (int)$_SESSION['user_id'];

if (!$pubId) {
    http_response_code(400);
    echo json_encode(['error' => 'ID inválido']);
    exit;
}

$db = getDB();

// Verify ownership
$stmt = $db->prepare('SELECT user_id FROM publications WHERE id = ?');
$stmt->execute([$pubId]);
$pub = $stmt->fetch();

if (!$pub) {
    http_response_code(404);
    echo json_encode(['error' => 'Publicación no encontrada']);
    exit;
}

if ((int)$pub['user_id'] !== $uid) {
    http_response_code(403);
    echo json_encode(['error' => 'Sin permiso']);
    exit;
}

$db->prepare("UPDATE publications SET status = 'removed' WHERE id = ?")->execute([$pubId]);

echo json_encode(['success' => true]);
