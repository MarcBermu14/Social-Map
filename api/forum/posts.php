<?php
require_once __DIR__ . '/../../config/db.php';
requireLogin();
header('Content-Type: application/json; charset=utf-8');

$db     = getDB();
$userId = (int)$_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

// ── Helpers ────────────────────────────────────────────────────────────────
function jsonOk(array $data = []): void   { echo json_encode(['success' => true]  + $data); exit; }
function jsonErr(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

function sanitizeContent(string $s): string {
    $s = trim($s);
    // Strip dangerous tags; keep newlines
    $s = strip_tags($s);
    return mb_substr($s, 0, 4000);
}

function checkRateLimit(PDO $db, int $userId, int $maxPerMin = 5): void {
    $cnt = $db->prepare(
        "SELECT COUNT(*) FROM event_forum_posts
         WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)"
    );
    $cnt->execute([$userId]);
    if ((int)$cnt->fetchColumn() >= $maxPerMin) {
        jsonErr('Demasiados posts en poco tiempo. Espera un minuto.', 429);
    }
}

function isAdmin(array $user): bool {
    return ($user['plan'] ?? 'free') === 'platinum';
}

// ── GET: list posts for event ──────────────────────────────────────────────
if ($method === 'GET') {
    $eventId = (int)($_GET['event'] ?? 0);
    if (!$eventId) jsonErr('event_id requerido');

    $page  = max(1, (int)($_GET['page'] ?? 1));
    $limit = 10;
    $offset = ($page - 1) * $limit;

    $sort = $_GET['sort'] ?? 'recent';
    $orderBy = match($sort) {
        'popular'       => 'p.like_count DESC, p.created_at DESC',
        'most_comments' => 'p.comment_count DESC, p.created_at DESC',
        default         => 'p.is_pinned DESC, p.created_at DESC',
    };

    // Total for pagination
    $total = $db->prepare(
        "SELECT COUNT(*) FROM event_forum_posts p
         WHERE p.event_id = ? AND p.status = 'active'"
    );
    $total->execute([$eventId]);
    $totalPosts = (int)$total->fetchColumn();

    $stmt = $db->prepare("
        SELECT p.id, p.event_id, p.user_id, p.content, p.is_pinned,
               p.like_count, p.comment_count, p.created_at, p.updated_at,
               u.username, u.full_name, u.avatar, u.plan,
               pub.user_id AS event_owner_id,
               (SELECT 1 FROM event_forum_likes
                WHERE user_id = ? AND target_type = 'post' AND target_id = p.id
                LIMIT 1) AS user_liked
        FROM event_forum_posts p
        JOIN users u ON u.id = p.user_id
        JOIN publications pub ON pub.id = p.event_id
        WHERE p.event_id = ? AND p.status = 'active'
        ORDER BY {$orderBy}
        LIMIT {$limit} OFFSET {$offset}
    ");
    $stmt->execute([$userId, $eventId]);
    $posts = $stmt->fetchAll();

    // Attach images to each post
    $postIds = array_column($posts, 'id');
    $images  = [];
    if ($postIds) {
        $in   = implode(',', array_map('intval', $postIds));
        $imgs = $db->query(
            "SELECT id, post_id, filename, width, height, file_size
             FROM event_forum_images WHERE post_id IN ({$in}) ORDER BY id ASC"
        )->fetchAll();
        foreach ($imgs as $img) {
            $images[$img['post_id']][] = [
                'id'       => $img['id'],
                'url'      => '/citylive/uploads/forum/' . $img['filename'],
                'width'    => $img['width'],
                'height'   => $img['height'],
                'size'     => $img['file_size'],
            ];
        }
    }

    foreach ($posts as &$p) {
        $p['images']      = $images[$p['id']] ?? [];
        $p['user_liked']  = (bool)$p['user_liked'];
        $p['is_pinned']   = (bool)$p['is_pinned'];
        $p['is_owner']    = ($p['user_id'] == $userId);
        $p['is_event_org']= ($p['event_owner_id'] == $p['user_id']);
        $p['can_edit']    = ($p['user_id'] == $userId);
        unset($p['event_owner_id']);
    }
    unset($p);

    jsonOk([
        'posts'      => $posts,
        'total'      => $totalPosts,
        'page'       => $page,
        'pages'      => (int)ceil($totalPosts / $limit),
        'has_more'   => ($page * $limit) < $totalPosts,
    ]);
}

// ── POST: create new post ──────────────────────────────────────────────────
if ($method === 'POST') {
    $body    = json_decode(file_get_contents('php://input'), true) ?? [];
    $eventId = (int)($body['event_id'] ?? 0);
    $content = sanitizeContent($body['content'] ?? '');
    $imgIds  = array_slice(array_map('intval', (array)($body['image_ids'] ?? [])), 0, 5);

    if (!$eventId) jsonErr('event_id requerido');
    if (mb_strlen($content) < 1) jsonErr('El contenido no puede estar vacío');
    if (mb_strlen($content) > 4000) jsonErr('El contenido es demasiado largo (máx 4000 chars)');

    // Verify event exists
    $ev = $db->prepare("SELECT id FROM publications WHERE id = ? AND status = 'active'");
    $ev->execute([$eventId]);
    if (!$ev->fetch()) jsonErr('Evento no encontrado');

    // Rate limit: max 5 posts/min
    checkRateLimit($db, $userId, 5);

    $db->beginTransaction();
    try {
        $ins = $db->prepare(
            "INSERT INTO event_forum_posts (event_id, user_id, content) VALUES (?, ?, ?)"
        );
        $ins->execute([$eventId, $userId, $content]);
        $postId = (int)$db->lastInsertId();

        // Attach pre-uploaded images
        if ($imgIds) {
            $upd = $db->prepare(
                "UPDATE event_forum_images SET post_id = ? WHERE id = ? AND user_id = ? AND post_id = 0"
            );
            foreach ($imgIds as $imgId) {
                $upd->execute([$postId, $imgId, $userId]);
            }
        }

        $db->commit();
    } catch (PDOException $e) {
        $db->rollBack();
        jsonErr('Error al crear el post', 500);
    }

    // Return the new post with user data
    $stmt = $db->prepare("
        SELECT p.id, p.event_id, p.user_id, p.content, p.is_pinned,
               p.like_count, p.comment_count, p.created_at, p.updated_at,
               u.username, u.full_name, u.avatar, u.plan,
               pub.user_id AS event_owner_id
        FROM event_forum_posts p
        JOIN users u ON u.id = p.user_id
        JOIN publications pub ON pub.id = p.event_id
        WHERE p.id = ?
    ");
    $stmt->execute([$postId]);
    $post = $stmt->fetch();

    $imgs = $db->prepare(
        "SELECT id, filename, width, height, file_size FROM event_forum_images WHERE post_id = ?"
    );
    $imgs->execute([$postId]);
    $post['images']       = array_map(fn($i) => $i + ['url' => '/citylive/uploads/forum/' . $i['filename']], $imgs->fetchAll());
    $post['user_liked']   = false;
    $post['is_pinned']    = false;
    $post['is_owner']     = true;
    $post['is_event_org'] = ($post['event_owner_id'] == $userId);
    $post['can_edit']     = true;
    unset($post['event_owner_id']);

    jsonOk(['post' => $post]);
}

// ── PATCH: edit post ───────────────────────────────────────────────────────
if ($method === 'PATCH') {
    $body    = json_decode(file_get_contents('php://input'), true) ?? [];
    $postId  = (int)($body['post_id'] ?? 0);
    $content = sanitizeContent($body['content'] ?? '');

    if (!$postId) jsonErr('post_id requerido');
    if (mb_strlen($content) < 1) jsonErr('El contenido no puede estar vacío');

    $post = $db->prepare("SELECT user_id FROM event_forum_posts WHERE id = ? AND status = 'active'");
    $post->execute([$postId]);
    $post = $post->fetch();
    if (!$post) jsonErr('Post no encontrado', 404);

    $me = currentUser();
    if ($post['user_id'] != $userId && !isAdmin($me)) jsonErr('Sin permiso', 403);

    $db->prepare("UPDATE event_forum_posts SET content = ? WHERE id = ?")
       ->execute([$content, $postId]);

    jsonOk(['content' => $content]);
}

// ── DELETE: soft-delete post ───────────────────────────────────────────────
if ($method === 'DELETE') {
    $body   = json_decode(file_get_contents('php://input'), true) ?? [];
    $postId = (int)($body['post_id'] ?? $_GET['post_id'] ?? 0);

    if (!$postId) jsonErr('post_id requerido');

    $post = $db->prepare("SELECT user_id FROM event_forum_posts WHERE id = ? AND status != 'deleted'");
    $post->execute([$postId]);
    $row = $post->fetch();
    if (!$row) jsonErr('Post no encontrado', 404);

    $me = currentUser();
    if ($row['user_id'] != $userId && !isAdmin($me)) jsonErr('Sin permiso', 403);

    $db->beginTransaction();
    try {
        // Mark post deleted
        $db->prepare("UPDATE event_forum_posts SET status = 'deleted' WHERE id = ?")
           ->execute([$postId]);

        // Delete image files from disk
        $imgs = $db->prepare("SELECT filename FROM event_forum_images WHERE post_id = ?");
        $imgs->execute([$postId]);
        foreach ($imgs->fetchAll() as $img) {
            $path = __DIR__ . '/../../uploads/forum/' . $img['filename'];
            if (file_exists($path)) @unlink($path);
        }

        // Log moderation if admin action
        if ($row['user_id'] != $userId) {
            $db->prepare(
                "INSERT INTO event_forum_mod_log (mod_id, action, target_type, target_id) VALUES (?, 'delete', 'post', ?)"
            )->execute([$userId, $postId]);
        }

        $db->commit();
    } catch (PDOException $e) {
        $db->rollBack();
        jsonErr('Error al eliminar', 500);
    }

    jsonOk();
}

jsonErr('Método no permitido', 405);
