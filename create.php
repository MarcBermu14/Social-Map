<?php
require_once __DIR__ . '/config/db.php';
requireLogin();

$db   = getDB();
$user = currentUser();

$errors = [];
$locationError = '';

function isValidCoordinate(?float $lat, ?float $lng): bool
{
    return $lat !== null && $lng !== null
        && $lat >= -90 && $lat <= 90
        && $lng >= -180 && $lng <= 180;
}

const TOKEN_COSTS = [
    'incident' => 0,
    'event'    => 0,
    'activity' => 150,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type        = $_POST['type'] ?? '';
    $title       = trim($_POST['title'] ?? '');
    $desc        = trim($_POST['description'] ?? '');
    $address     = trim($_POST['address'] ?? '');
    $lat         = (float)($_POST['lat'] ?? 0);
    $lng         = (float)($_POST['lng'] ?? 0);
    $category    = trim($_POST['category'] ?? '');
    $starts_at   = $_POST['starts_at'] ?? null;
    $expires_at  = $_POST['expires_at'] ?? null;
    $min_att     = (($_POST['min_attendees'] ?? '') !== '') ? (int)$_POST['min_attendees'] : null;
    $max_att     = (($_POST['max_attendees'] ?? '') !== '') ? (int)$_POST['max_attendees'] : null;
    $selectedAddress    = trim($_POST['selected_address'] ?? '');
    $selectedLat        = is_numeric($_POST['selected_lat'] ?? null) ? (float)$_POST['selected_lat'] : null;
    $selectedLng        = is_numeric($_POST['selected_lng'] ?? null) ? (float)$_POST['selected_lng'] : null;
    $isAddressConfirmed = ($_POST['is_address_confirmed'] ?? '') === '1';

    if (!in_array($type, ['incident', 'event', 'activity'], true)) $errors[] = 'Tipo de publicación inválido.';
    if (!$title) $errors[] = 'El título es obligatorio.';
    if (!$desc) $errors[] = 'La descripción es obligatoria.';
    if ($min_att !== null && $max_att !== null && $min_att > $max_att) $errors[] = 'El mínimo de asistentes no puede ser mayor que el máximo.';
    if ($starts_at && strtotime($starts_at) < time()) {
        $errors[] = 'La fecha de inicio no puede ser en el pasado.';
    }

    $cost = TOKEN_COSTS[$type] ?? 0;
    if ($cost > 0 && $user['tokens_balance'] < $cost) {
        $errors[] = "No tienes suficientes tokens. Necesitas {$cost} y tienes {$user['tokens_balance']}.";
    }

    if ($type === 'event') {
        $coordsMatchSelection = isValidCoordinate($selectedLat, $selectedLng)
            && abs($lat - $selectedLat) < 0.000001
            && abs($lng - $selectedLng) < 0.000001;

        if (!$address || !$selectedAddress || !$isAddressConfirmed || !isValidCoordinate($selectedLat, $selectedLng)) {
            $locationError = 'Selecciona una dirección válida de la lista antes de publicar el evento.';
            $errors[] = $locationError;
        } elseif ($address !== $selectedAddress || !$coordsMatchSelection) {
            $locationError = 'La dirección confirmada ya no coincide con el punto del mapa. Selecciónala de nuevo antes de publicar.';
            $errors[] = $locationError;
        }
    }

    if (empty($errors)) {
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

        if ($cost > 0) {
            $db->prepare('UPDATE users SET tokens_balance = tokens_balance - ? WHERE id = ?')->execute([$cost, $user['id']]);
            $db->prepare('INSERT INTO token_transactions (user_id, amount, type, description) VALUES (?, ?, "publication", ?)')->execute([
                $user['id'], -$cost, "Publicación: $title"
            ]);
        }

        header('Location: ' . BASE . "/activity.php?id=$newId");
        exit;
    }
}

