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
