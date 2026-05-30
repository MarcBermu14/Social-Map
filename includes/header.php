<?php
// Expect $pageTitle and $activePage to be set before including this file
$pageTitle  = $pageTitle  ?? 'CityLive';
$activePage = $activePage ?? '';
$user = currentUser();

$planEmoji = ['free' => '🆓', 'pro' => '⭐', 'platinum' => '💎'];
$planLabel = ['free' => 'Gratuita', 'pro' => 'Pro', 'platinum' => 'Platinum'];
$userPlan  = $user['plan'] ?? 'free';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> — CityLive</title>

  <!-- Leaflet CSS -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <!-- App CSS -->
  <link rel="stylesheet" href="/css/style.css">
</head>
<body>
<div class="app-shell">

  <!-- ══ SIDEBAR ══════════════════════════════════════ -->
  <aside class="sidebar">
    <div class="sidebar-logo">
      <div class="logo-icon">🗺️</div>
      <div class="logo-text">City<span>Live</span></div>
    </div>

    <nav class="sidebar-nav">
      <div class="nav-section-label">Principal</div>

      <a href="/dashboard.php"
         class="nav-item <?= $activePage === 'map' ? 'active' : '' ?>">
        <i class="nav-icon fa-solid fa-map"></i>
        <span>Mapa en vivo</span>
      </a>

      <a href="/create.php"
         class="nav-item <?= $activePage === 'create' ? 'active' : '' ?>">
        <i class="nav-icon fa-solid fa-plus-circle"></i>
        <span>Crear publicación</span>
      </a>

      <a href="/spin.php"
         class="nav-item <?= $activePage === 'spin' ? 'active' : '' ?>">
        <i class="nav-icon fa-solid fa-circle-dot"></i>
        <span>Ruleta</span>
        <span style="margin-left:auto;font-size:10px;font-weight:700;background:linear-gradient(135deg,#10b981,#059669);color:#fff;padding:2px 6px;border-radius:20px;">GRATIS</span>
      </a>

      <div class="nav-divider"></div>
      <div class="nav-section-label">Mi cuenta</div>

      <a href="/tokens.php"
         class="nav-item <?= $activePage === 'tokens' ? 'active' : '' ?>">
        <i class="nav-icon fa-solid fa-hexagon-nodes"></i>
        <span>Mis tokens</span>
        <?php if ($user): ?>
          <span style="margin-left:auto;font-size:11px;font-weight:700;color:var(--primary);">
            <?= number_format($user['tokens_balance']) ?>
          </span>
        <?php endif; ?>
      </a>

      <a href="/subscriptions.php"
         class="nav-item <?= $activePage === 'subs' ? 'active' : '' ?>">
        <i class="nav-icon fa-solid fa-crown"></i>
        <span>Suscripción</span>
        <span style="margin-left:auto;font-size:10px;font-weight:700;" class="badge badge-<?= $userPlan === 'platinum' ? 'purple' : ($userPlan === 'pro' ? 'primary' : 'gray') ?>">
          <?= $planLabel[$userPlan] ?>
        </span>
      </a>

      <a href="/profile.php"
         class="nav-item <?= $activePage === 'profile' ? 'active' : '' ?>">
        <i class="nav-icon fa-solid fa-circle-user"></i>
        <span>Mi perfil</span>
      </a>

      <div class="nav-divider"></div>

      <a href="/logout.php" class="nav-item" style="color:var(--red);">
        <i class="nav-icon fa-solid fa-right-from-bracket"></i>
        <span>Cerrar sesión</span>
      </a>
    </nav>

    <!-- User info at bottom -->
    <?php if ($user): ?>
    <div class="sidebar-user">
      <div class="sidebar-avatar">
        <?php if ($user['avatar']): ?>
          <img src="<?= htmlspecialchars($user['avatar']) ?>" alt="">
        <?php else: ?>
          <?= strtoupper(substr($user['full_name'] ?? $user['username'], 0, 1)) ?>
        <?php endif; ?>
      </div>
      <div class="sidebar-user-info">
        <div class="sidebar-user-name"><?= htmlspecialchars($user['full_name'] ?? $user['username']) ?></div>
        <div class="sidebar-user-plan">
          <?= $planEmoji[$userPlan] ?> <?= $planLabel[$userPlan] ?>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </aside>

  <!-- ══ MAIN AREA ═════════════════════════════════════ -->
  <div class="main-area">

    <!-- Topbar -->
    <header class="topbar">
      <div class="topbar-left">
        <span class="topbar-title"><?= htmlspecialchars($pageTitle) ?></span>
      </div>

      <div class="topbar-search">
        <i class="fa-solid fa-magnifying-glass" style="color:var(--text3);font-size:13px;"></i>
        <input type="text" placeholder="Buscar actividades, zonas...">
      </div>

      <div class="topbar-right">
        <button class="topbar-icon-btn" title="Notificaciones">
          <i class="fa-solid fa-bell"></i>
          <span class="notif-dot"></span>
        </button>
        <?php if ($user): ?>
        <a href="/profile.php" class="topbar-user">
          <div class="topbar-user-av">
            <?php if ($user['avatar']): ?>
              <img src="<?= htmlspecialchars($user['avatar']) ?>" alt="">
            <?php else: ?>
              <?= strtoupper(substr($user['full_name'] ?? $user['username'], 0, 1)) ?>
            <?php endif; ?>
          </div>
          <span class="topbar-user-name"><?= htmlspecialchars($user['username']) ?></span>
        </a>
        <?php endif; ?>
      </div>
    </header>