$postedType = $_POST['type'] ?? 'incident';
$postedLocationError = $_SERVER['REQUEST_METHOD'] === 'POST' ? $locationError : '';
$postedSelectedAddress = $_POST['selected_address'] ?? '';
$postedSelectedLat = $_POST['selected_lat'] ?? '';
$postedSelectedLng = $_POST['selected_lng'] ?? '';
$postedIsAddressConfirmed = $_POST['is_address_confirmed'] ?? '0';
$postedCategory = $_POST['category'] ?? '';
$postedTitle = $_POST['title'] ?? '';
$postedDescription = $_POST['description'] ?? '';
$postedAddress = $_POST['address'] ?? '';
$postedStartsAt = $_POST['starts_at'] ?? '';
$postedExpiresAt = $_POST['expires_at'] ?? '';
$postedMinAttendees = $_POST['min_attendees'] ?? '';
$postedMaxAttendees = $_POST['max_attendees'] ?? '';
$titleLength = function_exists('mb_strlen') ? mb_strlen($postedTitle) : strlen($postedTitle);
$descriptionLength = function_exists('mb_strlen') ? mb_strlen($postedDescription) : strlen($postedDescription);

$typeCards = [
    'incident' => [
        'icon' => 'fa-solid fa-triangle-exclamation',
        'title' => 'Incidencia',
        'description' => 'Tráfico, obras, accidentes, cortes de luz y avisos rápidos.',
        'cost' => 'Gratis',
        'accent' => 'incident',
    ],
    'event' => [
        'icon' => 'fa-solid fa-calendar-days',
        'title' => 'Evento',
        'description' => 'Conciertos, quedadas, fiestas y planes culturales abiertos.',
        'cost' => 'Gratis',
        'accent' => 'event',
    ],
    'activity' => [
        'icon' => 'fa-solid fa-bolt',
        'title' => 'Actividad',
        'description' => 'Mercadillos, pop-ups y servicios de pago con visibilidad extra.',
        'cost' => '150 tokens',
        'accent' => 'activity',
    ],
];

$categoryMeta = [
    '' => ['label' => 'Sin categoría', 'icon' => 'fa-solid fa-layer-group'],
    'Arte y Cultura' => ['label' => 'Arte', 'icon' => 'fa-solid fa-palette'],
    'Música' => ['label' => 'Música', 'icon' => 'fa-solid fa-music'],
    'Gastronomía' => ['label' => 'Gastro', 'icon' => 'fa-solid fa-utensils'],
    'Compras' => ['label' => 'Compras', 'icon' => 'fa-solid fa-bag-shopping'],
    'Deporte' => ['label' => 'Deporte', 'icon' => 'fa-solid fa-basketball'],
    'Tráfico' => ['label' => 'Tráfico', 'icon' => 'fa-solid fa-car-side'],
    'Obras' => ['label' => 'Obras', 'icon' => 'fa-solid fa-helmet-safety'],
    'Avería' => ['label' => 'Servicios', 'icon' => 'fa-solid fa-screwdriver-wrench'],
    'Cultura' => ['label' => 'Cultura', 'icon' => 'fa-solid fa-masks-theater'],
    'Otros' => ['label' => 'Más', 'icon' => 'fa-solid fa-ellipsis'],
];

$pageTitle = 'Crear publicación';
$activePage = 'create';
$bodyClass = 'page-create';
$extraStyles = [BASE . '/css/create-refresh.css'];

include __DIR__ . '/includes/header.php';
?>

