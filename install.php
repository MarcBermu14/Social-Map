<?php
require_once __DIR__ . '/config/db.php';

if (!envBool('INSTALLER_ENABLED', false)) {
    http_response_code(403);
    echo 'Installer disabled. Set INSTALLER_ENABLED=true in .env to run it.';
    exit;
}

/**
 * CityLive — Installer
 * Run once to create tables and seed demo data.
 * DELETE this file after installation.
 */

// ─── DB connection (bypass config since DB may not exist yet) ─
$host = envVar('DB_HOST', 'localhost');
$port = envVar('DB_PORT', '3306');
$user = envVar('DB_USER', 'root');
$pass = envVar('DB_PASS', '');
$dbName = envVar('DB_NAME', 'citylive');

try {
    $pdo = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (PDOException $e) {
    die('<b>Error connecting to MySQL:</b> ' . $e->getMessage() . '<br>Check DB_USER and DB_PASS in this file.');
}

$log = [];

function run(PDO $pdo, string $sql, string $label): void {
    global $log;
    try {
        $pdo->exec($sql);
        $log[] = "✅ $label";
    } catch (PDOException $e) {
        $log[] = "❌ $label — " . $e->getMessage();
    }
}

// ─── Create database ──────────────────────────────────
run($pdo, "CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci", "Create database");
run($pdo, "USE `$dbName`", "Select database");

// ─── Tables ───────────────────────────────────────────
run($pdo, "
CREATE TABLE IF NOT EXISTS users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50)  UNIQUE NOT NULL,
    email         VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name     VARCHAR(100),
    bio           TEXT,
    avatar        VARCHAR(255) DEFAULT NULL,
    reputation    DECIMAL(3,2) DEFAULT 0.00,
    rep_count     INT          DEFAULT 0,
    plan          ENUM('free','pro','platinum') DEFAULT 'free',
    tokens_balance INT         DEFAULT 0,
    verified      TINYINT(1)   DEFAULT 0,
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    last_active   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)", "Create users table");

