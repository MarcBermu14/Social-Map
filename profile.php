<?php
require_once __DIR__ . '/config/db.php';
requireLogin();

$db      = getDB();
$me      = currentUser();
$viewId  = isset($_GET['id']) ? (int)$_GET['id'] : $me['id'];

$stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$viewId]);
$profile = $stmt->fetch();
if (!$profile) { header('Location: /citylive/dashboard.php'); exit; }

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
    header('Location: /citylive/profile.php?id=' . $viewId);
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

$typeEmoji = ['incident' => '🚨', 'event' => '🎉', 'activity' => '⚡'];
$typeColor = ['incident' => 'badge-red', 'event' => 'badge-yellow', 'activity' => 'badge-primary'];
$typeLabel = ['incident' => 'Incidencia', 'event' => 'Evento', 'activity' => 'Actividad'];
$planLabel = ['free' => '🆓 Gratuita', 'pro' => '⭐ Pro', 'platinum' => '💎 Platinum'];
$planColor = ['free' => 'badge-gray', 'pro' => 'badge-primary', 'platinum' => 'badge-purple'];

$categoryEmoji = [
    'Arte y Cultura' => '🎨', 'Música' => '🎵', 'Gastronomía' => '🍕',
    'Compras' => '🛍️', 'Deporte' => '🏃', 'Tráfico' => '🚗',
    'Obras' => '🚧', 'Avería' => '⚡', 'Cultura' => '🎭',
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
        <?= strtoupper(substr($profile['full_name'] ?? $profile['username'], 0, 1)) ?>
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
            <span style="color:var(--primary);font-size:18px;">✓</span>
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
    </div>

    <div style="display:flex;flex-direction:column;gap:8px;flex-shrink:0;align-items:flex-end;">
      <?php if ($isMe): ?>
        <a href="/citylive/subscriptions.php" class="btn btn-outline btn-sm">
          <i class="fa-solid fa-crown"></i> Gestionar plan
        </a>
        <a href="/citylive/create.php" class="btn btn-primary btn-sm">
          <i class="fa-solid fa-plus"></i> Nueva publicación
        </a>
      <?php else: ?>
        <form method="POST">
          <?php if ($isFollowing): ?>
            <button type="submit" name="unfollow" class="btn btn-outline btn-sm">Siguiendo ✓</button>
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
        <?= $profile['rep_count'] > 0 ? '⭐ ' . number_format($profile['reputation'], 1) : '—' ?>
      </div>
      <div class="profile-stat-lbl">Reputación</div>
    </div>
    <div class="profile-stat">
      <div class="profile-stat-val" style="color:var(--primary);">
        <?= number_format($profile['tokens_balance']) ?> ⬡
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
    🎟️ Eventos en los que me he apuntado
  </div>
  <?php if (empty($regEvents)): ?>
    <div class="card mb-24" style="text-align:center;padding:32px;color:var(--text3);">
      <div style="font-size:36px;margin-bottom:10px;">🎉</div>
      <div>Todavía no estás apuntado a ningún evento.</div>
      <a href="/citylive/dashboard.php" style="font-size:13px;color:var(--primary);margin-top:8px;display:inline-block;">Explorar eventos →</a>
    </div>
  <?php else: ?>
    <div class="reg-events-grid mb-24">
      <?php foreach ($regEvents as $ev): ?>
        <?php
          $evEmoji  = $categoryEmoji[$ev['category']] ?? '🎉';
          $tsMs     = $ev['starts_at'] ? (new DateTime($ev['starts_at']))->getTimestamp() * 1000 : null;
          $isPast   = $tsMs && $tsMs < time() * 1000;
        ?>
        <a href="/citylive/activity.php?id=<?= $ev['id'] ?>" class="reg-event-card">
          <div class="reg-event-top">
            <div class="reg-event-icon"><?= $evEmoji ?></div>
            <div style="flex:1;min-width:0;">
              <div class="reg-event-title"><?= htmlspecialchars($ev['title']) ?></div>
              <?php if ($ev['address']): ?>
                <div class="reg-event-addr">📍 <?= htmlspecialchars($ev['address']) ?></div>
              <?php endif; ?>
            </div>
            <?php if ($ev['attendees'] > 0): ?>
              <span class="badge badge-gray" style="flex-shrink:0;">👥 <?= $ev['attendees'] ?></span>
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
      <div style="font-size:40px;margin-bottom:12px;">📭</div>
      <div>Todavía no hay publicaciones.</div>
    </div>
  <?php else: ?>
    <div class="pub-grid">
      <?php foreach ($pubs as $p): ?>
        <?php
          $emoji = $categoryEmoji[$p['category']] ?? $typeEmoji[$p['type']] ?? '📍';
          $badgeCls = $typeColor[$p['type']] ?? 'badge-gray';
        ?>
        <a href="/citylive/activity.php?id=<?= $p['id'] ?>" class="pub-item" style="flex-direction:column;align-items:flex-start;gap:10px;">
          <div style="display:flex;align-items:center;gap:10px;width:100%;">
            <div class="pub-icon <?= $p['type'] ?>">
              <?= $emoji ?>
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
              <span class="badge badge-primary">⬡ <?= $p['token_cost'] ?></span>
            <?php endif; ?>
            <?php if ($p['attendees'] > 0): ?>
              <span class="badge badge-gray">👥 <?= $p['attendees'] ?></span>
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