<div class="create-layout">
  <div class="create-page-header">
    <div class="create-page-title-mark">
      <i class="fa-solid fa-sparkles"></i>
    </div>
    <div>
      <h1>Crear publicación</h1>
      <p>Comparte lo que está pasando en tu ciudad con la comunidad.</p>
    </div>
  </div>

  <?php foreach ($errors as $e): ?>
    <div class="flash flash-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($e) ?></div>
  <?php endforeach; ?>

  <form method="POST" action="" id="createForm" class="create-grid">
    <section class="create-main-column">
      <div class="create-surface-card create-flow-card">
        <section class="create-section">
          <div class="create-section-header">
            <span class="create-step">1.</span>
            <div>
              <h2>¿Qué quieres publicar?</h2>
              <p>Elige el formato que mejor representa lo que vas a compartir.</p>
            </div>
          </div>

          <div class="create-type-grid">
            <?php foreach ($typeCards as $typeKey => $typeCard): ?>
            <button type="button"
                    class="type-card create-type-card <?= $postedType === $typeKey ? 'selected' : '' ?>"
                    data-type="<?= htmlspecialchars($typeKey) ?>">
              <span class="create-type-icon accent-<?= htmlspecialchars($typeCard['accent']) ?>">
                <i class="<?= htmlspecialchars($typeCard['icon']) ?>"></i>
              </span>
              <span class="create-type-copy">
                <span class="tc-name"><?= htmlspecialchars($typeCard['title']) ?></span>
                <span class="tc-desc"><?= htmlspecialchars($typeCard['description']) ?></span>
              </span>
              <span class="tc-cost"><?= htmlspecialchars($typeCard['cost']) ?></span>
            </button>
            <?php endforeach; ?>
          </div>
          <input type="hidden" name="type" id="typeInput" value="<?= htmlspecialchars($postedType) ?>">

          <div class="token-estimate-box create-token-banner" id="tokenEstimate" style="<?= $postedType !== 'activity' ? 'display:none;' : '' ?>">
            <div class="create-token-icon"><i class="fa-solid fa-coins"></i></div>
            <div>
              <div class="create-token-label">Coste estimado</div>
              <div class="te-val">150 tokens</div>
              <div class="te-note">Se descontarán al publicar la actividad.</div>
            </div>
            <div class="create-token-balance">
              <span>Tu saldo</span>
              <strong class="<?= $user['tokens_balance'] >= 150 ? 'is-ok' : 'is-low' ?>"><?= number_format($user['tokens_balance']) ?></strong>
              <?php if ($user['tokens_balance'] < 150): ?>
              <a href="<?= BASE ?>/tokens.php">Comprar tokens</a>
              <?php endif; ?>
            </div>
          </div>
        </section>

        <section class="create-section">
          <div class="create-section-header">
            <span class="create-step">2.</span>
            <div>
              <h2>Cuéntanos los detalles</h2>
              <p>Hazlo claro y directo para que la comunidad entienda rápido el contexto.</p>
            </div>
          </div>

          <div class="form-group">
            <div class="create-label-row">
              <label class="form-label" for="title">Título</label>
              <span class="create-counter" id="titleCount"><?= $titleLength ?>/80</span>
            </div>
            <input class="form-input create-input" type="text" id="title" name="title"
                   value="<?= htmlspecialchars($postedTitle) ?>"
                   placeholder="Dale un nombre claro y descriptivo" required>
          </div>

          <div class="form-group">
            <div class="create-label-row">
              <label class="form-label" for="description">Descripción</label>
              <span class="create-counter" id="descriptionCount"><?= $descriptionLength ?>/500</span>
            </div>
            <textarea class="form-textarea create-textarea" id="description" name="description"
                      placeholder="Describe qué está pasando, cuándo y por qué es relevante..." required><?= htmlspecialchars($postedDescription) ?></textarea>
          </div>
        </section>

        <section class="create-section">
          <div class="create-section-header">
            <span class="create-step">3.</span>
            <div>
              <h2>Categoría</h2>
              <p>Ayuda a que se encuentre mejor dentro del mapa y del feed.</p>
            </div>
          </div>

          <div class="create-category-grid" id="categoryGrid">
            <?php foreach ($categoryMeta as $categoryValue => $category): ?>
            <button type="button"
                    class="create-category-chip <?= $postedCategory === $categoryValue ? 'active' : '' ?>"
                    data-value="<?= htmlspecialchars($categoryValue) ?>">
              <i class="<?= htmlspecialchars($category['icon']) ?>"></i>
              <span><?= htmlspecialchars($category['label']) ?></span>
            </button>
            <?php endforeach; ?>
          </div>

          <select class="create-native-select" id="category" name="category">
            <?php foreach ($categoryMeta as $categoryValue => $category): ?>
            <option value="<?= htmlspecialchars($categoryValue) ?>" <?= $postedCategory === $categoryValue ? 'selected' : '' ?>>
              <?= htmlspecialchars($categoryValue === '' ? 'Sin categoría' : $categoryValue) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </section>

        <section class="create-section">
          <div class="create-section-header">
            <span class="create-step">4.</span>
            <div>
              <h2>Ubicación</h2>
              <p>Fija una dirección precisa y comprueba el punto exacto en el mapa.</p>
            </div>
          </div>

          <div class="create-location-grid">
            <div class="create-location-panel">
              <div class="form-group">
                <label class="form-label" for="address">Dirección</label>
                <div class="create-address-row">
                  <div class="create-address-input-wrap">
                    <i class="fa-solid fa-location-dot"></i>
                    <input class="form-input create-input create-address-input" type="text" id="address" name="address"
                           value="<?= htmlspecialchars($postedAddress) ?>"
                           placeholder="Busca una dirección o lugar">
                  </div>
                  <button type="button" id="geocodeBtn" class="create-locate-btn">
                    <i class="fa-solid fa-location-crosshairs"></i>
                    <span>Ubicar</span>
                  </button>
                </div>
                <input type="hidden" name="selected_address" id="selectedAddressInput" value="<?= htmlspecialchars($postedSelectedAddress) ?>">
                <input type="hidden" name="selected_lat" id="selectedLatInput" value="<?= htmlspecialchars($postedSelectedLat) ?>">
                <input type="hidden" name="selected_lng" id="selectedLngInput" value="<?= htmlspecialchars($postedSelectedLng) ?>">
                <input type="hidden" name="is_address_confirmed" id="isAddressConfirmedInput" value="<?= htmlspecialchars($postedIsAddressConfirmed) ?>">
                <div id="addressResults" class="create-address-results" style="display:none;"></div>
                <div id="addressMessage" class="form-helper create-address-message <?= $postedLocationError ? 'is-error' : '' ?>">
                  <?= htmlspecialchars($postedLocationError ?: 'Busca una dirección y selecciona una opción válida para fijar la ubicación exacta.') ?>
                </div>
              </div>
            </div>

            <div class="create-map-panel">
              <div class="create-map-header">
                <span>Vista previa del lugar</span>
                <span class="create-map-pill">Mapa real</span>
              </div>
              <div id="pickMap" class="create-pick-map"></div>
              <div class="create-map-helper">Puedes escribir la dirección, pulsar “Ubicar” o mover el marcador manualmente.</div>
              <input type="hidden" name="lat" id="latInput" value="<?= htmlspecialchars($_POST['lat'] ?? '41.3851') ?>">
              <input type="hidden" name="lng" id="lngInput" value="<?= htmlspecialchars($_POST['lng'] ?? '2.1734') ?>">
            </div>
          </div>
        </section>

        <section class="create-section">
          <div class="create-section-header">
            <span class="create-step">5.</span>
            <div>
              <h2>Fecha y hora</h2>
              <p>Programa cuándo empieza y cuándo deja de mostrarse, si aplica.</p>
            </div>
          </div>

          <div class="create-date-grid">
            <div class="form-group">
              <label class="form-label" for="starts_at">Inicio</label>
              <input class="form-input create-input" type="datetime-local" id="starts_at" name="starts_at"
                     value="<?= htmlspecialchars($postedStartsAt) ?>">
            </div>
            <div class="form-group">
              <label class="form-label" for="expires_at">Fin / Expiración</label>
              <input class="form-input create-input" type="datetime-local" id="expires_at" name="expires_at"
                     value="<?= htmlspecialchars($postedExpiresAt) ?>">
            </div>
          </div>

          <div id="attendeesRow" class="create-attendees-row" style="<?= $postedType !== 'event' ? 'display:none;' : '' ?>">
            <div class="create-date-grid">
              <div class="form-group">
                <label class="form-label" for="min_attendees">Mínimo de asistentes</label>
                <input class="form-input create-input" type="number" id="min_attendees" name="min_attendees"
                       min="1" placeholder="Sin mínimo"
                       value="<?= htmlspecialchars($postedMinAttendees) ?>">
              </div>
              <div class="form-group">
                <label class="form-label" for="max_attendees">Máximo de asistentes</label>
                <input class="form-input create-input" type="number" id="max_attendees" name="max_attendees"
                       min="1" placeholder="Sin límite"
                       value="<?= htmlspecialchars($postedMaxAttendees) ?>">
              </div>
            </div>
          </div>
        </section>
      </div>
    </section>

    <aside class="create-side-column">
      <div class="create-surface-card create-summary-card">
        <div class="create-summary-header">
          <div class="create-summary-mark"><i class="fa-solid fa-sparkles"></i></div>
          <h2>Resumen de tu publicación</h2>
        </div>

        <div class="create-summary-block">
          <span class="create-summary-label">Tipo de publicación</span>
          <div class="create-summary-type" id="summaryType">
            <span class="summary-type-icon accent-<?= htmlspecialchars($typeCards[$postedType]['accent']) ?>" id="summaryTypeIcon">
              <i class="<?= htmlspecialchars($typeCards[$postedType]['icon']) ?>"></i>
            </span>
            <div class="summary-type-copy">
              <strong id="summaryTypeLabel"><?= htmlspecialchars($typeCards[$postedType]['title']) ?></strong>
              <span id="summaryTypeCostBadge"><?= htmlspecialchars($typeCards[$postedType]['cost']) ?></span>
            </div>
          </div>
        </div>

        <div class="create-summary-block">
          <span class="create-summary-label">Ubicación</span>
          <div class="create-summary-text" id="summaryLocation"><?= htmlspecialchars($postedSelectedAddress ?: ($postedAddress ?: 'Añade una ubicación precisa')) ?></div>
        </div>

        <div class="create-summary-split">
          <div class="create-summary-block">
            <span class="create-summary-label">Fecha y hora</span>
            <div class="create-summary-text" id="summaryDateTime"><?= htmlspecialchars($postedStartsAt ?: 'Por definir') ?></div>
          </div>
          <div class="create-summary-block">
            <span class="create-summary-label">Coste estimado</span>
            <div class="create-summary-cost" id="summaryCost"><?= ($postedType === 'activity') ? '150 tokens' : 'Gratis' ?></div>
          </div>
        </div>

        <div class="create-summary-block">
          <span class="create-summary-label">Visibilidad</span>
          <div class="create-summary-visibility">
            <i class="fa-solid fa-lock-open"></i>
            <span>Tu publicación será visible para toda la comunidad.</span>
          </div>
        </div>

        <?php if ($user['plan'] === 'free'): ?>
        <div class="create-plan-note">
          <i class="fa-solid fa-circle-info"></i>
          <div>
            <strong>Tu plan actual es Gratuita.</strong>
            <span>Puedes seguir publicando incidencias y eventos gratis, y actividades si tienes tokens suficientes.</span>
          </div>
        </div>
        <?php endif; ?>

        <button class="create-publish-btn" type="submit" id="submitBtn">
          <i class="fa-solid fa-paper-plane"></i>
          <span id="submitBtnLabel"><?= $postedType === 'activity' ? 'Publicar actividad' : ($postedType === 'event' ? 'Publicar evento' : 'Publicar incidencia') ?></span>
        </button>
      </div>

      <div class="create-surface-card create-tips-card">
        <div class="create-tips-header">
          <i class="fa-solid fa-wand-magic-sparkles"></i>
          <h3>Consejos para una publicación clara</h3>
        </div>
        <ul class="create-tips-list">
          <li><i class="fa-solid fa-circle-check"></i><span>Usa un título breve y descriptivo.</span></li>
          <li><i class="fa-solid fa-circle-check"></i><span>Incluye detalles importantes en la descripción.</span></li>
          <li><i class="fa-solid fa-circle-check"></i><span>Selecciona la categoría correcta para aparecer en el filtro adecuado.</span></li>
          <li><i class="fa-solid fa-circle-check"></i><span>Añade una ubicación precisa para ayudar a la comunidad.</span></li>
        </ul>
      </div>
    </aside>
  </form>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const createForm = document.getElementById('createForm');
