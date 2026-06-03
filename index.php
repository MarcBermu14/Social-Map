<?php
require_once __DIR__ . '/config/db.php';

// Already logged in → go to dashboard
if (isLoggedIn()) {
<<<<<<< HEAD
    redirectTo('dashboard.php');
=======
    header('Location: ' . BASE . '/dashboard.php');
    exit;
>>>>>>> main
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!$email || !$password) {
        $error = 'Por favor, completa todos los campos.';
    } else {
        $stmt = getDB()->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
<<<<<<< HEAD
            loginUser((int)$user['id']);
            // Update last_active
            getDB()->prepare('UPDATE users SET last_active = NOW() WHERE id = ?')->execute([$user['id']]);
            redirectTo('dashboard.php');
=======
            // Verificar que el email esté confirmado
            if (!$user['verified']) {
                $error = 'Por favor confirma tu email antes de iniciar sesión. Revisa tu bandeja de entrada.';
            } else {
                $_SESSION['user_id'] = $user['id'];
                // Update last_active
                getDB()->prepare('UPDATE users SET last_active = NOW() WHERE id = ?')->execute([$user['id']]);
                header('Location: ' . BASE . '/dashboard.php');
                exit;
            }
>>>>>>> main
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
<<<<<<< HEAD
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="<?= appUrl('css/style.css') ?>">
=======
  <link rel="stylesheet" href="<?= BASE ?>/css/fontawesome.min.css">
  <link rel="stylesheet" href="<?= BASE ?>/css/style.css">
>>>>>>> main
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
      <?= csrfInput() ?>
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
<<<<<<< HEAD
      ¿No tienes cuenta? <a href="<?= appUrl('register.php') ?>">Crear cuenta gratis</a>
=======
      ¿No tienes cuenta? <a href="<?= BASE ?>/register.php">Crear cuenta gratis</a>
>>>>>>> main
    </p>

  </div>
</div>

</body>
</html>



