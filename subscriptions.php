<?php
require_once __DIR__ . '/config/db.php';
requireLogin();

$user = currentUser();
$db   = getDB();

$message = '';
$msgType = 'success';

// Handle plan upgrade (demo: just update plan and add tokens, no real payment)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['plan'])) {
    $newPlan = $_POST['plan'];
    if (in_array($newPlan, ['free', 'pro', 'platinum'])) {
        // Si es un plan de pago, requerir Bizum
        if ($newPlan !== 'free') {
            $planPrices = ['pro' => '9,99€', 'platinum' => '29,99€'];
            $price = $planPrices[$newPlan] ?? 'el importe';
            header('Location: ' . BASE . '/subscriptions.php?payment_required=1&plan=' . urlencode($newPlan) . '&price=' . urlencode($price));
            exit;
        }
        
        // Solo procesar cambio a plan gratuito
        $tokens = PLAN_TOKENS[$newPlan];

        $db->prepare('UPDATE users SET plan = ?, tokens_balance = tokens_balance + ? WHERE id = ?')
           ->execute([$newPlan, $tokens, $user['id']]);
        $db->prepare('INSERT INTO token_transactions (user_id, amount, type, description) VALUES (?, ?, "subscription", ?)')
           ->execute([$user['id'], $tokens, 'Activación plan ' . ucfirst($newPlan)]);
        $db->prepare('INSERT INTO subscriptions (user_id, plan, renews_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 MONTH))
                      ON DUPLICATE KEY UPDATE plan = VALUES(plan), renews_at = VALUES(renews_at)')
           ->execute([$user['id'], $newPlan]);

        $message = "¡Plan $newPlan activado correctamente! Se han añadido $tokens tokens a tu cuenta.";
        header('Location: ' . BASE . '/subscriptions.php?ok=1');
        exit;
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
    <h1><i class="fa-solid fa-crown" style="color:var(--red);margin-right:10px;"></i>Planes y suscripciones</h1>
    <p>Elige el plan que mejor se adapte a tu actividad en la ciudad.</p>
  </div>

  <?php if (isset($_GET['ok'])): ?>
    <div class="flash flash-success">
      <i class="fa-solid fa-circle-check"></i>
      Plan actualizado correctamente. Los tokens se han añadido a tu cuenta.
    </div>
  <?php endif; ?>

  <?php if (isset($_GET['payment_required'])): ?>
    <div class="flash flash-warning">
      <i class="fa-solid fa-warning"></i>
      <strong>Pago requerido:</strong> Para cambiar al plan <strong><?= htmlspecialchars(ucfirst($_GET['plan'] ?? '')) ?></strong> (<?= htmlspecialchars($_GET['price'] ?? '') ?>/mes), debes realizar un pago mediante <strong>Bizum</strong>.
      <div style="margin-top:12px;padding-top:12px;border-top:1px solid rgba(255,255,255,.2);">
        <strong style="display:block;margin-bottom:8px;"><i class="fa-solid fa-mobile-screen-button" style="margin-right:6px;"></i>Envía un Bizum a:</strong>
        <div style="font-size:16px;font-weight:800;color:#fff;background:rgba(0,0,0,.3);padding:12px;border-radius:8px;margin-bottom:8px;">666 666 666</div>
        <strong style="display:block;margin-bottom:4px;">Importe:</strong>
        <div style="color:#fff;margin-bottom:12px;"><?= htmlspecialchars($_GET['price'] ?? '') ?> (primer mes)</div>
        <div style="font-size:12px;opacity:.9;">Una vez realizado el pago, tu plan se actualizará lo más pronto posible.</div>
      </div>
    </div>
  <?php endif; ?>

  <!-- Current plan banner -->
  <div class="card mb-24" style="background:linear-gradient(135deg,rgba(0,212,255,.06),rgba(124,58,237,.06));border-color:rgba(0,212,255,.2);">
    <div style="display:flex;align-items:center;gap:14px;">
      <div style="font-size:36px;">
        <i class="<?= $user['plan'] === 'platinum' ? 'fa-solid fa-crown' : ($user['plan'] === 'pro' ? 'fa-solid fa-bolt' : 'fa-regular fa-compass') ?>" style="color:var(--red);"></i>
      </div>
      <div style="flex:1;">
        <div style="font-size:18px;font-weight:800;margin-bottom:3px;">
          Plan actual: <?= ucfirst($user['plan']) ?>
        </div>
        <div style="font-size:13px;color:var(--text2);">
          Saldo: <strong style="color:var(--primary);"><i class="fa-solid fa-coins"></i> <?= number_format($user['tokens_balance']) ?> tokens</strong>
        </div>
      </div>
      <a href="<?= BASE ?>/tokens.php" class="btn btn-outline btn-sm">Ver tokens <i class="fa-solid fa-arrow-right"></i></a>
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
        <span style="font-size:20px;color:var(--primary);"><i class="fa-solid fa-coins"></i></span>
        <div>
          <div class="tok-amount" style="color:var(--text2);">0 tokens</div>
          <div class="tok-lbl">al mes</div>
        </div>
      </div>
      <ul class="plan-features">
        <li><span class="fi"><i class="fa-solid fa-check"></i></span> Publicar incidencias y eventos</li>
        <li><span class="fi"><i class="fa-solid fa-check"></i></span> Ver el mapa en tiempo real</li>
        <li><span class="fi"><i class="fa-solid fa-check"></i></span> Perfil público básico</li>
        <li><span class="fi"><i class="fa-solid fa-check"></i></span> Comentar y valorar</li>
        <li><span class="fi"><i class="fa-solid fa-xmark"></i></span> Actividades lucrativas</li>
        <li><span class="fi"><i class="fa-solid fa-xmark"></i></span> Estadísticas avanzadas</li>
        <li><span class="fi"><i class="fa-solid fa-xmark"></i></span> Prioridad en el mapa</li>
      </ul>
      <form method="POST">
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
        <div class="plan-badge-top popular"><i class="fa-solid fa-star"></i> Popular</div>
      <?php endif; ?>
      <div class="plan-name">Pro</div>
      <div class="plan-price">
        <span class="amount">9,99</span><span class="period">€/mes</span>
      </div>
      <div class="plan-tokens-row t-pro">
        <span style="font-size:20px;color:var(--primary);"><i class="fa-solid fa-coins"></i></span>
        <div>
          <div class="tok-amount" style="color:var(--primary);">1.000 tokens</div>
          <div class="tok-lbl">al mes</div>
        </div>
      </div>
      <ul class="plan-features">
        <li><span class="fi"><i class="fa-solid fa-check"></i></span> Todo lo de Gratuita</li>
        <li><span class="fi"><i class="fa-solid fa-check"></i></span> Actividades lucrativas (≤500 tokens)</li>
        <li><span class="fi"><i class="fa-solid fa-check"></i></span> Estadísticas básicas de publicaciones</li>
        <li><span class="fi"><i class="fa-solid fa-check"></i></span> Badge Pro en tu perfil</li>
        <li><span class="fi"><i class="fa-solid fa-check"></i></span> Soporte prioritario</li>
        <li><span class="fi"><i class="fa-solid fa-xmark"></i></span> Actividades de gran alcance (&gt;500 tokens)</li>
        <li><span class="fi"><i class="fa-solid fa-xmark"></i></span> Prioridad máxima en el mapa</li>
      </ul>
      <form method="POST">
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
        <div class="plan-badge-top premium"><i class="fa-solid fa-crown"></i> Premium</div>
      <?php endif; ?>
      <div class="plan-name">Platinum</div>
      <div class="plan-price">
        <span class="amount">29,99</span><span class="period">€/mes</span>
      </div>
      <div class="plan-tokens-row t-plat">
        <span style="font-size:20px;color:var(--purple);"><i class="fa-solid fa-coins"></i></span>
        <div>
          <div class="tok-amount" style="color:var(--purple);">10.000 tokens</div>
          <div class="tok-lbl">al mes</div>
        </div>
      </div>
      <ul class="plan-features">
        <li><span class="fi"><i class="fa-solid fa-check"></i></span> Todo lo de Pro</li>
        <li><span class="fi"><i class="fa-solid fa-check"></i></span> Actividades de máximo alcance</li>
        <li><span class="fi"><i class="fa-solid fa-check"></i></span> Estadísticas avanzadas y KPIs</li>
        <li><span class="fi"><i class="fa-solid fa-check"></i></span> Prioridad máxima en el mapa</li>
        <li><span class="fi"><i class="fa-solid fa-check"></i></span> Badge Platinum verificado</li>
        <li><span class="fi"><i class="fa-solid fa-check"></i></span> Acceso a API de integración</li>
        <li><span class="fi"><i class="fa-solid fa-check"></i></span> Manager de cuenta dedicado</li>
      </ul>
      <form method="POST">
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
    <div class="card-title mb-16"><i class="fa-solid fa-chart-column" style="color:var(--red);margin-right:8px;"></i>Coste de tokens por tipo de actividad</div>
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
          ['<i class="fa-solid fa-triangle-exclamation" style="margin-right:8px;color:#bf5347;"></i>Incidencia', 'Gratis', 'Free'],
          ['<i class="fa-solid fa-calendar-days" style="margin-right:8px;color:#d17b2f;"></i>Evento social', 'Gratis', 'Free'],
          ['<i class="fa-solid fa-bolt" style="margin-right:8px;color:#1690a7;"></i>Actividad pequeña', '50–150 tokens', 'Pro'],
          ['<i class="fa-solid fa-bolt" style="margin-right:8px;color:#1690a7;"></i>Actividad mediana', '150–300 tokens', 'Pro'],
          ['<i class="fa-solid fa-bolt" style="margin-right:8px;color:#1690a7;"></i>Actividad grande', '300–500 tokens', 'Pro'],
          ['<i class="fa-solid fa-bolt" style="margin-right:8px;color:#1690a7;"></i>Gran alcance', '500–2000 tokens', 'Platinum'],
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
