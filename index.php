<?php
require_once __DIR__ . '/config/db.php';

// Already logged in → go to dashboard
if (isLoggedIn()) {
    header('Location: /citylive/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!$email || !$password) {
        $error = 'Por favor, completa todos los campos.';
    } else {
        $stmt = getDB()->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            // Update last_active
            getDB()->prepare('UPDATE users SET last_active = NOW() WHERE id = ?')->execute([$user['id']]);
            header('Location: /citylive/dashboard.php');
            exit;
        } else {
            $error = 'Email o contraseña incorrectos.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Iniciar sesión — CityLive</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="/citylive/css/style.css">
</head>
<body>
<div class="auth-page">
  <div class="auth-card">
    <div class="auth-logo">
      <div class="logo-icon">🗺️</div>
      <div class="logo-text">City<span>Live</span></div>
    </div>

    <h1 class="auth-title">Bienvenido de nuevo</h1>
    <p class="auth-subtitle">Inicia sesión para ver tu ciudad en tiempo real.</p>

    <?php if ($error): ?>
      <div class="flash flash-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
      <div class="form-group">
        <label class="form-label" for="email">Email</label>
        <input class="form-input" type="email" id="email" name="email"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
               placeholder="tu@email.com" required autofocus>
      </div>

      <div class="form-group">
        <label class="form-label" for="password">Contraseña</label>
        <input class="form-input" type="password" id="password" name="password"
               placeholder="••••••••" required>
      </div>

      <button class="btn btn-primary btn-block btn-lg" type="submit" style="margin-top:8px;">
        <i class="fa-solid fa-right-to-bracket"></i> Entrar
      </button>
    </form>

    <div class="divider"></div>

    <p class="text-sm text-muted" style="text-align:center;margin-bottom:16px;">
      ¿No tienes cuenta? <a href="/citylive/register.php">Crear cuenta gratis</a>
    </p>

    <!-- Demo credentials -->
    <div style="background:var(--card);border:1px solid var(--border);border-radius:var(--r);padding:14px;">
      <div style="font-size:11px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.08em;margin-bottom:10px;">
        Cuentas de demo
      </div>
      <div style="display:flex;flex-direction:column;gap:6px;">
        <button type="button" class="demo-login-btn" onclick="fillDemo('maria@citylive.app')"
                style="display:flex;align-items:center;gap:8px;background:none;border:1px solid var(--border);border-radius:8px;padding:8px 12px;color:var(--text2);font-size:12px;cursor:pointer;transition:.15s;text-align:left;">
          <span style="font-size:16px;">💎</span>
          <div>
            <strong style="color:var(--text);">maria@citylive.app</strong>
            <span style="color:var(--text3);"> · Platinum</span>
          </div>
        </button>
        <button type="button" onclick="fillDemo('carlos@citylive.app')"
                style="display:flex;align-items:center;gap:8px;background:none;border:1px solid var(--border);border-radius:8px;padding:8px 12px;color:var(--text2);font-size:12px;cursor:pointer;transition:.15s;text-align:left;">
          <span style="font-size:16px;">⭐</span>
          <div>
            <strong style="color:var(--text);">carlos@citylive.app</strong>
            <span style="color:var(--text3);"> · Pro</span>
          </div>
        </button>
        <button type="button" onclick="fillDemo('alex@citylive.app')"
                style="display:flex;align-items:center;gap:8px;background:none;border:1px solid var(--border);border-radius:8px;padding:8px 12px;color:var(--text2);font-size:12px;cursor:pointer;transition:.15s;text-align:left;">
          <span style="font-size:16px;">🆓</span>
          <div>
            <strong style="color:var(--text);">alex@citylive.app</strong>
            <span style="color:var(--text3);"> · Free</span>
          </div>
        </button>
      </div>
      <p style="font-size:11px;color:var(--text3);margin-top:8px;">Contraseña: <code style="color:var(--primary);">demo1234</code></p>
    </div>
  </div>
</div>

<script>
function fillDemo(email) {
  document.getElementById('email').value    = email;
  document.getElementById('password').value = 'demo1234';
}
</script>
</body>
</html>
