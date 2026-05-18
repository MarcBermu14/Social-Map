/* CityLive — Global JS */

// ─── Filter chips ──────────────────────────────────────
document.querySelectorAll('.filter-chip[data-filter]').forEach(chip => {
  chip.addEventListener('click', function () {
    const group = this.closest('[data-filter-group]');
    if (group) {
      group.querySelectorAll('.filter-chip').forEach(c => c.classList.remove('active'));
    }
    this.classList.toggle('active');
    if (typeof window.applyFilter === 'function') {
      window.applyFilter(this.dataset.filter, this.classList.contains('active'));
    }
  });
});

// ─── Type selector (create page) ──────────────────────
window.selectType = function (el, type) {
  document.querySelectorAll('.type-card').forEach(c => c.classList.remove('selected'));
  el.classList.add('selected');

  const estimate = document.getElementById('tokenEstimate');
  const submitBtn = document.getElementById('submitBtn');

  if (type === 'activity') {
    if (estimate) estimate.style.display = 'flex';
  } else {
    if (estimate) estimate.style.display = 'none';
  }

  // Update submit button text
  if (submitBtn) {
    if (type === 'incident') submitBtn.textContent = 'Publicar incidencia — Gratis';
    if (type === 'event')    submitBtn.textContent = 'Publicar evento — Gratis';
    if (type === 'activity') submitBtn.textContent = 'Publicar actividad — 150 tokens';
  }
};

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
