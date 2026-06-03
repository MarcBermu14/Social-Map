<?php
require_once __DIR__ . '/config/db.php';
requireLogin();

$db   = getDB();
$user = currentUser();

$errors = [];
$ok     = false;

// Token costs per type
const TOKEN_COSTS = [
    'incident' => 0,
    'event'    => 0,
    'activity' => 150,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $type         = $_POST['type']        ?? '';
    $title        = trim($_POST['title']  ?? '');
    $desc         = trim($_POST['description'] ?? '');
    $address      = trim($_POST['address'] ?? '');
    $lat          = (float)($_POST['lat'] ?? 0);
    $lng          = (float)($_POST['lng'] ?? 0);
    $category     = trim($_POST['category'] ?? '');
    $starts_at    = $_POST['starts_at']  ?? null;
    $expires_at   = $_POST['expires_at'] ?? null;
    $min_att      = (($_POST['min_attendees'] ?? '') !== '') ? (int)$_POST['min_attendees'] : null;
    $max_att      = (($_POST['max_attendees'] ?? '') !== '') ? (int)$_POST['max_attendees'] : null;

    if (!in_array($type, ['incident', 'event', 'activity'])) $errors[] = 'Tipo de publicación inválido.';
    if (!$title) $errors[] = 'El título es obligatorio.';
    if (!$desc)  $errors[] = 'La descripción es obligatoria.';
    if ($min_att !== null && $max_att !== null && $min_att > $max_att) $errors[] = 'El mínimo de asistentes no puede ser mayor que el máximo.';
    if ($starts_at && strtotime($starts_at) < time()) {
        $errors[] = 'La fecha de inicio no puede ser en el pasado.';
    }

    // Check tokens for activities
    $cost = TOKEN_COSTS[$type] ?? 0;
    if ($cost > 0 && $user['tokens_balance'] < $cost) {
        $errors[] = "No tienes suficientes tokens. Necesitas {$cost} ⬡ y tienes {$user['tokens_balance']} ⬡.";
    }

    if (empty($errors)) {
        // Default Barcelona coords if none provided
        if (!$lat || !$lng) { $lat = 41.3851; $lng = 2.1734; }

        $db->prepare("
            INSERT INTO publications (user_id, type, title, description, latitude, longitude, address, category, token_cost, min_attendees, max_attendees, starts_at, expires_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            $user['id'], $type, $title, $desc,
            $lat, $lng, $address, $category, $cost,
            $min_att, $max_att,
            $starts_at ?: null, $expires_at ?: null,
        ]);
        $newId = (int)$db->lastInsertId();

        // Deduct tokens
        if ($cost > 0) {
            $db->prepare('UPDATE users SET tokens_balance = tokens_balance - ? WHERE id = ?')->execute([$cost, $user['id']]);
            $db->prepare('INSERT INTO token_transactions (user_id, amount, type, description) VALUES (?, ?, "publication", ?)')->execute([
                $user['id'], -$cost, "Publicación: $title"
            ]);
        }

<<<<<<< HEAD
        redirectTo("activity.php?id=$newId");
=======
        header('Location: ' . BASE . "/activity.php?id=$newId");
        exit;
>>>>>>> main
    }
}

$pageTitle  = 'Crear publicación';
$activePage = 'create';

include __DIR__ . '/includes/header.php';
?>

<div class="create-layout">
  <div class="page-header">
    <h1>Nueva publicación</h1>
    <p>Comparte lo que está pasando en tu ciudad con la comunidad.</p>
  </div>

  <?php foreach ($errors as $e): ?>
    <div class="flash flash-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($e) ?></div>
  <?php endforeach; ?>

  <form method="POST" action="">
    <?= csrfInput() ?>

    <!-- Type selector -->
    <div style="font-size:12px;font-weight:700;color:var(--text2);text-transform:uppercase;letter-spacing:.06em;margin-bottom:10px;">
      Tipo de publicación
    </div>
    <div class="type-cards mb-24">
      <div class="type-card <?= ($_POST['type'] ?? 'incident') === 'incident' ? 'selected' : '' ?>"
           data-type="incident">
        <div class="tc-icon">🚨</div>
        <div class="tc-name">Incidencia</div>
        <div class="tc-desc">Tráfico, obras, accidentes, cortes de luz...</div>
        <div class="tc-cost">⬡ Gratis</div>
      </div>
      <div class="type-card <?= ($_POST['type'] ?? '') === 'event' ? 'selected' : '' ?>"
           data-type="event">
        <div class="tc-icon">🎉</div>
        <div class="tc-name">Evento</div>
        <div class="tc-desc">Conciertos, fiestas, actos culturales...</div>
        <div class="tc-cost">⬡ Gratis</div>
      </div>
      <div class="type-card <?= ($_POST['type'] ?? '') === 'activity' ? 'selected' : '' ?>"
           data-type="activity">
        <div class="tc-icon">⚡</div>
        <div class="tc-name">Actividad</div>
        <div class="tc-desc">Mercadillos, pop-ups, servicios de pago...</div>
        <div class="tc-cost">⬡ 150 tokens</div>
      </div>
    </div>
    <input type="hidden" name="type" id="typeInput" value="<?= htmlspecialchars($_POST['type'] ?? 'incident') ?>">

    <!-- Token estimate (activity only) -->
    <div class="token-estimate-box" id="tokenEstimate"
         style="<?= ($_POST['type'] ?? 'incident') !== 'activity' ? 'display:none;' : '' ?>">
      <div style="font-size:28px;">⬡</div>
      <div>
        <div style="font-size:11px;color:var(--text2);">Coste estimado</div>
        <div class="te-val">150 tokens</div>
        <div class="te-note">Basado en el tipo y alcance de la actividad</div>
      </div>
      <div style="text-align:right;flex-shrink:0;">
        <div style="font-size:11px;color:var(--text2);">Tu saldo</div>
        <div style="font-size:16px;font-weight:800;color:<?= $user['tokens_balance'] >= 150 ? 'var(--green)' : 'var(--red)' ?>;">
          <?= number_format($user['tokens_balance']) ?> ⬡
        </div>
        <?php if ($user['tokens_balance'] < 150): ?>
