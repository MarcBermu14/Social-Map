<?php
require_once __DIR__ . '/config/db.php';
requireLogin();

$eventId = isset($_GET['event']) ? (int)$_GET['event'] : 0;
if (!$eventId) { header('Location: /citylive/dashboard.php'); exit; }

$db = getDB();

// Fetch event
$pub = $db->prepare("
    SELECT p.id, p.title, p.type, p.category, p.user_id, p.status,
           u.username AS creator_username, u.full_name AS creator_name
    FROM publications p
    JOIN users u ON u.id = p.user_id
    WHERE p.id = ? AND p.status = 'active'
");
$pub->execute([$eventId]);
$pub = $pub->fetch();
if (!$pub) { header('Location: /citylive/dashboard.php'); exit; }

$me        = currentUser();
$isAdmin   = ($me['plan'] ?? 'free') === 'platinum';
$isOrgUser = ((int)$me['id'] === (int)$pub['user_id']);

// Forum stats
$stats = $db->prepare("
    SELECT
        (SELECT COUNT(*) FROM event_forum_posts WHERE event_id = ? AND status = 'active') AS post_count,
        (SELECT COUNT(*) FROM event_forum_posts p2
         JOIN event_forum_comments c ON c.post_id = p2.id
         WHERE p2.event_id = ? AND p2.status = 'active' AND c.status = 'active') AS comment_count
");
$stats->execute([$eventId, $eventId]);
$stats = $stats->fetch();

$typeEmoji = ['incident' => '🚨', 'event' => '🎉', 'activity' => '⚡'];
$emoji     = $typeEmoji[$pub['type']] ?? '📍';

$pageTitle  = 'Foro · ' . htmlspecialchars($pub['title']);
$activePage = '';

include __DIR__ . '/includes/header.php';
?>

<style>
/* ── Forum page layout ─────────────────────────────────────────────── */
.forum-wrap     { max-width: 860px; margin: 0 auto; padding: 0 16px 60px; }
.forum-header   { background: var(--card); border: 1px solid var(--border); border-radius: var(--r-lg);
                  padding: 20px 24px; margin-bottom: 20px;
                  display: flex; align-items: center; gap: 14px; }
.forum-header-emoji { font-size: 32px; line-height: 1; }
.forum-header-info  { flex: 1; }
.forum-header-title { font-size: 18px; font-weight: 800; margin-bottom: 3px; }
.forum-header-meta  { font-size: 13px; color: var(--text2); display: flex; gap: 14px; }
.forum-header-meta span { display: flex; align-items: center; gap: 4px; }

/* Toolbar */
.forum-toolbar {
  display: flex; align-items: center; gap: 10px; margin-bottom: 18px; flex-wrap: wrap;
}
.forum-sort-btn {
  background: var(--card); border: 1px solid var(--border); color: var(--text2);
  border-radius: 20px; padding: 6px 14px; font-size: 13px; font-weight: 600; cursor: pointer;
  transition: all .15s;
}
.forum-sort-btn.active,
.forum-sort-btn:hover { background: var(--primary); color: #fff; border-color: var(--primary); }
.forum-btn-post {
  margin-left: auto; background: var(--primary); color: #fff; border: none;
  border-radius: 20px; padding: 8px 18px; font-size: 13px; font-weight: 700; cursor: pointer;
  display: flex; align-items: center; gap: 6px; transition: opacity .15s;
}
.forum-btn-post:hover { opacity: .87; }

/* ── Create post form ──────────────────────────────────────────────── */
.forum-compose {
  background: var(--card); border: 1px solid var(--border); border-radius: var(--r-lg);
  padding: 18px; margin-bottom: 20px; display: none;
}
.forum-compose.open { display: block; }
.forum-compose-head { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; }
.forum-compose textarea {
  width: 100%; min-height: 100px; background: var(--bg); border: 1px solid var(--border);
  border-radius: var(--r-sm); padding: 12px; font-size: 14px; color: var(--text);
  resize: vertical; outline: none; transition: border .15s; line-height: 1.6;
}
.forum-compose textarea:focus { border-color: var(--primary); }
.forum-compose-actions {
  display: flex; align-items: center; gap: 8px; margin-top: 10px; flex-wrap: wrap;
}
.forum-compose-actions .btn-img-upload {
  background: var(--bg); border: 1px solid var(--border); border-radius: var(--r-sm);
  padding: 7px 12px; font-size: 13px; cursor: pointer; display: flex; align-items: center; gap: 6px;
  transition: border .15s;
}
.forum-compose-actions .btn-img-upload:hover { border-color: var(--primary); }
.forum-img-preview {
  display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px;
}
.forum-img-preview-item {
  position: relative; width: 80px; height: 80px; border-radius: var(--r-sm);
  overflow: hidden; border: 1px solid var(--border);
}
.forum-img-preview-item img { width: 100%; height: 100%; object-fit: cover; }
.forum-img-preview-item .rm-img {
  position: absolute; top: 2px; right: 2px; background: rgba(0,0,0,.65); color: #fff;
  border: none; border-radius: 50%; width: 18px; height: 18px; font-size: 11px;
  cursor: pointer; display: flex; align-items: center; justify-content: center;
}
.compose-char-count { margin-left: auto; font-size: 12px; color: var(--text3); }
.compose-char-count.warn { color: var(--red); }

/* ── Post card ────────────────────────────────────────────────────── */
.forum-post {
  background: var(--card); border: 1px solid var(--border); border-radius: var(--r-lg);
  padding: 18px; margin-bottom: 14px; transition: box-shadow .15s;
}
.forum-post:hover { box-shadow: 0 4px 16px rgba(0,0,0,.07); }
.forum-post.pinned {
  border-color: var(--primary); background: linear-gradient(135deg,rgba(14,165,233,.04),var(--card));
}
.post-head {
  display: flex; align-items: flex-start; gap: 10px; margin-bottom: 12px;
}
.post-avatar {
  width: 38px; height: 38px; border-radius: 50%; background: linear-gradient(135deg,var(--primary),var(--purple));
  display: flex; align-items: center; justify-content: center; color: #fff;
  font-weight: 700; font-size: 15px; flex-shrink: 0; overflow: hidden;
}
.post-avatar img { width: 100%; height: 100%; object-fit: cover; }
.post-meta { flex: 1; }
.post-meta-top { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; margin-bottom: 2px; }
.post-username { font-weight: 700; font-size: 14px; }
.post-badge-org { font-size: 10px; font-weight: 700; background: var(--primary); color: #fff;
                  padding: 2px 6px; border-radius: 10px; }
.post-badge-pin { font-size: 10px; font-weight: 700; background: var(--yellow); color: #fff;
                  padding: 2px 6px; border-radius: 10px; }
.post-time { font-size: 12px; color: var(--text3); }
.post-menu-btn {
  background: none; border: none; color: var(--text3); font-size: 16px;
  cursor: pointer; padding: 4px 8px; border-radius: var(--r-sm); margin-left: auto;
}
.post-menu-btn:hover { background: var(--bg); color: var(--text); }

.post-content {
  font-size: 14px; line-height: 1.7; color: var(--text); white-space: pre-wrap;
  word-break: break-word; margin-bottom: 12px;
}
.post-content.collapsed { max-height: 200px; overflow: hidden; position: relative; }
.post-content.collapsed::after {
  content: ''; position: absolute; bottom: 0; left: 0; right: 0;
  height: 60px; background: linear-gradient(transparent, var(--card));
}
.post-expand-btn {
  font-size: 13px; color: var(--primary); cursor: pointer; margin-bottom: 10px;
  background: none; border: none; padding: 0; font-weight: 600;
}

/* Images in post */
.post-images {
  display: grid; gap: 6px; margin-bottom: 12px;
  grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
}
.post-images.count-1 { grid-template-columns: 1fr; max-width: 420px; }
.post-images.count-2 { grid-template-columns: 1fr 1fr; }
.post-img-item {
  border-radius: var(--r-sm); overflow: hidden; cursor: pointer;
  aspect-ratio: 1/1; background: var(--bg);
}
.post-img-item img { width: 100%; height: 100%; object-fit: cover; transition: transform .2s; }
.post-img-item:hover img { transform: scale(1.03); }

/* Post actions */
.post-actions {
  display: flex; align-items: center; gap: 14px; border-top: 1px solid var(--border);
  padding-top: 10px;
}
.post-action-btn {
  background: none; border: none; color: var(--text3); font-size: 13px; font-weight: 600;
  cursor: pointer; display: flex; align-items: center; gap: 5px;
  padding: 5px 8px; border-radius: var(--r-sm); transition: all .15s;
}
.post-action-btn:hover { background: var(--bg); color: var(--text); }
.post-action-btn.liked { color: var(--red); }
.post-action-btn.liked:hover { color: var(--red); }

/* ── Dropdown menus ───────────────────────────────────────────────── */
.forum-dropdown {
  position: absolute; background: var(--card); border: 1px solid var(--border);
  border-radius: var(--r-sm); box-shadow: 0 8px 24px rgba(0,0,0,.12);
  z-index: 50; min-width: 160px; overflow: hidden;
}
.forum-dropdown a, .forum-dropdown button {
  display: block; width: 100%; text-align: left; padding: 9px 14px;
  font-size: 13px; color: var(--text); background: none; border: none; cursor: pointer;
  transition: background .1s;
}
.forum-dropdown a:hover, .forum-dropdown button:hover { background: var(--bg); }
.forum-dropdown .danger { color: var(--red); }

/* ── Comments section ─────────────────────────────────────────────── */
.comments-section { margin-top: 12px; display: none; }
.comments-section.open { display: block; }
.comment-list { border-top: 1px solid var(--border); padding-top: 12px; }
.comment-item {
  display: flex; gap: 9px; margin-bottom: 12px;
}
.comment-avatar {
  width: 28px; height: 28px; border-radius: 50%; background: linear-gradient(135deg,var(--green),var(--primary));
  display: flex; align-items: center; justify-content: center; color: #fff;
  font-size: 12px; font-weight: 700; flex-shrink: 0; overflow: hidden;
}
.comment-avatar img { width: 100%; height: 100%; object-fit: cover; }
.comment-body { flex: 1; }
.comment-bubble {
  background: var(--bg); border-radius: 0 var(--r-sm) var(--r-sm) var(--r-sm);
  padding: 9px 12px; font-size: 13px; line-height: 1.6; word-break: break-word;
  white-space: pre-wrap;
}
.comment-username { font-weight: 700; font-size: 12px; margin-bottom: 2px; }
.comment-footer {
  display: flex; align-items: center; gap: 10px; margin-top: 4px; flex-wrap: wrap;
}
.comment-time { font-size: 11px; color: var(--text3); }
.comment-action { font-size: 12px; font-weight: 600; color: var(--text3); background: none;
                  border: none; cursor: pointer; padding: 0; }
.comment-action.liked { color: var(--red); }
.comment-action:hover { color: var(--text); }

/* Replies indent */
.comment-replies { margin-left: 37px; margin-top: 8px; }

/* Reply compose */
.comment-compose {
  display: flex; gap: 8px; align-items: flex-start; margin-top: 10px;
}
.comment-compose textarea {
  flex: 1; background: var(--bg); border: 1px solid var(--border);
  border-radius: var(--r-sm); padding: 8px 12px; font-size: 13px;
  resize: none; outline: none; min-height: 38px; max-height: 120px; color: var(--text);
  transition: border .15s; line-height: 1.5;
}
.comment-compose textarea:focus { border-color: var(--primary); }
.comment-compose-send {
  background: var(--primary); color: #fff; border: none; border-radius: var(--r-sm);
  padding: 8px 14px; font-size: 13px; font-weight: 700; cursor: pointer; white-space: nowrap;
  transition: opacity .15s;
}
.comment-compose-send:hover { opacity: .87; }

/* ── Lightbox ─────────────────────────────────────────────────────── */
.forum-lightbox {
  position: fixed; inset: 0; background: rgba(0,0,0,.9); z-index: 1000;
  display: none; align-items: center; justify-content: center; padding: 20px;
}
.forum-lightbox.open { display: flex; }
.forum-lightbox img { max-width: 100%; max-height: 90vh; object-fit: contain; border-radius: var(--r-sm); }
.lightbox-close {
  position: absolute; top: 16px; right: 16px; background: rgba(255,255,255,.15);
  color: #fff; border: none; border-radius: 50%; width: 38px; height: 38px;
  font-size: 20px; cursor: pointer; display: flex; align-items: center; justify-content: center;
}
.lightbox-nav {
  position: absolute; top: 50%; transform: translateY(-50%);
  background: rgba(255,255,255,.15); color: #fff; border: none; border-radius: 50%;
  width: 44px; height: 44px; font-size: 20px; cursor: pointer; display: flex; align-items: center; justify-content: center;
}
.lightbox-nav.prev { left: 16px; }
.lightbox-nav.next { right: 16px; }
.lightbox-download {
  position: absolute; bottom: 20px; background: rgba(255,255,255,.15);
  color: #fff; border: none; border-radius: 20px; padding: 8px 18px;
  font-size: 13px; font-weight: 700; cursor: pointer; text-decoration: none;
  display: flex; align-items: center; gap: 6px;
}

/* ── Skeleton loaders ─────────────────────────────────────────────── */
.skeleton { background: linear-gradient(90deg, var(--bg) 25%, rgba(0,0,0,.05) 50%, var(--bg) 75%);
            background-size: 200% 100%; animation: skeletonWave 1.4s infinite; border-radius: 6px; }
@keyframes skeletonWave { 0%{background-position:200% 0} 100%{background-position:-200% 0} }
.skeleton-post { height: 140px; border-radius: var(--r-lg); margin-bottom: 14px; }

/* ── Pagination ───────────────────────────────────────────────────── */
.forum-pagination { display: flex; justify-content: center; gap: 8px; margin-top: 24px; }
.forum-page-btn {
  width: 36px; height: 36px; border-radius: 50%; border: 1px solid var(--border);
  background: var(--card); cursor: pointer; font-size: 13px; font-weight: 600;
  display: flex; align-items: center; justify-content: center; transition: all .15s;
}
.forum-page-btn.active, .forum-page-btn:hover { background: var(--primary); color: #fff; border-color: var(--primary); }

/* Edit textarea in post */
.post-edit-box {
  width: 100%; min-height: 80px; background: var(--bg); border: 1px solid var(--primary);
  border-radius: var(--r-sm); padding: 10px; font-size: 14px; resize: vertical;
  outline: none; color: var(--text); line-height: 1.6; margin-bottom: 8px; display: none;
}
.post-edit-actions { display: none; gap: 8px; margin-bottom: 10px; }
.post-edit-actions.open { display: flex; }

/* Report modal */
.forum-modal-overlay {
  position: fixed; inset: 0; background: rgba(0,0,0,.5); z-index: 200;
  display: none; align-items: center; justify-content: center; padding: 16px;
}
.forum-modal-overlay.open { display: flex; }
.forum-modal {
  background: var(--card); border-radius: var(--r-lg); padding: 24px;
  max-width: 400px; width: 100%; box-shadow: 0 16px 48px rgba(0,0,0,.2);
}
.forum-modal h3 { font-size: 17px; font-weight: 800; margin-bottom: 14px; }
.forum-modal label { font-size: 13px; font-weight: 600; display: block; margin-bottom: 6px; }
.forum-modal select, .forum-modal textarea {
  width: 100%; background: var(--bg); border: 1px solid var(--border);
  border-radius: var(--r-sm); padding: 9px 12px; font-size: 13px; color: var(--text);
  outline: none; margin-bottom: 12px;
}
.forum-modal-actions { display: flex; gap: 8px; justify-content: flex-end; }

/* Empty state */
.forum-empty {
  text-align: center; padding: 60px 20px; color: var(--text2);
}
.forum-empty-icon { font-size: 48px; margin-bottom: 12px; }
.forum-empty h3 { font-size: 17px; font-weight: 700; margin-bottom: 6px; color: var(--text); }

/* Responsive */
@media (max-width: 640px) {
  .forum-toolbar { flex-wrap: wrap; }
  .forum-btn-post { margin-left: 0; width: 100%; justify-content: center; }
  .post-images { grid-template-columns: repeat(2, 1fr); }
}
</style>

<div class="forum-wrap">

  <!-- Header -->
  <div class="forum-header">
    <div class="forum-header-emoji"><?= $emoji ?></div>
    <div class="forum-header-info">
      <div class="forum-header-title">
        <a href="/citylive/activity.php?id=<?= $pub['id'] ?>" style="color:var(--text);">
          <?= htmlspecialchars($pub['title']) ?>
        </a>
      </div>
      <div class="forum-header-meta">
        <span>💬 <b id="stat-posts"><?= $stats['post_count'] ?></b> publicaciones</span>
        <span>↩ <b><?= $stats['comment_count'] ?></b> respuestas</span>
        <?php if ($isOrgUser): ?>
          <span class="post-badge-org">Organizador</span>
        <?php endif; ?>
      </div>
    </div>
    <a href="/citylive/activity.php?id=<?= $pub['id'] ?>" class="btn btn-outline btn-sm">
      ← Evento
    </a>
  </div>

  <!-- Toolbar -->
  <div class="forum-toolbar">
    <button class="forum-sort-btn active" data-sort="recent">🕐 Recientes</button>
    <button class="forum-sort-btn" data-sort="popular">🔥 Populares</button>
    <button class="forum-sort-btn" data-sort="most_comments">💬 Más comentados</button>
    <button class="forum-btn-post" id="btnOpenCompose">
      <i class="fa-solid fa-plus"></i> Nueva publicación
    </button>
  </div>

  <!-- Compose form -->
  <div class="forum-compose" id="composeBox">
    <div class="forum-compose-head">
      <div class="post-avatar">
        <?php if ($me['avatar']): ?>
          <img src="<?= htmlspecialchars($me['avatar']) ?>" alt="">
        <?php else: ?>
          <?= strtoupper(substr($me['full_name'] ?? $me['username'], 0, 1)) ?>
        <?php endif; ?>
      </div>
      <span style="font-weight:700;font-size:14px;"><?= htmlspecialchars($me['username']) ?></span>
      <?php if ($isOrgUser): ?><span class="post-badge-org">Organizador</span><?php endif; ?>
    </div>

    <textarea id="composeText" placeholder="Escribe algo para el foro del evento..." maxlength="4000"></textarea>

    <div class="forum-img-preview" id="composeImgPreview"></div>

    <div class="forum-compose-actions">
      <label class="btn-img-upload" id="imgUploadLabel">
        <i class="fa-solid fa-image"></i> Imágenes
        <input type="file" id="imgFileInput" accept="image/jpeg,image/png,image/gif,image/webp" multiple style="display:none">
      </label>
      <span class="compose-char-count" id="charCount">0 / 4000</span>
      <div style="margin-left:auto;display:flex;gap:8px;">
        <button class="btn btn-outline btn-sm" id="btnCancelCompose">Cancelar</button>
        <button class="btn btn-primary btn-sm" id="btnSubmitPost">Publicar</button>
      </div>
    </div>
  </div>

  <!-- Posts list -->
  <div id="postsContainer"></div>

  <!-- Pagination -->
  <div class="forum-pagination" id="paginationBar"></div>

</div>

<!-- Lightbox -->
<div class="forum-lightbox" id="lightbox">
  <button class="lightbox-close" id="lightboxClose">✕</button>
  <button class="lightbox-nav prev" id="lightboxPrev">‹</button>
  <img id="lightboxImg" src="" alt="">
  <button class="lightbox-nav next" id="lightboxNext">›</button>
  <a class="lightbox-download" id="lightboxDownload" download>⬇ Descargar</a>
</div>

<!-- Report modal -->
<div class="forum-modal-overlay" id="reportModal">
  <div class="forum-modal">
    <h3>🚩 Reportar contenido</h3>
    <label>Motivo</label>
    <select id="reportReason">
      <option value="spam">Spam o publicidad</option>
      <option value="offensive">Contenido ofensivo</option>
      <option value="inappropriate">Imágenes inapropiadas</option>
      <option value="other">Otro</option>
    </select>
    <label>Descripción adicional (opcional)</label>
    <textarea id="reportDesc" placeholder="Añade más detalles..." rows="3" maxlength="500"></textarea>
    <div class="forum-modal-actions">
      <button class="btn btn-outline btn-sm" id="btnCancelReport">Cancelar</button>
      <button class="btn btn-danger btn-sm" id="btnSubmitReport">Enviar reporte</button>
    </div>
  </div>
</div>

<script>
const FORUM_EVENT_ID  = <?= $eventId ?>;
const CURRENT_USER_ID = <?= (int)$me['id'] ?>;
const IS_ADMIN        = <?= $isAdmin ? 'true' : 'false' ?>;

/* ── State ──────────────────────────────────────────────────────── */
let currentPage  = 1;
let currentSort  = 'recent';
let pendingImgIds = [];
let currentPosts  = [];
let lightboxImages = [];
let lightboxIndex  = 0;
let reportTarget   = null;

/* ── Utils ──────────────────────────────────────────────────────── */
function esc(s) {
  const d = document.createElement('div');
  d.textContent = s ?? '';
  return d.innerHTML;
}

function timeAgo(dateStr) {
  const diff = (Date.now() - new Date(dateStr).getTime()) / 1000;
  if (diff < 60)   return 'ahora mismo';
  if (diff < 3600) return Math.floor(diff/60) + 'm';
  if (diff < 86400)return Math.floor(diff/3600) + 'h';
  return Math.floor(diff/86400) + 'd';
}

function avatarHtml(u, size = 38) {
  const initials = (u.full_name || u.username || '?')[0].toUpperCase();
  if (u.avatar) {
    return `<div class="post-avatar" style="width:${size}px;height:${size}px;"><img src="${esc(u.avatar)}" alt=""></div>`;
  }
  return `<div class="post-avatar" style="width:${size}px;height:${size}px;">${initials}</div>`;
}

function showFlash(msg, type = 'success') {
  const el = document.createElement('div');
  el.className = 'flash-msg flash-' + type;
  el.textContent = msg;
  el.style.cssText = 'position:fixed;top:80px;right:20px;z-index:9999;background:var(--card);border:1px solid var(--border);border-radius:10px;padding:12px 18px;font-size:13px;box-shadow:0 8px 24px rgba(0,0,0,.15);max-width:320px;';
  if (type === 'success') el.style.borderLeftColor = 'var(--green)';
  if (type === 'error')   el.style.borderLeftColor = 'var(--red)';
  document.body.appendChild(el);
  setTimeout(() => el.remove(), 3500);
}

/* ── Load posts ─────────────────────────────────────────────────── */
async function loadPosts(page = 1, sort = currentSort) {
  currentPage = page;
  currentSort = sort;

  const container = document.getElementById('postsContainer');
  container.innerHTML = `
    <div class="skeleton skeleton-post"></div>
    <div class="skeleton skeleton-post"></div>
    <div class="skeleton skeleton-post"></div>
  `;

  try {
    const res  = await fetch(`/citylive/api/forum/posts.php?event=${FORUM_EVENT_ID}&page=${page}&sort=${sort}`);
    const data = await res.json();
    if (!data.success) { container.innerHTML = '<p style="color:var(--red);padding:20px;">Error al cargar posts.</p>'; return; }

    currentPosts = data.posts;
    renderPosts(data.posts, container);
    renderPagination(data.page, data.pages);
  } catch (e) {
    container.innerHTML = '<p style="color:var(--red);padding:20px;">Error de conexión.</p>';
  }
}

/* ── Render posts ───────────────────────────────────────────────── */
function renderPosts(posts, container) {
  if (!posts.length) {
    container.innerHTML = `
      <div class="forum-empty">
        <div class="forum-empty-icon">💬</div>
        <h3>Sin publicaciones aún</h3>
        <p>Sé el primero en escribir algo en el foro de este evento.</p>
      </div>`;
    return;
  }
  container.innerHTML = posts.map(p => renderPostHtml(p)).join('');
  container.querySelectorAll('.forum-post').forEach(el => attachPostEvents(el));
}

function renderPostHtml(p) {
  const avatarEl = p.avatar
    ? `<img src="${esc(p.avatar)}" alt="">`
    : (p.full_name || p.username || '?')[0].toUpperCase();

  const imgGrid = p.images.length
    ? `<div class="post-images count-${Math.min(p.images.length,4)}" data-post-id="${p.id}">
        ${p.images.map((img,idx) =>
          `<div class="post-img-item" data-idx="${idx}" data-post-id="${p.id}">
            <img src="${esc(img.url)}" loading="lazy" alt="Imagen ${idx+1}">
           </div>`
        ).join('')}
       </div>`
    : '';

  const contentLines = (p.content || '').split('\n').length > 12 || p.content.length > 800;
  const contentClass = contentLines ? 'post-content collapsed' : 'post-content';
  const expandBtn    = contentLines
    ? `<button class="post-expand-btn" data-expand="true">Leer más ↓</button>` : '';

  return `
  <div class="forum-post ${p.is_pinned ? 'pinned' : ''}" data-post-id="${p.id}" data-user-liked="${p.user_liked ? '1' : '0'}">
    <div class="post-head">
      <div class="post-avatar">${avatarEl}</div>
      <div class="post-meta">
        <div class="post-meta-top">
          <span class="post-username">${esc(p.full_name || p.username)}</span>
          <span style="font-size:12px;color:var(--text3);">@${esc(p.username)}</span>
          ${p.is_event_org ? '<span class="post-badge-org">Organizador</span>' : ''}
          ${p.is_pinned   ? '<span class="post-badge-pin">📌 Fijado</span>' : ''}
        </div>
        <span class="post-time">${timeAgo(p.created_at)}</span>
      </div>
      <div style="position:relative;">
        <button class="post-menu-btn" data-post-id="${p.id}">⋯</button>
        <div class="forum-dropdown" id="menu-${p.id}" style="display:none;right:0;top:28px;">
          ${p.can_edit ? `
            <button class="edit-post-btn" data-post-id="${p.id}">✏️ Editar</button>
            <button class="delete-post-btn danger" data-post-id="${p.id}">🗑️ Eliminar</button>
          ` : `
            <button class="report-btn" data-type="post" data-id="${p.id}">🚩 Reportar</button>
          `}
          ${IS_ADMIN && !p.can_edit ? `
            <button class="mod-pin-btn" data-post-id="${p.id}" data-pinned="${p.is_pinned ? '1' : '0'}">
              ${p.is_pinned ? '📌 Desfijar' : '📌 Fijar'}
            </button>
            <button class="mod-hide-btn danger" data-post-id="${p.id}">🙈 Ocultar</button>
            <button class="mod-del-btn danger" data-post-id="${p.id}">🗑️ Eliminar (mod)</button>
          ` : ''}
        </div>
      </div>
    </div>

    <div class="${contentClass}" id="content-${p.id}">${esc(p.content)}</div>
    ${expandBtn}
    ${imgGrid}

    <!-- Edit box (hidden) -->
    <textarea class="post-edit-box" id="edit-box-${p.id}" maxlength="4000">${esc(p.content)}</textarea>
    <div class="post-edit-actions" id="edit-actions-${p.id}">
      <button class="btn btn-sm btn-outline cancel-edit" data-post-id="${p.id}">Cancelar</button>
      <button class="btn btn-sm btn-primary save-edit" data-post-id="${p.id}">Guardar</button>
    </div>

    <div class="post-actions">
      <button class="post-action-btn like-btn ${p.user_liked ? 'liked' : ''}" data-post-id="${p.id}">
        ${p.user_liked ? '❤️' : '🤍'} <span class="like-count">${p.like_count}</span>
      </button>
      <button class="post-action-btn toggle-comments-btn" data-post-id="${p.id}">
        💬 <span class="comment-count">${p.comment_count}</span>
      </button>
      <button class="post-action-btn reply-btn" data-post-id="${p.id}">↩ Responder</button>
    </div>

    <!-- Comments section -->
    <div class="comments-section" id="comments-${p.id}">
      <div class="comment-list" id="comment-list-${p.id}"></div>
      <div class="comment-compose" id="compose-${p.id}">
        <div class="post-avatar" style="width:28px;height:28px;">
          ${p.avatar ? `<img src="${esc(p.avatar)}" alt="">` : (p.full_name || p.username || '?')[0].toUpperCase()}
        </div>
        <textarea class="comment-textarea" data-post-id="${p.id}" placeholder="Escribe un comentario..." rows="1"></textarea>
        <button class="comment-compose-send send-comment-btn" data-post-id="${p.id}">Enviar</button>
      </div>
    </div>
  </div>`;
}

function attachPostEvents(el) {
  const postId = parseInt(el.dataset.postId);

  // Menu toggle
  el.querySelector('.post-menu-btn')?.addEventListener('click', function(e) {
    e.stopPropagation();
    const menu = document.getElementById('menu-' + postId);
    document.querySelectorAll('.forum-dropdown').forEach(m => { if (m !== menu) m.style.display = 'none'; });
    menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
  });

  // Like
  el.querySelector('.like-btn')?.addEventListener('click', () => toggleLike('post', postId, el));

  // Toggle comments
  el.querySelector('.toggle-comments-btn')?.addEventListener('click', () => toggleComments(postId));

  // Reply button
  el.querySelector('.reply-btn')?.addEventListener('click', () => {
    toggleComments(postId, true);
  });

  // Expand content
  el.querySelector('.post-expand-btn')?.addEventListener('click', function() {
    const content = document.getElementById('content-' + postId);
    content.classList.remove('collapsed');
    this.remove();
  });

  // Image lightbox
  el.querySelectorAll('.post-img-item').forEach(imgEl => {
    imgEl.addEventListener('click', () => {
      const pid = parseInt(imgEl.dataset.postId);
      const post = currentPosts.find(p => p.id === pid);
      if (post) openLightbox(post.images, parseInt(imgEl.dataset.idx));
    });
  });

  // Edit
  el.querySelector('.edit-post-btn')?.addEventListener('click', () => {
    document.getElementById('menu-' + postId).style.display = 'none';
    startEditPost(postId);
  });

  el.querySelector('.cancel-edit')?.addEventListener('click', () => cancelEditPost(postId, el));
  el.querySelector('.save-edit')?.addEventListener('click', () => saveEditPost(postId, el));

  // Delete
  el.querySelector('.delete-post-btn')?.addEventListener('click', async () => {
    document.getElementById('menu-' + postId).style.display = 'none';
    if (!confirm('¿Eliminar esta publicación? Esta acción no se puede deshacer.')) return;
    await deletePost(postId, el);
  });

  // Report
  el.querySelector('.report-btn')?.addEventListener('click', () => {
    document.getElementById('menu-' + postId).style.display = 'none';
    reportTarget = { type: 'post', id: postId };
    document.getElementById('reportModal').classList.add('open');
  });

  // Mod actions
  el.querySelector('.mod-pin-btn')?.addEventListener('click', async function() {
    document.getElementById('menu-' + postId).style.display = 'none';
    const isPinned = this.dataset.pinned === '1';
    await modAction(isPinned ? 'unpin' : 'pin', 'post', postId);
    loadPosts(currentPage, currentSort);
  });

  el.querySelector('.mod-hide-btn')?.addEventListener('click', async () => {
    document.getElementById('menu-' + postId).style.display = 'none';
    if (!confirm('¿Ocultar este post?')) return;
    await modAction('hide', 'post', postId);
    el.remove();
  });

  el.querySelector('.mod-del-btn')?.addEventListener('click', async () => {
    document.getElementById('menu-' + postId).style.display = 'none';
    if (!confirm('¿Eliminar este post (acción de moderación)?')) return;
    await modAction('delete', 'post', postId);
    el.remove();
  });

  // Send comment
  el.querySelector('.send-comment-btn')?.addEventListener('click', () => sendComment(postId, null, el));

  // Auto-resize comment textarea
  el.querySelector('.comment-textarea')?.addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 120) + 'px';
  });
  el.querySelector('.comment-textarea')?.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      sendComment(postId, null, el);
    }
  });
}