const typeInput = document.getElementById('typeInput');
const addressInput = document.getElementById('address');
const geocodeBtn = document.getElementById('geocodeBtn');
const latInput = document.getElementById('latInput');
const lngInput = document.getElementById('lngInput');
const selectedAddressInput = document.getElementById('selectedAddressInput');
const selectedLatInput = document.getElementById('selectedLatInput');
const selectedLngInput = document.getElementById('selectedLngInput');
const isAddressConfirmedInput = document.getElementById('isAddressConfirmedInput');
const addressResults = document.getElementById('addressResults');
const addressMessage = document.getElementById('addressMessage');
const categorySelect = document.getElementById('category');
const tokenEstimate = document.getElementById('tokenEstimate');
const attendeesRow = document.getElementById('attendeesRow');
const submitBtnLabel = document.getElementById('submitBtnLabel');
const summaryTypeLabel = document.getElementById('summaryTypeLabel');
const summaryTypeCostBadge = document.getElementById('summaryTypeCostBadge');
const summaryTypeIcon = document.getElementById('summaryTypeIcon');
const summaryLocation = document.getElementById('summaryLocation');
const summaryDateTime = document.getElementById('summaryDateTime');
const summaryCost = document.getElementById('summaryCost');
const titleInput = document.getElementById('title');
const descriptionInput = document.getElementById('description');
const titleCount = document.getElementById('titleCount');
const descriptionCount = document.getElementById('descriptionCount');
const startsInput = document.getElementById('starts_at');
const expiresInput = document.getElementById('expires_at');

