<?php
header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/config/db.php';

if (isLoggedIn()) {
    header('Location: ' . BASE . '/dashboard.php');
    exit;
}

$error = '';
$errorMessage = '';
$errorLinkUrl = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!$email || !$password) {
        $error = 'Por favor, completa todos los campos.';
    } else {
        $stmt = getDB()->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            if (!$user['verified']) {
                $error = 'Por favor confirma tu email antes de iniciar sesión. Revisa tu bandeja de entrada.';
            } else {
                $_SESSION['user_id'] = $user['id'];
                getDB()->prepare('UPDATE users SET last_active = NOW() WHERE id = ?')->execute([$user['id']]);
                header('Location: ' . BASE . '/dashboard.php');
                exit;
            }
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
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Outfit:wght@500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE ?>/css/fontawesome.min.css">
  <link rel="stylesheet" href="<?= BASE ?>/css/auth-landing.css">
</head>
<body class="auth-screen auth-login">
  <main class="auth-shell-modern">
    <section class="auth-stage">
      <header class="auth-header-modern">
        <a href="<?= BASE ?>/landing.php" class="auth-brand-modern" aria-label="CityLive">
          <span class="auth-brand-mark"><span class="auth-brand-core"></span></span>
          <span class="auth-brand-text">City<span>Live</span></span>
        </a>
      </header>

      <div class="auth-layout-modern">
        <aside class="auth-side-copy">
          <div class="auth-side-accent" aria-hidden="true"><span></span><span></span><span></span></div>
          <h1>
            <span>Vive Barcelona.</span>
            <span>Conecta.</span>
            <span>En tiempo real.</span>
          </h1>
          <p>
            Inicia sesión para descubrir eventos, conectar con personas y vivir lo mejor de Barcelona
            en tiempo real.
          </p>
          <div class="auth-side-art" aria-hidden="true"></div>
        </aside>

        <section class="auth-card-wrap">
          <div class="auth-card-modern">
            <div class="auth-card-accent" aria-hidden="true"><span></span><span></span><span></span></div>
            <h2>Bienvenido de nuevo</h2>
            <p class="auth-card-subtitle">
              Nos alegra verte de nuevo. Inicia sesión para seguir conectado con tu ciudad.
            </p>

            <?php
header('Content-Type: text/html; charset=UTF-8'); if ($errorLinkUrl): ?>
              <div class="auth-flash"><i class="fa-solid fa-circle-exclamation"></i><?= htmlspecialchars($errorMessage) ?> <a href="<?= htmlspecialchars($errorLinkUrl) ?>">verificación de correo</a>.</div>
            <?php
header('Content-Type: text/html; charset=UTF-8'); elseif ($error): ?>
              <div class="auth-flash"><i class="fa-solid fa-circle-exclamation"></i><?= htmlspecialchars($error) ?></div>
            <?php
header('Content-Type: text/html; charset=UTF-8'); endif; ?>

            <form method="POST" action="" class="auth-form-modern">
              <div class="auth-form-group">
                <label for="email">Correo electrónico</label>
                <div class="auth-input-shell">
                  <i class="fa-regular fa-envelope"></i>
                  <input type="email" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="ejemplo@citylive.com" required autofocus>
                </div>
              </div>

              <div class="auth-form-group">
                <label for="password">Contraseña</label>
                <div class="auth-input-shell">
                  <i class="fa-regular fa-lock"></i>
                  <input type="password" id="password" name="password" placeholder="••••••••" required data-password-input>
                  <button type="button" aria-label="Mostrar contraseña" data-toggle-password>
                    <i class="fa-regular fa-eye"></i>
                  </button>
                </div>
              </div>

              <div class="auth-meta-row">
                <label class="auth-checkbox">
                  <input type="checkbox" name="remember" value="1">
                  <span>Recordarme</span>
                </label>
                <a class="auth-forgot" href="mailto:soporte@citylive.app?subject=Recuperar%20contrase%C3%B1a%20CityLive">¿Has olvidado tu contraseña?</a>
              </div>

              <button class="auth-btn-primary" type="submit">
                <span>Iniciar sesión</span>
                <i class="fa-solid fa-arrow-right"></i>
              </button>
            </form>

            <div class="auth-divider">o</div>

            <a class="auth-card-alt-link" href="<?= BASE ?>/register.php">
              <i class="fa-regular fa-user-plus"></i>
              <span>Crear cuenta</span>
            </a>
          </div>

          <div class="auth-legal">
            Al iniciar sesión, aceptas nuestros
            <a href="#">Términos de servicio</a> y nuestra
            <a href="#">Política de privacidad</a>.
          </div>
        </section>

        <section class="auth-map-panel" aria-hidden="true">
          <div class="auth-map-fade"></div>
          <img src="<?= BASE ?>/assets/landing/landing-background.png" alt="">
        </section>
      </div>
    </section>
  </main>

  <script>
    document.querySelectorAll('[data-toggle-password]').forEach(function (toggle) {
      toggle.addEventListener('click', function () {
        var input = this.parentElement.querySelector('[data-password-input]');
        if (!input) return;
        var icon = this.querySelector('i');
        var nextType = input.type === 'password' ? 'text' : 'password';
        input.type = nextType;
        if (icon) {
          icon.className = nextType === 'password' ? 'fa-regular fa-eye' : 'fa-regular fa-eye-slash';
        }
      });
    });
  </script>
</body>
</html>
