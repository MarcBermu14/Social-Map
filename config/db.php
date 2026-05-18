<?php
// ─── Database configuration ───────────────────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'citylive');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHAR', 'utf8mb4');

// ─── PDO connection (singleton) ───────────────────────
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHAR);
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
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
