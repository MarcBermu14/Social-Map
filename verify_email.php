<?php
require_once __DIR__ . '/config/db.php';

$db = getDB();
$errors = [];
$success = '';
$emailValue = trim($_GET['email'] ?? '');
$token = trim($_GET['token'] ?? '');

if ($token !== '') {
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        $errors[] = 'El enlace de verificación no es válido.';
    } else {
        $hash = hash('sha256', $token);
        $stmt = $db->prepare('SELECT id, email, full_name, username, verified, email_verification_expires_at FROM users WHERE email_verification_token = ?');
        $stmt->execute([$hash]);
        $user = $stmt->fetch();
        if (!$user) {
            $errors[] = 'El enlace de verificación no es válido o ya se usó.';
        } elseif ((int)($user['verified'] ?? 0) === 1) {
            $success = 'Tu email ya estaba verificado. Puedes iniciar sesión.';
        } else {
            $expiresAt = $user['email_verification_expires_at'];
            if ($expiresAt && strtotime($expiresAt) < time()) {
                $errors[] = 'El enlace de verificación ha caducado. Solicita uno nuevo.';
                $emailValue = $user['email'];
            } else {
                $db->prepare('
                    UPDATE users
                    SET verified = 1,
                        email_verification_token = NULL,
                        email_verification_expires_at = NULL
                    WHERE id = ?
                ')->execute([$user['id']]);
                $success = '¡Cuenta verificada! Ya puedes iniciar sesión.';
            }
        }
    }
} elseif (isset($_GET['sent'])) {
    $success = 'Te enviamos un correo de verificación. Revisa tu bandeja de entrada.';
} elseif (isset($_GET['error']) && $_GET['error'] === 'mail') {
    $errors[] = 'No pudimos enviar el correo. Revisa la configuración de email e inténtalo de nuevo.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $emailValue = $email;
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Introduce un email válido.';
    } else {
        $stmt = $db->prepare('SELECT id, email, full_name, username, verified FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if (!$user) {
            $errors[] = 'No encontramos una cuenta con ese email.';
        } elseif ((int)($user['verified'] ?? 0) === 1) {
            $success = 'Tu cuenta ya está verificada. Puedes iniciar sesión.';
        } else {
            try {
                $token = bin2hex(random_bytes(32));
            } catch (\Exception $e) {
                error_log('Verification token generation failed: ' . $e->getMessage());
                $token = null;
            }
            if ($token === null) {
                $errors[] = 'No se pudo generar el enlace de verificación.';
            } else {
                $tokenHash = hash('sha256', $token);
                $expiresAt = (new DateTime())->modify('+24 hours')->format('Y-m-d H:i:s');
                $db->prepare('
                    UPDATE users
                    SET email_verification_token = ?, email_verification_expires_at = ?
                    WHERE id = ?
                ')->execute([$tokenHash, $expiresAt, $user['id']]);
                if (sendVerificationEmail($email, $user['full_name'] ?: $user['username'], $token)) {
                    $success = 'Te enviamos un nuevo correo de verificación.';
                } else {
                    $errors[] = 'No pudimos enviar el correo. Inténtalo más tarde.';
                }
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
  <title>Verificar email — CityLive</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="<?= url_for('css/style.css') ?>">
</head>
<body>
<div class="auth-page">
  <div class="auth-card">
    <div class="auth-logo">
      <div class="logo-icon">🗺️</div>
      <div class="logo-text">City<span>Live</span></div>
    </div>

    <h1 class="auth-title">Verifica tu email</h1>
    <p class="auth-subtitle">Activa tu cuenta para acceder al mapa.</p>

    <?php foreach ($errors as $e): ?>
      <div class="flash flash-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>

    <?php if ($success): ?>
      <div class="flash flash-success"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="POST" action="" style="margin-top:16px;">
      <div class="form-group">
        <label class="form-label" for="email">Reenviar enlace</label>
        <input class="form-input" type="email" id="email" name="email"
               value="<?= htmlspecialchars($emailValue) ?>"
               placeholder="tu@email.com" required>
      </div>
      <button class="btn btn-primary btn-block" type="submit">
        <i class="fa-solid fa-paper-plane"></i> Reenviar verificación
      </button>
    </form>

    <p class="text-sm text-muted" style="text-align:center;margin-top:18px;">
      ¿Ya verificaste? <a href="<?= url_for('index.php') ?>">Iniciar sesión</a>
    </p>
  </div>
</div>
</body>
</html>
