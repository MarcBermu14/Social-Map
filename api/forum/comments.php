<?php
require_once __DIR__ . '/../../config/db.php';
requireLogin();
header('Content-Type: application/json; charset=utf-8');

$db     = getDB();
$userId = (int)$_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

function jsonOk(array $data = []): void { echo json_encode(['success' => true] + $data); exit; }
function jsonErr(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

function sanitizeContent(string $s): string {
    return mb_substr(strip_tags(trim($s)), 0, 2000);
}

function isAdmin(array $user): bool {
    return ($user['plan'] ?? 'free') === 'platinum';
}

// ── GET: comments for a post ───────────────────────────────────────────────
if ($method === 'GET') {
    $postId = (int)($_GET['post_id'] ?? 0);
    if (!$postId) jsonErr('post_id requerido');

    // Verify post exists
    $chk = $db->prepare("SELECT id FROM event_forum_posts WHERE id = ? AND status = 'active'");
    $chk->execute([$postId]);
    if (!$chk->fetch()) jsonErr('Post no encontrado', 404);

    $stmt = $db->prepare("
        SELECT c.id, c.post_id, c.user_id, c.parent_id, c.content,
               c.like_count, c.created_at, c.updated_at,
               u.username, u.full_name, u.avatar, u.plan,
               (SELECT 1 FROM event_forum_likes
                WHERE user_id = ? AND target_type = 'comment' AND target_id = c.id
                LIMIT 1) AS user_liked
        FROM event_forum_comments c
        JOIN users u ON u.id = c.user_id
        WHERE c.post_id = ? AND c.status = 'active'
        ORDER BY c.parent_id ASC, c.created_at ASC
    ");
    $stmt->execute([$userId, $postId]);
    $rows = $stmt->fetchAll();

    // Build threaded structure: top-level + replies
    $topLevel = [];
    $replies  = [];
    foreach ($rows as $c) {
        $c['user_liked'] = (bool)$c['user_liked'];
        $c['is_owner']   = ($c['user_id'] == $userId);
        $c['can_edit']   = ($c['user_id'] == $userId);
        $c['replies']    = [];
        if ($c['parent_id']) {
            $replies[$c['parent_id']][] = $c;
        } else {
            $topLevel[$c['id']] = $c;
        }
    }
    foreach ($replies as $parentId => $children) {
        if (isset($topLevel[$parentId])) {
            $topLevel[$parentId]['replies'] = $children;
        }
    }

    jsonOk(['comments' => array_values($topLevel)]);
}

// ── POST: create comment ───────────────────────────────────────────────────
if ($method === 'POST') {
    $body     = json_decode(file_get_contents('php://input'), true) ?? [];
    $postId   = (int)($body['post_id'] ?? 0);
    $parentId = isset($body['parent_id']) ? (int)$body['parent_id'] : null;
    $content  = sanitizeContent($body['content'] ?? '');

    if (!$postId) jsonErr('post_id requerido');
    if (mb_strlen($content) < 1) jsonErr('El comentario no puede estar vacío');
    if (mb_strlen($content) > 2000) jsonErr('Comentario demasiado largo (máx 2000 chars)');

    // Verify post
    $chk = $db->prepare("SELECT id FROM event_forum_posts WHERE id = ? AND status = 'active'");
    $chk->execute([$postId]);
    if (!$chk->fetch()) jsonErr('Post no encontrado', 404);

    // Validate parent_id (only 1 level deep)
    if ($parentId) {
        $par = $db->prepare(
            "SELECT id, parent_id FROM event_forum_comments WHERE id = ? AND post_id = ? AND status = 'active'"
        );
        $par->execute([$parentId, $postId]);
        $parRow = $par->fetch();
        if (!$parRow) jsonErr('Comentario padre no encontrado', 404);
        if ($parRow['parent_id']) $parentId = $parRow['parent_id']; // flatten to 1 level
    }

    // Rate limit: max 10 comments/min
    $cnt = $db->prepare(
        "SELECT COUNT(*) FROM event_forum_comments
         WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)"
    );
    $cnt->execute([$userId]);
    if ((int)$cnt->fetchColumn() >= 10) jsonErr('Demasiados comentarios. Espera un momento.', 429);

    $db->beginTransaction();
    try {
        $ins = $db->prepare(
            "INSERT INTO event_forum_comments (post_id, user_id, parent_id, content) VALUES (?, ?, ?, ?)"
        );
        $ins->execute([$postId, $userId, $parentId, $content]);
        $commentId = (int)$db->lastInsertId();

        // Increment comment_count on post
        $db->prepare("UPDATE event_forum_posts SET comment_count = comment_count + 1 WHERE id = ?")
           ->execute([$postId]);

        $db->commit();
    } catch (PDOException $e) {
        $db->rollBack();
        jsonErr('Error al crear comentario', 500);
    }

    $me = currentUser();
    $comment = [
        'id'         => $commentId,
        'post_id'    => $postId,
        'user_id'    => $userId,
        'parent_id'  => $parentId,
        'content'    => $content,
        'like_count' => 0,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
        'username'   => $me['username'],
        'full_name'  => $me['full_name'],
        'avatar'     => $me['avatar'],
        'plan'       => $me['plan'],
        'user_liked' => false,
        'is_owner'   => true,
        'can_edit'   => true,
        'replies'    => [],
    ];

    jsonOk(['comment' => $comment]);
}

// ── PATCH: edit comment ────────────────────────────────────────────────────
if ($method === 'PATCH') {
    $body      = json_decode(file_get_contents('php://input'), true) ?? [];
    $commentId = (int)($body['comment_id'] ?? 0);
    $content   = sanitizeContent($body['content'] ?? '');

    if (!$commentId) jsonErr('comment_id requerido');
    if (mb_strlen($content) < 1) jsonErr('El comentario no puede estar vacío');

    $row = $db->prepare("SELECT user_id FROM event_forum_comments WHERE id = ? AND status = 'active'");
    $row->execute([$commentId]);
    $row = $row->fetch();
    if (!$row) jsonErr('Comentario no encontrado', 404);

    $me = currentUser();
    if ($row['user_id'] != $userId && !isAdmin($me)) jsonErr('Sin permiso', 403);

    $db->prepare("UPDATE event_forum_comments SET content = ? WHERE id = ?")
       ->execute([$content, $commentId]);

    jsonOk(['content' => $content]);
}

// ── DELETE: soft-delete comment ────────────────────────────────────────────
if ($method === 'DELETE') {
    $body      = json_decode(file_get_contents('php://input'), true) ?? [];
    $commentId = (int)($body['comment_id'] ?? $_GET['comment_id'] ?? 0);

    if (!$commentId) jsonErr('comment_id requerido');

    $row = $db->prepare(
        "SELECT c.user_id, c.post_id FROM event_forum_comments c WHERE c.id = ? AND c.status != 'deleted'"
    );
    $row->execute([$commentId]);
    $row = $row->fetch();
    if (!$row) jsonErr('Comentario no encontrado', 404);

    $me = currentUser();
    if ($row['user_id'] != $userId && !isAdmin($me)) jsonErr('Sin permiso', 403);

    $db->beginTransaction();
    try {
        $db->prepare("UPDATE event_forum_comments SET status = 'deleted' WHERE id = ?")
           ->execute([$commentId]);
        $db->prepare(
            "UPDATE event_forum_posts SET comment_count = GREATEST(0, comment_count - 1) WHERE id = ?"
        )->execute([$row['post_id']]);

        if ($row['user_id'] != $userId) {
            $db->prepare(
                "INSERT INTO event_forum_mod_log (mod_id, action, target_type, target_id) VALUES (?, 'delete', 'comment', ?)"
            )->execute([$userId, $commentId]);
        }

        $db->commit();
    } catch (PDOException $e) {
        $db->rollBack();
        jsonErr('Error al eliminar', 500);
    }

    jsonOk();
}

jsonErr('Método no permitido', 405);
