<?php
require_once __DIR__ . '/config/db.php';
requireLogin();

$user = currentUser();
$db   = getDB();

$message = '';
$msgType = 'success';

// Handle plan upgrade (demo: just update plan and add tokens, no real payment)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['plan'])) {
    requireCsrf();
    $newPlan = $_POST['plan'];
    if (in_array($newPlan, ['free', 'pro', 'platinum'])) {
        $tokens = PLAN_TOKENS[$newPlan];

        $db->prepare('UPDATE users SET plan = ?, tokens_balance = tokens_balance + ? WHERE id = ?')
           ->execute([$newPlan, $tokens, $user['id']]);
        $db->prepare('INSERT INTO token_transactions (user_id, amount, type, description) VALUES (?, ?, "subscription", ?)')
           ->execute([$user['id'], $tokens, 'Activación plan ' . ucfirst($newPlan)]);
        $db->prepare('INSERT INTO subscriptions (user_id, plan, renews_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 MONTH))
                      ON DUPLICATE KEY UPDATE plan = VALUES(plan), renews_at = VALUES(renews_at)')
           ->execute([$user['id'], $newPlan]);

        $message = "¡Plan $newPlan activado correctamente! Se han añadido $tokens tokens a tu cuenta.";
        redirectTo('subscriptions.php?ok=1');
    }
}

// Reload user after possible update
$user = currentUser();

$pageTitle  = 'Suscripciones';
$activePage = 'subs';

include __DIR__ . '/includes/header.php';
?>