/* ── Like ───────────────────────────────────────────────────────── */
async function toggleLike(type, id, postEl) {
  const btn  = postEl.querySelector('.like-btn');
  if (!btn || btn.disabled) return;
  btn.disabled = true;

  try {
    const res  = await fetch('/citylive/api/forum/likes.php', {
      method: 'POST', headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ target_type: type, target_id: id })
    });
    const data = await res.json();
    if (data.success) {
      btn.classList.toggle('liked', data.liked);
      btn.querySelector('.like-count').textContent = data.like_count;
      btn.innerHTML = (data.liked ? '❤️' : '🤍') + ' <span class="like-count">' + data.like_count + '</span>';
      // Update currentPosts state
      const p = currentPosts.find(p => p.id === id);
      if (p) { p.user_liked = data.liked; p.like_count = data.like_count; }
    }
  } catch(e) {}
  btn.disabled = false;
}

/* ── Comments ────────────────────────────────────────────────────── */
async function toggleComments(postId, forceOpen = false) {
  const section  = document.getElementById('comments-' + postId);
  const isOpen   = section.classList.contains('open');

  if (isOpen && !forceOpen) {
    section.classList.remove('open');
    return;
  }

  section.classList.add('open');
  const list = document.getElementById('comment-list-' + postId);
  if (list.dataset.loaded) return;
  list.dataset.loaded = '1';

  list.innerHTML = '<div class="skeleton" style="height:40px;margin:8px 0;border-radius:8px;"></div>'.repeat(2);

  try {
    const res  = await fetch('/citylive/api/forum/comments.php?post_id=' + postId);
    const data = await res.json();
    if (data.success) renderComments(data.comments, postId);
  } catch(e) {
    list.innerHTML = '<p style="color:var(--red);font-size:13px;">Error al cargar comentarios.</p>';
  }
}

