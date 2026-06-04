<?php
require_once __DIR__ . '/config/db.php';
requireLogin();

$db   = getDB();
$user = currentUser();
$id   = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    header('Location: ' . BASE . '/dashboard.php');
    exit;
}

$stmt = $db->prepare("SELECT * FROM publications WHERE id = ? AND status = 'active'");
$stmt->execute([$id]);
$pub = $stmt->fetch();

if (!$pub || (int)$pub['user_id'] !== (int)$user['id']) {
    header('Location: ' . BASE . '/dashboard.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title      = trim($_POST['title'] ?? '');
    $desc       = trim($_POST['description'] ?? '');
    $address    = trim($_POST['address'] ?? '');
    $lat        = (float)($_POST['lat'] ?? $pub['latitude']);
    $lng        = (float)($_POST['lng'] ?? $pub['longitude']);
    $category   = trim($_POST['category'] ?? '');
    $starts_at  = $_POST['starts_at'] ?? null;
    $expires_at = $_POST['expires_at'] ?? null;
    $min_att    = $_POST['min_attendees'] !== '' ? (int)$_POST['min_attendees'] : null;
    $max_att    = $_POST['max_attendees'] !== '' ? (int)$_POST['max_attendees'] : null;

    if (!$title) $errors[] = 'El título es obligatorio.';
    if (!$desc)  $errors[] = 'La descripción es obligatoria.';
    if ($min_att !== null && $max_att !== null && $min_att > $max_att) {
        $errors[] = 'El mínimo de asistentes no puede ser mayor que el máximo.';
    }
    if ($max_att !== null && $max_att < (int)$pub['attendees']) {
        $errors[] = "El máximo ({$max_att}) no puede ser inferior al número actual de asistentes ({$pub['attendees']}).";
    }
    if ($starts_at && $expires_at && strtotime($expires_at) <= strtotime($starts_at)) {
        $errors[] = 'La fecha de fin debe ser posterior a la fecha de inicio.';
    }

    if (empty($errors)) {
        $db->prepare("
            UPDATE publications
            SET title = ?, description = ?, address = ?, category = ?,
                latitude = ?, longitude = ?,
                min_attendees = ?, max_attendees = ?,
                starts_at = ?, expires_at = ?
            WHERE id = ? AND user_id = ?
        ")->execute([
            $title, $desc, $address, $category,
            $lat, $lng,
            $min_att, $max_att,
            $starts_at ?: null, $expires_at ?: null,
            $id, $user['id'],
        ]);
        header('Location: ' . BASE . "/activity.php?id=$id");
        exit;
    }

    $pub = array_merge($pub, [
        'title' => $title,
        'description' => $desc,
        'address' => $address,
        'category' => $category,
        'latitude' => $lat,
        'longitude' => $lng,
        'starts_at' => $starts_at,
        'expires_at' => $expires_at,
        'min_attendees' => $min_att !== null ? $min_att : '',
        'max_attendees' => $max_att !== null ? $max_att : '',
    ]);
}

$pageTitle   = 'Editar publicación';
$activePage  = '';
$bodyClass   = 'page-create';
$extraStyles = [BASE . '/css/create-refresh.css'];

$typeLabel = ['incident' => 'Incidencia', 'event' => 'Evento', 'activity' => 'Actividad'];
$typeIcon  = ['incident' => 'fa-solid fa-triangle-exclamation', 'event' => 'fa-solid fa-calendar-days', 'activity' => 'fa-solid fa-bolt'];

include __DIR__ . '/includes/header.php';
?>