const typeMeta = {
  incident: {
    label: 'Incidencia',
    cost: 'Gratis',
    costSummary: 'Gratis',
    icon: 'fa-solid fa-triangle-exclamation',
    accent: 'incident',
    submit: 'Publicar incidencia'
  },
  event: {
    label: 'Evento',
    cost: 'Gratis',
    costSummary: 'Gratis',
    icon: 'fa-solid fa-calendar-days',
    accent: 'event',
    submit: 'Publicar evento'
  },
  activity: {
    label: 'Actividad',
    cost: '150 tokens',
    costSummary: '150 tokens',
    icon: 'fa-solid fa-bolt',
    accent: 'activity',
    submit: 'Publicar actividad'
  }
};

function setCounter(input, counter, limit) {
  counter.textContent = `${input.value.length}/${limit}`;
}

setCounter(titleInput, titleCount, 80);
setCounter(descriptionInput, descriptionCount, 500);
titleInput.addEventListener('input', () => setCounter(titleInput, titleCount, 80));
descriptionInput.addEventListener('input', () => setCounter(descriptionInput, descriptionCount, 500));

const pickMap = L.map('pickMap', { zoomControl: false });
L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
  subdomains: 'abcd', maxZoom: 19
}).addTo(pickMap);
L.control.zoom({ position: 'bottomright' }).addTo(pickMap);

