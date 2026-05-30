<?php
require_once __DIR__ . '/config/db.php';
requireLogin();

$pageTitle  = 'Mapa en vivo';
$activePage = 'map';

$db   = getDB();
$user = currentUser();

// Fetch publications for left panel
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

$typeEmoji = ['incident' => '🚨', 'event' => '🎉', 'activity' => '⚡'];
$typeColor = ['incident' => 'var(--red)', 'event' => 'var(--yellow)', 'activity' => 'var(--primary)'];
$typeLabel = ['incident' => 'Incidencia', 'event' => 'Evento', 'activity' => 'Actividad'];

$categoryEmoji = [
    'Arte y Cultura' => '🎨', 'Música' => '🎵', 'Gastronomía' => '🍕',
    'Compras' => '🛍️', 'Deporte' => '🏃', 'Tráfico' => '🚗',
    'Obras' => '🚧', 'Avería' => '⚡', 'Cultura' => '🎭',
];

include __DIR__ . '/includes/header.php';
?>

    <!-- Dashboard: full-width map + panels -->
    <div class="dashboard-shell">

      <!-- LEFT PANEL: filters + list -->
      <div class="map-left-panel">

        <!-- Filters: type -->
        <div class="map-filters" data-filter-group="type">
          <div class="filter-chip active" data-filter="all">🌐 Todo</div>
          <div class="filter-chip" data-filter="incident">🚨 Incidencias</div>
          <div class="filter-chip" data-filter="event">🎉 Eventos</div>
          <div class="filter-chip" data-filter="activity">⚡ Actividades</div>
        </div>

        <!-- Filters: category -->
        <div class="map-filters map-filters--category" data-filter-group="category">
          <div class="filter-chip active" data-filter="all">Todas</div>
          <div class="filter-chip" data-filter="Arte y Cultura">🎨 Arte</div>
          <div class="filter-chip" data-filter="Música">🎵 Música</div>
          <div class="filter-chip" data-filter="Gastronomía">🍕 Gastro</div>
          <div class="filter-chip" data-filter="Deporte">🏃 Deporte</div>
          <div class="filter-chip" data-filter="Compras">🛍️ Compras</div>
          <div class="filter-chip" data-filter="Tráfico">🚗 Tráfico</div>
          <div class="filter-chip" data-filter="Obras">🚧 Obras</div>
          <div class="filter-chip" data-filter="Avería">⚡ Avería</div>
          <div class="filter-chip" data-filter="Cultura">🎭 Cultura</div>
        </div>

        <!-- Publication list -->
        <div class="map-pub-list">
          <?php if (empty($publications)): ?>
            <div style="padding:24px;text-align:center;color:var(--text3);font-size:14px;">
              No hay publicaciones activas.
            </div>
          <?php else: ?>
            <?php foreach ($publications as $pub): ?>
              <?php
                $emoji = $categoryEmoji[$pub['category']] ?? $typeEmoji[$pub['type']] ?? '📍';
                $color = $typeColor[$pub['type']] ?? 'var(--primary)';
                $time  = (new DateTime($pub['created_at']))->format('H:i');
              ?>
              <a href="/activity.php?id=<?= $pub['id'] ?>"
                 class="map-pub-item" data-id="<?= $pub['id'] ?>"
                 data-lat="<?= $pub['latitude'] ?>" data-lng="<?= $pub['longitude'] ?>"
                 data-type="<?= $pub['type'] ?>" data-category="<?= htmlspecialchars($pub['category'] ?? '') ?>">
                <div class="map-pub-icon <?= $pub['type'] ?>">
                  <?= $emoji ?>
                </div>
                <div class="map-pub-info" style="min-width:0;">
                  <div class="pub-title"><?= htmlspecialchars($pub['title']) ?></div>
                  <div class="pub-addr" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                    <?= htmlspecialchars($pub['address'] ?? '') ?>
                  </div>
                  <div class="pub-bottom">
                    <span class="badge badge-<?= $pub['type'] === 'incident' ? 'red' : ($pub['type'] === 'event' ? 'yellow' : 'primary') ?>" style="font-size:10px;padding:2px 7px;">
                      <?= $typeLabel[$pub['type']] ?>
                    </span>
                    <?php if ($pub['token_cost'] > 0): ?>
                      <span class="badge badge-primary" style="font-size:10px;padding:2px 7px;">⬡ <?= $pub['token_cost'] ?></span>
                    <?php endif; ?>
                    <span style="font-size:10px;color:var(--text3);margin-left:auto;"><?= $time ?></span>
                  </div>
                </div>
              </a>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <!-- Create CTA at bottom of panel -->
        <div style="padding:12px;border-top:1px solid var(--border);">
          <a href="/create.php" class="btn btn-primary btn-block">
            <i class="fa-solid fa-plus"></i> Crear publicación
          </a>
        </div>
      </div>

      <!-- MAP -->
      <div class="map-container">
        <div id="map"></div>
        <div class="map-detail-panel" id="detailPanel"></div>
      </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="/js/map.js"></script>
    <script>
    let activeType = 'all';
    let activeCat  = 'all';

    function applyAllFilters() {
      // Map markers
      if (window.CityLiveMap) {
        window.CityLiveMap.applyFilters(activeType, activeCat);
      }
      // Left panel list
      document.querySelectorAll('.map-pub-item').forEach(item => {
        const matchType = activeType === 'all' || item.dataset.type === activeType;
        const matchCat  = activeCat  === 'all' || item.dataset.category === activeCat;
        item.style.display = (matchType && matchCat) ? '' : 'none';
      });
    }

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
