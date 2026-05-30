<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/email.php';

if (isLoggedIn()) { header('Location: /citylive/dashboard.php'); exit; }

$errors = [];
$success = false;
$email_sent = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username  = trim($_POST['username']  ?? '');
    $email     = trim($_POST['email']     ?? '');
    $fullName  = trim($_POST['full_name'] ?? '');
    $password  = $_POST['password']  ?? '';
    $password2 = $_POST['password2'] ?? '';

    // ─── Validaciones ─────────────────────────────────────
    if (!$username)  $errors[] = 'El nombre de usuario es obligatorio.';
    if (strlen($username) < 3) $errors[] = 'El usuario debe tener al menos 3 caracteres.';
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) $errors[] = 'El usuario solo puede contener letras, números, guiones y guiones bajos.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email inválido.';
    if (strlen($password) < 6) $errors[] = 'La contraseña debe tener al menos 6 caracteres.';
    if ($password !== $password2) $errors[] = 'Las contraseñas no coinciden.';
    if (!$fullName) $errors[] = 'El nombre completo es obligatorio.';

    if (empty($errors)) {
        $db = getDB();

        // ─── Verificar que email y usuario sean únicos ──────
        $stmt = $db->prepare('SELECT id FROM users WHERE email = ? OR username = ?');
        $stmt->execute([$email, $username]);
        if ($stmt->fetch()) {
            $errors[] = 'El email o nombre de usuario ya está en uso. Por favor elige otro.';
        } else {
            try {
                // ─── Crear usuario sin verificar ──────────────
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $verificationToken = generateVerificationToken();
                
                $db->prepare('
                    INSERT INTO users 
                    (username, email, password_hash, full_name, verified, verification_token, token_created_at) 
                    VALUES (?, ?, ?, ?, 0, ?, NOW())
                ')->execute([$username, $email, $hash, $fullName, $verificationToken]);
                
                $userId = (int)$db->lastInsertId();

                // ─── Crear suscripción gratuita ───────────────
                $db->prepare('INSERT INTO subscriptions (user_id, plan) VALUES (?, "free")')
                   ->execute([$userId]);

                // ─── Enviar correo de verificación ────────────
                if (sendVerificationEmail($email, $fullName, $verificationToken)) {
                    $success = true;
                    $email_sent = true;
                } else {
                    // Si falla el email, igual continuamos (pero lo registramos)
                    error_log("Failed to send verification email to $email");
                    $success = true;
                    $email_sent = false;
                }
                
            } catch (Exception $e) {
                error_log("Registration error: " . $e->getMessage());
                $errors[] = 'Error al crear la cuenta. Por favor intenta de nuevo.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Crear cuenta — CityLive</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="/css/style.css">
</head>
<body>
<div class="auth-page">
  <div class="auth-card">
    <div class="auth-logo">
      <div class="logo-icon">🗺️</div>
      <div class="logo-text">City<span>Live</span></div>
    </div>

    <h1 class="auth-title">Crear cuenta</h1>
    <p class="auth-subtitle">Únete y empieza a explorar tu ciudad en tiempo real.</p>

    <?php if ($success && $email_sent): ?>
      <div class="flash flash-success" style="background:#d4edda;border-color:#c3e6cb;color:#155724;margin-bottom:20px;padding:15px;border-radius:4px;border:1px solid">
        <i class="fa-solid fa-circle-check"></i> 
        <strong>¡Cuenta creada!</strong> 
        Revisa tu correo para confirmar tu email. Si no ves el mensaje, revisa la carpeta de spam.
      </div>
      <p style="text-align:center;margin-bottom:20px;">
        <a href="/citylive/index.php" style="color:#007bff;text-decoration:none;">Ir a iniciar sesión</a>
      </p>
    <?php elseif ($success && !$email_sent): ?>
      <div class="flash" style="background:#fff3cd;border-color:#ffc107;color:#856404;margin-bottom:20px;padding:15px;border-radius:4px;border:1px solid">
        <i class="fa-solid fa-triangle-exclamation"></i> 
        <strong>Cuenta creada,</strong> pero no pudimos enviar el correo de confirmación. Contacta con soporte.
      </div>
    <?php else: ?>
      <?php foreach ($errors as $e): ?>
        <div class="flash flash-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($e) ?></div>
      <?php endforeach; ?>

    <form method="POST" action="">
      <div class="form-group">
        <label class="form-label" for="full_name">Nombre completo</label>
        <input class="form-input" type="text" id="full_name" name="full_name"
               value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
               placeholder="Tu nombre" required>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div class="form-group">
          <label class="form-label" for="username">Usuario</label>
          <input class="form-input" type="text" id="username" name="username"
                 value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                 placeholder="nombredeusuario" required>
        </div>
        <div class="form-group">
          <label class="form-label" for="email">Email</label>
          <input class="form-input" type="email" id="email" name="email"
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                 placeholder="tu@email.com" required>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div class="form-group">
          <label class="form-label" for="password">Contraseña</label>
          <input class="form-input" type="password" id="password" name="password"
                 placeholder="Mín. 6 caracteres" required>
        </div>
        <div class="form-group">
          <label class="form-label" for="password2">Confirmar</label>
          <input class="form-input" type="password" id="password2" name="password2"
                 placeholder="Repetir contraseña" required>
        </div>
      </div>

      <div style="background:var(--card);border:1px solid var(--border);border-radius:var(--r);padding:14px;margin-bottom:16px;">
        <div style="font-size:12px;color:var(--text2);line-height:1.5;">
          ✅ Cuenta <strong style="color:var(--text);">gratuita</strong> — Publicar incidencias y eventos sin coste.<br>
          ⬆️ Actualiza a <strong style="color:var(--primary);">Pro</strong> o <strong style="color:var(--purple);">Platinum</strong> para actividades lucrativas.
        </div>
      </div>

      <button class="btn btn-primary btn-block btn-lg" type="submit">
        <i class="fa-solid fa-user-plus"></i> Crear cuenta
      </button>
    </form>

    <p class="text-sm text-muted" style="text-align:center;margin-top:20px;">
      ¿Ya tienes cuenta? <a href="/citylive/index.php">Iniciar sesión</a>
    </p>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