const defaultLat = parseFloat(latInput.value) || 41.3851;
const defaultLng = parseFloat(lngInput.value) || 2.1734;
pickMap.setView([defaultLat, defaultLng], 14);

let pickMarker = L.marker([defaultLat, defaultLng], { draggable: true }).addTo(pickMap);
let selectedAddress = selectedAddressInput.value.trim();
let selectedLat = selectedLatInput.value !== '' ? parseFloat(selectedLatInput.value) : null;
let selectedLng = selectedLngInput.value !== '' ? parseFloat(selectedLngInput.value) : null;
let isAddressConfirmed = isAddressConfirmedInput.value === '1';
let searchResults = [];

function updateCoords(latlng) {
  latInput.value = latlng.lat.toFixed(6);
  lngInput.value = latlng.lng.toFixed(6);
}

function isEventType() {
  return typeInput.value === 'event';
}

function hasValidSelection() {
  return isAddressConfirmed
    && selectedAddress !== ''
    && Number.isFinite(selectedLat)
    && Number.isFinite(selectedLng);
}

function coordsMatchSelection() {
  if (!hasValidSelection()) return false;
  const lat = parseFloat(latInput.value);
  const lng = parseFloat(lngInput.value);
  return Number.isFinite(lat)
    && Number.isFinite(lng)
    && Math.abs(lat - selectedLat) < 0.000001
    && Math.abs(lng - selectedLng) < 0.000001;
}

function syncSelectionInputs() {
  selectedAddressInput.value = selectedAddress;
  selectedLatInput.value = Number.isFinite(selectedLat) ? selectedLat.toFixed(6) : '';
  selectedLngInput.value = Number.isFinite(selectedLng) ? selectedLng.toFixed(6) : '';
  isAddressConfirmedInput.value = isAddressConfirmed ? '1' : '0';
}

