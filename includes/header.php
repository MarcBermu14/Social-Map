<?php
header('Content-Type: text/html; charset=UTF-8');
// Expect $pageTitle and $activePage to be set before including this file
$pageTitle  = $pageTitle  ?? 'CityLive';
$activePage = $activePage ?? '';
$bodyClass  = $bodyClass  ?? '';
$extraStyles = $extraStyles ?? [];
$bodyClass  = trim('app-internal ' . $bodyClass);
$user = currentUser();

$planLabel = ['free' => 'Gratuita', 'pro' => 'Pro', 'platinum' => 'Platinum'];
$planIcon  = [
  'free' => 'fa-regular fa-compass',
  'pro' => 'fa-solid fa-bolt',
  'platinum' => 'fa-solid fa-crown'
];
$userPlan  = $user['plan'] ?? 'free';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> — CityLive</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Outfit:wght@600;700;800&display=swap" rel="stylesheet">

  <!-- Leaflet CSS -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <!-- Font Awesome (local) -->
  <link rel="stylesheet" href="<?= BASE ?>/css/fontawesome.min.css">
  <!-- App CSS -->
  <link rel="stylesheet" href="<?= BASE ?>/css/style.css">
  <link rel="stylesheet" href="<?= BASE ?>/css/internal-refresh.css">
  <link rel="stylesheet" href="<?= BASE ?>/css/dashboard-refresh.css">
  <?php foreach ($extraStyles as $styleHref): ?>
  <link rel="stylesheet" href="<?= htmlspecialchars($styleHref) ?>">
  <?php endforeach; ?>
  <script>window.CL_BASE = '<?= BASE ?>';</script>
