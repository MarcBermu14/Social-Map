<?php
require_once __DIR__ . '/../config/db.php';
requireLogin();
header('Content-Type: application/json; charset=utf-8');

$db     = getDB();
$userId = (int)$_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $pubId = (int)($_GET['pub_id'] ?? 0);
    if (!$pubId) { echo json_encode(['saved' => false]); exit; }
    $s = $db->prepare('SELECT 1 FROM saves WHERE user_id = ? AND publication_id = ?');
    $s->execute([$userId, $pubId]);
    echo json_encode(['saved' => (bool)$s->fetchColumn()]);
    exit;
}

if ($method === 'POST') {
    $body  = json_decode(file_get_contents('php://input'), true) ?? [];
    $pubId = (int)($body['pub_id'] ?? 0);
    if (!$pubId) { http_response_code(400); echo json_encode(['success' => false, 'error' => 'pub_id requerido']); exit; }

    $chk = $db->prepare('SELECT 1 FROM saves WHERE user_id = ? AND publication_id = ?');
    $chk->execute([$userId, $pubId]);
    $alreadySaved = (bool)$chk->fetchColumn();

    if ($alreadySaved) {
        $db->prepare('DELETE FROM saves WHERE user_id = ? AND publication_id = ?')->execute([$userId, $pubId]);
    } else {
        $db->prepare('INSERT IGNORE INTO saves (user_id, publication_id) VALUES (?, ?)')->execute([$userId, $pubId]);
    }

    echo json_encode(['success' => true, 'saved' => !$alreadySaved]);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Método no permitido']);