function renderComments(comments, postId) {
  const list = document.getElementById('comment-list-' + postId);
  if (!comments.length) {
    list.innerHTML = '<p style="font-size:13px;color:var(--text3);padding:8px 0;">Sin comentarios. ¡Sé el primero!</p>';
    return;
  }
  list.innerHTML = comments.map(c => renderCommentHtml(c, postId, false)).join('');
  list.querySelectorAll('.comment-item').forEach(el => attachCommentEvents(el, postId));
}

function renderCommentHtml(c, postId, isReply) {
  const initials = (c.full_name || c.username || '?')[0].toUpperCase();
  const avatarEl = c.avatar ? `<img src="${esc(c.avatar)}" alt="">` : initials;

  const repliesHtml = (!isReply && c.replies?.length)
    ? `<div class="comment-replies">${c.replies.map(r => renderCommentHtml(r, postId, true)).join('')}</div>`
    : '';

  return `
  <div class="comment-item" data-comment-id="${c.id}" data-post-id="${postId}">
    <div class="comment-avatar">${avatarEl}</div>
    <div class="comment-body">
      <div class="comment-bubble">
        <div class="comment-username">${esc(c.full_name || c.username)}</div>
        <div class="comment-text">${esc(c.content)}</div>
      </div>
      <div class="comment-footer">
        <span class="comment-time">${timeAgo(c.created_at)}</span>
        <button class="comment-action like-comment-btn ${c.user_liked ? 'liked' : ''}"
                data-comment-id="${c.id}" data-post-id="${postId}">
          ${c.user_liked ? '❤️' : '🤍'} <span class="comment-like-count">${c.like_count}</span>
        </button>
        ${!isReply ? `<button class="comment-action reply-comment-btn" data-comment-id="${c.id}" data-post-id="${postId}">Responder</button>` : ''}
        ${c.can_edit ? `<button class="comment-action edit-comment-btn" data-comment-id="${c.id}">Editar</button>
                        <button class="comment-action danger delete-comment-btn" data-comment-id="${c.id}" data-post-id="${postId}">Eliminar</button>` : ''}
        ${!c.can_edit ? `<button class="comment-action report-comment-btn" data-comment-id="${c.id}">Reportar</button>` : ''}
        ${IS_ADMIN && !c.can_edit ? `<button class="comment-action danger mod-del-comment-btn" data-comment-id="${c.id}" data-post-id="${postId}">Mod:Eliminar</button>` : ''}
      </div>
      ${repliesHtml}
    </div>
  </div>`;
}

