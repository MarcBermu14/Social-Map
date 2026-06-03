<?php
require_once __DIR__ . '/config/db.php';
requireLogin();

$db      = getDB();
$me      = currentUser();
$viewId  = isset($_GET['id']) ? (int)$_GET['id'] : $me['id'];

$stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$viewId]);
$profile = $stmt->fetch();
if (!$profile) { header('Location: ' . BASE . '/dashboard.php'); exit; }

$isMe = ($profile['id'] === $me['id']);

// Stats
$pubCount = $db->prepare('SELECT COUNT(*) FROM publications WHERE user_id = ? AND status = "active"');
$pubCount->execute([$viewId]);
$pubCount = (int)$pubCount->fetchColumn();

$followerCount = $db->prepare('SELECT COUNT(*) FROM followers WHERE following_id = ?');
$followerCount->execute([$viewId]);
$followerCount = (int)$followerCount->fetchColumn();

$followingCount = $db->prepare('SELECT COUNT(*) FROM followers WHERE follower_id = ?');
$followingCount->execute([$viewId]);
$followingCount = (int)$followingCount->fetchColumn();

$isFollowing = false;
if (!$isMe) {
    $stmt2 = $db->prepare('SELECT 1 FROM followers WHERE follower_id = ? AND following_id = ?');
    $stmt2->execute([$me['id'], $viewId]);
    $isFollowing = (bool)$stmt2->fetchColumn();
}

// Handle follow / unfollow
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isMe) {
    if (isset($_POST['follow'])) {
        $db->prepare('INSERT IGNORE INTO followers (follower_id, following_id) VALUES (?,?)')->execute([$me['id'], $viewId]);
    } elseif (isset($_POST['unfollow'])) {
        $db->prepare('DELETE FROM followers WHERE follower_id=? AND following_id=?')->execute([$me['id'], $viewId]);
    }
    header('Location: ' . BASE . '/profile.php?id=' . $viewId);
    exit;
}

