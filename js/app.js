/* CityLive — Global JS */

// NOTE: selectType is defined in create.php (needs to update #typeInput) — do NOT define it here.

// ─── Flash messages auto-dismiss ──────────────────────
document.querySelectorAll('.flash').forEach(el => {
  setTimeout(() => el.style.opacity = '0', 4000);
  setTimeout(() => el.remove(), 4500);
});

// ─── Star rating display ───────────────────────────────
window.renderStars = function (rating, max = 5) {
  return Array.from({ length: max }, (_, i) => i < rating ? '★' : '☆').join('');
};

// ─── Number formatting ─────────────────────────────────
window.formatNum = function (n) {
  if (n >= 1000) return (n / 1000).toFixed(1).replace('.0', '') + 'k';
  return n.toString();
};

// ─── Tooltip (simple title-based) ─────────────────────
document.querySelectorAll('[data-tooltip]').forEach(el => {
  el.title = el.dataset.tooltip;
});

// ─── Notifications dropdown ───────────────────────────
(() => {
  const btn = document.getElementById('notifBtn');
  const dropdown = document.getElementById('notifDropdown');
  const list = document.getElementById('notifList');
  const dot = document.getElementById('notifDot');
  if (!btn || !dropdown || !list) return;

  const base = window.CL_BASE || '';
  let isOpen = false;
  let isLoading = false;

  const formatTime = (raw) => {
    if (!raw) return '';
    const iso = raw.replace(' ', 'T');
    const dt = new Date(iso);
    if (isNaN(dt.getTime())) return '';
    return dt.toLocaleString('es-ES', {
      day: '2-digit',
      month: 'short',
      hour: '2-digit',
      minute: '2-digit'
    });
  };

  const setDot = (count) => {
    if (!dot) return;
    if (count > 0) dot.classList.add('notif-dot--show');
    else dot.classList.remove('notif-dot--show');
  };

  const renderList = (items) => {
    if (!items || items.length === 0) {
      list.innerHTML = '<div class="notif-empty">No tienes notificaciones</div>';
      return;
    }
    list.innerHTML = '';
    items.forEach(n => {
      const item = document.createElement('div');
      item.className = 'notif-item' + (n.is_read ? '' : ' notif-item--unread');

      const main = n.url ? document.createElement('a') : document.createElement('div');
      main.className = 'notif-main';
      if (n.url) main.href = n.url;

      const title = document.createElement('div');
      title.className = 'notif-title';
      title.textContent = n.title || 'Notificacion';

      const body = document.createElement('div');
      body.className = 'notif-body';
      body.textContent = n.body || '';

      const meta = document.createElement('div');
      meta.className = 'notif-meta';
      meta.textContent = formatTime(n.created_at);

      main.append(title, body, meta);

      const actions = document.createElement('div');
      actions.className = 'notif-actions';
      const delBtn = document.createElement('button');
      delBtn.className = 'notif-del';
      delBtn.type = 'button';
      delBtn.title = 'Borrar';
      delBtn.innerHTML = '<i class="fa-solid fa-trash"></i>';
      delBtn.addEventListener('click', async (e) => {
        e.preventDefault();
        e.stopPropagation();
        await deleteNotification(n.id);
      });
      actions.appendChild(delBtn);

      item.append(main, actions);
      list.appendChild(item);
    });
  };

  const fetchNotifications = async (markRead = false) => {
    if (isLoading) return;
    isLoading = true;
    try {
      const url = base + '/api/notifications.php' + (markRead ? '?mark_read=1' : '');
      const res = await fetch(url);
      const data = await res.json();
      if (data && data.success) {
        renderList(data.notifications || []);
        setDot(data.unread_count || 0);
      }
    } catch (e) {
      list.innerHTML = '<div class="notif-empty">No se pudieron cargar</div>';
    } finally {
      isLoading = false;
    }
  };

  const deleteNotification = async (id) => {
    try {
      const res = await fetch(base + '/api/notifications.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'delete', id })
      });
      const data = await res.json();
      if (data && data.success) {
        fetchNotifications(false);
      }
    } catch (e) {}
  };

  const openDropdown = () => {
    dropdown.classList.add('show');
    dropdown.setAttribute('aria-hidden', 'false');
    isOpen = true;
    fetchNotifications(true);
  };

  const closeDropdown = () => {
    dropdown.classList.remove('show');
    dropdown.setAttribute('aria-hidden', 'true');
    isOpen = false;
  };

  btn.addEventListener('click', (e) => {
    e.stopPropagation();
    if (isOpen) closeDropdown();
    else openDropdown();
  });

  document.addEventListener('click', (e) => {
    if (!dropdown.contains(e.target) && e.target !== btn) {
      closeDropdown();
    }
  });

  window.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && isOpen) closeDropdown();
  });

  fetchNotifications(false);
})();