function attachCommentEvents(el, postId) {
  const commentId = parseInt(el.dataset.commentId);

  el.querySelector('.like-comment-btn')?.addEventListener('click', async function() {
    this.disabled = true;
    try {
      const res  = await fetch('/citylive/api/forum/likes.php', {
        method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ target_type: 'comment', target_id: commentId })
      });
      const data = await res.json();
      if (data.success) {
        this.classList.toggle('liked', data.liked);
        this.querySelector('.comment-like-count').textContent = data.like_count;
        this.innerHTML = (data.liked ? '❤️' : '🤍') + ' <span class="comment-like-count">' + data.like_count + '</span>';
      }
    } catch(e) {}
    this.disabled = false;
  });

  el.querySelector('.reply-comment-btn')?.addEventListener('click', () => {
    // Prepopulate compose with @mention
    const textarea = document.querySelector(`#compose-${postId} .comment-textarea`);
    if (textarea) {
      textarea.dataset.parentId = commentId;
      textarea.placeholder = 'Respondiendo a @' + (el.querySelector('.comment-username')?.textContent || '');
      textarea.focus();
    }
  });

  el.querySelector('.delete-comment-btn')?.addEventListener('click', async () => {
    if (!confirm('¿Eliminar este comentario?')) return;
    try {
      const res  = await fetch('/citylive/api/forum/comments.php', {
        method: 'DELETE', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ comment_id: commentId })
      });
      const data = await res.json();
      if (data.success) {
        el.remove();
        updateCommentCount(postId, -1);
      } else showFlash(data.error || 'Error', 'error');
    } catch(e) {}
  });

  el.querySelector('.report-comment-btn')?.addEventListener('click', () => {
    reportTarget = { type: 'comment', id: commentId };
    document.getElementById('reportModal').classList.add('open');
  });

  el.querySelector('.mod-del-comment-btn')?.addEventListener('click', async () => {
    if (!confirm('¿Eliminar comentario (moderación)?')) return;
    await modAction('delete', 'comment', commentId);
    el.remove();
    updateCommentCount(postId, -1);
  });

  el.querySelector('.edit-comment-btn')?.addEventListener('click', () => {
    const textEl = el.querySelector('.comment-text');
    const orig   = textEl.textContent;
    const input  = document.createElement('textarea');
    input.value  = orig;
    input.style.cssText = 'width:100%;border:1px solid var(--primary);border-radius:6px;padding:6px;font-size:13px;resize:none;margin-top:4px;';
    input.rows = 3;
    textEl.replaceWith(input);
    const saveBtn   = document.createElement('button');
    saveBtn.textContent = 'Guardar';
    saveBtn.className   = 'btn btn-sm btn-primary';
    saveBtn.style.marginTop = '4px';
    const cancelBtn = document.createElement('button');
    cancelBtn.textContent = 'Cancelar';
    cancelBtn.className   = 'btn btn-sm btn-outline';
    cancelBtn.style.marginTop = '4px';
    cancelBtn.style.marginLeft = '6px';
    input.after(saveBtn, cancelBtn);

    cancelBtn.addEventListener('click', () => {
      const newText = document.createElement('div');
      newText.className = 'comment-text';
      newText.textContent = orig;
      input.replaceWith(newText);
      saveBtn.remove(); cancelBtn.remove();
    });

    saveBtn.addEventListener('click', async () => {
      const newContent = input.value.trim();
      if (!newContent) return;
      try {
        const res  = await fetch('/citylive/api/forum/comments.php', {
          method: 'PATCH', headers: {'Content-Type':'application/json'},
          body: JSON.stringify({ comment_id: commentId, content: newContent })
        });
        const data = await res.json();
        if (data.success) {
          const newText = document.createElement('div');
          newText.className = 'comment-text';
          newText.textContent = data.content;
          input.replaceWith(newText);
          saveBtn.remove(); cancelBtn.remove();
        } else showFlash(data.error || 'Error', 'error');
      } catch(e) {}
    });
  });
}