// Publications
$pubs = $db->prepare("
    SELECT * FROM publications WHERE user_id = ? AND status = 'active'
    ORDER BY created_at DESC LIMIT 30
");
$pubs->execute([$viewId]);
$pubs = $pubs->fetchAll();

// Registered events (own profile only)
$regEvents = [];
if ($isMe) {
    $stmt3 = $db->prepare("
        SELECT p.id, p.title, p.category, p.address, p.starts_at, p.attendees
        FROM event_registrations er
        JOIN publications p ON p.id = er.publication_id
        WHERE er.user_id = ? AND p.status = 'active' AND p.type = 'event'
        ORDER BY p.starts_at ASC
    ");
    $stmt3->execute([$me['id']]);
    $regEvents = $stmt3->fetchAll();
}

// Parse social_links
$profileSocial = [];
if (!empty($profile['social_links'])) {
    $decoded = json_decode($profile['social_links'], true);
    if (is_array($decoded)) $profileSocial = $decoded;
}

$typeIcon = ['incident' => 'fa-solid fa-triangle-exclamation', 'event' => 'fa-solid fa-calendar-days', 'activity' => 'fa-solid fa-bolt'];
$typeColor = ['incident' => 'badge-red', 'event' => 'badge-yellow', 'activity' => 'badge-primary'];
$typeLabel = ['incident' => 'Incidencia', 'event' => 'Evento', 'activity' => 'Actividad'];
$planLabel = ['free' => 'Gratuita', 'pro' => 'Pro', 'platinum' => 'Platinum'];
$planColor = ['free' => 'badge-gray', 'pro' => 'badge-primary', 'platinum' => 'badge-purple'];

$categoryIcon = [
    'Arte y Cultura' => 'fa-solid fa-palette', 'Música' => 'fa-solid fa-music', 'Gastronomía' => 'fa-solid fa-utensils',
    'Compras' => 'fa-solid fa-bag-shopping', 'Deporte' => 'fa-solid fa-dumbbell', 'Tráfico' => 'fa-solid fa-car-side',
    'Obras' => 'fa-solid fa-hammer', 'Avería' => 'fa-solid fa-screwdriver-wrench', 'Cultura' => 'fa-solid fa-landmark',
];

$pageTitle  = htmlspecialchars($profile['full_name'] ?? $profile['username']);
$activePage = $isMe ? 'profile' : '';

include __DIR__ . '/includes/header.php';
?>

<div class="profile-layout">

  <!-- Cover -->
  <div class="profile-cover">
    <div class="profile-avatar-wrap">
      <div class="avatar avatar-xl profile-avatar" style="color:#fff;font-size:40px;">
        <?php if ($profile['avatar']): ?>
          <img src="<?= htmlspecialchars($profile['avatar']) ?>" alt="Avatar"
               style="width:100%;height:100%;object-fit:cover;">
        <?php else: ?>
          <?= strtoupper(substr($profile['full_name'] ?? $profile['username'], 0, 1)) ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Profile info row -->
  <div class="profile-info-row">
    <div style="flex:1;">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px;">
        <h1 style="font-size:22px;font-weight:800;">
          <?= htmlspecialchars($profile['full_name'] ?? $profile['username']) ?>
          <?php if ($profile['verified']): ?>
            <span style="color:var(--primary);font-size:18px;"><i class="fa-solid fa-circle-check"></i></span>
          <?php endif; ?>
        </h1>
        <span class="badge <?= $planColor[$profile['plan']] ?>">
          <?= $planLabel[$profile['plan']] ?>
        </span>
      </div>
      <div style="font-size:13px;color:var(--text2);margin-bottom:8px;">
        @<?= htmlspecialchars($profile['username']) ?>
      </div>
      <?php if ($profile['bio']): ?>
        <p style="font-size:14px;color:var(--text2);line-height:1.6;max-width:500px;">
          <?= htmlspecialchars($profile['bio']) ?>
        </p>
      <?php endif; ?>
      <?php if ($profileSocial): ?>
        <?php
          $socialMeta = [
              'twitter'   => ['fa-brands fa-x-twitter', fn($v) => 'https://x.com/' . ltrim($v, '@')],
              'instagram' => ['fa-brands fa-instagram',  fn($v) => 'https://instagram.com/' . ltrim($v, '@')],
              'tiktok'    => ['fa-brands fa-tiktok',     fn($v) => 'https://tiktok.com/@' . ltrim($v, '@')],
              'facebook'  => ['fa-brands fa-facebook',   fn($v) => str_starts_with($v, 'http') ? $v : 'https://facebook.com/' . $v],
              'website'   => ['fa-solid fa-globe',       fn($v) => $v],
          ];
          $socialLabels = ['twitter' => 'Twitter', 'instagram' => 'Instagram',
                           'tiktok'  => 'TikTok',  'facebook'  => 'Facebook', 'website' => 'Web'];
        ?>
        <div class="ep-social-links">
          <?php foreach ($socialMeta as $net => [$icon, $urlFn]): ?>
            <?php if (!empty($profileSocial[$net])): ?>
              <a href="<?= htmlspecialchars($urlFn($profileSocial[$net])) ?>"
                 class="ep-social-link" target="_blank" rel="noopener noreferrer">
                <i class="<?= $icon ?>"></i>
                <?= $net === 'website' ? htmlspecialchars(parse_url($profileSocial[$net], PHP_URL_HOST) ?: $profileSocial[$net]) : htmlspecialchars($socialLabels[$net]) ?>
              </a>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div style="display:flex;flex-direction:column;gap:8px;flex-shrink:0;align-items:flex-end;">
      <?php if ($isMe): ?>
        <a href="<?= BASE ?>/edit_profile.php" class="btn btn-primary btn-sm">
          <i class="fa-solid fa-pen"></i> Editar perfil
        </a>
        <a href="<?= BASE ?>/subscriptions.php" class="btn btn-outline btn-sm">
          <i class="fa-solid fa-crown"></i> Gestionar plan
        </a>
        <a href="<?= BASE ?>/create.php" class="btn btn-outline btn-sm">
          <i class="fa-solid fa-plus"></i> Nueva publicación
        </a>
      <?php else: ?>
        <form method="POST">
          <?php if ($isFollowing): ?>
            <button type="submit" name="unfollow" class="btn btn-outline btn-sm"><i class="fa-solid fa-check"></i> Siguiendo</button>
          <?php else: ?>
            <button type="submit" name="follow" class="btn btn-primary btn-sm">+ Seguir</button>
          <?php endif; ?>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <!-- Stats -->
  <div class="profile-stats mb-24">
    <div class="profile-stat">
      <div class="profile-stat-val"><?= $pubCount ?></div>
      <div class="profile-stat-lbl">Publicaciones</div>
    </div>
    <div class="profile-stat">
      <div class="profile-stat-val"><?= number_format($followerCount) ?></div>
      <div class="profile-stat-lbl">Seguidores</div>
    </div>
    <div class="profile-stat">
      <div class="profile-stat-val"><?= $followingCount ?></div>
      <div class="profile-stat-lbl">Siguiendo</div>
    </div>
    <div class="profile-stat">
      <div class="profile-stat-val" style="color:var(--yellow);">
        <?= $profile['rep_count'] > 0 ? '<i class="fa-solid fa-star"></i> ' . number_format($profile['reputation'], 1) : '—' ?>
      </div>
      <div class="profile-stat-lbl">Reputación</div>
    </div>
    <div class="profile-stat">
      <div class="profile-stat-val" style="color:var(--primary);">
        <i class="fa-solid fa-coins"></i> <?= number_format($profile['tokens_balance']) ?>
      </div>
      <div class="profile-stat-lbl">Tokens</div>
    </div>
  </div>

  <!-- Reputation bar -->
  <?php if ($profile['rep_count'] > 0): ?>
  <div class="card mb-24">
    <div style="display:flex;justify-content:space-between;margin-bottom:8px;font-size:13px;">
      <span class="text-muted">Credibilidad</span>
      <span style="color:var(--green);font-weight:700;">
        <?= $profile['reputation'] >= 4.5 ? 'Experta verificada' : ($profile['reputation'] >= 3.5 ? 'Buena reputación' : 'En construcción') ?>
      </span>
    </div>
    <div class="rep-bar" style="height:7px;">
      <div class="rep-bar-fill" style="width:<?= min(100, ($profile['reputation'] / 5) * 100) ?>%;"></div>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($isMe): ?>
  <!-- Registered events section -->
  <div style="font-size:16px;font-weight:700;margin-bottom:14px;">
    Eventos en los que me he apuntado
  </div>
  <?php if (empty($regEvents)): ?>
    <div class="card mb-24" style="text-align:center;padding:32px;color:var(--text3);">
      <div style="font-size:36px;margin-bottom:10px;"><i class="fa-regular fa-calendar-check" style="color:var(--red);"></i></div>
      <div>Todavía no estás apuntado a ningún evento.</div>
      <a href="<?= BASE ?>/dashboard.php" style="font-size:13px;color:var(--primary);margin-top:8px;display:inline-block;">Explorar eventos <i class="fa-solid fa-arrow-right"></i></a>
    </div>
  <?php else: ?>
    <div class="reg-events-grid mb-24">
      <?php foreach ($regEvents as $ev): ?>
        <?php
          $evIcon  = $categoryIcon[$ev['category']] ?? 'fa-solid fa-calendar-days';
          $tsMs     = $ev['starts_at'] ? (new DateTime($ev['starts_at']))->getTimestamp() * 1000 : null;
          $isPast   = $tsMs && $tsMs < time() * 1000;
        ?>
        <a href="<?= BASE ?>/activity.php?id=<?= $ev['id'] ?>" class="reg-event-card">
          <div class="reg-event-top">
            <div class="reg-event-icon"><i class="<?= $evIcon ?>"></i></div>
            <div style="flex:1;min-width:0;">
              <div class="reg-event-title"><?= htmlspecialchars($ev['title']) ?></div>
              <?php if ($ev['address']): ?>
                <div class="reg-event-addr"><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($ev['address']) ?></div>
              <?php endif; ?>
            </div>
            <?php if ($ev['attendees'] > 0): ?>
              <span class="badge badge-gray" style="flex-shrink:0;"><i class="fa-solid fa-users"></i> <?= $ev['attendees'] ?></span>
            <?php endif; ?>
          </div>
          <?php if ($tsMs && !$isPast): ?>
            <div class="event-countdown" data-starts="<?= $tsMs ?>">
              <div class="countdown-blocks">
                <div class="countdown-block"><span class="countdown-num" data-unit="d">--</span><span class="countdown-unit">días</span></div>
                <div class="countdown-sep">:</div>
                <div class="countdown-block"><span class="countdown-num" data-unit="h">--</span><span class="countdown-unit">hrs</span></div>
                <div class="countdown-sep">:</div>
                <div class="countdown-block"><span class="countdown-num" data-unit="m">--</span><span class="countdown-unit">min</span></div>
                <div class="countdown-sep">:</div>
                <div class="countdown-block"><span class="countdown-num" data-unit="s">--</span><span class="countdown-unit">seg</span></div>
              </div>
              <div class="countdown-live-dot"></div>
            </div>
          <?php elseif ($tsMs && $isPast): ?>
            <div style="font-size:12px;color:var(--text3);padding:8px 0 4px;text-align:center;">Evento finalizado</div>
          <?php else: ?>
            <div style="font-size:12px;color:var(--text3);padding:8px 0 4px;text-align:center;">Fecha por confirmar</div>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <?php endif; ?>

  <!-- Publications grid -->
  <div style="font-size:16px;font-weight:700;margin-bottom:14px;">
    Publicaciones recientes
  </div>

  <?php if (empty($pubs)): ?>
    <div class="card" style="text-align:center;padding:40px;color:var(--text3);">
      <div style="font-size:40px;margin-bottom:12px;"><i class="fa-regular fa-folder-open" style="color:var(--red);"></i></div>
      <div>Todavía no hay publicaciones.</div>
    </div>
  <?php else: ?>
    <div class="pub-grid">
      <?php foreach ($pubs as $p): ?>
        <?php
          $iconClass = $categoryIcon[$p['category']] ?? $typeIcon[$p['type']] ?? 'fa-solid fa-location-dot';
          $badgeCls = $typeColor[$p['type']] ?? 'badge-gray';
        ?>
        <a href="<?= BASE ?>/activity.php?id=<?= $p['id'] ?>" class="pub-item" style="flex-direction:column;align-items:flex-start;gap:10px;">
          <div style="display:flex;align-items:center;gap:10px;width:100%;">
            <div class="pub-icon <?= $p['type'] ?>">
              <i class="<?= $iconClass ?>"></i>
            </div>
            <div style="flex:1;min-width:0;">
              <div class="pub-title"><?= htmlspecialchars($p['title']) ?></div>
              <div style="font-size:11px;color:var(--text3);margin-top:2px;">
                <?= (new DateTime($p['created_at']))->format('d M Y') ?>
              </div>
            </div>
          </div>
          <div style="display:flex;gap:6px;flex-wrap:wrap;">
            <span class="badge <?= $badgeCls ?>"><?= $typeLabel[$p['type']] ?></span>
            <?php if ($p['token_cost'] > 0): ?>
              <span class="badge badge-primary"><i class="fa-solid fa-coins"></i> <?= $p['token_cost'] ?></span>
            <?php endif; ?>
            <?php if ($p['attendees'] > 0): ?>
              <span class="badge badge-gray"><i class="fa-solid fa-users"></i> <?= $p['attendees'] ?></span>
            <?php endif; ?>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

</div>

<?php if ($isMe && !empty($regEvents)): ?>
<script>
(function () {
  function pad(n) { return String(n).padStart(2, '0'); }
  function tick() {
    document.querySelectorAll('.event-countdown[data-starts]').forEach(function (el) {
      var diff = parseInt(el.dataset.starts) - Date.now();
      if (diff <= 0) {
        el.innerHTML = '<div style="font-size:12px;color:var(--text3);text-align:center;">¡El evento ha comenzado!</div>';
        return;
      }
      var s = Math.floor(diff / 1000);
      var d = Math.floor(s / 86400); s %= 86400;
      var h = Math.floor(s / 3600);  s %= 3600;
      var m = Math.floor(s / 60);    s %= 60;
      el.querySelector('[data-unit="d"]').textContent = pad(d);
      el.querySelector('[data-unit="h"]').textContent = pad(h);
      el.querySelector('[data-unit="m"]').textContent = pad(m);
      el.querySelector('[data-unit="s"]').textContent = pad(s);
    });
  }
  tick();
  setInterval(tick, 1000);
})();
</script>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
