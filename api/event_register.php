<?php
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/config/db.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autenticado']);
    exit;
}

$uid = (int)$_SESSION['user_id'];

// GET: check registration status
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $pubId = (int)($_GET['pub_id'] ?? 0);
    if (!$pubId) { echo json_encode(['registered' => false]); exit; }
    try {
        $db   = getDB();
        $stmt = $db->prepare('SELECT 1 FROM event_registrations WHERE user_id = ? AND publication_id = ?');
        $stmt->execute([$uid, $pubId]);
        echo json_encode(['registered' => (bool)$stmt->fetchColumn()]);
    } catch (Exception $e) {
        echo json_encode(['registered' => false]);
    }
    exit;
}

requireCsrf();

$data   = json_decode(file_get_contents('php://input'), true);
$action = $data['action']         ?? ($_POST['action'] ?? '');
$pubId  = (int)($data['pub_id']   ?? ($_POST['pub_id'] ?? 0));
$userId = (int)$_SESSION['user_id'];

if (!$pubId || !in_array($action, ['register', 'unregister'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Parámetros inválidos']);
    exit;
}

$db = getDB();

// Verify the publication exists, is active, and is of type 'event'
$stmt = $db->prepare("SELECT id, type, starts_at, attendees, max_attendees FROM publications WHERE id = ? AND status = 'active'");
$stmt->execute([$pubId]);
$pub = $stmt->fetch();

if (!$pub || $pub['type'] !== 'event') {
    http_response_code(404);
    echo json_encode(['error' => 'Evento no encontrado']);
    exit;
}

try {
    if ($action === 'register') {
        // Enforce max_attendees cap
        if ($pub['max_attendees'] !== null && (int)$pub['attendees'] >= (int)$pub['max_attendees']) {
            echo json_encode(['success' => false, 'error' => 'El evento ya ha alcanzado el límite de asistentes.']);
            exit;
        }
        $ins = $db->prepare('INSERT IGNORE INTO event_registrations (user_id, publication_id) VALUES (?, ?)');
        $ins->execute([$userId, $pubId]);
        if ($ins->rowCount() > 0) {
            $db->prepare('UPDATE publications SET attendees = attendees + 1 WHERE id = ?')->execute([$pubId]);
        }
        echo json_encode(['success' => true, 'registered' => true]);
    } else {
        $del = $db->prepare('DELETE FROM event_registrations WHERE user_id = ? AND publication_id = ?');
        $del->execute([$userId, $pubId]);
        if ($del->rowCount() > 0) {
            $db->prepare('UPDATE publications SET attendees = GREATEST(0, attendees - 1) WHERE id = ?')->execute([$pubId]);
        }
        echo json_encode(['success' => true, 'registered' => false]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de base de datos. ¿Has ejecutado install.php?', 'detail' => $e->getMessage()]);
}
