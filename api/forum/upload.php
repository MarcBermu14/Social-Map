<?php
require_once __DIR__ . '/../../config/db.php';
requireLogin();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$db     = getDB();
$userId = (int)$_SESSION['user_id'];

// ── Config ─────────────────────────────────────────────────────────────────
const FORUM_UPLOAD_DIR  = __DIR__ . '/../../uploads/forum/';
$forumUploadUrl = url_for('uploads/forum/');
const MAX_FILE_BYTES    = 5 * 1024 * 1024;   // 5 MB per image
const MAX_IMAGES_BATCH  = 5;                  // max per upload call
const MAX_DIMENSION     = 1920;               // max pixel width/height
const JPEG_QUALITY      = 82;

// ── Validate uploads ───────────────────────────────────────────────────────
if (empty($_FILES['images'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No se han enviado imágenes']);
    exit;
}

// Normalize $_FILES structure (handles single or multiple)
$files = $_FILES['images'];
if (!is_array($files['name'])) {
    $files = [
        'name'     => [$files['name']],
        'type'     => [$files['type']],
        'tmp_name' => [$files['tmp_name']],
        'error'    => [$files['error']],
        'size'     => [$files['size']],
    ];
}

$count = count($files['name']);
if ($count > MAX_IMAGES_BATCH) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Máximo ' . MAX_IMAGES_BATCH . ' imágenes por post']);
    exit;
}

// ── Rate limit: max 20 images per minute ──────────────────────────────────
$imgRate = $db->prepare(
    "SELECT COUNT(*) FROM event_forum_images WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)"
);
$imgRate->execute([$userId]);
if ((int)$imgRate->fetchColumn() + $count > 20) {
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => 'Demasiadas imágenes subidas. Espera un momento.']);
    exit;
}

// ── Process each file ──────────────────────────────────────────────────────
$uploaded = [];
$errors   = [];

for ($i = 0; $i < $count; $i++) {
    if ($files['error'][$i] !== UPLOAD_ERR_OK) {
        $errors[] = "Archivo {$i}: error de subida ({$files['error'][$i]})";
        continue;
    }
    if ($files['size'][$i] > MAX_FILE_BYTES) {
        $errors[] = htmlspecialchars($files['name'][$i]) . ': excede 5 MB';
        continue;
    }

    $tmp  = $files['tmp_name'][$i];
    $orig = $files['name'][$i];

    // Verify real image type via GD (not just extension or MIME)
    $imgType = @exif_imagetype($tmp);
    $allowed  = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP];
    if (!$imgType || !in_array($imgType, $allowed, true)) {
        $errors[] = htmlspecialchars($orig) . ': formato no permitido (JPEG, PNG, GIF, WebP)';
        continue;
    }

    // Load image with GD — this also strips any embedded malicious payloads
    $src = match($imgType) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($tmp),
        IMAGETYPE_PNG  => @imagecreatefrompng($tmp),
        IMAGETYPE_GIF  => @imagecreatefromgif($tmp),
        IMAGETYPE_WEBP => @imagecreatefromwebp($tmp),
        default        => false,
    };
    if (!$src) {
        $errors[] = htmlspecialchars($orig) . ': no se pudo procesar la imagen';
        continue;
    }

    $origW = imagesx($src);
    $origH = imagesy($src);

    // Scale down if too large
    if ($origW > MAX_DIMENSION || $origH > MAX_DIMENSION) {
        $ratio = min(MAX_DIMENSION / $origW, MAX_DIMENSION / $origH);
        $newW  = (int)round($origW * $ratio);
        $newH  = (int)round($origH * $ratio);
        $dst   = imagecreatetruecolor($newW, $newH);
        // Preserve transparency for PNG
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefill($dst, 0, 0, $transparent);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
        imagedestroy($src);
        $finalW = $newW;
        $finalH = $newH;
        $img    = $dst;
    } else {
        $finalW = $origW;
        $finalH = $origH;
        $img    = $src;
    }

    // Generate safe random filename
    $filename = bin2hex(random_bytes(16)) . '.jpg';
    $destPath = FORUM_UPLOAD_DIR . $filename;

    if (!imagejpeg($img, $destPath, JPEG_QUALITY)) {
        imagedestroy($img);
        $errors[] = htmlspecialchars($orig) . ': error al guardar';
        continue;
    }
    imagedestroy($img);

    $fileSize = filesize($destPath);

    // Store in DB with post_id = 0 (will be linked when post is created)
    $ins = $db->prepare(
        "INSERT INTO event_forum_images (post_id, user_id, filename, original_name, file_size, width, height)
         VALUES (0, ?, ?, ?, ?, ?, ?)"
    );
    $ins->execute([$userId, $filename, mb_substr($orig, 0, 255), $fileSize, $finalW, $finalH]);
    $imgId = (int)$db->lastInsertId();

    $uploaded[] = [
        'id'     => $imgId,
        'url'    => $forumUploadUrl . $filename,
        'width'  => $finalW,
        'height' => $finalH,
        'size'   => $fileSize,
    ];
}

echo json_encode([
    'success'  => true,
    'images'   => $uploaded,
    'errors'   => $errors,
]);