run($pdo, "
CREATE TABLE IF NOT EXISTS publications (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT          NOT NULL,
    type        ENUM('incident','event','activity') NOT NULL,
    title       VARCHAR(200) NOT NULL,
    description TEXT,
    latitude    DECIMAL(10,8) NOT NULL,
    longitude   DECIMAL(11,8) NOT NULL,
    address     VARCHAR(255),
    category    VARCHAR(50),
    image_url   VARCHAR(255),
    token_cost  INT          DEFAULT 0,
    status      ENUM('active','expired','removed') DEFAULT 'active',
    views       INT          DEFAULT 0,
    attendees   INT          DEFAULT 0,
    starts_at   DATETIME,
    expires_at  DATETIME,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)", "Create publications table");

run($pdo, "
CREATE TABLE IF NOT EXISTS reviews (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    publication_id INT       NOT NULL,
    user_id        INT       NOT NULL,
    rating         TINYINT   NOT NULL,
    comment        TEXT,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (publication_id) REFERENCES publications(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)        REFERENCES users(id)        ON DELETE CASCADE,
    UNIQUE KEY unique_review (publication_id, user_id)
)", "Create reviews table");

run($pdo, "
CREATE TABLE IF NOT EXISTS token_transactions (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT          NOT NULL,
    amount      INT          NOT NULL,
    type        ENUM('subscription','purchase','publication','reward','refund') NOT NULL,
    description VARCHAR(255),
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)", "Create token_transactions table");

run($pdo, "
CREATE TABLE IF NOT EXISTS subscriptions (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNIQUE   NOT NULL,
    plan       ENUM('free','pro','platinum') DEFAULT 'free',
    started_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    renews_at  TIMESTAMP    NULL,
    active     TINYINT(1)   DEFAULT 1,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)", "Create subscriptions table");

run($pdo, "
CREATE TABLE IF NOT EXISTS followers (
    follower_id  INT NOT NULL,
    following_id INT NOT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (follower_id, following_id)
)", "Create followers table");

run($pdo, "
CREATE TABLE IF NOT EXISTS saves (
    user_id        INT NOT NULL,
    publication_id INT NOT NULL,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, publication_id)
)", "Create saves table");

run($pdo, "
CREATE TABLE IF NOT EXISTS event_registrations (
    user_id        INT NOT NULL,
    publication_id INT NOT NULL,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, publication_id)
)", "Create event_registrations table");

// ─── Seed users ───────────────────────────────────────
$users = [
    ['maria.llull',   'maria@citylive.app',   'demo1234', 'María Llull',    'Urbanista y exploradora urbana 🏙️ Comparto lo mejor de BCN en tiempo real.', 'platinum', 8450, 4.9, 284, 1],
    ['carlos.mendez', 'carlos@citylive.app',  'demo1234', 'Carlos Méndez',  'Fotógrafo urbano y fanático de la arquitectura barcelonesa 📸',               'pro',      620,  4.6, 127, 0],
    ['alex.rivera',   'alex@citylive.app',    'demo1234', 'Alex Rivera',    'Músico y nómada digital. Siempre en busca del próximo concierto 🎵',            'free',     0,    4.2, 43,  0],
    ['sara.pons',     'sara@citylive.app',    'demo1234', 'Sara Pons',      'Foodie y viajera local. Te cuento dónde comer y qué ver 🍕',                  'pro',      880,  4.8, 91,  1],
    ['demo',          'demo@citylive.app',    'demo1234', 'Demo User',      'Cuenta de demostración de CityLive 🚀',                                       'platinum', 9200, 0,   0,   0],
];

$stmtUser = $pdo->prepare("
    INSERT IGNORE INTO users (username, email, password_hash, full_name, bio, plan, tokens_balance, reputation, rep_count, verified)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");
$stmtSub = $pdo->prepare("INSERT IGNORE INTO subscriptions (user_id, plan, renews_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 MONTH))");

foreach ($users as $u) {
    $hash = password_hash($u[2], PASSWORD_DEFAULT);
    $stmtUser->execute([$u[0], $u[1], $hash, $u[3], $u[4], $u[5], $u[6], $u[7], $u[8], $u[9]]);
    $uid = $pdo->lastInsertId();
    if ($uid) {
        $stmtSub->execute([$uid, $u[5]]);
    }
    $log[] = "👤 User '{$u[0]}' seeded";
}

// ─── Get user IDs ─────────────────────────────────────
$userIds = [];
foreach ($pdo->query("SELECT id, username FROM users") as $row) {
    $userIds[$row['username']] = $row['id'];
}

// ─── Seed publications ────────────────────────────────
$now   = date('Y-m-d H:i:s');
$today = date('Y-m-d');

$pubs = [
    // [user, type, title, desc, lat, lng, address, category, token_cost, attendees, starts_at, expires_at]
    [
        'maria.llull', 'activity',
        'Mercadillo de Arte y Diseño en Gràcia',
        'Mercadillo mensual de arte, diseño, artesanía y segunda mano en el corazón de Gràcia. Más de 30 puestos de artistas locales, actuaciones en directo y food trucks.',
        41.4017, 2.1590, 'Carrer de Verdi, 34, Gràcia', 'Arte y Cultura', 150, 47,
        "$today 10:00:00", "$today 20:00:00"
    ],
    [
        'carlos.mendez', 'event',
        'Concierto Jazz en vivo – Bar Marsella',
        'Noche de jazz en directo en el mítico Bar Marsella, uno de los bares más antiguos de Barcelona. Entrada libre hasta completar aforo.',
        41.3787, 2.1750, 'Carrer dels Escudellers, 62', 'Música', 0, 120,
        "$today 21:00:00", "$today 01:00:00"
    ],
    [
        'alex.rivera', 'incident',
        'Atasco severo en Via Laietana',
        'Accidente de tráfico en la confluencia con C/ Jonqueres. Retención de más de 2 km. Se recomienda evitar la zona y usar calles alternativas.',
        41.3837, 2.1785, 'Via Laietana, altura C/ Jonqueres', 'Tráfico', 0, 0,
        null, date('Y-m-d H:i:s', strtotime('+3 hours'))
    ],
    [
        'sara.pons', 'activity',
        'Pop-up Store Nike – Eixample',
        'Apertura especial de la nueva colección Barcelona Edition de Nike. Descuentos exclusivos del 30% para los primeros 100 clientes.',
        41.3919, 2.1635, 'Passeig de Gràcia, 91', 'Compras', 300, 78,
        "$today 09:00:00", "$today 21:00:00"
    ],
    [
        'carlos.mendez', 'incident',
        'Obras en Passeig de Gràcia',
        'Obras de renovación de la calzada en el tramo entre C/ Aragó y C/ Consell de Cent. Carril de bici cerrado. Prevista finalización en 2 semanas.',
        41.3940, 2.1620, 'Passeig de Gràcia, entre C/Aragó y C/Consell de Cent', 'Obras', 0, 0,
        null, date('Y-m-d H:i:s', strtotime('+14 days'))
    ],
    [
        'maria.llull', 'event',
        'Flash Mob – Plaça de Catalunya',
        'Flashmob de danza contemporánea organizado por el colectivo UrbanArts. Participación libre para todo el mundo. Punto de encuentro en la fuente central.',
        41.3870, 2.1700, 'Plaça de Catalunya', 'Cultura', 0, 200,
        "$today 18:00:00", "$today 19:30:00"
    ],
    [
        'sara.pons', 'activity',
        'Mercat Gastronòmic del Born',
        'Mercado gastronómico con productores locales de la comarca del Maresme. Degustaciones, talleres de cocina y venta directa de productos ecológicos.',
        41.3840, 2.1820, 'Passeig del Born, 6', 'Gastronomía', 200, 63,
        "$today 10:00:00", "$today 15:00:00"
    ],
    [
        'alex.rivera', 'event',
        'Torneo de Ajedrez Urbano',
        'Tercer torneo de ajedrez al aire libre en el Parc de la Ciutadella. Todos los niveles bienvenidos. Traed vuestro propio tablero o usad los del parque.',
        41.3865, 2.1860, 'Parc de la Ciutadella, zona central', 'Deporte', 0, 34,
        "$today 11:00:00", "$today 18:00:00"
    ],
    [
        'maria.llull', 'activity',
        'Tour Fotografía Urbana – Barrio Gótico',
        'Tour guiado por el Barrio Gótico con enfoque en fotografía arquitectónica. Cupo máximo 15 personas. Material fotográfico no incluido.',
        41.3820, 2.1760, 'Plaça Nova, 1 (frente a la Catedral)', 'Arte y Cultura', 500, 12,
        "$today 16:00:00", "$today 19:00:00"
    ],
    [
        'carlos.mendez', 'incident',
        'Corte de luz en el Raval',
        'Avería en la red eléctrica afecta a varios bloques entre C/Tallers y C/Joaquim Costa. Equipo de mantenimiento trabajando. ETA de reparación: 2h.',
        41.3810, 2.1680, 'C/ Tallers / C/ Joaquim Costa, El Raval', 'Avería', 0, 0,
        null, date('Y-m-d H:i:s', strtotime('+2 hours'))
    ],
];

$stmtPub = $pdo->prepare("
    INSERT INTO publications (user_id, type, title, description, latitude, longitude, address, category, token_cost, attendees, starts_at, expires_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

foreach ($pubs as $p) {
    $uid = $userIds[$p[0]] ?? null;
    if (!$uid) continue;
    $stmtPub->execute([$uid, $p[1], $p[2], $p[3], $p[4], $p[5], $p[6], $p[7], $p[8], $p[9], $p[10], $p[11]]);
    $log[] = "📌 Publication '{$p[2]}' seeded";
}

// ─── Seed reviews ─────────────────────────────────────
$pubIds = [];
foreach ($pdo->query("SELECT id FROM publications ORDER BY id LIMIT 10") as $row) {
    $pubIds[] = $row['id'];
}

$reviews = [
    [$pubIds[0] ?? 1, $userIds['carlos.mendez'] ?? 2, 5, 'Brutal el ambiente! Muchos puestos y artistas increíbles. La zona de food trucks estaba llena pero valió la pena.'],
    [$pubIds[0] ?? 1, $userIds['alex.rivera']   ?? 3, 4, 'Muy recomendable. Cogí una ilustración preciosa por 12€.'],
    [$pubIds[0] ?? 1, $userIds['sara.pons']      ?? 4, 5, 'Una joya de evento. El DJ del fondo era increíble.'],
    [$pubIds[1] ?? 2, $userIds['maria.llull']    ?? 1, 5, 'Jazz de altísimo nivel. El local tiene una atmósfera única.'],
    [$pubIds[3] ?? 4, $userIds['alex.rivera']    ?? 3, 4, 'Buenas ofertas, aunque había mucha cola. Llegad pronto!'],
];

$stmtRev = $pdo->prepare("INSERT IGNORE INTO reviews (publication_id, user_id, rating, comment) VALUES (?, ?, ?, ?)");
foreach ($reviews as $r) {
    $stmtRev->execute($r);
}
$log[] = "⭐ Reviews seeded";

// ─── Seed token transactions ──────────────────────────
if (!empty($userIds['maria.llull'])) {
    $uid = $userIds['maria.llull'];
    $txs = [
        [$uid, 10000, 'subscription', 'Renovación plan Platinum'],
        [$uid, -150,  'publication',  'Mercadillo de Arte y Diseño en Gràcia'],
        [$uid, -300,  'publication',  'Tour Fotografía Urbana – Barrio Gótico (cost share)'],
        [$uid, 10,    'reward',       'Valoración positiva recibida'],
        [$uid, 200,   'reward',       'Bonus: 10 actividades completadas este mes'],
        [$uid, -500,  'publication',  'Tour Fotografía Urbana – Barrio Gótico'],
    ];
    $stmtTx = $pdo->prepare("INSERT INTO token_transactions (user_id, amount, type, description) VALUES (?, ?, ?, ?)");
    foreach ($txs as $t) $stmtTx->execute($t);
    $log[] = "💰 Token transactions seeded";
}

// ─── Seed followers ───────────────────────────────────
if (count($userIds) >= 2) {
    $ids = array_values($userIds);
    $pairs = [[$ids[0],$ids[1]],[$ids[0],$ids[2]],[$ids[1],$ids[0]],[$ids[2],$ids[0]],[$ids[3],$ids[0]]];
    $stmtF = $pdo->prepare("INSERT IGNORE INTO followers (follower_id, following_id) VALUES (?, ?)");
    foreach ($pairs as [$a, $b]) $stmtF->execute([$a, $b]);
    $log[] = "👥 Followers seeded";
}

?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>CityLive — Instalador</title>
<style>
  body{font-family:monospace;background:#070B14;color:#A8B4C8;padding:40px;max-width:700px;margin:0 auto;}
  h1{color:#00D4FF;margin-bottom:24px;}
  .log{background:#0F1629;border:1px solid rgba(255,255,255,.07);border-radius:12px;padding:24px;}
  .log p{margin:4px 0;font-size:14px;line-height:1.6;}
  .creds{background:#141B2D;border:1px solid rgba(0,212,255,.2);border-radius:12px;padding:24px;margin-top:24px;}
  .creds h2{color:#00D4FF;margin-bottom:16px;font-family:sans-serif;}
  table{width:100%;border-collapse:collapse;}
  th,td{text-align:left;padding:8px 12px;border-bottom:1px solid rgba(255,255,255,.07);font-size:13px;}
  th{color:#5A6478;font-weight:600;}
  .btn{display:inline-block;margin-top:24px;padding:14px 28px;background:#00D4FF;color:#000;border-radius:12px;text-decoration:none;font-family:sans-serif;font-weight:700;}
  .warn{color:#FFB300;margin-top:16px;font-family:sans-serif;font-size:13px;}
</style>
</head>
<body>
<h1>🗺️ CityLive — Instalador</h1>
<div class="log">
  <?php foreach ($log as $l) echo "<p>$l</p>"; ?>
</div>

<div class="creds">
  <h2>Credenciales de demo</h2>
  <table>
    <tr><th>Usuario</th><th>Email</th><th>Contraseña</th><th>Plan</th></tr>
    <tr><td>maria.llull</td><td>maria@citylive.app</td><td>demo1234</td><td>💎 Platinum</td></tr>
    <tr><td>carlos.mendez</td><td>carlos@citylive.app</td><td>demo1234</td><td>⭐ Pro</td></tr>
    <tr><td>alex.rivera</td><td>alex@citylive.app</td><td>demo1234</td><td>🆓 Free</td></tr>
    <tr><td>sara.pons</td><td>sara@citylive.app</td><td>demo1234</td><td>⭐ Pro</td></tr>
    <tr><td>demo</td><td>demo@citylive.app</td><td>demo1234</td><td>💎 Platinum</td></tr>
  </table>
</div>

<a class="btn" href="index.php">Ir a la aplicación →</a>
<p class="warn">⚠️ Elimina este archivo (install.php) después de la instalación.</p>
</body>
</html>

