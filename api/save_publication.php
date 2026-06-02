<?php
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/config/db.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autenticado']);
    exit;
}

$uid = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $pubId = (int)($_GET['pub_id'] ?? 0);
    if (!$pubId) {
        echo json_encode(['saved' => false]);
        exit;
    }
    try {
        $db = getDB();
        $stmt = $db->prepare('SELECT 1 FROM saves WHERE user_id = ? AND publication_id = ?');
        $stmt->execute([$uid, $pubId]);
        echo json_encode(['saved' => (bool)$stmt->fetchColumn()]);
    } catch (Exception $e) {
        echo json_encode(['saved' => false]);
    }
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? ($_POST['action'] ?? '');
$pubId = (int)($data['pub_id'] ?? ($_POST['pub_id'] ?? 0));

if (!$pubId || !in_array($action, ['save', 'unsave', 'toggle'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Parámetros inválidos']);
    exit;
}

$db = getDB();
$stmt = $db->prepare("SELECT id FROM publications WHERE id = ? AND status = 'active'");
$stmt->execute([$pubId]);
if (!$stmt->fetchColumn()) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Publicación no encontrada']);
    exit;
}

try {
    if ($action === 'toggle') {
        $chk = $db->prepare('SELECT 1 FROM saves WHERE user_id = ? AND publication_id = ?');
        $chk->execute([$uid, $pubId]);
        $action = $chk->fetchColumn() ? 'unsave' : 'save';
    }

    if ($action === 'save') {
        $db->prepare('INSERT IGNORE INTO saves (user_id, publication_id) VALUES (?, ?)')->execute([$uid, $pubId]);
        echo json_encode(['success' => true, 'saved' => true, 'message' => 'Publicación guardada']);
    } else {
        $db->prepare('DELETE FROM saves WHERE user_id = ? AND publication_id = ?')->execute([$uid, $pubId]);
        echo json_encode(['success' => true, 'saved' => false, 'message' => 'Publicación eliminada de guardados']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error al guardar la publicación']);
}
