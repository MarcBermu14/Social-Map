<?php

// Load a local .env file if present.
function loadEnvFile(string $path): void
{
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if ($key === '') {
            continue;
        }

        $value = trim($value, "\"'");
        if (getenv($key) === false) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

function envVar(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    if ($value !== false) {
        return $value;
    }
    return $_ENV[$key] ?? $_SERVER[$key] ?? $default;
}

loadEnvFile(dirname(__DIR__) . '/.env');

function envBool(string $key, bool $default = false): bool
{
    $value = envVar($key);
    if ($value === null) {
        return $default;
    }
    return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
}

function detectAppBasePath(): string
{
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    if ($scriptName === '') {
        return '';
    }

    foreach (['/api/', '/config/', '/includes/', '/css/', '/js/'] as $segment) {
        $pos = strpos($scriptName, $segment);
        if ($pos !== false) {
            $base = substr($scriptName, 0, $pos);
            return $base === '/' ? '' : rtrim($base, '/');
        }
    }

    $base = dirname($scriptName);
    if ($base === '/' || $base === '\\' || $base === '.') {
        return '';
    }

    return rtrim(str_replace('\\', '/', $base), '/');
}

function appBasePath(): string
{
    static $basePath = null;
    if ($basePath !== null) {
        return $basePath;
    }

    $configured = envVar('APP_BASE_PATH');
    if ($configured !== null) {
        $configured = trim($configured);
        if ($configured === '' || $configured === '/') {
            $basePath = '';
            return $basePath;
        }
        $basePath = '/' . trim($configured, '/');
        return $basePath;
    }

    $basePath = detectAppBasePath();
    return $basePath;
}

function appUrl(string $path = ''): string
{
    $path = ltrim($path, '/');
    $base = appBasePath();

    if ($path === '') {
        return $base !== '' ? $base : '/';
    }

    return ($base !== '' ? $base : '') . '/' . $path;
}

function redirectTo(string $path): void
{
    header('Location: ' . appUrl($path));
    exit;
}

define('DB_HOST', envVar('DB_HOST', 'localhost'));
define('DB_PORT', envVar('DB_PORT', '3306'));
define('DB_NAME', envVar('DB_NAME', 'citylive'));
define('DB_USER', envVar('DB_USER', 'root'));
define('DB_PASS', envVar('DB_PASS', ''));
define('DB_CHAR', envVar('DB_CHAR', 'utf8mb4'));

function getDB(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHAR);
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            $pdo->exec("CREATE TABLE IF NOT EXISTS event_registrations (
                user_id        INT NOT NULL,
                publication_id INT NOT NULL,
                created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (user_id, publication_id)
            )");

            foreach (['min_attendees', 'max_attendees'] as $col) {
                try {
                    $pdo->exec("ALTER TABLE publications ADD COLUMN $col INT DEFAULT NULL");
                } catch (PDOException $e) {
                    // Column already exists, ignore.
                }
            }
        } catch (PDOException $e) {
            http_response_code(500);
            exit(json_encode(['error' => 'DB connection failed: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}

if (session_status() === PHP_SESSION_NONE) {
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $sessionName = envVar('SESSION_NAME', 'citylive_session');
    session_name($sessionName);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => appBasePath() !== '' ? appBasePath() : '/',
        'secure' => $https || envBool('SESSION_SECURE_COOKIE', false),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    if (empty($_SESSION['initiated_at'])) {
        session_regenerate_id(true);
        $_SESSION['initiated_at'] = time();
    }
}

function isLoggedIn(): bool
{
    return !empty($_SESSION['user_id']);
}

function requireLogin(?string $redirect = null): void
{
    if (isLoggedIn()) {
        return;
    }

    if ($redirect === null) {
        redirectTo('index.php');
    }

    header('Location: ' . $redirect);
    exit;
}

function currentUser(): ?array
{
    if (!isLoggedIn()) {
        return null;
    }

    static $user = null;
    if ($user === null) {
        $stmt = getDB()->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch() ?: null;
    }
    return $user;
}

function loginUser(int $userId): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfInput(): string
{
    $token = htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="_csrf" value="' . $token . '">';
}

function isValidCsrfToken(?string $token): bool
{
    if (empty($_SESSION['csrf_token']) || !is_string($token) || $token === '') {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function requireCsrf(): void
{
    $token = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
    if (isValidCsrfToken($token)) {
        return;
    }

    if (str_starts_with($_SERVER['REQUEST_URI'] ?? '', appUrl('api/'))) {
        http_response_code(419);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'CSRF token invalido']);
        exit;
    }

    http_response_code(419);
    exit('CSRF token invalido.');
}

function deductTokens(int $userId, int $amount, string $description): bool
{
    $db = getDB();
    $stmt = $db->prepare('SELECT tokens_balance FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $balance = (int)($stmt->fetchColumn() ?? 0);
    if ($balance < $amount) {
        return false;
    }

    $db->prepare('UPDATE users SET tokens_balance = tokens_balance - ? WHERE id = ?')
       ->execute([$amount, $userId]);
    $db->prepare('INSERT INTO token_transactions (user_id, amount, type, description) VALUES (?, ?, "publication", ?)')
       ->execute([$userId, -$amount, $description]);
    return true;
}

const PLAN_TOKENS = ['free' => 0, 'pro' => 1000, 'platinum' => 10000];
const PLAN_PRICES = ['free' => 0, 'pro' => 9.99, 'platinum' => 29.99];
