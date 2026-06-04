<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/email.php';
require_once 'config.php';

if (isLoggedIn()) {
    header('Location: ' . BASE . '/dashboard.php');
    exit;
}

$errors = [];
$success = false;
$email_sent = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username  = trim($_POST['username'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $fullName  = trim($_POST['full_name'] ?? '');
    $password  = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    if (!$username) $errors[] = 'El nombre de usuario es obligatorio.';
    if (strlen($username) < 3) $errors[] = 'El usuario debe tener al menos 3 caracteres.';
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) $errors[] = 'El usuario solo puede contener letras, números, guiones y guiones bajos.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email inválido.';
    if (strlen($password) < 6) $errors[] = 'La contraseña debe tener al menos 6 caracteres.';
    if ($password !== $password2) $errors[] = 'Las contraseñas no coinciden.';
    if (!$fullName) $errors[] = 'El nombre completo es obligatorio.';

    if (empty($errors)) {
        $db = getDB();
        $stmt = $db->prepare('SELECT id FROM users WHERE email = ? OR username = ?');
        $stmt->execute([$email, $username]);

        if ($stmt->fetch()) {
            $errors[] = 'El email o nombre de usuario ya está en uso. Por favor elige otro.';
        } else {
            try {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $verificationToken = generateVerificationToken();

                $db->prepare('
                    INSERT INTO users
                    (username, email, password_hash, full_name, verified, verification_token, token_created_at)
                    VALUES (?, ?, ?, ?, 0, ?, NOW())
                ')->execute([$username, $email, $hash, $fullName, $verificationToken]);

                $userId = (int)$db->lastInsertId();

                $db->prepare('INSERT INTO subscriptions (user_id, plan) VALUES (?, "free")')
                   ->execute([$userId]);

                if (sendVerificationEmail($email, $fullName, $verificationToken)) {
                    $success = true;
                    $email_sent = true;
                } else {
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
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Outfit:wght@500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE ?>/css/fontawesome.min.css">
  <link rel="stylesheet" href="<?= BASE ?>/css/auth-landing.css">
</head>
<body class="auth-screen auth-register">
  <main class="auth-shell-modern">
    <section class="auth-stage">
      <header class="auth-header-modern">
        <a href="<?= BASE ?>/landing.php" class="auth-brand-modern" aria-label="CityLive">
          <span class="auth-brand-mark"><span class="auth-brand-core"></span></span>
          <span class="auth-brand-text">City<span>Live</span></span>
        </a>

        <div class="auth-top-switch">
          <span>¿Ya tienes cuenta?</span>
          <a href="<?= BASE ?>/index.php">
            <span>Iniciar sesión</span>
            <i class="fa-solid fa-arrow-right"></i>
          </a>
        </div>
      </header>

      <div class="auth-layout-modern">
        <section class="auth-card-wrap">
          <div class="auth-card-modern">
            <div class="auth-card-accent" aria-hidden="true"><span></span><span></span><span></span></div>
            <h2>Crea tu cuenta</h2>
            <p class="auth-card-subtitle">
              Únete a CityLive y conecta con personas, eventos y lugares increíbles en Barcelona.
            </p>

            <?php if ($success && $email_sent): ?>
              <div class="auth-inline-success">
                <i class="fa-solid fa-circle-check"></i><strong>¡Cuenta creada!</strong> Revisa tu correo para confirmar tu email. Si no ves el mensaje, revisa la carpeta de spam.
              </div>
              <div class="auth-success-copy">
                <a class="auth-card-alt-link" href="<?= BASE ?>/index.php">
                  <i class="fa-solid fa-arrow-right"></i>
                  <span>Ir a iniciar sesión</span>
                </a>
              </div>
            <?php elseif ($success && !$email_sent): ?>
              <div class="auth-inline-warn">
                <i class="fa-solid fa-triangle-exclamation"></i><strong>Cuenta creada,</strong> pero no pudimos enviar el correo de confirmación. Contacta con soporte.
              </div>
            <?php else: ?>
              <?php foreach ($errors as $e): ?>
                <div class="auth-flash"><i class="fa-solid fa-circle-exclamation"></i><?= htmlspecialchars($e) ?></div>
              <?php endforeach; ?>

              <form method="POST" action="" class="auth-form-modern">
                <div class="auth-form-grid">
                  <div class="auth-form-group">
                    <div class="auth-input-shell">
                      <i class="fa-solid fa-user"></i>
                      <input type="text" id="full_name" name="full_name" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" placeholder="Nombre completo" required>
                    </div>
                  </div>

                  <div class="auth-form-group">
                    <div class="auth-input-shell">
                      <i class="fa-solid fa-user"></i>
                      <input type="text" id="username" name="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" placeholder="Usuario" required>
                    </div>
                  </div>

                  <div class="auth-form-group">
                    <div class="auth-input-shell">
                      <i class="fa-regular fa-envelope"></i>
                      <input type="email" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="Email" required>
                    </div>
                  </div>

                  <div class="auth-form-group">
                    <div class="auth-input-shell">
                      <i class="fa-regular fa-lock"></i>
                      <input type="password" id="password" name="password" placeholder="Contraseña" required data-password-input>
                      <button type="button" aria-label="Mostrar contraseña" data-toggle-password>
                        <i class="fa-regular fa-eye"></i>
                      </button>
                    </div>
                  </div>

                  <div class="auth-form-group">
                    <div class="auth-input-shell">
                      <i class="fa-regular fa-lock"></i>
                      <input type="password" id="password2" name="password2" placeholder="Confirmar contraseña" required data-password-input>
                      <button type="button" aria-label="Mostrar contraseña" data-toggle-password>
                        <i class="fa-regular fa-eye"></i>
                      </button>
                    </div>
                  </div>
                </div>

                <div class="auth-card-note">
                  <i class="fa-regular fa-heart"></i>
                  <div>
                    <strong>Es gratis y siempre será así.</strong>
                    <p>Crea tu cuenta para participar en la comunidad, descubrir eventos y conocer gente.</p>
                  </div>
                </div>

                <button class="auth-btn-primary" type="submit">
                  <span>Crear cuenta</span>
                  <i class="fa-solid fa-user-plus"></i>
                </button>
              </form>

              <div class="auth-divider">o</div>

              <a class="auth-btn-secondary" href="<?= BASE ?>/index.php">
                <span>Ya tengo cuenta, iniciar sesión</span>
                <i class="fa-solid fa-arrow-right"></i>
              </a>
            <?php endif; ?>
          </div>

          <div class="auth-legal auth-legal-left">
            <i class="fa-solid fa-shield-heart"></i>
            Tus datos están protegidos. Consulta nuestra <a href="#">Política de Privacidad</a>.
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