async function sendComment(postId, parentId, postEl) {
  const compose  = document.getElementById('compose-' + postId);
  const textarea = compose.querySelector('.comment-textarea');
  const content  = textarea.value.trim();
  const pid      = parentId ?? (textarea.dataset.parentId ? parseInt(textarea.dataset.parentId) : null);

  if (!content) return;

  const sendBtn = compose.querySelector('.send-comment-btn');
  sendBtn.disabled = true;

  try {
    const res  = await fetch('/citylive/api/forum/comments.php', {
      method: 'POST', headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ post_id: postId, parent_id: pid, content })
    });
    const data = await res.json();
    if (data.success) {
      textarea.value = '';
      textarea.style.height = 'auto';
      delete textarea.dataset.parentId;
      textarea.placeholder = 'Escribe un comentario...';

      const list = document.getElementById('comment-list-' + postId);
      list.dataset.loaded = '1';
      const html = renderCommentHtml(data.comment, postId, !!pid);

      if (pid) {
        // Add as reply under parent
        const parentEl = list.querySelector(`[data-comment-id="${pid}"]`);
        if (parentEl) {
          let repliesEl = parentEl.querySelector('.comment-replies');
          if (!repliesEl) {
            repliesEl = document.createElement('div');
            repliesEl.className = 'comment-replies';
            parentEl.querySelector('.comment-body').appendChild(repliesEl);
          }
          repliesEl.insertAdjacentHTML('beforeend', html);
          repliesEl.querySelectorAll('.comment-item:last-child').forEach(el => attachCommentEvents(el, postId));
        }
      } else {
        list.insertAdjacentHTML('beforeend', html);
        list.querySelectorAll('.comment-item:last-child').forEach(el => attachCommentEvents(el, postId));
      }

      updateCommentCount(postId, 1);
    } else {
      showFlash(data.error || 'Error al comentar', 'error');
    }
  } catch(e) {
    showFlash('Error de conexión', 'error');
  }
  sendBtn.disabled = false;
}

