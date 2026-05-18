/* CityLive — Leaflet Map */

(function () {
  const MAP_ID = 'map';
  const mapEl  = document.getElementById(MAP_ID);
  if (!mapEl) return;

  // ─── Initialise map ─────────────────────────────────
  const map = L.map(MAP_ID, {
    center: [41.3851, 2.1734],
    zoom: 14,
    zoomControl: false,
    attributionControl: true,
  });

  // CartoDB dark tiles (no API key needed)
  L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; <a href="https://carto.com/attributions">CARTO</a>',
    subdomains: 'abcd',
    maxZoom: 19,
  }).addTo(map);

  // Custom zoom control (bottom-right)
  L.control.zoom({ position: 'bottomright' }).addTo(map);

  // ─── Marker factory ─────────────────────────────────
  const TYPE_META = {
    incident: { emoji: '🚨', color: 'var(--red)',     label: 'Incidencia' },
    event:    { emoji: '🎉', color: 'var(--yellow)',  label: 'Evento'     },
    activity: { emoji: '⚡', color: 'var(--primary)', label: 'Actividad'  },
  };

  const CATEGORY_EMOJI = {
    'Arte y Cultura': '🎨',
    'Música':         '🎵',
    'Gastronomía':    '🍕',
    'Compras':        '🛍️',
    'Deporte':        '🏃',
    'Tráfico':        '🚗',
    'Obras':          '🚧',
    'Avería':         '⚡',
    'Cultura':        '🎭',
    'default':        '📍',
  };

  function makeIcon(pub) {
    const meta  = TYPE_META[pub.type] || TYPE_META.activity;
    const emoji = CATEGORY_EMOJI[pub.category] || meta.emoji;
    const html  = `
      <div class="cl-marker ${pub.type}">
        <div class="cl-marker-pin">${emoji}</div>
        <div class="cl-marker-tail"></div>
      </div>`;
    return L.divIcon({ html, className: '', iconSize: [40, 48], iconAnchor: [20, 48], popupAnchor: [0, -52] });
  }

  // ─── Load publications from API ──────────────────────
  let markersLayer = L.layerGroup().addTo(map);
  let currentFilter = 'all';

  function loadPublications(filter) {
    currentFilter = filter || 'all';
    const url = '/citylive/api/publications.php' + (filter && filter !== 'all' ? '?type=' + filter : '');

    fetch(url)
      .then(r => r.json())
      .then(data => {
        markersLayer.clearLayers();
        if (!data.features) return;

        data.features.forEach(feature => {
          const pub   = feature.properties;
          const latlng = [feature.geometry.coordinates[1], feature.geometry.coordinates[0]];
          const icon  = makeIcon(pub);

          const marker = L.marker(latlng, { icon }).addTo(markersLayer);
          marker.on('click', () => openDetail(pub));
        });
      })
      .catch(console.error);
  }

  // ─── Detail panel ────────────────────────────────────
  const detailPanel = document.getElementById('detailPanel');

  function openDetail(pub) {
    if (!detailPanel) {
      // No panel → navigate to full page
      window.location.href = `/citylive/activity.php?id=${pub.id}`;
      return;
    }

    const meta  = TYPE_META[pub.type] || TYPE_META.activity;
    const emoji = CATEGORY_EMOJI[pub.category] || meta.emoji;

    const tokenHtml = pub.token_cost > 0 ? `
      <div class="detail-token-box">
        <div class="token-icon">⬡</div>
        <div>
          <div class="detail-token-lbl">Coste de publicación</div>
          <div class="detail-token-val">${pub.token_cost} tokens</div>
        </div>
        <span class="badge badge-primary" style="margin-left:auto;">Lucrativa</span>
      </div>` : '';

    const creatorRep = pub.reputation ? `⭐ ${parseFloat(pub.reputation).toFixed(1)}` : '';

    detailPanel.innerHTML = `
      <button class="detail-close" onclick="closeDetail()">✕</button>
      <div class="detail-hero">
        <span>${emoji}</span>
        <div class="detail-hero-overlay"></div>
      </div>
      <div class="detail-body">
        <div class="detail-category" style="color:${meta.color}">${meta.emoji} ${meta.label} · ${pub.category || ''}</div>
        <div class="detail-title">${escHtml(pub.title)}</div>
        <div class="detail-meta">
          ${pub.address ? `<div class="detail-meta-chip">📍 ${escHtml(pub.address)}</div>` : ''}
          ${pub.attendees > 0 ? `<div class="detail-meta-chip">👥 ${pub.attendees} personas</div>` : ''}
          ${pub.starts_at ? `<div class="detail-meta-chip">🕐 ${formatDateShort(pub.starts_at)}</div>` : ''}
        </div>
        ${pub.description ? `<div class="detail-desc">${escHtml(pub.description)}</div>` : ''}
        ${tokenHtml}
        <a href="/citylive/profile.php?id=${pub.user_id}" class="detail-creator">
          <div class="avatar avatar-sm" style="background:linear-gradient(135deg,var(--purple),var(--primary));color:#fff;font-size:14px;">
            ${(pub.creator_name || 'U')[0].toUpperCase()}
          </div>
          <div class="detail-creator-info">
            <div class="detail-creator-name">${escHtml(pub.creator_name || 'Usuario')}</div>
            <div class="detail-creator-sub">@${escHtml(pub.creator_username || '')} · ${pub.plan_label || ''}</div>
          </div>
          ${creatorRep ? `<div class="detail-creator-rep">${creatorRep}</div>` : ''}
        </a>
      </div>
      <div class="detail-actions">
        <a href="/citylive/activity.php?id=${pub.id}" class="btn btn-primary" style="flex:1;">Ver detalle</a>
        <button class="btn btn-outline btn-icon" title="Guardar">🔖</button>
        <button class="btn btn-outline btn-icon" title="Reportar">🚩</button>
      </div>`;

    detailPanel.classList.add('open');

    // Center map
    map.panTo([parseFloat(pub.lat), parseFloat(pub.lng)], { animate: true });

    // Highlight item in left list
    document.querySelectorAll('.map-pub-item').forEach(el => {
      el.classList.toggle('selected', el.dataset.id == pub.id);
    });
  }

  window.closeDetail = function () {
    if (detailPanel) detailPanel.classList.remove('open');
    document.querySelectorAll('.map-pub-item').forEach(el => el.classList.remove('selected'));
  };

  // ─── Left panel list items click ─────────────────────
  document.addEventListener('click', e => {
    const item = e.target.closest('.map-pub-item');
    if (!item) return;
    const id = item.dataset.id;
    // Find feature data already loaded
    fetch(`/citylive/api/publications.php?id=${id}`)
      .then(r => r.json())
      .then(data => {
        if (data.features && data.features[0]) {
          openDetail(data.features[0].properties);
        }
      });
  });

  // ─── Filter clicks ───────────────────────────────────
  window.applyFilter = function (filter) {
    loadPublications(filter === 'all' ? null : filter);
  };

  // ─── Helpers ─────────────────────────────────────────
  function escHtml(str) {
    return String(str || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function formatDateShort(dt) {
    if (!dt) return '';
    const d = new Date(dt);
    return d.toLocaleDateString('es-ES', { month: 'short', day: 'numeric' })
      + ' ' + d.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
  }

  // ─── Initial load ────────────────────────────────────
  loadPublications();

  // Expose for external use
  window.CityLiveMap = { loadPublications, openDetail, map };
})();