</head>
<body class="<?= htmlspecialchars($bodyClass) ?>">
<div class="app-shell">

  <!-- SIDEBAR -->
  <aside class="sidebar">
    <div class="sidebar-logo">
      <div class="logo-mark"><span class="logo-core"></span></div>
      <div class="logo-text">City<span>Live</span></div>
    </div>

    <nav class="sidebar-nav">
      <div class="nav-section-label">Principal</div>

      <a href="<?= BASE ?>/dashboard.php"
         class="nav-item <?= $activePage === 'map' ? 'active' : '' ?>">
        <i class="nav-icon fa-solid fa-map-location-dot"></i>
        <span>Mapa en vivo</span>
      </a>

      <a href="<?= BASE ?>/create.php"
         class="nav-item <?= $activePage === 'create' ? 'active' : '' ?>">
        <i class="nav-icon fa-solid fa-square-plus"></i>
        <span>Crear publicación</span>
      </a>

      <a href="<?= BASE ?>/spin.php"
         class="nav-item <?= $activePage === 'spin' ? 'active' : '' ?>">
        <i class="nav-icon fa-solid fa-dice"></i>
        <span>Ruleta</span>
        <span class="nav-badge nav-badge-free">Gratis</span>
      </a>

      <div class="nav-divider"></div>
      <div class="nav-section-label">Mi cuenta</div>

      <a href="<?= BASE ?>/tokens.php"
         class="nav-item <?= $activePage === 'tokens' ? 'active' : '' ?>">
        <i class="nav-icon fa-solid fa-coins"></i>
        <span>Mis tokens</span>
        <?php if ($user): ?>
          <span class="nav-value">
            <?= number_format($user['tokens_balance']) ?>
          </span>
        <?php endif; ?>
      </a>

      <a href="<?= BASE ?>/subscriptions.php"
         class="nav-item <?= $activePage === 'subs' ? 'active' : '' ?>">
        <i class="nav-icon fa-solid fa-crown"></i>
        <span>Suscripción</span>
        <span class="nav-plan-chip badge badge-<?= $userPlan === 'platinum' ? 'purple' : ($userPlan === 'pro' ? 'primary' : 'gray') ?>">
          <?= $planLabel[$userPlan] ?>
        </span>
      </a>

      <a href="<?= BASE ?>/profile.php"
         class="nav-item <?= $activePage === 'profile' ? 'active' : '' ?>">
        <i class="nav-icon fa-solid fa-user-circle"></i>
        <span>Mi perfil</span>
      </a>

      <?php if ($userPlan === 'platinum'): ?>
      <div class="nav-divider"></div>
      <div class="nav-section-label">Admin</div>
      <?php
        $pendingReports = 0;
        try {
          $rStmt = getDB()->query("SELECT COUNT(*) FROM publication_reports WHERE status = 'pending'");
          $pendingReports = (int)$rStmt->fetchColumn();
        } catch (Exception $e) {}
      ?>
      <a href="<?= BASE ?>/reports.php"
         class="nav-item <?= $activePage === 'reports' ? 'active' : '' ?>">
        <i class="nav-icon fa-solid fa-flag"></i>
        <span>Reportes</span>
        <?php if ($pendingReports > 0): ?>
          <span class="nav-badge nav-badge-alert">
            <?= $pendingReports ?>
          </span>
        <?php endif; ?>
      </a>
      <?php endif; ?>

      <div class="nav-divider"></div>

      <a href="<?= BASE ?>/logout.php" class="nav-item nav-item-danger">
        <i class="nav-icon fa-solid fa-right-from-bracket"></i>
        <span>Cerrar sesión</span>
      </a>
    </nav>

    <?php if ($user): ?>
    <div class="sidebar-user">
      <div class="sidebar-avatar">
        <?= strtoupper(substr($user['full_name'] ?? $user['username'], 0, 1)) ?>
        <?php if ($user['avatar']): ?>
          <img src="<?= htmlspecialchars($user['avatar']) ?>" alt="" onerror="this.remove()">
        <?php endif; ?>
      </div>
      <div class="sidebar-user-info">
        <div class="sidebar-user-name"><?= htmlspecialchars($user['full_name'] ?? $user['username']) ?></div>
        <div class="sidebar-user-plan">
          <i class="<?= htmlspecialchars($planIcon[$userPlan] ?? $planIcon['free']) ?>"></i>
          <span><?= $planLabel[$userPlan] ?></span>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </aside>

  <!-- MAIN AREA -->
  <div class="main-area">

    <header class="topbar">
      <div class="topbar-left">
        <div class="topbar-copy">
          <span class="topbar-kicker">CityLive · Barcelona</span>
          <span class="topbar-title"><?= htmlspecialchars($pageTitle) ?></span>
        </div>
      </div>

      <div class="topbar-right">
        <?php if ($activePage === 'map'): ?>
        <a href="<?= BASE ?>/create.php" class="topbar-create-btn">
          <i class="fa-solid fa-plus"></i>
          <span>Crear publicación</span>
        </a>
        <?php endif; ?>

        <div class="notif-wrap">
          <button class="topbar-icon-btn" id="notifBtn" title="Notificaciones">
            <i class="fa-solid fa-bell"></i>
            <span class="notif-dot" id="notifDot"></span>
          </button>
          <div class="notif-dropdown" id="notifDropdown" aria-hidden="true">
            <div class="notif-header">
              <span>Notificaciones</span>
            </div>
            <div class="notif-list" id="notifList">
              <div class="notif-empty">Cargando...</div>
            </div>
          </div>
        </div>
        <?php if ($user): ?>
        <a href="<?= BASE ?>/profile.php" class="topbar-user">
          <div class="topbar-user-av">
            <?= strtoupper(substr($user['full_name'] ?? $user['username'], 0, 1)) ?>
            <?php if ($user['avatar']): ?>
              <img src="<?= htmlspecialchars($user['avatar']) ?>" alt="" onerror="this.remove()">
            <?php endif; ?>
          </div>
          <span class="topbar-user-name"><?= htmlspecialchars($user['username']) ?></span>
        </a>
        <?php endif; ?>
      </div>
    </header>