<div class="page-content" style="max-width:1000px;">
  <div class="page-header">
    <h1>💎 Planes y suscripciones</h1>
    <p>Elige el plan que mejor se adapte a tu actividad en la ciudad.</p>
  </div>

  <?php if (isset($_GET['ok'])): ?>
    <div class="flash flash-success">
      <i class="fa-solid fa-circle-check"></i>
      Plan actualizado correctamente. Los tokens se han añadido a tu cuenta.
    </div>
  <?php endif; ?>

  <!-- Current plan banner -->
  <div class="card mb-24" style="background:linear-gradient(135deg,rgba(0,212,255,.06),rgba(124,58,237,.06));border-color:rgba(0,212,255,.2);">
    <div style="display:flex;align-items:center;gap:14px;">
      <div style="font-size:36px;">
        <?= $user['plan'] === 'platinum' ? '💎' : ($user['plan'] === 'pro' ? '⭐' : '🆓') ?>
      </div>
      <div style="flex:1;">
        <div style="font-size:18px;font-weight:800;margin-bottom:3px;">
          Plan actual: <?= ucfirst($user['plan']) ?>
        </div>
        <div style="font-size:13px;color:var(--text2);">
          Saldo: <strong style="color:var(--primary);"><?= number_format($user['tokens_balance']) ?> tokens ⬡</strong>
        </div>
      </div>
      <a href="<?= appUrl('tokens.php') ?>" class="btn btn-outline btn-sm">Ver tokens →</a>
    </div>
  </div>

  <div class="plans-grid">

    <!-- FREE -->
    <div class="plan-card <?= $user['plan'] === 'free' ? 'highlighted' : '' ?>">
      <?php if ($user['plan'] === 'free'): ?>
        <div class="plan-badge-top popular">Plan actual</div>
      <?php endif; ?>
      <div class="plan-name">Gratuita</div>
      <div class="plan-price"><span class="gratis">Gratis</span></div>
      <div class="plan-tokens-row t-free">
        <span style="font-size:20px;">⬡</span>
        <div>
          <div class="tok-amount" style="color:var(--text2);">0 tokens</div>
          <div class="tok-lbl">al mes</div>
        </div>
      </div>
      <ul class="plan-features">
        <li><span class="fi">✅</span> Publicar incidencias y eventos</li>
        <li><span class="fi">✅</span> Ver el mapa en tiempo real</li>
        <li><span class="fi">✅</span> Perfil público básico</li>
        <li><span class="fi">✅</span> Comentar y valorar</li>
        <li><span class="fi">❌</span> Actividades lucrativas</li>
        <li><span class="fi">❌</span> Estadísticas avanzadas</li>
        <li><span class="fi">❌</span> Prioridad en el mapa</li>
      </ul>
      <form method="POST">
        <?= csrfInput() ?>
        <input type="hidden" name="plan" value="free">
        <button class="btn btn-outline btn-block <?= $user['plan'] === 'free' ? '' : '' ?>"
                <?= $user['plan'] === 'free' ? 'disabled' : '' ?>>
          <?= $user['plan'] === 'free' ? 'Plan activo' : 'Usar plan gratuito' ?>
        </button>
      </form>
    </div>

    <!-- PRO -->
    <div class="plan-card highlighted">
      <div class="plan-glow cyan"></div>
      <?php if ($user['plan'] === 'pro'): ?>
        <div class="plan-badge-top popular">Plan actual</div>
      <?php else: ?>
        <div class="plan-badge-top popular">⭐ Popular</div>
      <?php endif; ?>
      <div class="plan-name">Pro</div>
      <div class="plan-price">
        <span class="amount">9,99</span><span class="period">€/mes</span>
      </div>
      <div class="plan-tokens-row t-pro">
        <span style="font-size:20px;">⬡</span>
        <div>
          <div class="tok-amount" style="color:var(--primary);">1.000 tokens</div>
          <div class="tok-lbl">al mes</div>
        </div>
      </div>
      <ul class="plan-features">
        <li><span class="fi">✅</span> Todo lo de Gratuita</li>
        <li><span class="fi">✅</span> Actividades lucrativas (≤500 tokens)</li>
        <li><span class="fi">✅</span> Estadísticas básicas de publicaciones</li>
        <li><span class="fi">✅</span> Badge ⭐ Pro en tu perfil</li>
        <li><span class="fi">✅</span> Soporte prioritario</li>
        <li><span class="fi">❌</span> Actividades de gran alcance (&gt;500 tokens)</li>
        <li><span class="fi">❌</span> Prioridad máxima en el mapa</li>
      </ul>
      <form method="POST">
        <?= csrfInput() ?>
        <input type="hidden" name="plan" value="pro">
        <button class="btn btn-primary btn-block"
                <?= $user['plan'] === 'pro' ? 'disabled' : '' ?>>
          <?= $user['plan'] === 'pro' ? 'Plan activo' : 'Activar Pro — 9,99€/mes' ?>
        </button>
      </form>
      <p style="font-size:11px;color:var(--text3);text-align:center;margin-top:10px;">(Demo: activa sin pago real)</p>
    </div>

    <!-- PLATINUM -->
    <div class="plan-card premium">
      <div class="plan-glow purple"></div>
      <?php if ($user['plan'] === 'platinum'): ?>
        <div class="plan-badge-top premium">Plan actual</div>
      <?php else: ?>
        <div class="plan-badge-top premium">💎 Premium</div>
      <?php endif; ?>
      <div class="plan-name">Platinum</div>
      <div class="plan-price">
        <span class="amount">29,99</span><span class="period">€/mes</span>
      </div>
      <div class="plan-tokens-row t-plat">
        <span style="font-size:20px;">⬡</span>
        <div>
          <div class="tok-amount" style="color:var(--purple);">10.000 tokens</div>
          <div class="tok-lbl">al mes</div>
        </div>
      </div>
      <ul class="plan-features">
        <li><span class="fi">✅</span> Todo lo de Pro</li>
        <li><span class="fi">✅</span> Actividades de máximo alcance</li>
        <li><span class="fi">✅</span> Estadísticas avanzadas y KPIs</li>
        <li><span class="fi">✅</span> Prioridad máxima en el mapa</li>
        <li><span class="fi">✅</span> Badge 💎 Platinum verificado</li>
        <li><span class="fi">✅</span> Acceso a API de integración</li>
        <li><span class="fi">✅</span> Manager de cuenta dedicado</li>
      </ul>
      <form method="POST">
        <?= csrfInput() ?>
        <input type="hidden" name="plan" value="platinum">
        <button class="btn btn-purple btn-block"
                <?= $user['plan'] === 'platinum' ? 'disabled' : '' ?>>
          <?= $user['plan'] === 'platinum' ? 'Plan activo' : 'Activar Platinum — 29,99€/mes' ?>
        </button>
      </form>
      <p style="font-size:11px;color:var(--text3);text-align:center;margin-top:10px;">(Demo: activa sin pago real)</p>
    </div>

  </div>

  <!-- Token cost table -->
  <div class="card" style="margin-top:28px;max-width:600px;">
    <div class="card-title mb-16">📊 Coste de tokens por tipo de actividad</div>
    <table style="width:100%;border-collapse:collapse;font-size:14px;">
      <thead>
        <tr style="border-bottom:1px solid var(--border);color:var(--text3);font-size:12px;text-transform:uppercase;letter-spacing:.06em;">
          <th style="padding:8px 0;text-align:left;font-weight:600;">Tipo</th>
          <th style="padding:8px 0;text-align:right;font-weight:600;">Tokens</th>
          <th style="padding:8px 0;text-align:right;font-weight:600;">Plan mínimo</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $rows = [
          ['🚨 Incidencia',         'Gratis',     '🆓 Free'],
          ['🎉 Evento social',       'Gratis',     '🆓 Free'],
          ['⚡ Actividad pequeña',   '50–150 ⬡',  '⭐ Pro'],
          ['⚡ Actividad mediana',   '150–300 ⬡', '⭐ Pro'],
          ['⚡ Actividad grande',    '300–500 ⬡', '⭐ Pro'],
          ['⚡ Gran alcance',        '500–2000 ⬡','💎 Platinum'],
        ];
        foreach ($rows as [$t, $tok, $plan]):
        ?>
        <tr style="border-bottom:1px solid var(--border);">
          <td style="padding:11px 0;color:var(--text2);"><?= $t ?></td>
          <td style="padding:11px 0;text-align:right;font-weight:700;color:var(--primary);"><?= $tok ?></td>
          <td style="padding:11px 0;text-align:right;font-size:12px;color:var(--text3);"><?= $plan ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <p style="font-size:12px;color:var(--text3);margin-top:12px;">
      Los tokens no usados se acumulan hasta 3 meses. Las incidencias y eventos sociales nunca consumen tokens.
    </p>
  </div>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>


