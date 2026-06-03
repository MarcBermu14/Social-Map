<?php
require_once __DIR__ . '/config/db.php';
requireLogin();

$pageTitle  = 'Mis Eventos';
$activePage = 'events';

$db   = getDB();
$user = currentUser();
$uid  = (int)$user['id'];

$now = new DateTime();

$stmt = $db->prepare("
    SELECT p.id, p.title, p.description, p.address, p.category,
           p.latitude, p.longitude, p.attendees, p.token_cost,
           p.starts_at, p.expires_at, p.created_at,
           u.username AS creator_username, u.full_name AS creator_name,
           er.created_at AS registered_at
    FROM event_registrations er
    JOIN publications p ON p.id = er.publication_id
    JOIN users u ON u.id = p.user_id
    WHERE er.user_id = ?
      AND p.status = 'active'
    ORDER BY p.starts_at ASC
");
$stmt->execute([$uid]);
$events = $stmt->fetchAll();

$categoryIcons = [
    'Arte y Cultura' => 'fa-solid fa-palette',
    'Música' => 'fa-solid fa-music',
    'Gastronomía' => 'fa-solid fa-utensils',
    'Compras' => 'fa-solid fa-bag-shopping',
    'Deporte' => 'fa-solid fa-dumbbell',
    'Tráfico' => 'fa-solid fa-car-side',
    'Obras' => 'fa-solid fa-hammer',
    'Avería' => 'fa-solid fa-screwdriver-wrench',
    'Cultura' => 'fa-solid fa-landmark',
];

$upcoming = [];
$past     = [];
foreach ($events as $ev) {
    $startsAt = $ev['starts_at'] ? new DateTime($ev['starts_at']) : null;
    $expiresAt = $ev['expires_at'] ? new DateTime($ev['expires_at']) : null;
    $ended = $expiresAt && $expiresAt < $now;
    if ($ended) {
        $past[] = $ev;
    } else {
        $upcoming[] = $ev;
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="page-content">

  <div class="page-header">
    <div>
      <h1 class="page-title">Mis eventos</h1>
      <p class="page-subtitle">Eventos a los que estás apuntado</p>
    </div>
    <a href="<?= BASE ?>/dashboard.php" class="btn btn-outline btn-sm">
      <i class="fa-solid fa-map"></i> Ver mapa
    </a>
  </div>

  <?php if (empty($upcoming) && empty($past)): ?>
    <div class="events-empty">
      <div style="font-size:56px;margin-bottom:16px;"><i class="fa-regular fa-calendar-check" style="color:var(--red);"></i></div>
      <div style="font-size:18px;font-weight:700;margin-bottom:8px;">Sin eventos registrados</div>
      <div style="font-size:14px;color:var(--text2);margin-bottom:24px;">
        Explora el mapa y apúntate a los eventos que te interesen.
      </div>
      <a href="<?= BASE ?>/dashboard.php" class="btn btn-primary">Explorar el mapa</a>
    </div>

  <?php else: ?>

    <?php if (!empty($upcoming)): ?>
    <div class="events-section-label">
      Próximos <span class="badge badge-primary" style="font-size:11px;"><?= count($upcoming) ?></span>
    </div>

    <div class="events-grid">
      <?php foreach ($upcoming as $ev):
        $iconClass = $categoryIcons[$ev['category']] ?? 'fa-solid fa-calendar-days';
        $startsAt  = $ev['starts_at'] ? new DateTime($ev['starts_at']) : null;
        $expiresAt = $ev['expires_at'] ? new DateTime($ev['expires_at']) : null;
        $started   = $startsAt && $startsAt <= $now;
        $startsTs  = $startsAt ? $startsAt->getTimestamp() * 1000 : null;
      ?>
      <div class="event-card" data-id="<?= $ev['id'] ?>">
        <div class="event-countdown <?= $started ? 'event-countdown--live' : '' ?>"
             data-starts="<?= $startsTs ?>">
          <?php if (!$startsAt): ?>
            <span class="countdown-label">Sin hora definida</span>
          <?php elseif ($started): ?>
            <span class="countdown-live-dot"></span>
            <span class="countdown-label">En curso</span>
          <?php else: ?>
            <div class="countdown-blocks">
              <div class="countdown-block"><span class="countdown-num" data-unit="d">--</span><span class="countdown-unit">días</span></div>
              <div class="countdown-sep">:</div>
              <div class="countdown-block"><span class="countdown-num" data-unit="h">--</span><span class="countdown-unit">horas</span></div>
              <div class="countdown-sep">:</div>
              <div class="countdown-block"><span class="countdown-num" data-unit="m">--</span><span class="countdown-unit">min</span></div>
              <div class="countdown-sep">:</div>
              <div class="countdown-block"><span class="countdown-num" data-unit="s">--</span><span class="countdown-unit">seg</span></div>
            </div>
          <?php endif; ?>
        </div>

        <div class="event-card-body">
          <div class="event-card-icon"><i class="<?= $iconClass ?>"></i></div>
          <div class="event-card-info">
            <a href="<?= BASE ?>/activity.php?id=<?= $ev['id'] ?>" class="event-card-title">
              <?= htmlspecialchars($ev['title']) ?>
            </a>

            <div class="event-card-meta">
              <?php if ($ev['address']): ?>
                <span><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($ev['address']) ?></span>
              <?php endif; ?>
              <?php if ($startsAt): ?>
                <span><i class="fa-regular fa-clock"></i> <?= $startsAt->format('d M · H:i') ?></span>
              <?php endif; ?>
              <?php if ($expiresAt): ?>
                <span><i class="fa-solid fa-hourglass-end"></i> Hasta <?= $expiresAt->format('H:i') ?></span>
              <?php endif; ?>
              <span><i class="fa-solid fa-users"></i> <?= $ev['attendees'] ?> asistentes</span>
            </div>
          </div>

          <button class="btn btn-danger btn-sm event-unreg-btn" data-pub="<?= $ev['id'] ?>" title="Desapuntarse">
            <i class="fa-solid fa-xmark"></i>
          </button>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($past)): ?>
    <div class="events-section-label" style="margin-top:32px;">
      Pasados <span class="badge badge-gray" style="font-size:11px;"><?= count($past) ?></span>
    </div>

    <div class="events-grid events-grid--past">
      <?php foreach ($past as $ev):
        $iconClass = $categoryIcons[$ev['category']] ?? 'fa-solid fa-calendar-days';
        $startsAt = $ev['starts_at'] ? new DateTime($ev['starts_at']) : null;
      ?>
      <div class="event-card event-card--past" data-id="<?= $ev['id'] ?>">
        <div class="event-countdown event-countdown--ended">
          <span class="countdown-label">Finalizado</span>
        </div>
        <div class="event-card-body">
          <div class="event-card-icon" style="opacity:.5;"><i class="<?= $iconClass ?>"></i></div>
          <div class="event-card-info">
            <a href="<?= BASE ?>/activity.php?id=<?= $ev['id'] ?>" class="event-card-title">
              <?= htmlspecialchars($ev['title']) ?>
            </a>
            <div class="event-card-meta">
              <?php if ($ev['address']): ?>
                <span><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($ev['address']) ?></span>
              <?php endif; ?>
              <?php if ($startsAt): ?>
                <span><i class="fa-regular fa-clock"></i> <?= $startsAt->format('d M · H:i') ?></span>
              <?php endif; ?>
            </div>
          </div>
          <button class="btn btn-outline btn-sm event-unreg-btn" data-pub="<?= $ev['id'] ?>" title="Quitar del historial">
            <i class="fa-solid fa-xmark"></i>
          </button>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

  <?php endif; ?>
</div>

<script>
const CL_BASE = '<?= BASE ?>';

function pad(n) { return String(n).padStart(2, '0'); }

function tick() {
  const now = Date.now();
  document.querySelectorAll('.event-countdown[data-starts]').forEach(el => {
    const starts = parseInt(el.dataset.starts, 10);
    if (isNaN(starts)) return;
    const diff = starts - now;
    if (diff <= 0) {
      el.classList.add('event-countdown--live');
      el.innerHTML = '<span class="countdown-live-dot"></span><span class="countdown-label">En curso</span>';
      return;
    }
    const totalSec = Math.floor(diff / 1000);
    const d = Math.floor(totalSec / 86400);
    const h = Math.floor((totalSec % 86400) / 3600);
    const m = Math.floor((totalSec % 3600) / 60);
    const s = totalSec % 60;
    const dEl = el.querySelector('[data-unit="d"]');
    const hEl = el.querySelector('[data-unit="h"]');
    const mEl = el.querySelector('[data-unit="m"]');
    const sEl = el.querySelector('[data-unit="s"]');
    if (dEl) dEl.textContent = pad(d);
    if (hEl) hEl.textContent = pad(h);
    if (mEl) mEl.textContent = pad(m);
    if (sEl) sEl.textContent = pad(s);
  });
}
tick();
setInterval(tick, 1000);

document.querySelectorAll('.event-unreg-btn').forEach(btn => {
  btn.addEventListener('click', async function () {
    const pubId = this.dataset.pub;
    const card  = this.closest('.event-card');
    if (!confirm('¿Desapuntarte de este evento?')) return;
    this.disabled = true;

    const res  = await fetch(CL_BASE + '/api/event_register.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'unregister', pub_id: parseInt(pubId) })
    });
    const data = await res.json();
    if (data.success) {
      card.style.transition = 'opacity .3s, transform .3s';
      card.style.opacity    = '0';
      card.style.transform  = 'scale(.95)';
      setTimeout(() => {
        card.remove();
        if (!document.querySelector('.event-card')) location.reload();
      }, 300);
    } else {
      this.disabled = false;
    }
  });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
