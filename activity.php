<?php
require_once __DIR__ . '/config/db.php';
requireLogin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { header('Location: /citylive/dashboard.php'); exit; }

$db  = getDB();
$pub = $db->prepare("
    SELECT p.*, u.id AS user_id, u.username AS creator_username,
           u.full_name AS creator_name, u.reputation, u.rep_count,
           u.plan AS creator_plan, u.verified, u.bio AS creator_bio,
           (SELECT COUNT(*) FROM publications WHERE user_id = u.id) AS creator_pub_count
    FROM publications p
    JOIN users u ON u.id = p.user_id
    WHERE p.id = ?
");
$pub->execute([$id]);
$pub = $pub->fetch();
if (!$pub || $pub['status'] !== 'active') { header('Location: /citylive/dashboard.php'); exit; }

// Increment view count
$db->prepare('UPDATE publications SET views = views + 1 WHERE id = ?')->execute([$id]);

// Check if current user is registered to this event
$isRegistered = false;
if ($pub['type'] === 'event') {
    $chk = $db->prepare('SELECT 1 FROM event_registrations WHERE user_id = ? AND publication_id = ?');
    $chk->execute([$_SESSION['user_id'], $id]);
    $isRegistered = (bool)$chk->fetchColumn();
}

// Fetch reviews
$reviews = $db->prepare("
    SELECT r.*, u.username, u.full_name, u.avatar
    FROM reviews r JOIN users u ON u.id = r.user_id
    WHERE r.publication_id = ?
    ORDER BY r.created_at DESC
");
$reviews->execute([$id]);
$reviews = $reviews->fetchAll();

$typeEmoji = ['incident' => '🚨', 'event' => '🎉', 'activity' => '⚡'];
$typeColor = ['incident' => 'var(--red)', 'event' => 'var(--yellow)', 'activity' => 'var(--primary)'];
$typeLabel = ['incident' => 'Incidencia', 'event' => 'Evento', 'activity' => 'Actividad'];
$planLabel = ['free' => '🆓 Gratuita', 'pro' => '⭐ Pro', 'platinum' => '💎 Platinum'];

$pageTitle  = htmlspecialchars($pub['title']);
$activePage = '';

$categoryEmoji = [
    'Arte y Cultura' => '🎨', 'Música' => '🎵', 'Gastronomía' => '🍕',
    'Compras' => '🛍️', 'Deporte' => '🏃', 'Tráfico' => '🚗',
    'Obras' => '🚧', 'Avería' => '⚡', 'Cultura' => '🎭',
];
$emoji = $categoryEmoji[$pub['category']] ?? $typeEmoji[$pub['type']] ?? '📍';
$color = $typeColor[$pub['type']] ?? 'var(--primary)';

$isOwner = ((int)$_SESSION['user_id'] === (int)$pub['user_id']);

include __DIR__ . '/includes/header.php';
?>

    <div class="activity-layout">
      <!-- MAIN COLUMN -->
      <div>
        <div style="margin-bottom:16px;">
          <a href="/citylive/dashboard.php" class="text-muted text-sm">
            ← Volver al mapa
          </a>
        </div>

        <!-- Hero -->
        <div class="activity-hero"><?= $emoji ?></div>

        <!-- Category + title -->
        <div style="font-size:12px;font-weight:700;color:<?= $color ?>;text-transform:uppercase;letter-spacing:.08em;margin-bottom:8px;">
          <?= $typeEmoji[$pub['type']] ?> <?= $typeLabel[$pub['type']] ?>
          <?php if ($pub['category']): ?> · <?= htmlspecialchars($pub['category']) ?><?php endif; ?>
        </div>

        <h1 style="font-size:26px;font-weight:900;margin-bottom:14px;line-height:1.2;">
          <?= htmlspecialchars($pub['title']) ?>
        </h1>

        <!-- Meta chips -->
        <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:20px;">
          <?php if ($pub['address']): ?>
            <div class="badge badge-gray">📍 <?= htmlspecialchars($pub['address']) ?></div>
          <?php endif; ?>
          <?php if ($pub['attendees'] > 0 || $pub['max_attendees']): ?>
            <div class="badge badge-gray">
              👥 <?= $pub['attendees'] ?> personas
              <?php if ($pub['max_attendees']): ?>
                / <?= $pub['max_attendees'] ?> máx
                <?php if ((int)$pub['attendees'] >= (int)$pub['max_attendees']): ?>
                  · <span style="color:var(--red);">Completo</span>
                <?php endif; ?>
              <?php endif; ?>
            </div>
          <?php endif; ?>
          <?php if ($pub['min_attendees'] && (int)$pub['attendees'] < (int)$pub['min_attendees']): ?>
            <div class="badge badge-yellow">⚠ Mínimo: <?= $pub['min_attendees'] ?> (faltan <?= $pub['min_attendees'] - $pub['attendees'] ?>)</div>
          <?php endif; ?>
          <?php if ($pub['starts_at']): ?>
            <div class="badge badge-gray">🕐 <?= (new DateTime($pub['starts_at']))->format('d M · H:i') ?></div>
          <?php endif; ?>
          <?php if ($pub['expires_at']): ?>
            <div class="badge badge-yellow">⏱ Hasta <?= (new DateTime($pub['expires_at']))->format('H:i') ?></div>
          <?php endif; ?>
          <div class="badge badge-gray">👁 <?= $pub['views'] ?> vistas</div>
        </div>

        <!-- Description -->
        <div style="font-size:15px;color:var(--text2);line-height:1.7;margin-bottom:24px;">
          <?= nl2br(htmlspecialchars($pub['description'] ?? '')) ?>
        </div>

        <!-- Token cost box (if activity) -->
        <?php if ($pub['token_cost'] > 0): ?>
        <div class="token-estimate-box" style="margin-bottom:24px;">
          <div style="font-size:32px;">⬡</div>
          <div>
            <div style="font-size:11px;color:var(--text2);margin-bottom:3px;">Coste de publicación (actividad lucrativa)</div>
            <div class="te-val"><?= $pub['token_cost'] ?> tokens</div>
            <div class="te-note">Basado en el alcance y potencial comercial de la actividad</div>
          </div>
          <span class="badge badge-primary" style="margin-left:auto;">Lucrativa</span>
        </div>
        <?php endif; ?>

        <!-- Map mini -->
        <div class="card mb-20">
          <div class="card-header"><span class="card-title">📍 Ubicación</span></div>
          <div id="miniMap" style="height:220px;border-radius:10px;overflow:hidden;"></div>
          <div style="margin-top:12px;font-size:13px;color:var(--text2);">
            <?= htmlspecialchars($pub['address'] ?? '') ?>
          </div>
        </div>

        <!-- Reviews -->
        <div class="card">
          <div class="card-header">
            <span class="card-title">Valoraciones <span style="color:var(--text3);font-weight:400;">(<?= count($reviews) ?>)</span></span>
            <div style="display:flex;align-items:center;gap:6px;">
              <span style="color:var(--yellow);font-size:14px;">★★★★★</span>
              <span style="font-size:13px;font-weight:700;">4.8</span>
            </div>
          </div>

          <?php if (empty($reviews)): ?>
            <p class="text-muted text-sm">Todavía no hay valoraciones.</p>
          <?php else: ?>
            <?php foreach ($reviews as $r): ?>
            <div class="review-item">
              <div class="avatar avatar-sm" style="color:#fff;">
                <?= strtoupper(substr($r['full_name'] ?? $r['username'], 0, 1)) ?>
              </div>
              <div class="review-body">
                <div class="review-header">
                  <span class="review-name"><?= htmlspecialchars($r['full_name'] ?? $r['username']) ?></span>
                  <span class="review-stars"><?= str_repeat('★', $r['rating']) . str_repeat('☆', 5 - $r['rating']) ?></span>
                  <span class="review-time"><?= (new DateTime($r['created_at']))->format('d M') ?></span>
                </div>
                <div class="review-text"><?= htmlspecialchars($r['comment'] ?? '') ?></div>
              </div>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <!-- SIDEBAR COLUMN -->
      <div>
        <!-- Action buttons -->
        <div class="card mb-16" style="position:sticky;top:calc(var(--topbar-h) + 16px);">
          <div style="display:flex;flex-direction:column;gap:10px;">

            <?php if ($pub['type'] === 'event'): ?>
            <button id="regBtn"
                    class="btn btn-block <?= $isRegistered ? 'btn-danger' : 'btn-primary' ?>"
                    data-pub="<?= $pub['id'] ?>"
                    data-registered="<?= $isRegistered ? '1' : '0' ?>">
              <?= $isRegistered ? '✗ Desapuntarse' : '🎟️ Apuntarse al evento' ?>
            </button>
            <?php endif; ?>

            <a href="https://maps.google.com/?q=<?= $pub['latitude'] ?>,<?= $pub['longitude'] ?>" target="_blank"
               class="btn btn-<?= $pub['type'] === 'event' ? 'outline' : 'primary' ?> btn-block">
              🧭 Cómo llegar
            </a>
            <div style="display:flex;gap:8px;">
              <button class="btn btn-outline" style="flex:1;">🔖 Guardar</button>
              <button class="btn btn-outline" style="flex:1;">📤 Compartir</button>
            </div>
            <button class="btn btn-danger btn-sm">🚩 Reportar</button>

            <?php if ($isOwner): ?>
            <a href="/citylive/edit.php?id=<?= $pub['id'] ?>" class="btn btn-outline btn-block">
              <i class="fa-solid fa-pen"></i> Editar publicación
            </a>
            <button id="deleteBtn" class="btn btn-danger btn-block" data-pub="<?= $pub['id'] ?>">
              <i class="fa-solid fa-trash"></i> Eliminar publicación
            </button>
            <?php endif; ?>

          </div>
        </div>

        <!-- Creator card -->
        <div class="card mb-16">
          <div class="card-header"><span class="card-title">Publicado por</span></div>
          <a href="/citylive/profile.php?id=<?= $pub['user_id'] ?>" style="text-decoration:none;color:var(--text);">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px;">
              <div class="avatar avatar-md" style="color:#fff;">
                <?= strtoupper(substr($pub['creator_name'] ?? $pub['creator_username'], 0, 1)) ?>
              </div>
              <div style="flex:1;">
                <div style="font-size:15px;font-weight:700;">
                  <?= htmlspecialchars($pub['creator_name'] ?? $pub['creator_username']) ?>
                  <?php if ($pub['verified']): ?> <span style="color:var(--primary);">✓</span><?php endif; ?>
                </div>
                <div style="font-size:12px;color:var(--text2);">@<?= htmlspecialchars($pub['creator_username']) ?></div>
              </div>
              <?php if ($pub['reputation'] > 0): ?>
                <div style="font-size:16px;font-weight:800;color:var(--yellow);">
                  ⭐ <?= number_format($pub['reputation'], 1) ?>
                </div>
              <?php endif; ?>
            </div>

            <?php if ($pub['creator_bio']): ?>
              <p style="font-size:13px;color:var(--text2);line-height:1.5;margin-bottom:12px;">
                <?= htmlspecialchars($pub['creator_bio']) ?>
              </p>
            <?php endif; ?>

            <div style="display:flex;gap:12px;margin-bottom:12px;">
              <div style="text-align:center;">
                <div style="font-size:18px;font-weight:800;"><?= $pub['creator_pub_count'] ?></div>
                <div style="font-size:11px;color:var(--text3);">Publicaciones</div>
              </div>
            </div>

            <div class="rep-bar">
              <div class="rep-bar-fill" style="width:<?= min(100, ($pub['reputation'] / 5) * 100) ?>%;"></div>
            </div>
            <div style="display:flex;justify-content:space-between;margin-top:5px;font-size:11px;color:var(--text3);">
              <span>Credibilidad</span>
              <span><?= $planLabel[$pub['creator_plan']] ?></span>
            </div>
          </a>

          <a href="/citylive/profile.php?id=<?= $pub['user_id'] ?>" class="btn btn-outline btn-block btn-sm" style="margin-top:14px;">
            Ver perfil completo
          </a>
        </div>

        <!-- Stats -->
        <div class="card">
          <div class="card-title" style="margin-bottom:12px;">Estadísticas</div>
          <div style="display:flex;flex-direction:column;gap:10px;">
            <div style="display:flex;justify-content:space-between;font-size:13px;">
              <span class="text-muted">Publicado</span>
              <span class="fw-bold"><?= (new DateTime($pub['created_at']))->format('d M Y') ?></span>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:13px;">
              <span class="text-muted">Vistas</span>
              <span class="fw-bold"><?= $pub['views'] ?></span>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:13px;">
              <span class="text-muted">Asistentes</span>
              <span class="fw-bold"><?= $pub['attendees'] ?></span>
            </div>
            <?php if ($pub['token_cost'] > 0): ?>
            <div style="display:flex;justify-content:space-between;font-size:13px;">
              <span class="text-muted">Coste tokens</span>
              <span class="fw-bold" style="color:var(--primary);">⬡ <?= $pub['token_cost'] ?></span>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
    const miniMap = L.map('miniMap', { zoomControl: false, dragging: false, scrollWheelZoom: false });
    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
      subdomains: 'abcd', maxZoom: 19
    }).addTo(miniMap);

    const latlng = [<?= $pub['latitude'] ?>, <?= $pub['longitude'] ?>];
    miniMap.setView(latlng, 16);

    const icon = L.divIcon({
      html: `<div style="width:38px;height:38px;border-radius:12px;background:<?= $color ?>;display:flex;align-items:center;justify-content:center;font-size:20px;border:2px solid rgba(255,255,255,.2);box-shadow:0 4px 14px rgba(0,0,0,.5);"><?= $emoji ?></div>`,
      className: '', iconSize: [38, 38], iconAnchor: [19, 19]
    });
    L.marker(latlng, { icon }).addTo(miniMap);
    </script>

    <!-- Register/unregister for events -->
    <?php if ($pub['type'] === 'event'): ?>
    <script>
    const regBtn = document.getElementById('regBtn');
    if (regBtn) {
      regBtn.addEventListener('click', async function () {
        const registered = this.dataset.registered === '1';
        this.disabled = true;

        const res  = await fetch('/citylive/api/event_register.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: registered ? 'unregister' : 'register', pub_id: parseInt(this.dataset.pub) })
        });
        const data = await res.json();

        if (data.success) {
          const now = data.registered;
          this.dataset.registered = now ? '1' : '0';
          this.className = 'btn btn-block ' + (now ? 'btn-danger' : 'btn-primary');
          this.textContent = now ? '✗ Desapuntarse' : '🎟️ Apuntarse al evento';
        } else {
          alert(data.error || 'Error al procesar la solicitud');
        }
        this.disabled = false;
      });
    }
    </script>
    <?php endif; ?>

    <!-- Delete publication (owner only) -->
    <?php if ($isOwner): ?>
    <script>
    const deleteBtn = document.getElementById('deleteBtn');
    if (deleteBtn) {
      deleteBtn.addEventListener('click', async function () {
        if (!confirm('¿Eliminar esta publicación? Esta acción no se puede deshacer.')) return;
        this.disabled = true;
        this.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Eliminando…';

        const res  = await fetch('/citylive/api/delete_publication.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ pub_id: parseInt(this.dataset.pub) })
        });
        const data = await res.json();
        if (data.success) {
          window.location.href = '/citylive/dashboard.php';
        } else {
          alert(data.error || 'Error al eliminar');
          this.disabled = false;
          this.innerHTML = '<i class="fa-solid fa-trash"></i> Eliminar publicación';
        }
      });
    }
    </script>
    <?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
