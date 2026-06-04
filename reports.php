<?php
require_once __DIR__ . '/config/db.php';
require_once 'config.php';
requireLogin();

$user = currentUser();
if (($user['plan'] ?? 'free') !== 'platinum') {
    header('Location: ' . BASE . '/dashboard.php');
    exit;
}

$db = getDB();

// Acción: cambiar estado de un reporte
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reportId = (int)($_POST['report_id'] ?? 0);
    $action   = $_POST['action'] ?? '';
    if ($reportId && in_array($action, ['reviewed', 'dismissed'], true)) {
        $db->prepare('UPDATE publication_reports SET status = ? WHERE id = ?')
           ->execute([$action, $reportId]);
    }
    header('Location: ' . BASE . '/reports.php');
    exit;
}

$filter = $_GET['filter'] ?? 'pending';
$validFilters = ['pending', 'reviewed', 'dismissed', 'all'];
if (!in_array($filter, $validFilters, true)) {
    $filter = 'pending';
}

$where = $filter === 'all' ? '' : "WHERE pr.status = '$filter'";

$reports = $db->query("
    SELECT pr.id, pr.reason, pr.description, pr.status, pr.created_at,
           reporter.username AS reporter_username, reporter.full_name AS reporter_name,
           p.id AS pub_id, p.title AS pub_title, p.type AS pub_type,
           owner.id AS owner_id, owner.username AS owner_username, owner.full_name AS owner_name
    FROM publication_reports pr
    JOIN users reporter ON reporter.id = pr.reporter_id
    JOIN publications p  ON p.id = pr.publication_id
    JOIN users owner     ON owner.id = p.user_id
    $where
    ORDER BY pr.created_at DESC
    LIMIT 100
")->fetchAll();

$counts = $db->query("
    SELECT status, COUNT(*) AS n FROM publication_reports GROUP BY status
")->fetchAll(PDO::FETCH_KEY_PAIR);

$reasonLabel = [
    'spam'          => 'Spam / Publicidad',
    'false_info'    => 'Información falsa',
    'inappropriate' => 'Contenido inapropiado',
    'other'         => 'Otro',
];
$typeLabel = ['incident' => 'Incidencia', 'event' => 'Evento', 'activity' => 'Actividad'];

$pageTitle  = 'Panel de reportes';
$activePage = 'reports';
include __DIR__ . '/includes/header.php';
?>

<div class="page-content" style="max-width:960px;">

  <div class="page-header">
    <div>
      <h1><i class="fa-solid fa-flag" style="color:var(--red);margin-right:10px;"></i>Panel de reportes</h1>
      <p>Gestiona los reportes de publicaciones enviados por los usuarios.</p>
    </div>
  </div>

  <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:24px;">
    <?php foreach (['pending' => 'Pendientes', 'reviewed' => 'Revisados', 'dismissed' => 'Descartados', 'all' => 'Todos'] as $f => $label): ?>
      <?php $n = ($f === 'all') ? array_sum($counts) : ($counts[$f] ?? 0); ?>
      <a href="<?= BASE ?>/reports.php?filter=<?= $f ?>"
         class="filter-chip <?= $filter === $f ? 'active' : '' ?>"
         style="text-decoration:none;">
        <?= $label ?>
        <?php if ($n > 0): ?>
          <span style="background:<?= $f === 'pending' ? 'var(--red)' : 'var(--text3)' ?>;color:#fff;border-radius:20px;padding:1px 7px;font-size:10px;margin-left:4px;">
            <?= $n ?>
          </span>
        <?php endif; ?>
      </a>
    <?php endforeach; ?>
  </div>

  <?php if (empty($reports)): ?>
    <div class="card" style="text-align:center;padding:48px 24px;color:var(--text3);">
      <div style="font-size:40px;margin-bottom:12px;"><i class="fa-solid fa-circle-check" style="color:var(--green);"></i></div>
      <div style="font-size:16px;font-weight:700;color:var(--text);">Sin reportes <?= $filter === 'all' ? '' : ($filter === 'pending' ? 'pendientes' : $filter) ?></div>
      <div style="font-size:13px;margin-top:6px;">Todo en orden por ahora.</div>
    </div>
  <?php else: ?>
    <?php foreach ($reports as $r): ?>
    <div class="card mb-16" style="border-left:4px solid <?= $r['status'] === 'pending' ? 'var(--red)' : ($r['status'] === 'reviewed' ? 'var(--green)' : 'var(--border)') ?>;">
      <div style="display:flex;align-items:flex-start;gap:14px;flex-wrap:wrap;">

        <div style="flex:1;min-width:200px;">
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;flex-wrap:wrap;">
            <span class="badge badge-<?= $r['status'] === 'pending' ? 'red' : ($r['status'] === 'reviewed' ? 'primary' : 'gray') ?>">
              <?= $r['status'] === 'pending' ? 'Pendiente' : ($r['status'] === 'reviewed' ? 'Revisado' : 'Descartado') ?>
            </span>
            <span class="badge badge-gray"><?= $reasonLabel[$r['reason']] ?? $r['reason'] ?></span>
            <span style="font-size:11px;color:var(--text3);">
              <?= (new DateTime($r['created_at']))->format('d M Y · H:i') ?>
            </span>
          </div>

          <div style="font-size:13px;margin-bottom:6px;">
            <span style="color:var(--text3);">Publicación:</span>
            <a href="<?= BASE ?>/activity.php?id=<?= $r['pub_id'] ?>" style="font-weight:700;color:var(--text);">
              <?= htmlspecialchars($r['pub_title']) ?>
            </a>
            <span style="color:var(--text3);font-size:12px;"> · <?= $typeLabel[$r['pub_type']] ?? '' ?></span>
          </div>

          <div style="font-size:13px;margin-bottom:6px;">
            <span style="color:var(--text3);">Autor:</span>
            <a href="<?= BASE ?>/profile.php?id=<?= $r['owner_id'] ?>" style="color:var(--text);">
              <?= htmlspecialchars($r['owner_name'] ?: $r['owner_username']) ?>
              <span style="color:var(--text3);">(@<?= htmlspecialchars($r['owner_username']) ?>)</span>
            </a>
          </div>

          <div style="font-size:13px;margin-bottom:<?= $r['description'] ? '8px' : '0' ?>;">
            <span style="color:var(--text3);">Reportado por:</span>
            <span style="color:var(--text);">
              <?= htmlspecialchars($r['reporter_name'] ?: $r['reporter_username']) ?>
              (@<?= htmlspecialchars($r['reporter_username']) ?>)
            </span>
          </div>

          <?php if ($r['description']): ?>
          <div style="background:var(--bg);border-radius:var(--r-sm);padding:10px 12px;font-size:13px;color:var(--text2);line-height:1.5;">
            "<?= htmlspecialchars($r['description']) ?>"
          </div>
          <?php endif; ?>
        </div>

        <?php if ($r['status'] === 'pending'): ?>
        <form method="POST" style="display:flex;flex-direction:column;gap:8px;flex-shrink:0;">
          <input type="hidden" name="report_id" value="<?= $r['id'] ?>">
          <a href="<?= BASE ?>/activity.php?id=<?= $r['pub_id'] ?>" class="btn btn-outline btn-sm" target="_blank">
            <i class="fa-solid fa-eye"></i> Ver publicación
          </a>
          <button type="submit" name="action" value="reviewed" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-check"></i> Marcar revisado
          </button>
          <button type="submit" name="action" value="dismissed" class="btn btn-outline btn-sm">
            <i class="fa-solid fa-xmark"></i> Descartar
          </button>
        </form>
        <?php else: ?>
        <a href="<?= BASE ?>/activity.php?id=<?= $r['pub_id'] ?>" class="btn btn-outline btn-sm" target="_blank" style="flex-shrink:0;">
          <i class="fa-solid fa-eye"></i> Ver
        </a>
        <?php endif; ?>

      </div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