<div class="create-layout" style="max-width:920px;">
  <div class="page-header">
    <div>
      <h1><i class="<?= $typeIcon[$pub['type']] ?>" style="color:var(--red);margin-right:10px;"></i>Editar <?= $typeLabel[$pub['type']] ?></h1>
      <p>Modifica los detalles de tu publicación.</p>
    </div>
  </div>

  <?php foreach ($errors as $e): ?>
    <div class="flash flash-error" style="margin-bottom:16px;"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($e) ?></div>
  <?php endforeach; ?>

  <div class="card" style="padding:24px;">
    <form method="POST" action="">

      <div class="form-group">
        <label class="form-label" for="title">Título *</label>
        <input class="form-input" type="text" id="title" name="title"
               value="<?= htmlspecialchars($pub['title']) ?>"
               placeholder="Dale un nombre claro y descriptivo" required>
      </div>

      <div class="form-group">
        <label class="form-label" for="description">Descripción *</label>
        <textarea class="form-textarea" id="description" name="description"
                  placeholder="Describe qué está pasando..." required><?= htmlspecialchars($pub['description'] ?? '') ?></textarea>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
        <div class="form-group">
          <label class="form-label" for="category">Categoría</label>
          <select class="form-select" id="category" name="category">
            <option value="">Sin categoría</option>
            <?php foreach (['Arte y Cultura', 'Música', 'Gastronomía', 'Compras', 'Deporte', 'Tráfico', 'Obras', 'Avería', 'Cultura', 'Otros'] as $cat): ?>
              <option value="<?= $cat ?>" <?= $pub['category'] === $cat ? 'selected' : '' ?>>
                <?= $cat ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label" for="address">Dirección</label>
          <div style="display:flex;gap:8px;">
            <input class="form-input" type="text" id="address" name="address"
                   value="<?= htmlspecialchars($pub['address'] ?? '') ?>"
                   placeholder="Calle, plaza, barrio...">
            <button type="button" id="geocodeBtn" class="btn btn-outline" style="white-space:nowrap;flex-shrink:0;">
              <i class="fa-solid fa-location-crosshairs"></i> Ubicar
            </button>
          </div>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
        <div class="form-group">
          <label class="form-label" for="starts_at">Inicio</label>
          <input class="form-input" type="datetime-local" id="starts_at" name="starts_at"
                 value="<?= htmlspecialchars($pub['starts_at'] ? (new DateTime($pub['starts_at']))->format('Y-m-d\TH:i') : '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label" for="expires_at">Fin / Expiración</label>
          <input class="form-input" type="datetime-local" id="expires_at" name="expires_at"
                 value="<?= htmlspecialchars($pub['expires_at'] ? (new DateTime($pub['expires_at']))->format('Y-m-d\TH:i') : '') ?>">
        </div>
      </div>

      <?php if ($pub['type'] === 'event'): ?>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
        <div class="form-group">
          <label class="form-label" for="min_attendees">Mínimo de asistentes</label>
          <input class="form-input" type="number" id="min_attendees" name="min_attendees"
                 min="1" placeholder="Sin mínimo"
                 value="<?= htmlspecialchars($pub['min_attendees'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label" for="max_attendees">Máximo de asistentes</label>
          <input class="form-input" type="number" id="max_attendees" name="max_attendees"
                 min="<?= (int)$pub['attendees'] ?: 1 ?>"
                 placeholder="Sin límite"
                 value="<?= htmlspecialchars($pub['max_attendees'] ?? '') ?>">
          <?php if ($pub['attendees'] > 0): ?>
            <div class="form-helper">Actualmente hay <?= $pub['attendees'] ?> asistente(s) apuntado(s).</div>
          <?php endif; ?>
        </div>
      </div>
      <?php else: ?>
        <input type="hidden" name="min_attendees" value="">
        <input type="hidden" name="max_attendees" value="">
      <?php endif; ?>

      <div class="form-group">
        <label class="form-label">Ubicación en el mapa</label>
        <div id="pickMap" style="height:250px;border-radius:18px;overflow:hidden;border:1px solid var(--border);"></div>
        <div class="form-helper">Escribe la dirección y pulsa "Ubicar", o haz clic directamente en el mapa.</div>
        <input type="hidden" name="lat" id="latInput" value="<?= htmlspecialchars($pub['latitude']) ?>">
        <input type="hidden" name="lng" id="lngInput" value="<?= htmlspecialchars($pub['longitude']) ?>">
      </div>

      <div style="display:flex;gap:12px;">
        <a href="<?= BASE ?>/activity.php?id=<?= $id ?>" class="btn btn-outline" style="flex:1;">Cancelar</a>
        <button class="btn btn-primary" type="submit" style="flex:2;">
          <i class="fa-solid fa-floppy-disk"></i> Guardar cambios
        </button>
      </div>

    </form>
  </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const pickMap = L.map('pickMap');
L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
  subdomains: 'abcd', maxZoom: 19
}).addTo(pickMap);

const defaultLat = parseFloat(document.getElementById('latInput').value);
const defaultLng = parseFloat(document.getElementById('lngInput').value);
pickMap.setView([defaultLat, defaultLng], 16);

let pickMarker = L.marker([defaultLat, defaultLng], { draggable: true }).addTo(pickMap);

function updateCoords(latlng) {
  document.getElementById('latInput').value = latlng.lat.toFixed(6);
  document.getElementById('lngInput').value = latlng.lng.toFixed(6);
}

pickMap.on('click', e => {
  pickMarker.setLatLng(e.latlng);
  updateCoords(e.latlng);
});
pickMarker.on('dragend', e => updateCoords(e.target.getLatLng()));

async function geocodeAddress() {
  const addr = document.getElementById('address').value.trim();
  if (!addr) return;
  const btn = document.getElementById('geocodeBtn');
  btn.disabled = true;
  btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
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
  btn.innerHTML = '<i class="fa-solid fa-location-crosshairs"></i> Ubicar';
}

document.getElementById('geocodeBtn').addEventListener('click', geocodeAddress);
document.getElementById('address').addEventListener('keydown', e => {
  if (e.key === 'Enter') {
    e.preventDefault();
    geocodeAddress();
  }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
