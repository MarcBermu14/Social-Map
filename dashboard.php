<?php
require_once __DIR__ . '/config/db.php';
require_once 'config.php';
requireLogin();

$pageTitle  = 'Mapa en vivo';
$activePage = 'map';
$bodyClass  = 'page-dashboard';

$db   = getDB();
$user = currentUser();

$stmt = $db->query("
    SELECT p.id, p.type, p.title, p.address, p.category, p.attendees, p.token_cost,
           p.latitude, p.longitude, p.created_at,
           u.username AS creator_username, u.full_name AS creator_name
    FROM publications p
    JOIN users u ON u.id = p.user_id
    WHERE p.status = 'active'
    ORDER BY p.created_at DESC
    LIMIT 50
");
$publications = $stmt->fetchAll();

$typeMeta = [
    'incident' => ['icon' => 'fa-solid fa-triangle-exclamation', 'label' => 'Incidencia', 'accent' => 'incident'],
    'event' => ['icon' => 'fa-solid fa-calendar-days', 'label' => 'Evento', 'accent' => 'event'],
    'activity' => ['icon' => 'fa-solid fa-bolt', 'label' => 'Actividad', 'accent' => 'activity'],
];

$categoryMeta = [
    'Arte y Cultura' => ['icon' => 'fa-solid fa-palette', 'short' => 'Arte'],
    'Música' => ['icon' => 'fa-solid fa-music', 'short' => 'Música'],
    'Gastronomía' => ['icon' => 'fa-solid fa-utensils', 'short' => 'Gastro'],
    'Compras' => ['icon' => 'fa-solid fa-bag-shopping', 'short' => 'Compras'],
    'Deporte' => ['icon' => 'fa-solid fa-basketball', 'short' => 'Deporte'],
    'Tráfico' => ['icon' => 'fa-solid fa-car-side', 'short' => 'Tráfico'],
    'Obras' => ['icon' => 'fa-solid fa-helmet-safety', 'short' => 'Obras'],
    'Avería' => ['icon' => 'fa-solid fa-screwdriver-wrench', 'short' => 'Avería'],
    'Cultura' => ['icon' => 'fa-solid fa-masks-theater', 'short' => 'Cultura'],
];

$typeTotals = ['all' => count($publications), 'incident' => 0, 'event' => 0, 'activity' => 0];
foreach ($publications as $publication) {
    if (isset($typeTotals[$publication['type']])) {
        $typeTotals[$publication['type']]++;
    }
}

include __DIR__ . '/includes/header.php';
?>

    <div class="dashboard-shell">
      <aside class="map-left-panel">
        <div class="map-panel-toolbar">
          <div class="map-panel-toolbar-copy">
            <span class="map-panel-toolbar-kicker">Explorar</span>
            <h1>Publicaciones cercanas</h1>
          </div>
          <a href="<?= BASE ?>/create.php" class="map-panel-toolbar-btn">
            <i class="fa-solid fa-plus"></i>
            <span>Crear</span>
          </a>
        </div>

        <div class="map-quick-actions">
          <a href="<?= BASE ?>/spin.php" class="map-quick-action">
            <i class="fa-solid fa-dice"></i>
            <span>Ruleta</span>
          </a>
          <a href="<?= BASE ?>/tokens.php" class="map-quick-action">
            <i class="fa-solid fa-coins"></i>
            <span>Tokens</span>
          </a>
          <a href="<?= BASE ?>/profile.php" class="map-quick-action">
            <i class="fa-solid fa-user-circle"></i>
            <span>Perfil</span>
          </a>
        </div>

        <div class="map-left-scroll">
          <div class="panel-search">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" id="pubSearch" placeholder="Buscar publicaciones, zonas o planes..." autocomplete="off">
            <button id="pubSearchClear" title="Limpiar">
              <i class="fa-solid fa-xmark"></i>
            </button>
          </div>

          <section class="filter-block">
            <div class="filter-block-title">Vista principal</div>
            <div class="map-filters" data-filter-group="type">
              <button type="button" class="filter-chip active" data-filter="all">
                <i class="fa-solid fa-globe"></i><span>Todo</span>
              </button>
              <button type="button" class="filter-chip" data-filter="incident">
                <i class="fa-solid fa-triangle-exclamation"></i><span>Incidencias</span>
              </button>
              <button type="button" class="filter-chip" data-filter="event">
                <i class="fa-solid fa-calendar-days"></i><span>Eventos</span>
              </button>
              <button type="button" class="filter-chip" data-filter="activity">
                <i class="fa-solid fa-bolt"></i><span>Actividades</span>
              </button>
            </div>
          </section>

          <section class="filter-block filter-block-categories">
            <div class="filter-block-title">Categorías</div>
            <div class="map-filters map-filters--category" data-filter-group="category">
              <button type="button" class="filter-chip active" data-filter="all">
                <i class="fa-solid fa-layer-group"></i><span>Todas</span>
              </button>
              <button type="button" class="filter-chip" data-filter="Arte y Cultura">
                <i class="fa-solid fa-palette"></i><span>Arte</span>
              </button>
              <button type="button" class="filter-chip" data-filter="Música">
                <i class="fa-solid fa-music"></i><span>Música</span>
              </button>
              <button type="button" class="filter-chip" data-filter="Gastronomía">
                <i class="fa-solid fa-utensils"></i><span>Gastro</span>
              </button>
              <button type="button" class="filter-chip" data-filter="Deporte">
                <i class="fa-solid fa-basketball"></i><span>Deporte</span>
              </button>
              <button type="button" class="filter-chip" data-filter="Compras">
                <i class="fa-solid fa-bag-shopping"></i><span>Compras</span>
              </button>
              <button type="button" class="filter-chip" data-filter="Tráfico">
                <i class="fa-solid fa-car-side"></i><span>Tráfico</span>
              </button>
              <button type="button" class="filter-chip" data-filter="Obras">
                <i class="fa-solid fa-helmet-safety"></i><span>Obras</span>
              </button>
              <button type="button" class="filter-chip" data-filter="Avería">
                <i class="fa-solid fa-screwdriver-wrench"></i><span>Avería</span>
              </button>
              <button type="button" class="filter-chip" data-filter="Cultura">
                <i class="fa-solid fa-masks-theater"></i><span>Cultura</span>
              </button>
            </div>
          </section>

          <div class="map-feed-header">
            <div>
              <h2>Publicaciones activas</h2>
              <p>Las 50 más recientes en el mapa.</p>
            </div>
            <span class="map-feed-counter"><?= $typeTotals['all'] ?></span>
          </div>

          <div class="map-pub-list">
            <?php if (empty($publications)): ?>
              <div class="map-empty-state">
                <i class="fa-solid fa-map-location-dot"></i>
                <strong>No hay publicaciones activas.</strong>
                <span>Cuando la comunidad publique algo nuevo, aparecerá aquí automáticamente.</span>
              </div>
            <?php else: ?>
              <?php foreach ($publications as $pub): ?>
                <?php
                  $type = $typeMeta[$pub['type']] ?? $typeMeta['activity'];
                  $category = $categoryMeta[$pub['category']] ?? ['icon' => $type['icon'], 'short' => $pub['category'] ?: 'Comunidad'];
                  $time = (new DateTime($pub['created_at']))->format('H:i');
                ?>
                <a href="<?= BASE ?>/activity.php?id=<?= $pub['id'] ?>"
                   class="map-pub-item" data-id="<?= $pub['id'] ?>"
                   data-lat="<?= $pub['latitude'] ?>" data-lng="<?= $pub['longitude'] ?>"
                   data-type="<?= $pub['type'] ?>" data-category="<?= htmlspecialchars($pub['category'] ?? '') ?>">
                  <div class="map-pub-icon <?= $pub['type'] ?>">
                    <i class="<?= htmlspecialchars($category['icon']) ?>"></i>
                  </div>

                  <div class="map-pub-info">
                    <div class="pub-topline">
                      <span class="pub-type-badge type-<?= htmlspecialchars($type['accent']) ?>">
                        <i class="<?= htmlspecialchars($type['icon']) ?>"></i>
                        <span><?= $type['label'] ?></span>
                      </span>
                      <span class="pub-time">
                        <i class="fa-regular fa-clock"></i>
                        <span><?= $time ?></span>
                      </span>
                    </div>

                    <div class="pub-title"><?= htmlspecialchars($pub['title']) ?></div>

                    <div class="pub-addr">
                      <i class="fa-solid fa-location-dot"></i>
                      <span><?= htmlspecialchars($pub['address'] ?? 'Ubicación por confirmar') ?></span>
                    </div>

                    <div class="pub-bottom">
                      <span class="pub-category-chip">
                        <i class="<?= htmlspecialchars($category['icon']) ?>"></i>
                        <span><?= htmlspecialchars($category['short']) ?></span>
                      </span>

                      <?php if ($pub['token_cost'] > 0): ?>
                        <span class="pub-token-chip">
                          <i class="fa-solid fa-coins"></i>
                          <span><?= (int)$pub['token_cost'] ?></span>
                        </span>
                      <?php endif; ?>

                      <span class="pub-open-indicator">
                        <i class="fa-solid fa-arrow-up-right-from-square"></i>
                      </span>
                    </div>
                  </div>
                </a>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <div class="map-panel-footer">
          <a href="<?= BASE ?>/create.php" class="dashboard-create-btn">
            <i class="fa-solid fa-plus"></i>
            <span>Crear publicación</span>
          </a>
        </div>
      </aside>

      <div class="map-container">
        <div class="map-stage">
          <div id="map"></div>
          <div class="map-detail-panel" id="detailPanel"></div>
        </div>
      </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>window.CL_BASE = '<?= BASE ?>';</script>
    <script src="<?= BASE ?>/js/map.js?v=3"></script>
    <script>
    let activeType = 'all';
    let activeCat = 'all';
    let activeQuery = '';

    function applyAllFilters() {
      const q = activeQuery.trim().toLowerCase();

      if (window.CityLiveMap) {
        window.CityLiveMap.applyFilters(activeType, activeCat);
      }

      document.querySelectorAll('.map-pub-item').forEach(item => {
        const matchType = activeType === 'all' || item.dataset.type === activeType;
        const matchCat = activeCat === 'all' || item.dataset.category === activeCat;
        const title = (item.querySelector('.pub-title')?.textContent || '').toLowerCase();
        const address = (item.querySelector('.pub-addr span')?.textContent || '').toLowerCase();
        const matchQuery = !q || title.includes(q) || address.includes(q);
        item.style.display = (matchType && matchCat && matchQuery) ? '' : 'none';
      });

      document.getElementById('pubSearchClear').style.display = q ? 'inline-flex' : 'none';
    }

    const searchInput = document.getElementById('pubSearch');
    const searchClear = document.getElementById('pubSearchClear');

    searchInput.addEventListener('input', function () {
      activeQuery = this.value;
      applyAllFilters();
    });

    searchClear.addEventListener('click', function () {
      searchInput.value = '';
      activeQuery = '';
      applyAllFilters();
      searchInput.focus();
    });

    document.querySelectorAll('[data-filter-group="type"] .filter-chip').forEach(chip => {
      chip.addEventListener('click', function () {
        document.querySelectorAll('[data-filter-group="type"] .filter-chip').forEach(c => c.classList.remove('active'));
        this.classList.add('active');
        activeType = this.dataset.filter;
        applyAllFilters();
      });
    });

    document.querySelectorAll('[data-filter-group="category"] .filter-chip').forEach(chip => {
      chip.addEventListener('click', function () {
        document.querySelectorAll('[data-filter-group="category"] .filter-chip').forEach(c => c.classList.remove('active'));
        this.classList.add('active');
        activeCat = this.dataset.filter;
        applyAllFilters();
      });
    });
    </script>

<?php include __DIR__ . '/includes/footer.php'; ?>
