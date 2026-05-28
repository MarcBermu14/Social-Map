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
