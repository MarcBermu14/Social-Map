<?php
require_once __DIR__ . '/config/db.php';
requireLogin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { header('Location: ' . BASE . '/dashboard.php'); exit; }

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
if (!$pub || $pub['status'] !== 'active') { header('Location: ' . BASE . '/dashboard.php'); exit; }

// Increment view count
$db->prepare('UPDATE publications SET views = views + 1 WHERE id = ?')->execute([$id]);

// Check if current user is registered to this event
$isRegistered = false;
if ($pub['type'] === 'event') {
    $chk = $db->prepare('SELECT 1 FROM event_registrations WHERE user_id = ? AND publication_id = ?');
    $chk->execute([$_SESSION['user_id'], $id]);
    $isRegistered = (bool)$chk->fetchColumn();
}

// Check if saved
$chkSave = $db->prepare('SELECT 1 FROM saves WHERE user_id = ? AND publication_id = ?');
$chkSave->execute([$_SESSION['user_id'], $id]);
$isSaved = (bool)$chkSave->fetchColumn();

// Fetch reviews
$reviews = $db->prepare("
    SELECT r.*, u.username, u.full_name, u.avatar
    FROM reviews r JOIN users u ON u.id = r.user_id
    WHERE r.publication_id = ?
    ORDER BY r.created_at DESC
");
$reviews->execute([$id]);
$reviews = $reviews->fetchAll();

$typeIcon = ['incident' => 'fa-solid fa-triangle-exclamation', 'event' => 'fa-solid fa-calendar-days', 'activity' => 'fa-solid fa-bolt'];
$typeColor = ['incident' => 'var(--red)', 'event' => 'var(--yellow)', 'activity' => 'var(--primary)'];
$typeLabel = ['incident' => 'Incidencia', 'event' => 'Evento', 'activity' => 'Actividad'];
$planLabel = ['free' => 'Gratuita', 'pro' => 'Pro', 'platinum' => 'Platinum'];

$pageTitle  = htmlspecialchars($pub['title']);
$activePage = '';

$categoryIcon = [
    'Arte y Cultura' => 'fa-solid fa-palette', 'Música' => 'fa-solid fa-music', 'Gastronomía' => 'fa-solid fa-utensils',
    'Compras' => 'fa-solid fa-bag-shopping', 'Deporte' => 'fa-solid fa-dumbbell', 'Tráfico' => 'fa-solid fa-car-side',
    'Obras' => 'fa-solid fa-hammer', 'Avería' => 'fa-solid fa-screwdriver-wrench', 'Cultura' => 'fa-solid fa-landmark',
];
$iconClass = $categoryIcon[$pub['category']] ?? $typeIcon[$pub['type']] ?? 'fa-solid fa-location-dot';
$color = $typeColor[$pub['type']] ?? 'var(--primary)';

$isOwner = ((int)$_SESSION['user_id'] === (int)$pub['user_id']);
$saveChk = $db->prepare('SELECT 1 FROM saves WHERE user_id = ? AND publication_id = ?');
$saveChk->execute([$_SESSION['user_id'], $id]);
$isSaved = (bool)$saveChk->fetchColumn();

include __DIR__ . '/includes/header.php';
?>

    <div class="activity-layout">
      <!-- MAIN COLUMN -->
      <div>
        <div style="margin-bottom:16px;">
          <a href="<?= BASE ?>/dashboard.php" class="text-muted text-sm">
            ← Volver al mapa
          </a>
        </div>

        <!-- Hero -->
        <div class="activity-hero"><i class="<?= $iconClass ?>"></i></div>

        <!-- Category + title -->
        <div style="font-size:12px;font-weight:700;color:<?= $color ?>;text-transform:uppercase;letter-spacing:.08em;margin-bottom:8px;">
          <i class="<?= $typeIcon[$pub['type']] ?>" style="margin-right:6px;"></i><?= $typeLabel[$pub['type']] ?>
          <?php if ($pub['category']): ?> · <?= htmlspecialchars($pub['category']) ?><?php endif; ?>
        </div>

        <h1 style="font-size:26px;font-weight:900;margin-bottom:14px;line-height:1.2;">
          <?= htmlspecialchars($pub['title']) ?>
        </h1>

        <!-- Meta chips -->
        <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:20px;">
          <?php if ($pub['address']): ?>
            <div class="badge badge-gray"><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($pub['address']) ?></div>
          <?php endif; ?>
          <?php if ($pub['attendees'] > 0 || $pub['max_attendees']): ?>
            <div class="badge badge-gray">
              <i class="fa-solid fa-users"></i> <?= $pub['attendees'] ?> personas
              <?php if ($pub['max_attendees']): ?>
                / <?= $pub['max_attendees'] ?> máx
                <?php if ((int)$pub['attendees'] >= (int)$pub['max_attendees']): ?>
                  · <span style="color:var(--red);">Completo</span>
                <?php endif; ?>
              <?php endif; ?>
            </div>
          <?php endif; ?>
          <?php if ($pub['min_attendees'] && (int)$pub['attendees'] < (int)$pub['min_attendees']): ?>
            <div class="badge badge-yellow"><i class="fa-solid fa-triangle-exclamation"></i> Mínimo: <?= $pub['min_attendees'] ?> (faltan <?= $pub['min_attendees'] - $pub['attendees'] ?>)</div>
          <?php endif; ?>
          <?php if ($pub['starts_at']): ?>
            <div class="badge badge-gray"><i class="fa-regular fa-clock"></i> <?= (new DateTime($pub['starts_at']))->format('d M · H:i') ?></div>
          <?php endif; ?>
          <?php if ($pub['expires_at']): ?>
            <div class="badge badge-yellow"><i class="fa-solid fa-hourglass-end"></i> Hasta <?= (new DateTime($pub['expires_at']))->format('H:i') ?></div>
          <?php endif; ?>
          <div class="badge badge-gray"><i class="fa-regular fa-eye"></i> <?= $pub['views'] ?> vistas</div>
        </div>

        <!-- Description -->
        <div style="font-size:15px;color:var(--text2);line-height:1.7;margin-bottom:24px;">
          <?= nl2br(htmlspecialchars($pub['description'] ?? '')) ?>
        </div>

        <!-- Token cost box (if activity) -->
        <?php if ($pub['token_cost'] > 0): ?>
        <div class="token-estimate-box" style="margin-bottom:24px;">
          <div style="font-size:32px;color:var(--primary);"><i class="fa-solid fa-coins"></i></div>
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
          <div class="card-header"><span class="card-title"><i class="fa-solid fa-location-dot" style="color:var(--red);margin-right:8px;"></i>Ubicación</span></div>
          <div id="miniMap" style="height:220px;border-radius:10px;overflow:hidden;"></div>
          <div style="margin-top:12px;font-size:13px;color:var(--text2);">
            <?= htmlspecialchars($pub['address'] ?? '') ?>
          </div>
        </div>

        <!-- Forum access banner (only for events and activities) -->
        <?php if ($pub['type'] !== 'incident'): ?>
        <?php
        $forumCount = $db->prepare("SELECT COUNT(*) FROM event_forum_posts WHERE event_id = ? AND status = 'active'");
        $forumCount->execute([$id]);
        $fPostCount = (int)$forumCount->fetchColumn();
        ?>
        <div class="card mb-20" style="border: 1px solid var(--primary); background: linear-gradient(135deg,rgba(14,165,233,.06),var(--card));">
          <div style="display:flex;align-items:center;gap:14px;">
            <div style="font-size:28px;color:var(--red);"><i class="fa-solid fa-comments"></i></div>
            <div style="flex:1;">
              <div style="font-weight:800;font-size:15px;margin-bottom:3px;">Foro del evento</div>
              <div style="font-size:13px;color:var(--text2);">
                <?= $fPostCount ?> publicaci<?= $fPostCount === 1 ? 'ón' : 'ones' ?> · Participa, pregunta y comparte
              </div>
            </div>
            <a href="<?= BASE ?>/forum.php?event=<?= $pub['id'] ?>" class="btn btn-primary btn-sm">
              Abrir foro <i class="fa-solid fa-arrow-right"></i>
            </a>
          </div>
        </div>
        <?php endif; ?>

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
              <?= $isRegistered ? 'Desapuntarse' : 'Apuntarse al evento' ?>
            </button>
            <?php endif; ?>

            <a href="https://maps.google.com/?q=<?= $pub['latitude'] ?>,<?= $pub['longitude'] ?>" target="_blank"
               class="btn btn-<?= $pub['type'] === 'event' ? 'outline' : 'primary' ?> btn-block">
              <i class="fa-solid fa-diamond-turn-right"></i> Cómo llegar
            </a>
            <div style="display:flex;gap:8px;">
              <button id="shareBtn" class="btn btn-outline" style="flex:1;"
                      data-title="<?= htmlspecialchars($pub['title']) ?>">
                <i class="fa-solid fa-share-nodes"></i> Compartir
              </button>
              <button id="reportBtn"
                      class="btn btn-outline btn-icon"
                      title="<?= $isOwner ? 'No puedes reportar tu propia publicación' : 'Reportar publicación' ?>"
                      data-pub="<?= $id ?>"
                      <?= $isOwner ? 'disabled style="opacity:.4;cursor:not-allowed;"' : '' ?>>
                <i class="fa-solid fa-flag"></i>
              </button>
            </div>

            <?php if ($isOwner): ?>
            <a href="<?= BASE ?>/edit.php?id=<?= $pub['id'] ?>" class="btn btn-outline btn-block">
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
          <a href="<?= BASE ?>/profile.php?id=<?= $pub['user_id'] ?>" style="text-decoration:none;color:var(--text);">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px;">
              <div class="avatar avatar-md" style="color:#fff;">
                <?= strtoupper(substr($pub['creator_name'] ?? $pub['creator_username'], 0, 1)) ?>
              </div>
              <div style="flex:1;">
                <div style="font-size:15px;font-weight:700;">
                  <?= htmlspecialchars($pub['creator_name'] ?? $pub['creator_username']) ?>
                  <?php if ($pub['verified']): ?> <span style="color:var(--primary);"><i class="fa-solid fa-circle-check"></i></span><?php endif; ?>
                </div>
                <div style="font-size:12px;color:var(--text2);">@<?= htmlspecialchars($pub['creator_username']) ?></div>
              </div>
              <?php if ($pub['reputation'] > 0): ?>
                <div style="font-size:16px;font-weight:800;color:var(--yellow);">
                  <i class="fa-solid fa-star"></i> <?= number_format($pub['reputation'], 1) ?>
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

          <a href="<?= BASE ?>/profile.php?id=<?= $pub['user_id'] ?>" class="btn btn-outline btn-block btn-sm" style="margin-top:14px;">
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
              <span class="fw-bold" style="color:var(--primary);"><i class="fa-solid fa-coins"></i> <?= $pub['token_cost'] ?></span>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
    const miniMap = L.map('miniMap', { zoomControl: false, dragging: false, scrollWheelZoom: false });
    L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
      subdomains: 'abcd', maxZoom: 19
    }).addTo(miniMap);

    const latlng = [<?= $pub['latitude'] ?>, <?= $pub['longitude'] ?>];
    miniMap.setView(latlng, 16);

    const icon = L.divIcon({
      html: `<div style="width:38px;height:38px;border-radius:12px;background:<?= $color ?>;display:flex;align-items:center;justify-content:center;font-size:20px;border:2px solid rgba(255,255,255,.2);box-shadow:0 4px 14px rgba(0,0,0,.5);"><i class="<?= $iconClass ?>" style="color:#fff;"></i></div>`,
      className: '', iconSize: [38, 38], iconAnchor: [19, 19]
    });
    L.marker(latlng, { icon }).addTo(miniMap);
    </script>

    <!-- Register/unregister for events -->
    <?php if ($pub['type'] === 'event'): ?>
    <script>
    const CL_BASE = '<?= BASE ?>';
    const regBtn = document.getElementById('regBtn');
    if (regBtn) {
      regBtn.addEventListener('click', async function () {
        const registered = this.dataset.registered === '1';
        this.disabled = true;

        const res  = await fetch(CL_BASE + '/api/event_register.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: registered ? 'unregister' : 'register', pub_id: parseInt(this.dataset.pub) })
        });
        const data = await res.json();

        if (data.success) {
          const now = data.registered;
          this.dataset.registered = now ? '1' : '0';
          this.className = 'btn btn-block ' + (now ? 'btn-danger' : 'btn-primary');
          this.textContent = now ? 'Desapuntarse' : 'Apuntarse al evento';
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
    const CL_BASE = typeof CL_BASE !== 'undefined' ? CL_BASE : '<?= BASE ?>';
    const deleteBtn = document.getElementById('deleteBtn');
    if (deleteBtn) {
      deleteBtn.addEventListener('click', async function () {
        if (!confirm('¿Eliminar esta publicación? Esta acción no se puede deshacer.')) return;
        this.disabled = true;
        this.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Eliminando…';

        const res  = await fetch(CL_BASE + '/api/delete_publication.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ pub_id: parseInt(this.dataset.pub) })
        });
        const data = await res.json();
        if (data.success) {
          window.location.href = CL_BASE + '/dashboard.php';
        } else {
          alert(data.error || 'Error al eliminar');
          this.disabled = false;
          this.innerHTML = '<i class="fa-solid fa-trash"></i> Eliminar publicación';
        }
      });
    }
    </script>
    <?php endif; ?>

<!-- Report modal -->
<div id="reportModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:500;align-items:center;justify-content:center;padding:16px;">
  <div style="background:var(--card);border-radius:var(--r-lg);padding:28px;max-width:400px;width:100%;box-shadow:0 16px 48px rgba(0,0,0,.2);">
    <h3 style="font-size:17px;font-weight:800;margin-bottom:16px;"><i class="fa-solid fa-flag" style="color:var(--red);margin-right:8px;"></i>Reportar publicación</h3>
    <label class="form-label">Motivo</label>
    <select id="reportReason" class="form-select" style="margin-bottom:14px;">
      <option value="spam">Spam o publicidad</option>
      <option value="false_info">Información falsa</option>
      <option value="inappropriate">Contenido inapropiado</option>
      <option value="other">Otro</option>
    </select>
    <label class="form-label">Descripción adicional (opcional)</label>
    <textarea id="reportDesc" class="form-textarea" placeholder="Añade más detalles..." rows="3" maxlength="500" style="min-height:80px;margin-bottom:16px;"></textarea>
    <div style="display:flex;gap:8px;justify-content:flex-end;">
      <button id="reportCancel" class="btn btn-outline btn-sm">Cancelar</button>
      <button id="reportSubmit" class="btn btn-danger btn-sm">Enviar reporte</button>
    </div>
  </div>
</div>

<script>
const CL_BASE_ACT = '<?= BASE ?>';
const PUB_ID      = <?= $id ?>;

// ── Compartir ─────────────────────────────────────────
const shareBtn = document.getElementById('shareBtn');
if (shareBtn) {
  shareBtn.addEventListener('click', async function () {
    const url   = 'http://localhost' + CL_BASE_ACT + '/activity.php?id=' + PUB_ID;
    const title = this.dataset.title || document.title;
    if (navigator.share) {
      try { await navigator.share({ title, url }); } catch (_) {}
    } else {
      try {
        await navigator.clipboard.writeText(url);
        showToast('¡Enlace copiado al portapapeles!');
      } catch (_) {
        prompt('Copia este enlace:', url);
      }
    }
  });
}

// ── Reportar ──────────────────────────────────────────
const reportBtn    = document.getElementById('reportBtn');
const reportModal  = document.getElementById('reportModal');
const reportCancel = document.getElementById('reportCancel');
const reportSubmit = document.getElementById('reportSubmit');

if (reportBtn) {
  reportBtn.addEventListener('click', () => {
    reportModal.style.display = 'flex';
  });
}
reportCancel?.addEventListener('click', () => {
  reportModal.style.display = 'none';
  document.getElementById('reportDesc').value = '';
});
reportModal?.addEventListener('click', e => {
  if (e.target === reportModal) {
    reportModal.style.display = 'none';
  }
});
reportSubmit?.addEventListener('click', async function () {
  this.disabled = true;
  const res  = await fetch(CL_BASE_ACT + '/api/report_publication.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      pub_id: PUB_ID,
      reason: document.getElementById('reportReason').value,
      description: document.getElementById('reportDesc').value,
    })
  });
  const data = await res.json();
  reportModal.style.display = 'none';
  document.getElementById('reportDesc').value = '';
  showToast(data.success ? data.message : (data.error || 'Error al enviar'), data.success ? 'ok' : 'err');
  this.disabled = false;
});

// ── Toast ─────────────────────────────────────────────
function showToast(msg, type = 'ok') {
  const el = document.createElement('div');
  el.textContent = msg;
  el.style.cssText = `position:fixed;bottom:24px;left:50%;transform:translateX(-50%);
    background:${type === 'err' ? 'var(--red)' : 'var(--text)'};color:#fff;
    padding:10px 20px;border-radius:20px;font-size:13px;font-weight:600;
    z-index:9999;box-shadow:0 4px 20px rgba(0,0,0,.25);white-space:nowrap;`;
  document.body.appendChild(el);
  setTimeout(() => el.remove(), 3000);
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