function updateCommentCount(postId, delta) {
  const btn = document.querySelector(`[data-post-id="${postId}"] .comment-count`);
  if (btn) btn.textContent = Math.max(0, parseInt(btn.textContent || '0') + delta);
  const p = currentPosts.find(x => x.id === postId);
  if (p) p.comment_count = Math.max(0, (p.comment_count || 0) + delta);
}

/* ── Edit post ───────────────────────────────────────────────────── */
function startEditPost(postId) {
  const contentEl = document.getElementById('content-' + postId);
  const editBox   = document.getElementById('edit-box-' + postId);
  const editActs  = document.getElementById('edit-actions-' + postId);
  contentEl.style.display = 'none';
  editBox.style.display   = 'block';
  editActs.classList.add('open');
  editBox.focus();
}

function cancelEditPost(postId, postEl) {
  const contentEl = document.getElementById('content-' + postId);
  const editBox   = document.getElementById('edit-box-' + postId);
  const editActs  = document.getElementById('edit-actions-' + postId);
  contentEl.style.display = '';
  editBox.style.display   = 'none';
  editActs.classList.remove('open');
}

async function saveEditPost(postId, postEl) {
  const editBox = document.getElementById('edit-box-' + postId);
  const content = editBox.value.trim();
  if (!content) return;
  try {
    const res  = await fetch('/citylive/api/forum/posts.php', {
      method: 'PATCH', headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ post_id: postId, content })
    });
    const data = await res.json();
    if (data.success) {
      const contentEl = document.getElementById('content-' + postId);
      contentEl.textContent = data.content;
      cancelEditPost(postId, postEl);
      const p = currentPosts.find(x => x.id === postId);
      if (p) p.content = data.content;
    } else {
      showFlash(data.error || 'Error al guardar', 'error');
    }
  } catch(e) {
    showFlash('Error de conexión', 'error');
  }
}