function setAddressMessage(message, isError = false) {
  addressMessage.textContent = message;
  addressMessage.classList.toggle('is-error', isError);
}

function clearAddressResults() {
  searchResults = [];
  addressResults.innerHTML = '';
  addressResults.style.display = 'none';
}

function invalidateConfirmedAddress(message) {
  if (!hasValidSelection() && !message) return;
  isAddressConfirmed = false;
  syncSelectionInputs();
  if (message) setAddressMessage(message, true);
  updateSummaryLocation();
}

function updateSummaryType() {
  const meta = typeMeta[typeInput.value] || typeMeta.incident;
  summaryTypeLabel.textContent = meta.label;
  summaryTypeCostBadge.textContent = meta.cost;
  summaryCost.textContent = meta.costSummary;
  submitBtnLabel.textContent = meta.submit;
  summaryTypeIcon.className = `summary-type-icon accent-${meta.accent}`;
  summaryTypeIcon.innerHTML = `<i class="${meta.icon}"></i>`;
}

function updateSummaryLocation() {
  if (hasValidSelection() && addressInput.value.trim() === selectedAddress) {
    summaryLocation.textContent = selectedAddress;
  } else if (addressInput.value.trim()) {
    summaryLocation.textContent = addressInput.value.trim();
  } else {
    summaryLocation.textContent = 'Añade una ubicación precisa';
  }
}

function formatDateTime(value) {
  if (!value) return 'Por definir';
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return value;
  return d.toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric' }) + ' · '
    + d.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
}

function updateSummaryDateTime() {
  summaryDateTime.textContent = startsInput.value ? formatDateTime(startsInput.value) : 'Por definir';
}

function setGeocodeButtonState(isLoading) {
  geocodeBtn.disabled = isLoading;
  geocodeBtn.innerHTML = isLoading
    ? '<i class="fa-solid fa-spinner fa-spin"></i><span>Buscando</span>'
    : '<i class="fa-solid fa-location-crosshairs"></i><span>Ubicar</span>';
}

function confirmAddress(result) {
  selectedAddress = result.display_name;
  selectedLat = parseFloat(result.lat);
  selectedLng = parseFloat(result.lon);
  isAddressConfirmed = true;
  addressInput.value = selectedAddress;
  pickMarker.setLatLng([selectedLat, selectedLng]);
  pickMap.setView([selectedLat, selectedLng], 16);
  updateCoords({ lat: selectedLat, lng: selectedLng });
  syncSelectionInputs();
  clearAddressResults();
  setAddressMessage('Dirección confirmada. Ya puedes publicar el evento.', false);
  updateSummaryLocation();
}

function renderAddressResults(results) {
  searchResults = results;
  addressResults.innerHTML = '';

  if (!results.length) {
    addressResults.style.display = 'none';
    setAddressMessage('No encontramos coincidencias. Prueba con una dirección más específica.', true);
    return;
  }

  const fragment = document.createDocumentFragment();

  results.forEach((result, index) => {
    const option = document.createElement('button');
    option.type = 'button';
    option.className = 'create-address-result-item';
    option.innerHTML = `<i class="fa-solid fa-location-dot"></i><span>${result.display_name}</span>`;
    option.addEventListener('click', () => confirmAddress(searchResults[index]));
    fragment.appendChild(option);
  });

  addressResults.appendChild(fragment);
  addressResults.style.display = 'block';
  setAddressMessage('Selecciona una dirección válida de la lista antes de publicar el evento.', false);
}

function validateEventLocation() {
  if (!isEventType()) return true;

  if (!addressInput.value.trim() || !hasValidSelection()) {
    setAddressMessage('Selecciona una dirección válida de la lista antes de publicar el evento.', true);
    return false;
  }

  if (addressInput.value.trim() !== selectedAddress || !coordsMatchSelection()) {
    setAddressMessage('La dirección confirmada ya no coincide con el punto del mapa. Selecciónala de nuevo antes de publicar.', true);
    return false;
  }

  setAddressMessage('Dirección confirmada. Ya puedes publicar el evento.', false);
  return true;
}