<<<<<<< HEAD
          <a href="<?= appUrl('tokens.php') ?>" style="font-size:11px;color:var(--primary);">+ Comprar tokens</a>
=======
          <a href="<?= BASE ?>/tokens.php" style="font-size:11px;color:var(--primary);">+ Comprar tokens</a>
>>>>>>> main
        <?php endif; ?>
      </div>
    </div>

    <!-- Form fields -->
    <div class="form-group">
      <label class="form-label" for="title">Título *</label>
      <input class="form-input" type="text" id="title" name="title"
             value="<?= htmlspecialchars($_POST['title'] ?? '') ?>"
             placeholder="Dale un nombre claro y descriptivo" required>
    </div>

    <div class="form-group">
      <label class="form-label" for="description">Descripción *</label>
      <textarea class="form-textarea" id="description" name="description"
                placeholder="Describe qué está pasando, cuándo y por qué es relevante..." required><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
      <div class="form-group">
        <label class="form-label" for="category">Categoría</label>
        <select class="form-select" id="category" name="category">
          <option value="">Sin categoría</option>
          <?php foreach (['Arte y Cultura','Música','Gastronomía','Compras','Deporte','Tráfico','Obras','Avería','Cultura','Otros'] as $cat): ?>
            <option value="<?= $cat ?>" <?= ($_POST['category'] ?? '') === $cat ? 'selected' : '' ?>>
              <?= $cat ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label" for="address">Dirección</label>
        <div style="display:flex;gap:8px;">
          <input class="form-input" type="text" id="address" name="address"
                 value="<?= htmlspecialchars($_POST['address'] ?? '') ?>"
                 placeholder="Calle, plaza, barrio...">
          <button type="button" id="geocodeBtn" class="btn btn-outline" style="white-space:nowrap;flex-shrink:0;">
            📍 Ubicar
          </button>
        </div>
      </div>
    </div>

    <?php $nowMin = date('Y-m-d\TH:i'); ?>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
      <div class="form-group">
        <label class="form-label" for="starts_at">Inicio</label>
        <input class="form-input" type="datetime-local" id="starts_at" name="starts_at"
               value="<?= htmlspecialchars($_POST['starts_at'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label class="form-label" for="expires_at">Fin / Expiración</label>
        <input class="form-input" type="datetime-local" id="expires_at" name="expires_at"
               value="<?= htmlspecialchars($_POST['expires_at'] ?? '') ?>">
      </div>
    </div>

    <!-- Min / max attendees (events only) -->
    <div id="attendeesRow" style="<?= ($_POST['type'] ?? 'incident') !== 'event' ? 'display:none;' : '' ?>">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
        <div class="form-group">
          <label class="form-label" for="min_attendees">Mínimo de asistentes</label>
          <input class="form-input" type="number" id="min_attendees" name="min_attendees"
                 min="1" placeholder="Sin mínimo"
                 value="<?= htmlspecialchars($_POST['min_attendees'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label" for="max_attendees">Máximo de asistentes</label>
          <input class="form-input" type="number" id="max_attendees" name="max_attendees"
                 min="1" placeholder="Sin límite"
                 value="<?= htmlspecialchars($_POST['max_attendees'] ?? '') ?>">
        </div>
      </div>
    </div>

    <!-- Location picker map -->
    <div class="form-group">
      <label class="form-label">Ubicación en el mapa</label>
      <div id="pickMap" style="height:250px;border-radius:12px;overflow:hidden;border:1px solid var(--border);"></div>
      <div class="form-helper">Escribe la dirección y pulsa "📍 Ubicar", o haz clic directamente en el mapa.</div>
      <input type="hidden" name="lat" id="latInput" value="<?= htmlspecialchars($_POST['lat'] ?? '41.3851') ?>">
      <input type="hidden" name="lng" id="lngInput" value="<?= htmlspecialchars($_POST['lng'] ?? '2.1734') ?>">
    </div>

    <!-- Plan check -->
    <?php if ($user['plan'] === 'free'): ?>
    <div class="card mb-16" style="border-color:rgba(255,179,0,.3);background:rgba(255,179,0,.05);">
      <div style="display:flex;align-items:center;gap:10px;">
        <span style="font-size:24px;">⚠️</span>
        <div>
          <div style="font-size:14px;font-weight:700;color:var(--yellow);">Plan Gratuito</div>
          <div style="font-size:13px;color:var(--text2);">
