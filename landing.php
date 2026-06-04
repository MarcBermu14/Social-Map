<?php
require_once __DIR__ . '/config/db.php';
require_once 'config.php';

if (isLoggedIn()) {
    header('Location: ' . BASE . '/dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CityLive — Vive Barcelona en tiempo real</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Outfit:wght@500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE ?>/css/fontawesome.min.css">
  <link rel="stylesheet" href="<?= BASE ?>/css/landing.css">
</head>
<body class="landing-body">
  <main class="landing-shell">
    <section class="landing-hero">
      <div class="landing-backdrop"></div>
      <header class="landing-header">
        <a href="<?= BASE ?>/landing.php" class="landing-brand" aria-label="CityLive">
          <span class="landing-brand-mark">
            <span class="landing-brand-core"></span>
          </span>
          <span class="landing-brand-text">City<span>Live</span></span>
        </a>

        <nav class="landing-nav" aria-label="Principal">
          <a href="#como-funciona">Cómo funciona</a>
          <a href="#comunidades">Comunidades</a>
          <a href="#eventos">Eventos</a>
          <a href="#lugares">Lugares</a>
          <a href="#blog">Blog</a>
        </nav>

        <div class="landing-header-actions">
          <button class="landing-lang" type="button" aria-label="Idioma">
            <i class="fa-solid fa-globe"></i>
            <span>ES</span>
            <i class="fa-solid fa-chevron-down"></i>
          </button>
          <a class="landing-download" href="#descargar">
            <i class="fa-regular fa-mobile-screen-button"></i>
            <span>Descargar app</span>
          </a>
        </div>
      </header>

      <div class="landing-grid">
        <div class="landing-copy">
          <div class="landing-wordmark" aria-hidden="true">
            <span>City</span><span>Live</span>
          </div>

          <div class="landing-accent" aria-hidden="true">
            <span></span>
            <span></span>
            <span></span>
          </div>

          <h1>
            <span>Vive Barcelona.</span>
            <span>Conecta.</span>
            <span>En tiempo real.</span>
          </h1>

          <p>
            Descubre qué está pasando cerca de ti, conecta con personas increíbles y participa
            en lo que hace única a Barcelona.
          </p>

          <div class="landing-cta-row">
            <a class="landing-btn landing-btn-primary" href="<?= BASE ?>/register.php">
              <span>Crear cuenta</span>
              <i class="fa-regular fa-user-plus"></i>
            </a>
            <a class="landing-btn landing-btn-secondary" href="<?= BASE ?>/index.php">
              <span>Iniciar sesión</span>
              <i class="fa-solid fa-arrow-right"></i>
            </a>
          </div>

          <div class="landing-social-proof">
            <div class="landing-avatars" aria-hidden="true">
              <span class="avatar avatar-a">L</span>
              <span class="avatar avatar-b">M</span>
              <span class="avatar avatar-c">A</span>
              <span class="avatar avatar-d">J</span>
            </div>
            <div class="landing-proof-copy">
              <strong>+180K <i class="fa-solid fa-heart"></i></strong>
              <span>personas conectando<br>cada día en Barcelona</span>
            </div>
          </div>
        </div>

        <div class="landing-visual">
          <div class="landing-map-glow"></div>
          <div class="landing-scene">
            <img src="<?= BASE ?>/assets/landing/landing-background.png" alt="Ilustración de Barcelona" class="landing-scene-image">
          </div>
        </div>
      </div>
    </section>
  </main>
</body>
</html>
