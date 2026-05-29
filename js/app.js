/* CityLive - Global JS */

window.CityLive = window.CityLive || {};
window.CityLive.baseUrl = (window.CITYLIVE_CONFIG && window.CITYLIVE_CONFIG.baseUrl) || '';
window.CityLive.csrfToken = (window.CITYLIVE_CONFIG && window.CITYLIVE_CONFIG.csrfToken) || '';

window.CityLive.url = function (path) {
  const cleanPath = String(path || '').replace(/^\/+/, '');
  if (!cleanPath) return window.CityLive.baseUrl || '/';
  return (window.CityLive.baseUrl || '') + '/' + cleanPath;
};

window.CityLive.fetchJson = async function (path, options) {
  const opts = Object.assign({}, options || {});
  opts.headers = Object.assign({}, opts.headers || {}, {
    'X-CSRF-Token': window.CityLive.csrfToken
  });
  const response = await fetch(window.CityLive.url(path), opts);
  return response.json();
};

// Flash messages auto-dismiss
document.querySelectorAll('.flash').forEach(el => {
  setTimeout(() => el.style.opacity = '0', 4000);
  setTimeout(() => el.remove(), 4500);
});

window.renderStars = function (rating, max = 5) {
  return Array.from({ length: max }, (_, i) => i < rating ? '?' : '?').join('');
};

window.formatNum = function (n) {
  if (n >= 1000) return (n / 1000).toFixed(1).replace('.0', '') + 'k';
  return n.toString();
};

document.querySelectorAll('[data-tooltip]').forEach(el => {
  el.title = el.dataset.tooltip;
});
