<?php
<<<<<<< HEAD
=======
// ─── Load .env file (robust version for all environments) ──────────────────────
$envConfig = [];
$envFile = __DIR__ . '/../.env';

if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip empty lines and comments
        if (empty(trim($line)) || strpos(trim($line), '#') === 0) {
            continue;
        }
        // Parse KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            $envConfig[$key] = $value;
        }
    }
}

// ─── Base URL path (e.g. '/citylive' on localhost, '' on domain root) ─────────
define('BASE', rtrim(parse_url($envConfig['APP_URL'] ?? 'http://localhost/citylive', PHP_URL_PATH) ?? '', '/'));

// ─── Database configuration (use .env first, then defaults) ───────────────────
define('DB_HOST', $envConfig['DB_HOST'] ?? 'localhost');
define('DB_NAME', $envConfig['DB_NAME'] ?? 'citylive');
define('DB_USER', $envConfig['DB_USER'] ?? 'root');
define('DB_PASS', $envConfig['DB_PASS'] ?? '');
define('DB_CHAR', $envConfig['DB_CHAR'] ?? 'utf8mb4');
define('DB_PORT', $envConfig['DB_PORT'] ?? '3306');
>>>>>>> main

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
<<<<<<< HEAD

=======
            
            // ─── Auto-migrate: Add email verification columns if missing ────
            try { $pdo->exec("ALTER TABLE users ADD COLUMN verification_token VARCHAR(64) DEFAULT NULL"); }
            catch (PDOException $e) {} // Already exists
            try { $pdo->exec("ALTER TABLE users ADD COLUMN token_created_at TIMESTAMP DEFAULT NULL"); }
            catch (PDOException $e) {} // Already exists
            
            // Auto-create event_registrations if missing (new table added after initial install)
>>>>>>> main
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
            // Auto-create spin_history table
            $pdo->exec("CREATE TABLE IF NOT EXISTS spin_history (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                user_id     INT          NOT NULL,
                spin_type   ENUM('daily','paid') NOT NULL,
                cost        INT          NOT NULL DEFAULT 0,
                reward      INT          NOT NULL DEFAULT 0,
                prize_label VARCHAR(50),
                created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_sh_user    (user_id),
                INDEX idx_sh_created (created_at),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            // Add 'spin' to token_transactions ENUM if not already present
            try {
                $col = $pdo->query("SHOW COLUMNS FROM token_transactions LIKE 'type'")->fetch();
                if ($col && strpos($col['Type'], 'spin') === false) {
                    $pdo->exec("ALTER TABLE token_transactions MODIFY type
                        ENUM('subscription','purchase','publication','reward','refund','spin') NOT NULL");
                }
            } catch (PDOException $e) {}
            // Auto-add profile fields to users if missing
            $profileCols = [
                "social_links TEXT NULL DEFAULT NULL",
                "updated_at   TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP",
            ];
            foreach ($profileCols as $colDef) {
                try { $pdo->exec("ALTER TABLE users ADD COLUMN $colDef"); }
                catch (PDOException $e) {}
            }
            // ── Publication reports ───────────────────────────────────────────
            $pdo->exec("CREATE TABLE IF NOT EXISTS publication_reports (
                id             INT AUTO_INCREMENT PRIMARY KEY,
                reporter_id    INT NOT NULL,
                publication_id INT NOT NULL,
                reason         ENUM('spam','false_info','inappropriate','other') NOT NULL,
                description    TEXT,
                status         ENUM('pending','reviewed','dismissed') DEFAULT 'pending',
                created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_report (reporter_id, publication_id),
                INDEX idx_pr_pub (publication_id),
                FOREIGN KEY (reporter_id)    REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (publication_id) REFERENCES publications(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // ── Forum tables ──────────────────────────────────────────────────
            $pdo->exec("CREATE TABLE IF NOT EXISTS event_forum_posts (
                id            INT AUTO_INCREMENT PRIMARY KEY,
                event_id      INT NOT NULL,
                user_id       INT NOT NULL,
                content       TEXT NOT NULL,
                is_pinned     TINYINT(1) DEFAULT 0,
                status        ENUM('active','deleted','hidden') DEFAULT 'active',
                like_count    INT DEFAULT 0,
                comment_count INT DEFAULT 0,
                created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_fp_event   (event_id),
                INDEX idx_fp_user    (user_id),
                INDEX idx_fp_created (created_at),
                INDEX idx_fp_likes   (like_count),
                FOREIGN KEY (event_id) REFERENCES publications(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id)  REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS event_forum_comments (
                id        INT AUTO_INCREMENT PRIMARY KEY,
                post_id   INT NOT NULL,
                user_id   INT NOT NULL,
                parent_id INT DEFAULT NULL,
                content   TEXT NOT NULL,
                status    ENUM('active','deleted','hidden') DEFAULT 'active',
                like_count INT DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_fc_post    (post_id),
                INDEX idx_fc_user    (user_id),
                INDEX idx_fc_parent  (parent_id),
                FOREIGN KEY (post_id)   REFERENCES event_forum_posts(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id)   REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (parent_id) REFERENCES event_forum_comments(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS event_forum_likes (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                user_id     INT NOT NULL,
                target_type ENUM('post','comment') NOT NULL,
                target_id   INT NOT NULL,
                created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_like (user_id, target_type, target_id),
                INDEX idx_fl_target (target_type, target_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $pdo->exec("CREATE TABLE IF NOT EXISTS event_forum_reports (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                reporter_id INT NOT NULL,
                target_type ENUM('post','comment') NOT NULL,
                target_id   INT NOT NULL,
                reason      ENUM('spam','offensive','inappropriate','other') NOT NULL,
                description TEXT,
                status      ENUM('pending','reviewed','dismissed') DEFAULT 'pending',
                created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_report (reporter_id, target_type, target_id),
                INDEX idx_fr_status (status),
                FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $pdo->exec("CREATE TABLE IF NOT EXISTS event_forum_images (
                id            INT AUTO_INCREMENT PRIMARY KEY,
                post_id       INT NOT NULL,
                user_id       INT NOT NULL,
                filename      VARCHAR(255) NOT NULL,
                original_name VARCHAR(255) NOT NULL,
                file_size     INT NOT NULL DEFAULT 0,
                width         INT DEFAULT 0,
                height        INT DEFAULT 0,
                created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_fi_post (post_id),
                FOREIGN KEY (post_id)  REFERENCES event_forum_posts(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id)  REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // Moderación logs
            $pdo->exec("CREATE TABLE IF NOT EXISTS event_forum_mod_log (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                mod_id      INT NOT NULL,
                action      VARCHAR(50) NOT NULL,
                target_type ENUM('post','comment') NOT NULL,
                target_id   INT NOT NULL,
                reason      TEXT,
                created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_fml_mod (mod_id),
                FOREIGN KEY (mod_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
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

<<<<<<< HEAD
    if (empty($_SESSION['initiated_at'])) {
        session_regenerate_id(true);
        $_SESSION['initiated_at'] = time();
=======
function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

function requireLogin(string $redirect = '/index.php'): void {
    if (!isLoggedIn()) {
        header('Location: ' . BASE . $redirect);
        exit;
>>>>>>> main
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
