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

  L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; <a href="https://carto.com/attributions">CARTO</a>',
    subdomains: 'abcd',
    maxZoom: 19,
  }).addTo(map);

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

  // ─── Marker store (all loaded, filter client-side) ───
  let allEntries = [];
  const markersLayer = L.layerGroup().addTo(map);

  let activeType = 'all';
  let activeCat  = 'all';

  // ─── Load ALL publications once ──────────────────────
  function loadPublications() {
    fetch('/api/publications.php')
      .then(r => r.json())
      .then(data => {
        markersLayer.clearLayers();
        allEntries = [];
        if (!data.features) return;

        data.features.forEach(feature => {
          const pub    = feature.properties;
          const latlng = [feature.geometry.coordinates[1], feature.geometry.coordinates[0]];
          const marker = L.marker(latlng, { icon: makeIcon(pub) });
          marker.on('click', () => openDetail(pub));
          allEntries.push({ marker, pub });
        });

        applyFilters(activeType, activeCat);
      })
      .catch(console.error);
  }

  // ─── Filter markers client-side ──────────────────────
  function applyFilters(type, category) {
    activeType = type || 'all';
    activeCat  = category || 'all';

    markersLayer.clearLayers();
    allEntries.forEach(({ marker, pub }) => {
      const matchType = activeType === 'all' || pub.type === activeType;
      const matchCat  = activeCat  === 'all' || pub.category === activeCat;
      if (matchType && matchCat) marker.addTo(markersLayer);
    });
  }

  // ─── Detail panel ────────────────────────────────────
  const detailPanel = document.getElementById('detailPanel');

  function openDetail(pub) {
    if (!detailPanel) {
      window.location.href = `/activity.php?id=${pub.id}`;
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
        <a href="/profile.php?id=${pub.user_id}" class="detail-creator">
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
        <a href="/activity.php?id=${pub.id}" class="btn btn-${pub.type === 'event' ? 'outline' : 'primary'}" style="flex:1;">Ver detalle</a>
        ${pub.type === 'event' ? `<button class="btn btn-primary detail-join-btn" data-pub="${pub.id}" data-registered="0" style="flex:2;">🎟️ Apuntarse</button>` : ''}
        ${pub.type !== 'event' ? `<button class="btn btn-outline btn-icon detail-save-btn" title="Guardar" data-pub="${pub.id}">🔖</button>` : ''}
        <button class="btn btn-outline btn-icon detail-report-btn" title="Reportar" data-pub="${pub.id}">🚩</button>
      </div>`;

    detailPanel.classList.add('open');
    map.panTo([parseFloat(pub.lat), parseFloat(pub.lng)], { animate: true });

    document.querySelectorAll('.map-pub-item').forEach(el => {
      el.classList.toggle('selected', el.dataset.id == pub.id);
    });

    // For events: check registration status and wire up join button
    if (pub.type === 'event') {
      const joinBtn = detailPanel.querySelector('.detail-join-btn');
      // Check current status
      fetch(`/api/event_register.php?pub_id=${pub.id}`)
        .then(r => r.json())
        .then(data => {
          if (data.registered) {
            joinBtn.dataset.registered = '1';
            joinBtn.className = 'btn btn-danger detail-join-btn';
            joinBtn.style.flex = '2';
            joinBtn.textContent = '✗ Desapuntarse';
          }
        })
        .catch(() => {});

      joinBtn.addEventListener('click', async function () {
        const registered = this.dataset.registered === '1';
        this.disabled = true;
        const res  = await fetch('/api/event_register.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: registered ? 'unregister' : 'register', pub_id: parseInt(this.dataset.pub) })
        });
        const data = await res.json();
        if (data.success) {
          const now = data.registered;
          this.dataset.registered = now ? '1' : '0';
          this.className = 'btn detail-join-btn ' + (now ? 'btn-danger' : 'btn-primary');
          this.style.flex = '2';
          this.textContent = now ? '✗ Desapuntarse' : '🎟️ Apuntarse';
        } else {
          alert(data.error || 'Error al procesar');
        }
        this.disabled = false;
      });
    }

    bindDetailActions(pub);
  }

  window.closeDetail = function () {
    if (detailPanel) detailPanel.classList.remove('open');
    document.querySelectorAll('.map-pub-item').forEach(el => el.classList.remove('selected'));
  };

  // ─── Left panel list item click ──────────────────────
  // Items are <a> tags so they work without JS; intercept to show panel instead
  document.addEventListener('click', e => {
    const item = e.target.closest('.map-pub-item');
    if (!item) return;
    e.preventDefault();
    fetch(`/api/publications.php?id=${item.dataset.id}`)
      .then(r => r.json())
      .then(data => {
        if (data.features && data.features[0]) openDetail(data.features[0].properties);
      });
  });

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

  function bindDetailActions(pub) {
    const saveBtn = detailPanel.querySelector('.detail-save-btn');
    if (saveBtn) {
      fetch(`/api/save_publication.php?pub_id=${pub.id}`)
        .then(r => r.json())
        .then(data => {
          if (data.saved) {
            saveBtn.textContent = '✅';
            saveBtn.title = 'Guardado';
          }
        })
        .catch(() => {});

      saveBtn.addEventListener('click', async function () {
        this.disabled = true;
        const res = await fetch('/api/save_publication.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'toggle', pub_id: parseInt(this.dataset.pub) })
        });
        const data = await res.json();
        if (data.success) {
          this.textContent = data.saved ? '✅' : '🔖';
          this.title = data.saved ? 'Guardado' : 'Guardar';
        } else {
          alert(data.error || 'No se pudo guardar');
        }
        this.disabled = false;
      });
    }

    const reportBtn = detailPanel.querySelector('.detail-report-btn');
    if (reportBtn) {
      reportBtn.addEventListener('click', async function () {
        const reason = prompt('Motivo del reporte (spam, offensive, inappropriate, other):', 'spam');
        if (reason === null) return;
        const normalizedReason = reason.trim().toLowerCase();
        if (!['spam', 'offensive', 'inappropriate', 'other'].includes(normalizedReason)) {
          alert('Motivo inválido');
          return;
        }
        const description = prompt('Descripción adicional (opcional):', '') || '';
        this.disabled = true;
        const res = await fetch('/api/report_publication.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            pub_id: parseInt(this.dataset.pub),
            reason: normalizedReason,
            description
          })
        });
        const data = await res.json();
        alert(data.success ? data.message : (data.error || 'No se pudo enviar el reporte'));
        this.disabled = false;
      });
    }
  }

  // ─── Initial load ────────────────────────────────────
  loadPublications();

  window.CityLiveMap = { loadPublications, applyFilters, openDetail, map };
})();
