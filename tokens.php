<?php
require_once 'config.php';
require_once __DIR__ . '/config/db.php';
requireLogin();

$user = currentUser();
$db   = getDB();

// Paquetes disponibles: amount => price in cents (display only)
$packs = [500 => 499, 2000 => 999, 5000 => 1999, 200 => 199];

// Manejar compra de tokens con validación mejorada
$error_message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buy_tokens'])) {
    $amount = (int)($_POST['buy_tokens']);
    
    // Validar que el monto sea válido y esté en los paquetes disponibles
    if (!isset($packs[$amount])) {
        $error_message = 'Paquete inválido seleccionado.';
    } elseif ($amount <= 0) {
        $error_message = 'La cantidad debe ser mayor a cero.';
    } else {
        // Requerir pago mediante Bizum - No procesar la compra
        $price = $packs[$amount];
        $priceFormatted = number_format($price / 100, 2, ',', '') . '€';
        header('Location: ' . BASE . '/tokens.php?payment_required=1&amount=' . urlencode($amount) . '&price=' . urlencode($priceFormatted));
        exit;
    }
}

// Recargar datos del usuario después de la transacción
$user = currentUser();

// Obtener historial de transacciones (últimas 20)
$txs = $db->prepare('SELECT * FROM token_transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 20');
$txs->execute([$user['id']]);
$txs = $txs->fetchAll();

// Generar datos de gráfico: consumo por día en los últimos 7 días
$chart = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $stmt = $db->prepare("SELECT COALESCE(SUM(ABS(amount)), 0) FROM token_transactions WHERE user_id = ? AND amount < 0 AND DATE(created_at) = ?");
    $stmt->execute([$user['id'], $date]);
    $chart[] = ['day' => date('D', strtotime($date)), 'val' => (int)$stmt->fetchColumn()];
}
$maxChart = max(1, ...array_column($chart, 'val'));

// Calcular estadísticas del mes actual
$used = $db->prepare("SELECT COALESCE(SUM(ABS(amount)),0) FROM token_transactions WHERE user_id = ? AND amount < 0 AND MONTH(created_at) = MONTH(NOW())");
$used->execute([$user['id']]);
$used = (int)$used->fetchColumn();

$earned = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM token_transactions WHERE user_id = ? AND amount > 0 AND MONTH(created_at) = MONTH(NOW())");
$earned->execute([$user['id']]);
$earned = (int)$earned->fetchColumn();

$planLabel = ['free' => 'Gratuita', 'pro' => 'Pro', 'platinum' => 'Platinum'];
$planTokens = PLAN_TOKENS;

$pageTitle  = 'Mis Tokens';
$activePage = 'tokens';

include __DIR__ . '/includes/header.php';
?>

