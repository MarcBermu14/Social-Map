<?php
/**
 * Verificación de Email
 * Se accede via: /verify-email.php?token=xxxxx
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/email.php';

$error = '';
$success = false;
$token = $_GET['token'] ?? '';

if (!$token) {
    $error = 'Token de verificación no proporcionado.';
} else {
    // Validar el token
    $validation = validateVerificationToken($token);
    
    if (!$validation) {
        $error = 'Token inválido o expirado. Por favor, regístrate de nuevo.';
    } else {
        // Marcar usuario como verificado
        try {
            $db = getDB();
            $db->prepare('
                UPDATE users 
                SET verified = 1, 
                    verification_token = NULL, 
                    token_created_at = NULL
                WHERE id = ?
            ')->execute([$validation['user_id']]);
            
            $success = true;
        } catch (Exception $e) {
            error_log("Error verifying email: " . $e->getMessage());
            $error = 'Error al verificar el email. Intenta de nuevo más tarde.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Verificar Email — CityLive</title>
  <link rel="stylesheet" href="<?= BASE ?>/css/fontawesome.min.css">
  <link rel="stylesheet" href="<?= BASE ?>/css/style.css">
</head>
<body>
<div class="auth-page">
  <div class="auth-card" style="max-width:500px;">
    <div class="auth-logo">
      <div class="logo-icon">🗺️</div>
      <div class="logo-text">City<span>Live</span></div>
    </div>

    <?php if ($success): ?>
      <div style="text-align:center;padding:40px 20px;">
        <div style="font-size:60px;margin-bottom:20px;">✅</div>
        <h1 class="auth-title">¡Email Confirmado!</h1>
        <p class="auth-subtitle">Tu cuenta está lista para usar. Ya puedes iniciar sesión.</p>
        
        <a href="<?= BASE ?>/index.php" class="btn btn-primary btn-block btn-lg" style="margin-top:30px;">
          <i class="fa-solid fa-arrow-right"></i> Ir a iniciar sesión
        </a>
      </div>
    <?php else: ?>
      <div style="text-align:center;padding:40px 20px;">
        <div style="font-size:60px;margin-bottom:20px;">❌</div>
        <h1 class="auth-title">Verificación Fallida</h1>
        <div class="flash flash-error">
          <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?>
        </div>
        
        <div style="margin-top:30px;padding:20px;background:var(--card);border-radius:var(--r);border:1px solid var(--border);">
          <p style="margin:0;font-size:14px;color:var(--text2);">
            <strong>¿Qué hacer?</strong><br><br>
            • Si el enlace expiró, <a href="<?= BASE ?>/register.php" style="color:var(--primary);text-decoration:none;">regístrate de nuevo</a><br>
            • Si tienes problemas, <a href="<?= BASE ?>/index.php" style="color:var(--primary);text-decoration:none;">contacta con soporte</a>
          </p>
        </div>
        
        <a href="/" class="btn btn-secondary btn-block" style="margin-top:20px;">
          <i class="fa-solid fa-house"></i> Ir a inicio
        </a>
      </div>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