async function deletePost(postId, postEl) {
  try {
    const res  = await fetch('/citylive/api/forum/posts.php', {
      method: 'DELETE', headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ post_id: postId })
    });
    const data = await res.json();
    if (data.success) {
      postEl.style.transition = 'opacity .3s';
      postEl.style.opacity    = '0';
      setTimeout(() => postEl.remove(), 300);
      const statEl = document.getElementById('stat-posts');
      if (statEl) statEl.textContent = Math.max(0, parseInt(statEl.textContent) - 1);
    } else {
      showFlash(data.error || 'Error al eliminar', 'error');
    }
  } catch(e) {
    showFlash('Error de conexión', 'error');
  }
}

/* ── Moderation ─────────────────────────────────────────────────── */
async function modAction(action, type, id) {
  try {
    const res  = await fetch('/citylive/api/forum/moderate.php', {
      method: 'POST', headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ action, target_type: type, target_id: id })
    });
    const data = await res.json();
    if (!data.success) showFlash(data.error || 'Error', 'error');
    else showFlash('Acción completada', 'success');
  } catch(e) {
    showFlash('Error de conexión', 'error');
  }
}

/* ── Image upload ────────────────────────────────────────────────── */
const imgInput   = document.getElementById('imgFileInput');
const imgPreview = document.getElementById('composeImgPreview');

imgInput?.addEventListener('change', async function() {
  if (!this.files.length) return;
  if (pendingImgIds.length + this.files.length > 5) {
    showFlash('Máximo 5 imágenes por publicación', 'error');
    this.value = '';
    return;
  }

  const form = new FormData();
  Array.from(this.files).forEach(f => form.append('images[]', f));

  const label = document.getElementById('imgUploadLabel');
  label.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Subiendo...';

  try {
    const res  = await fetch('/citylive/api/forum/upload.php', { method: 'POST', body: form });
    const data = await res.json();
    if (data.images?.length) {
      data.images.forEach(img => {
        pendingImgIds.push(img.id);
        const item = document.createElement('div');
        item.className = 'forum-img-preview-item';
        item.dataset.imgId = img.id;
        item.innerHTML = `<img src="${esc(img.url)}" alt="">
          <button class="rm-img" data-img-id="${img.id}">✕</button>`;
        imgPreview.appendChild(item);
        item.querySelector('.rm-img').addEventListener('click', function() {
          const id = parseInt(this.dataset.imgId);
          pendingImgIds = pendingImgIds.filter(x => x !== id);
          item.remove();
        });
      });
    }
    if (data.errors?.length) showFlash(data.errors[0], 'error');
  } catch(e) {
    showFlash('Error al subir imágenes', 'error');
  }
  label.innerHTML = '<i class="fa-solid fa-image"></i> Imágenes';
  this.value = '';
});