<div class="tokens-layout">

  <!-- LEFT COLUMN -->
  <div>
    <?php if (isset($_GET['ok'])): ?>
      <div class="flash flash-success">
        <i class="fa-solid fa-circle-check"></i>
        Se han añadido <?= (int)$_GET['ok'] ?> tokens a tu cuenta.
      </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['payment_required'])): ?>
      <div class="flash flash-warning">
        <i class="fa-solid fa-warning"></i>
        <strong>Pago requerido:</strong> Para comprar <strong><?= htmlspecialchars($_GET['amount'] ?? '') ?> tokens</strong>, debes realizar un pago mediante <strong>Bizum</strong>.
        <div style="margin-top:12px;padding-top:12px;border-top:1px solid rgba(255,255,255,.2);">
          <strong style="display:block;margin-bottom:8px;"><i class="fa-solid fa-mobile-screen-button" style="margin-right:6px;"></i>Envía un Bizum a:</strong>
          <div style="font-size:16px;font-weight:800;color:#fff;background:rgba(0,0,0,.3);padding:12px;border-radius:8px;margin-bottom:8px;">666 666 666</div>
          <strong style="display:block;margin-bottom:4px;">Importe:</strong>
          <div style="color:#fff;margin-bottom:12px;"><?= htmlspecialchars($_GET['price'] ?? '') ?></div>
          <div style="font-size:12px;opacity:.9;">Una vez realizado el pago, los tokens se añadirán a tu cuenta lo más pronto posible.</div>
        </div>
      </div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
      <div class="flash flash-error">
        <i class="fa-solid fa-circle-exclamation"></i>
        <?= htmlspecialchars($error_message) ?>
      </div>
    <?php endif; ?>

    <!-- Balance card -->
    <div class="balance-card mb-24">
      <div class="balance-glow"></div>
      <div class="balance-label">Saldo de tokens</div>
      <div class="balance-amount"><span><?= number_format($user['tokens_balance']) ?></span></div>
      <div class="balance-unit"><i class="fa-solid fa-coins"></i> tokens</div>
      <div style="margin-top:14px;position:relative;">
        <span class="badge badge-purple">
          <?= $planLabel[$user['plan']] ?>
          · <?= number_format($planTokens[$user['plan']]) ?> tokens/mes
        </span>
      </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid mb-24" style="grid-template-columns:repeat(3,1fr);">
      <div class="stat-card">
        <div class="stat-icon"><i class="fa-solid fa-arrow-trend-down"></i></div>
        <div class="stat-val" style="color:var(--red);"><?= number_format($used) ?> tokens</div>
        <div class="stat-lbl">Usados este mes</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon"><i class="fa-solid fa-arrow-trend-up"></i></div>
        <div class="stat-val" style="color:var(--green);">+<?= number_format($earned) ?> tokens</div>
        <div class="stat-lbl">Ganados este mes</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon"><i class="fa-solid fa-wallet"></i></div>
        <div class="stat-val"><?= count($txs) ?></div>
        <div class="stat-lbl">Transacciones</div>
      </div>
    </div>

    <!-- Chart -->
    <div class="card mb-24">
      <div class="card-title mb-16">Uso últimos 7 días</div>
      <div class="bar-chart">
        <?php foreach ($chart as $c): ?>
          <div class="bar-item">
            <div class="bar" style="height:<?= $c['val'] > 0 ? max(10, round(($c['val'] / $maxChart) * 100)) : 4 ?>%;"></div>
            <div class="bar-lbl"><?= $c['day'] ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Earn tokens -->
    <div class="card mb-24">
      <div class="card-title mb-16"><i class="fa-solid fa-bullseye" style="color:var(--red);margin-right:8px;"></i>Gana tokens gratis</div>
      <?php
      $earn = [
        ['fa-solid fa-location-dot', 'Publicar una incidencia verificada', '+25 tokens'],
        ['fa-solid fa-circle-check', 'Que tu publicación sea confirmada x5', '+50 tokens'],
        ['fa-solid fa-star', 'Recibir 5 valoraciones positivas', '+50 tokens'],
        ['fa-solid fa-bullseye', 'Completar 10 actividades al mes', '+200 tokens'],
        ['fa-solid fa-link', 'Invitar a un amigo que se registre', '+100 tokens'],
      ];
      foreach ($earn as [$icon, $label, $reward]):
      ?>
      <div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border);">
        <span style="font-size:20px;color:var(--red);"><i class="<?= $icon ?>"></i></span>
        <div style="flex:1;font-size:13px;color:var(--text2);"><?= $label ?></div>
        <span class="badge badge-primary"><?= $reward ?></span>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Transaction history -->
    <div class="card">
      <div class="card-title mb-16"><i class="fa-solid fa-clock-rotate-left" style="color:var(--red);margin-right:8px;"></i>Historial de transacciones</div>
      <?php if (empty($txs)): ?>
        <p class="text-muted text-sm">No hay transacciones todavía.</p>
      <?php else: ?>
        <?php
        // Mapeo de iconos según tipo de transacción
        $txIcons = [
            'subscription' => 'fa-solid fa-crown',
            'purchase' => 'fa-solid fa-cart-shopping',
            'publication' => 'fa-solid fa-bolt',
            'reward' => 'fa-solid fa-bullseye',
            'refund' => 'fa-solid fa-rotate-left'
        ];
        foreach ($txs as $tx):
          $sign = $tx['amount'] > 0 ? '+' : '';
          $cls  = $tx['amount'] > 0 ? 'pos' : 'neg';
        ?>
        <div class="tx-item">
          <div class="tx-icon-box"><i class="<?= $txIcons[$tx['type']] ?? 'fa-solid fa-coins' ?>"></i></div>
          <div style="flex:1;">
            <div class="tx-name"><?= htmlspecialchars($tx['description'] ?? ucfirst($tx['type'])) ?></div>
            <div class="tx-date"><?= (new DateTime($tx['created_at']))->format('d M Y · H:i') ?></div>
          </div>
          <div class="tx-amount <?= $cls ?>"><?= $sign . number_format($tx['amount']) ?> tokens</div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- RIGHT COLUMN -->
  <div>
    <div class="card mb-16" style="position:sticky;top:calc(var(--topbar-h) + 16px);">
      <div class="card-title mb-16"><i class="fa-solid fa-cart-shopping" style="color:var(--red);margin-right:8px;"></i>Comprar tokens extra</div>

      <?php
      // Paquetes disponibles para compra
      $purchase_packs = [
        [200,  '200 tokens',  'Pack de prueba',            '0,99€', ''],
        [500,  '500 tokens',  'Pack básico',               '2,99€', ''],
        [2000, '2.000 tokens','Pack popular · +200 bonus', '9,99€', 'var(--primary)'],
        [5000, '5.000 tokens','Pack pro · +1.000 bonus',   '19,99€','var(--purple)'],
      ];
      foreach ($purchase_packs as [$amount, $name, $bonus, $price, $borderColor]):
      ?>
      <form method="POST" style="margin-bottom: 8px;">
        <input type="hidden" name="buy_tokens" value="<?= $amount ?>">
        <button type="submit" class="pack-card" style="width:100%;text-align:left;<?= $borderColor ? "border-color:$borderColor;" : '' ?>" title="Comprar <?= $name ?>">
          <div class="pack-icon"><i class="fa-solid fa-coins"></i></div>
          <div class="pack-info">
            <div class="pack-amount"><?= $name ?></div>
            <div class="pack-bonus"><?= $bonus ?></div>
          </div>
          <div class="pack-price"><?= $price ?></div>
        </button>
      </form>
      <?php endforeach; ?>

      <div class="divider"></div>
      <a href="<?= BASE ?>/subscriptions.php" class="btn btn-primary btn-block">
        <i class="fa-solid fa-crown"></i> Upgrade de plan
      </a>
      <p style="font-size:12px;color:var(--text3);text-align:center;margin-top:10px;">
        <strong>Pro:</strong> 1.000 tokens/mes · <strong>Platinum:</strong> 10.000 tokens/mes
      </p>
    </div>

    <!-- Plan info -->
    <div class="card">
      <div class="card-title mb-12"><i class="fa-solid fa-chart-pie" style="color:var(--red);margin-right:8px;"></i>Tu plan actual</div>
      <div style="font-size:28px;margin-bottom:8px;">
        <i class="<?= $user['plan'] === 'platinum' ? 'fa-solid fa-crown' : ($user['plan'] === 'pro' ? 'fa-solid fa-bolt' : 'fa-regular fa-compass') ?>" style="color:var(--red);margin-right:8px;"></i>
        <?= ucfirst($user['plan']) ?>
      </div>
      <div style="font-size:13px;color:var(--text2);margin-bottom:14px;">
        <strong><?= number_format($planTokens[$user['plan']]) ?></strong> tokens mensuales incluidos
      </div>
      <?php if ($user['plan'] !== 'platinum'): ?>
      <a href="<?= BASE ?>/subscriptions.php" class="btn btn-outline btn-sm btn-block">
        Ver planes disponibles <i class="fa-solid fa-arrow-right"></i>
      </a>
      <?php endif; ?>
    </div>
  </div>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
