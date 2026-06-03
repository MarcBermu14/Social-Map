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
  <!-- Font Awesome (local) -->
  <link rel="stylesheet" href="<?= BASE ?>/css/fontawesome.min.css">
  <!-- App CSS -->
  <link rel="stylesheet" href="<?= BASE ?>/css/style.css">
  <script>window.CL_BASE = '<?= BASE ?>';</script>
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

      <a href="<?= BASE ?>/dashboard.php"
         class="nav-item <?= $activePage === 'map' ? 'active' : '' ?>">
        <i class="nav-icon fa-solid fa-map"></i>
        <span>Mapa en vivo</span>
      </a>

      <a href="<?= BASE ?>/create.php"
         class="nav-item <?= $activePage === 'create' ? 'active' : '' ?>">
        <i class="nav-icon fa-solid fa-plus-circle"></i>
        <span>Crear publicación</span>
      </a>

      <a href="<?= BASE ?>/spin.php"
         class="nav-item <?= $activePage === 'spin' ? 'active' : '' ?>">
        <i class="nav-icon fa-solid fa-circle-dot"></i>
        <span>Ruleta</span>
        <span style="margin-left:auto;font-size:10px;font-weight:700;background:linear-gradient(135deg,#10b981,#059669);color:#fff;padding:2px 6px;border-radius:20px;">GRATIS</span>
      </a>

      <div class="nav-divider"></div>
      <div class="nav-section-label">Mi cuenta</div>

      <a href="<?= BASE ?>/tokens.php"
         class="nav-item <?= $activePage === 'tokens' ? 'active' : '' ?>">
        <i class="nav-icon fa-solid fa-hexagon-nodes"></i>
        <span>Mis tokens</span>
        <?php if ($user): ?>
          <span style="margin-left:auto;font-size:11px;font-weight:700;color:var(--primary);">
            <?= number_format($user['tokens_balance']) ?>
          </span>
        <?php endif; ?>
      </a>

      <a href="<?= BASE ?>/subscriptions.php"
         class="nav-item <?= $activePage === 'subs' ? 'active' : '' ?>">
        <i class="nav-icon fa-solid fa-crown"></i>
        <span>Suscripción</span>
        <span style="margin-left:auto;font-size:10px;font-weight:700;" class="badge badge-<?= $userPlan === 'platinum' ? 'purple' : ($userPlan === 'pro' ? 'primary' : 'gray') ?>">
          <?= $planLabel[$userPlan] ?>
        </span>
      </a>

      <a href="<?= BASE ?>/profile.php"
         class="nav-item <?= $activePage === 'profile' ? 'active' : '' ?>">
        <i class="nav-icon fa-solid fa-circle-user"></i>
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
          <span style="margin-left:auto;font-size:10px;font-weight:700;background:var(--red);color:#fff;padding:2px 7px;border-radius:20px;">
            <?= $pendingReports ?>
          </span>
        <?php endif; ?>
      </a>
      <?php endif; ?>

      <div class="nav-divider"></div>

      <a href="<?= BASE ?>/logout.php" class="nav-item" style="color:var(--red);">
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


      <div class="topbar-right">
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