/* ── Compose post ────────────────────────────────────────────────── */
const composeBox    = document.getElementById('composeBox');
const composeText   = document.getElementById('composeText');
const charCount     = document.getElementById('charCount');

document.getElementById('btnOpenCompose')?.addEventListener('click', () => {
  composeBox.classList.toggle('open');
  if (composeBox.classList.contains('open')) composeText.focus();
});

document.getElementById('btnCancelCompose')?.addEventListener('click', () => {
  composeBox.classList.remove('open');
  composeText.value = '';
  imgPreview.innerHTML = '';
  pendingImgIds = [];
});

composeText?.addEventListener('input', function() {
  charCount.textContent = this.value.length + ' / 4000';
  charCount.classList.toggle('warn', this.value.length > 3800);
});

document.getElementById('btnSubmitPost')?.addEventListener('click', async function() {
  const content = composeText.value.trim();
  if (!content && !pendingImgIds.length) {
    showFlash('Escribe algo antes de publicar', 'error');
    return;
  }

  this.disabled = true;
  this.textContent = 'Publicando...';

  try {
    const res  = await fetch('/citylive/api/forum/posts.php', {
      method: 'POST', headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ event_id: FORUM_EVENT_ID, content, image_ids: pendingImgIds })
    });
    const data = await res.json();
    if (data.success) {
      composeText.value = '';
      imgPreview.innerHTML = '';
      pendingImgIds = [];
      charCount.textContent = '0 / 4000';
      composeBox.classList.remove('open');

      // Prepend new post
      currentPosts.unshift(data.post);
      const container = document.getElementById('postsContainer');
      const emptyEl   = container.querySelector('.forum-empty');
      if (emptyEl) emptyEl.remove();

      const tempDiv = document.createElement('div');
      tempDiv.innerHTML = renderPostHtml(data.post);
      const postEl = tempDiv.firstElementChild;
      container.prepend(postEl);
      attachPostEvents(postEl);
      postEl.style.animation = 'none';

      const statEl = document.getElementById('stat-posts');
      if (statEl) statEl.textContent = parseInt(statEl.textContent || '0') + 1;

      showFlash('Publicación creada', 'success');
    } else {
      showFlash(data.error || 'Error al publicar', 'error');
    }
  } catch(e) {
    showFlash('Error de conexión', 'error');
  }

  this.disabled = false;
  this.textContent = 'Publicar';
});

/* ── Sort ───────────────────────────────────────────────────────── */
document.querySelectorAll('.forum-sort-btn').forEach(btn => {
  btn.addEventListener('click', function() {
    document.querySelectorAll('.forum-sort-btn').forEach(b => b.classList.remove('active'));
    this.classList.add('active');
    loadPosts(1, this.dataset.sort);
  });
});

/* ── Pagination ─────────────────────────────────────────────────── */
function renderPagination(page, pages) {
  const bar = document.getElementById('paginationBar');
  if (pages <= 1) { bar.innerHTML = ''; return; }
  let html = '';
  for (let i = 1; i <= pages; i++) {
    html += `<button class="forum-page-btn ${i === page ? 'active' : ''}" data-page="${i}">${i}</button>`;
  }
  bar.innerHTML = html;
  bar.querySelectorAll('.forum-page-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      loadPosts(parseInt(btn.dataset.page), currentSort);
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  });
}

/* ── Lightbox ────────────────────────────────────────────────────── */
function openLightbox(images, startIdx) {
  lightboxImages = images;
  lightboxIndex  = startIdx;
  showLightboxImage();
  document.getElementById('lightbox').classList.add('open');
}

function showLightboxImage() {
  const img  = lightboxImages[lightboxIndex];
  const el   = document.getElementById('lightboxImg');
  const dl   = document.getElementById('lightboxDownload');
  el.src     = img.url;
  dl.href    = img.url;
  dl.download = 'imagen-foro-' + (lightboxIndex + 1) + '.jpg';
  document.getElementById('lightboxPrev').style.display = lightboxImages.length > 1 ? '' : 'none';
  document.getElementById('lightboxNext').style.display = lightboxImages.length > 1 ? '' : 'none';
}

document.getElementById('lightboxClose')?.addEventListener('click', () => {
  document.getElementById('lightbox').classList.remove('open');
});
document.getElementById('lightboxPrev')?.addEventListener('click', () => {
  lightboxIndex = (lightboxIndex - 1 + lightboxImages.length) % lightboxImages.length;
  showLightboxImage();
});
document.getElementById('lightboxNext')?.addEventListener('click', () => {
  lightboxIndex = (lightboxIndex + 1) % lightboxImages.length;
  showLightboxImage();
});
document.getElementById('lightbox')?.addEventListener('click', function(e) {
  if (e.target === this) this.classList.remove('open');
});
document.addEventListener('keydown', e => {
  const lb = document.getElementById('lightbox');
  if (!lb.classList.contains('open')) return;
  if (e.key === 'Escape') lb.classList.remove('open');
  if (e.key === 'ArrowLeft') { lightboxIndex = (lightboxIndex - 1 + lightboxImages.length) % lightboxImages.length; showLightboxImage(); }
  if (e.key === 'ArrowRight') { lightboxIndex = (lightboxIndex + 1) % lightboxImages.length; showLightboxImage(); }
});

/* ── Report modal ────────────────────────────────────────────────── */
document.getElementById('btnCancelReport')?.addEventListener('click', () => {
  document.getElementById('reportModal').classList.remove('open');
  reportTarget = null;
});

document.getElementById('btnSubmitReport')?.addEventListener('click', async function() {
  if (!reportTarget) return;
  this.disabled = true;

  try {
    const res  = await fetch('/citylive/api/forum/report.php', {
      method: 'POST', headers: {'Content-Type':'application/json'},
      body: JSON.stringify({
        target_type: reportTarget.type,
        target_id: reportTarget.id,
        reason: document.getElementById('reportReason').value,
        description: document.getElementById('reportDesc').value,
      })
    });
    const data = await res.json();
    showFlash(data.success ? data.message : data.error, data.success ? 'success' : 'error');
    document.getElementById('reportModal').classList.remove('open');
    document.getElementById('reportDesc').value = '';
    reportTarget = null;
  } catch(e) {
    showFlash('Error al enviar reporte', 'error');
  }
  this.disabled = false;
});

/* ── Close dropdowns on outside click ────────────────────────────── */
document.addEventListener('click', e => {
  if (!e.target.closest('.post-menu-btn') && !e.target.closest('.forum-dropdown')) {
    document.querySelectorAll('.forum-dropdown').forEach(m => m.style.display = 'none');
  }
});

/* ── Init ────────────────────────────────────────────────────────── */
loadPosts(1, 'recent');
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
