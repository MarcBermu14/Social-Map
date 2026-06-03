/* CityLive - Leaflet Map */

(function () {
  const MAP_ID = 'map';
  const mapEl = document.getElementById(MAP_ID);
  if (!mapEl) return;

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

  L.control.zoom({ position: 'topleft' }).addTo(map);

  const TYPE_META = {
    incident: {
      icon: 'fa-solid fa-triangle-exclamation',
      color: 'var(--red)',
      label: 'Incidencia',
      markerClass: 'incident'
    },
    event: {
      icon: 'fa-solid fa-calendar-days',
      color: 'var(--yellow)',
      label: 'Evento',
      markerClass: 'event'
    },
    activity: {
      icon: 'fa-solid fa-bolt',
      color: 'var(--primary)',
      label: 'Actividad',
      markerClass: 'activity'
    },
  };

  const CATEGORY_META = {
    'Arte y Cultura': { icon: 'fa-solid fa-palette', markerClass: 'culture', short: 'Arte y Cultura' },
    'Música': { icon: 'fa-solid fa-music', markerClass: 'culture', short: 'Música' },
    'Gastronomía': { icon: 'fa-solid fa-utensils', markerClass: 'event', short: 'Gastronomía' },
    'Compras': { icon: 'fa-solid fa-bag-shopping', markerClass: 'activity', short: 'Compras' },
    'Deporte': { icon: 'fa-solid fa-basketball', markerClass: 'activity', short: 'Deporte' },
    'Tráfico': { icon: 'fa-solid fa-car-side', markerClass: 'mobility', short: 'Tráfico' },
    'Obras': { icon: 'fa-solid fa-helmet-safety', markerClass: 'mobility', short: 'Obras' },
    'Avería': { icon: 'fa-solid fa-screwdriver-wrench', markerClass: 'mobility', short: 'Avería' },
    'Cultura': { icon: 'fa-solid fa-masks-theater', markerClass: 'culture', short: 'Cultura' },
    default: { icon: 'fa-solid fa-location-dot', markerClass: 'activity', short: 'Comunidad' }
  };

  function getCategoryMeta(category, type) {
    const fallbackType = TYPE_META[type] || TYPE_META.activity;
    const categoryMeta = CATEGORY_META[category] || CATEGORY_META.default;
    return {
      icon: categoryMeta.icon || fallbackType.icon,
      markerClass: categoryMeta.markerClass || fallbackType.markerClass,
      short: categoryMeta.short || category || fallbackType.label
    };
  }

  function makeIcon(pub) {
    const typeMeta = TYPE_META[pub.type] || TYPE_META.activity;
    const categoryMeta = getCategoryMeta(pub.category, pub.type);
    const html = `
      <div class="cl-marker ${typeMeta.markerClass} ${categoryMeta.markerClass}">
        <div class="cl-marker-pin">
          <i class="marker-icon ${categoryMeta.icon}"></i>
        </div>
        <div class="cl-marker-tail"></div>
      </div>`;

    return L.divIcon({
      html,
      className: '',
      iconSize: [54, 64],
      iconAnchor: [27, 58],
      popupAnchor: [0, -54]
    });
  }

  let allEntries = [];
  const markersLayer = L.layerGroup().addTo(map);
  let activeType = 'all';
  let activeCat = 'all';

  const B = window.CL_BASE || '';
  const detailPanel = document.getElementById('detailPanel');

  function loadPublications() {
    fetch(B + '/api/publications.php')
      .then(r => r.json())
      .then(data => {
        markersLayer.clearLayers();
        allEntries = [];
        if (!data.features) return;

        data.features.forEach(feature => {
          const pub = feature.properties;
          const latlng = [feature.geometry.coordinates[1], feature.geometry.coordinates[0]];
          const marker = L.marker(latlng, { icon: makeIcon(pub) });
          marker.on('click', () => openDetail(pub));
          allEntries.push({ marker, pub });
        });

        applyFilters(activeType, activeCat);
      })
      .catch(console.error);
  }

  function applyFilters(type, category) {
    activeType = type || 'all';
    activeCat = category || 'all';

    markersLayer.clearLayers();
    allEntries.forEach(({ marker, pub }) => {
      const matchType = activeType === 'all' || pub.type === activeType;
      const matchCat = activeCat === 'all' || pub.category === activeCat;
      if (matchType && matchCat) marker.addTo(markersLayer);
    });
  }

  function openDetail(pub) {
    if (!detailPanel) {
      window.location.href = `${B}/activity.php?id=${pub.id}`;
      return;
    }

    const typeMeta = TYPE_META[pub.type] || TYPE_META.activity;
    const categoryMeta = getCategoryMeta(pub.category, pub.type);
    const lat = parseFloat(pub.lat || pub.latitude || 41.3851);
    const lng = parseFloat(pub.lng || pub.longitude || 2.1734);
    const tokenHtml = pub.token_cost > 0 ? `
      <div class="detail-token-box">
        <div class="token-icon"><i class="fa-solid fa-coins"></i></div>
        <div>
          <div class="detail-token-lbl">Acceso lucrativo</div>
          <div class="detail-token-val">${pub.token_cost} tokens</div>
        </div>
        <span class="badge badge-primary" style="margin-left:auto;">Pro</span>
      </div>` : '';
    const creatorRep = pub.reputation ? `${parseFloat(pub.reputation).toFixed(1)}` : '';

    detailPanel.innerHTML = `
      <button class="detail-close" onclick="closeDetail()">
        <i class="fa-solid fa-xmark"></i>
      </button>
      <div class="detail-hero">
        <div class="detail-hero-icon ${typeMeta.markerClass}">
          <i class="${categoryMeta.icon}"></i>
        </div>
        <div class="detail-hero-actions">
          <button class="detail-mini-btn detail-share-btn" data-pub="${pub.id}" data-title="${escAttr(pub.title)}" title="Compartir">
            <i class="fa-solid fa-share-nodes"></i>
          </button>
          <button class="detail-mini-btn detail-save-btn" data-pub="${pub.id}" title="Guardar">
            <i class="fa-regular fa-bookmark"></i>
          </button>
          <button class="detail-mini-btn detail-report-btn" data-pub="${pub.id}" title="Reportar">
            <i class="fa-regular fa-flag"></i>
          </button>
        </div>
        <div class="detail-hero-overlay"></div>
      </div>
      <div class="detail-body">
        <div class="detail-category" style="color:${typeMeta.color}">
          <i class="${typeMeta.icon}"></i>
          <span>${typeMeta.label}${pub.category ? ' · ' + escHtml(pub.category) : ''}</span>
        </div>
        <div class="detail-title">${escHtml(pub.title)}</div>
        <div class="detail-meta">
          ${pub.address ? `<div class="detail-meta-chip"><i class="fa-solid fa-location-dot"></i><span>${escHtml(pub.address)}</span></div>` : ''}
          ${pub.attendees > 0 ? `<div class="detail-meta-chip"><i class="fa-solid fa-users"></i><span>${pub.attendees} personas</span></div>` : ''}
          ${pub.starts_at ? `<div class="detail-meta-chip"><i class="fa-regular fa-clock"></i><span>${formatDateShort(pub.starts_at)}</span></div>` : ''}
        </div>
        ${pub.description ? `<div class="detail-desc">${escHtml(pub.description)}</div>` : ''}
        ${tokenHtml}
        <a href="${B}/profile.php?id=${pub.user_id}" class="detail-creator">
          <div class="avatar avatar-sm" style="background:linear-gradient(135deg,#ff7b61,#8b73ea);color:#fff;font-size:14px;">
            ${(pub.creator_name || 'U')[0].toUpperCase()}
          </div>
          <div class="detail-creator-info">
            <div class="detail-creator-name">${escHtml(pub.creator_name || 'Usuario')}</div>
            <div class="detail-creator-sub">@${escHtml(pub.creator_username || '')}${pub.plan_label ? ' · ' + escHtml(pub.plan_label) : ''}</div>
          </div>
          ${creatorRep ? `<div class="detail-creator-rep"><i class="fa-solid fa-star"></i> ${creatorRep}</div>` : ''}
        </a>
      </div>
      <div class="detail-actions">
        <a href="${B}/activity.php?id=${pub.id}" class="btn btn-outline" style="flex:1;">
          <i class="fa-solid fa-arrow-up-right-from-square"></i>
          <span>Ver detalle</span>
        </a>
        ${pub.type === 'event' ? `
          <button class="btn btn-primary detail-join-btn" data-pub="${pub.id}" data-registered="0" style="flex:2;">
            <i class="fa-solid fa-ticket"></i>
            <span>Apuntarse</span>
          </button>` : `
          <a href="${B}/create.php" class="btn btn-primary" style="flex:2;">
            <i class="fa-solid fa-plus"></i>
            <span>Crear publicación</span>
          </a>`}
      </div>`;

    detailPanel.classList.add('open');
    map.panTo([lat, lng], { animate: true });

    document.querySelectorAll('.map-pub-item').forEach(el => {
      el.classList.toggle('selected', el.dataset.id == pub.id);
    });

    const shareMapBtn = detailPanel.querySelector('.detail-share-btn');
    if (shareMapBtn) {
      shareMapBtn.addEventListener('click', async function () {
        const url = location.origin + B + '/activity.php?id=' + this.dataset.pub;
        const title = this.dataset.title || 'CityLive';
        if (navigator.share) {
          try { await navigator.share({ title, url }); } catch (_) {}
        } else {
          try {
            await navigator.clipboard.writeText(url);
            mapToast('Enlace copiado al portapapeles');
          } catch (_) {
            prompt('Copia este enlace:', url);
          }
        }
      });
    }

    if (pub.type === 'event') {
      const joinBtn = detailPanel.querySelector('.detail-join-btn');
      fetch(B + `/api/event_register.php?pub_id=${pub.id}`)
        .then(r => r.json())
        .then(data => {
          if (data.registered) {
            setJoinButtonState(joinBtn, true);
          }
        })
        .catch(() => {});

      joinBtn.addEventListener('click', async function () {
        const registered = this.dataset.registered === '1';
        this.disabled = true;
        const res = await fetch(B + '/api/event_register.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: registered ? 'unregister' : 'register', pub_id: parseInt(this.dataset.pub, 10) })
        });
        const data = await res.json();
        if (data.success) {
          setJoinButtonState(this, !!data.registered);
        } else {
          alert(data.error || 'Error al procesar');
        }
        this.disabled = false;
      });
    }

    bindDetailActions(pub);
  }

  function setJoinButtonState(button, registered) {
    if (!button) return;
    button.dataset.registered = registered ? '1' : '0';
    button.className = 'btn detail-join-btn ' + (registered ? 'btn-danger' : 'btn-primary');
    button.style.flex = '2';
    button.innerHTML = registered
      ? '<i class="fa-solid fa-user-minus"></i><span>Desapuntarse</span>'
      : '<i class="fa-solid fa-ticket"></i><span>Apuntarse</span>';
  }

  window.closeDetail = function () {
    if (detailPanel) detailPanel.classList.remove('open');
    document.querySelectorAll('.map-pub-item').forEach(el => el.classList.remove('selected'));
  };

  document.addEventListener('click', e => {
    const item = e.target.closest('.map-pub-item');
    if (!item) return;
    e.preventDefault();
    fetch(B + `/api/publications.php?id=${item.dataset.id}`)
      .then(r => r.json())
      .then(data => {
        if (data.features && data.features[0]) openDetail(data.features[0].properties);
      });
  });

  function escHtml(str) {
    return String(str || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function escAttr(str) {
    return escHtml(str).replace(/'/g, '&#39;');
  }

  function mapToast(msg) {
    const el = document.createElement('div');
    el.textContent = msg;
    el.style.cssText = 'position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:#131a4b;color:#fff;padding:12px 20px;border-radius:999px;font-size:13px;font-weight:700;z-index:9999;box-shadow:0 10px 24px rgba(19,26,75,.22);white-space:nowrap;';
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 2800);
  }

  function formatDateShort(dt) {
    if (!dt) return '';
    const d = new Date(dt);
    return d.toLocaleDateString('es-ES', { month: 'short', day: 'numeric' }) +
      ' ' + d.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
  }

  function bindDetailActions(pub) {
    const saveBtn = detailPanel.querySelector('.detail-save-btn');
    if (saveBtn) {
      fetch(B + `/api/save_publication.php?pub_id=${encodeURIComponent(pub.id)}`)
        .then(r => r.json())
        .then(data => {
          if (data.saved) {
            saveBtn.innerHTML = '<i class="fa-solid fa-bookmark"></i>';
            saveBtn.title = 'Guardado';
          }
        })
        .catch(err => console.error('No se pudo comprobar el estado de guardado:', err));

      saveBtn.addEventListener('click', async function () {
        this.disabled = true;
        const res = await fetch(B + '/api/save_publication.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'toggle', pub_id: parseInt(this.dataset.pub, 10) })
        });
        const data = await res.json();
        if (data.success) {
          this.innerHTML = data.saved
            ? '<i class="fa-solid fa-bookmark"></i>'
            : '<i class="fa-regular fa-bookmark"></i>';
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
        const res = await fetch(B + '/api/report_publication.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            pub_id: parseInt(this.dataset.pub, 10),
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

  loadPublications();

  window.CityLiveMap = { loadPublications, applyFilters, openDetail, map };
})();