<<<<<<< HEAD
            Puedes publicar incidencias y eventos gratis. Para actividades lucrativas necesitas <a href="<?= appUrl('subscriptions.php') ?>">Pro o Platinum</a>.
=======
            Puedes publicar incidencias y eventos gratis. Para actividades lucrativas necesitas <a href="<?= BASE ?>/subscriptions.php">Pro o Platinum</a>.
>>>>>>> main
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <button class="btn btn-primary btn-block btn-lg" type="submit" id="submitBtn">
      Publicar incidencia — Gratis
    </button>
  </form>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
// ─── Location picker map ──────────────────────────────
const pickMap = L.map('pickMap');
L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
  subdomains: 'abcd', maxZoom: 19
}).addTo(pickMap);

const defaultLat = parseFloat(document.getElementById('latInput').value) || 41.3851;
const defaultLng = parseFloat(document.getElementById('lngInput').value) || 2.1734;
pickMap.setView([defaultLat, defaultLng], 14);

let pickMarker = L.marker([defaultLat, defaultLng], { draggable: true }).addTo(pickMap);

function updateCoords(latlng) {
  document.getElementById('latInput').value = latlng.lat.toFixed(6);
  document.getElementById('lngInput').value = latlng.lng.toFixed(6);
}

pickMap.on('click', e => {
  pickMarker.setLatLng(e.latlng);
  updateCoords(e.latlng);
});
pickMarker.on('dragend', e => {
  updateCoords(e.target.getLatLng());
});

// ─── Address geocoding ────────────────────────────────
async function geocodeAddress() {
  const addr = document.getElementById('address').value.trim();
  if (!addr) return;
  const btn = document.getElementById('geocodeBtn');
  btn.disabled = true;
  btn.textContent = '...';
  try {
    const res  = await fetch('https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' + encodeURIComponent(addr));
    const data = await res.json();
    if (data.length > 0) {
      const lat = parseFloat(data[0].lat);
      const lng = parseFloat(data[0].lon);
      pickMarker.setLatLng([lat, lng]);
      pickMap.setView([lat, lng], 16);
      updateCoords({ lat, lng });
    } else {
      alert('Dirección no encontrada. Intenta ser más específico.');
    }
  } catch (e) {
    alert('Error al buscar la dirección.');
  }
  btn.disabled = false;
  btn.textContent = '📍 Ubicar';
}

document.getElementById('geocodeBtn').addEventListener('click', geocodeAddress);
document.getElementById('address').addEventListener('keydown', e => {
  if (e.key === 'Enter') { e.preventDefault(); geocodeAddress(); }
});

// ─── Fecha mínima = ahora mismo ───────────────────────
const NOW_MIN = '<?= $nowMin ?>';
const startsInput  = document.getElementById('starts_at');
const expiresInput = document.getElementById('expires_at');

// Fecha mínima siempre activa para todos los tipos
startsInput.min  = NOW_MIN;
expiresInput.min = NOW_MIN;

function setDateConstraints(type) {
  startsInput.required = (type === 'event');
}

// ─── Type selector ────────────────────────────────────
document.querySelectorAll('.type-card[data-type]').forEach(card => {
  card.addEventListener('click', function () {
    const type = this.dataset.type;
    document.querySelectorAll('.type-card').forEach(c => c.classList.remove('selected'));
    this.classList.add('selected');
    document.getElementById('typeInput').value = type;
    setDateConstraints(type);

    const est    = document.getElementById('tokenEstimate');
    const btn    = document.getElementById('submitBtn');
    const attRow = document.getElementById('attendeesRow');
    if (type === 'activity') {
      est.style.display    = 'flex';
      btn.textContent      = 'Publicar actividad — 150 tokens';
      attRow.style.display = 'none';
    } else if (type === 'event') {
      est.style.display    = 'none';
      btn.textContent      = 'Publicar evento — Gratis';
      attRow.style.display = '';
    } else {
      est.style.display    = 'none';
      btn.textContent      = 'Publicar incidencia — Gratis';
      attRow.style.display = 'none';
    }
  });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>


