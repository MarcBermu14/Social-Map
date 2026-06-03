<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require_once dirname(__DIR__) . '/config/db.php';

$db   = getDB();
$type = $_GET['type'] ?? null;
$id   = isset($_GET['id']) ? (int)$_GET['id'] : null;

// Resolve the requesting user for permission checks (session already started by db.php)
$_apiUser      = currentUser();
$_apiUserId    = $_apiUser ? (int)$_apiUser['id'] : null;
$_apiIsAdmin   = $_apiUser ? isAdmin($_apiUser) : false;

$planLabel = ['free' => 'Gratuita', 'pro' => '⭐ Pro', 'platinum' => '💎 Platinum'];

// Build query
$where  = ["p.status = 'active'"];
$params = [];

if ($id) {
    $where[]  = 'p.id = ?';
    $params[] = $id;
} elseif ($type && in_array($type, ['incident', 'event', 'activity'])) {
    $where[]  = 'p.type = ?';
    $params[] = $type;
}

$whereSQL = implode(' AND ', $where);

$sql = "
    SELECT
        p.id, p.type, p.title, p.description,
        p.latitude  AS lat,
        p.longitude AS lng,
        p.address, p.category, p.token_cost,
        p.attendees, p.views,
        p.starts_at, p.expires_at, p.created_at,
        u.id         AS user_id,
        u.username   AS creator_username,
        u.full_name  AS creator_name,
        u.reputation,
        u.plan       AS creator_plan
    FROM publications p
    JOIN users u ON u.id = p.user_id
    WHERE $whereSQL
    ORDER BY p.created_at DESC
    LIMIT 100
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Build GeoJSON
$features = [];
foreach ($rows as $row) {
    $row['plan_label'] = $planLabel[$row['creator_plan']] ?? 'Gratuita';

    // Strip economic data unless the requester is the owner or an admin
    if (!$_apiIsAdmin && $_apiUserId !== (int)$row['user_id']) {
        unset($row['token_cost']);
    }

    $features[] = [
        'type'       => 'Feature',
        'geometry'   => [
            'type'        => 'Point',
            'coordinates' => [(float)$row['lng'], (float)$row['lat']],
        ],
        'properties' => $row,
    ];
}

echo json_encode([
    'type'     => 'FeatureCollection',
    'features' => $features,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
