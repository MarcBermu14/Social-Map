<?php
// ─── Database configuration ───────────────────────────
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'citylive');
define('DB_USER', getenv('DB_USER') ?: 'root');
$dbPass = getenv('DB_PASS');
define('DB_PASS', $dbPass === false ? '' : $dbPass);
define('DB_CHAR', getenv('DB_CHAR') ?: 'utf8mb4');
define('DB_PORT', getenv('DB_PORT') ?: '3306');

// ─── PDO connection (singleton) ───────────────────────
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHAR);
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            // Auto-create event_registrations if missing (new table added after initial install)
            $pdo->exec("CREATE TABLE IF NOT EXISTS event_registrations (
                user_id        INT NOT NULL,
                publication_id INT NOT NULL,
                created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (user_id, publication_id)
            )");
            // Auto-add min/max attendees columns if missing
            foreach (['min_attendees', 'max_attendees'] as $col) {
                try { $pdo->exec("ALTER TABLE publications ADD COLUMN $col INT DEFAULT NULL"); }
                catch (PDOException $e) {}
            }
        } catch (PDOException $e) {
            http_response_code(500);
            exit(json_encode(['error' => 'DB connection failed: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}

// ─── Session helpers ──────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

function requireLogin(string $redirect = '/citylive/index.php'): void {
    if (!isLoggedIn()) {
        header('Location: ' . $redirect);
        exit;
    }
}

function currentUser(): ?array {
    if (!isLoggedIn()) return null;
    static $user = null;
    if ($user === null) {
        $stmt = getDB()->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch() ?: null;
    }
    return $user;
}

// ─── Token helpers ────────────────────────────────────
function deductTokens(int $userId, int $amount, string $description): bool {
    $db = getDB();
    $stmt = $db->prepare('SELECT tokens_balance FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $balance = (int)($stmt->fetchColumn() ?? 0);
    if ($balance < $amount) return false;

    $db->prepare('UPDATE users SET tokens_balance = tokens_balance - ? WHERE id = ?')
       ->execute([$amount, $userId]);
    $db->prepare('INSERT INTO token_transactions (user_id, amount, type, description) VALUES (?, ?, "publication", ?)')
       ->execute([$userId, -$amount, $description]);
    return true;
}

// ─── Plan token limits ────────────────────────────────
const PLAN_TOKENS = ['free' => 0, 'pro' => 1000, 'platinum' => 10000];
const PLAN_PRICES = ['free' => 0, 'pro' => 9.99, 'platinum' => 29.99];