pickMap.on('click', e => {
  pickMarker.setLatLng(e.latlng);
  updateCoords(e.latlng);
  if (isEventType()) {
    invalidateConfirmedAddress('La dirección confirmada ya no coincide con el punto del mapa. Selecciónala de nuevo antes de publicar.');
  }
});

pickMarker.on('dragend', e => {
  updateCoords(e.target.getLatLng());
  if (isEventType()) {
    invalidateConfirmedAddress('La dirección confirmada ya no coincide con el punto del mapa. Selecciónala de nuevo antes de publicar.');
  }
});

async function geocodeAddress() {
  const addr = addressInput.value.trim();
  if (!addr) {
    setAddressMessage('Escribe una dirección para buscar coincidencias.', true);
    clearAddressResults();
    return;
  }

  setGeocodeButtonState(true);
  try {
    const res = await fetch('https://nominatim.openstreetmap.org/search?format=json&limit=5&addressdetails=1&q=' + encodeURIComponent(addr), {
      headers: { Accept: 'application/json' }
    });
    const data = await res.json();
    renderAddressResults(Array.isArray(data) ? data : []);
  } catch (e) {
    clearAddressResults();
    setAddressMessage('Error al buscar la dirección. Inténtalo de nuevo.', true);
  }
  setGeocodeButtonState(false);
}

geocodeBtn.addEventListener('click', geocodeAddress);
addressInput.addEventListener('input', () => {
  clearAddressResults();
  if (addressInput.value.trim() !== selectedAddress) {
    invalidateConfirmedAddress('Selecciona una dirección válida de la lista antes de publicar el evento.');
  }
  updateSummaryLocation();
});

addressInput.addEventListener('keydown', e => {
  if (e.key === 'Enter') {
    e.preventDefault();
    geocodeAddress();
  }
});

createForm.addEventListener('submit', e => {
  if (!validateEventLocation()) {
    e.preventDefault();
  }
});

if (hasValidSelection() && addressInput.value.trim() === selectedAddress && coordsMatchSelection()) {
  setAddressMessage('Dirección confirmada. Ya puedes publicar el evento.', false);
} else if (isEventType() && addressInput.value.trim() !== '') {
  isAddressConfirmed = false;
  syncSelectionInputs();
}

const NOW_MIN = '<?= date('Y-m-d\TH:i') ?>';
startsInput.min = NOW_MIN;
expiresInput.min = NOW_MIN;

function setDateConstraints(type) {
  startsInput.required = (type === 'event');
}

function syncTypeUI(type) {
  document.querySelectorAll('.type-card[data-type]').forEach(c => c.classList.toggle('selected', c.dataset.type === type));
  typeInput.value = type;
  setDateConstraints(type);
  tokenEstimate.style.display = (type === 'activity') ? 'flex' : 'none';
  attendeesRow.style.display = (type === 'event') ? '' : 'none';
  updateSummaryType();

  if (type === 'event') {
    validateEventLocation();
  } else if (hasValidSelection()) {
    setAddressMessage('Dirección confirmada.', false);
  } else {
    setAddressMessage('Busca una dirección y selecciona una opción válida para fijar la ubicación exacta.', false);
  }
}

document.querySelectorAll('.type-card[data-type]').forEach(card => {
  card.addEventListener('click', function () {
    syncTypeUI(this.dataset.type);
  });
});

document.querySelectorAll('.create-category-chip').forEach(chip => {
  chip.addEventListener('click', function () {
    const value = this.dataset.value;
    categorySelect.value = value;
    document.querySelectorAll('.create-category-chip').forEach(node => node.classList.toggle('active', node === this));
  });
});

categorySelect.addEventListener('change', function () {
  document.querySelectorAll('.create-category-chip').forEach(node => {
    node.classList.toggle('active', node.dataset.value === this.value);
  });
});

startsInput.addEventListener('input', updateSummaryDateTime);
expiresInput.addEventListener('input', updateSummaryDateTime);

syncSelectionInputs();
syncTypeUI(typeInput.value);
updateSummaryLocation();
updateSummaryDateTime();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
