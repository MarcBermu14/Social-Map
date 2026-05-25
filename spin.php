<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/spin_config.php';
requireLogin();

$user = currentUser();
$db   = getDB();

// ─── Estado de tirada gratis ──────────────────────────
$lastFreeStmt = $db->prepare(
    "SELECT MAX(created_at) FROM spin_history WHERE user_id = ? AND spin_type = 'daily'"
);
$lastFreeStmt->execute([$user['id']]);
$lastFreeTs = $lastFreeStmt->fetchColumn();

$freeAvailable  = true;
$freeRemaining  = 0;
if ($lastFreeTs) {
    $elapsed = time() - strtotime($lastFreeTs);
    if ($elapsed < SPIN_FREE_INTERVAL) {
        $freeAvailable = false;
        $freeRemaining = SPIN_FREE_INTERVAL - $elapsed;
    }
}

// ─── Historial de tiradas del usuario (últimas 15) ────
$histStmt = $db->prepare(
    'SELECT spin_type, cost, reward, prize_label, created_at
     FROM spin_history WHERE user_id = ? ORDER BY created_at DESC LIMIT 15'
);
$histStmt->execute([$user['id']]);
$history = $histStmt->fetchAll();

// ─── Estadísticas globales ────────────────────────────
$statsStmt = $db->prepare(
    'SELECT COUNT(*) AS total,
            COALESCE(SUM(reward),0) AS total_earned,
            COALESCE(SUM(cost),0)   AS total_spent
     FROM spin_history WHERE user_id = ?'
);
$statsStmt->execute([$user['id']]);
$stats = $statsStmt->fetch();

$pageTitle  = 'Ruleta de Tokens';
$activePage = 'spin';
include __DIR__ . '/includes/header.php';

// Pasar config al frontend de forma segura
$jsConfig = [
    'prizes'        => array_values(SPIN_PRIZES),
    'spinCost'      => SPIN_COST,
    'freeAvailable' => $freeAvailable,
    'freeRemaining' => $freeRemaining,
    'freeEnabled'   => SPIN_FREE_ENABLED,
    'balance'       => (int)$user['tokens_balance'],
];
?>

<style>
/* ── Ruleta layout ── */
.spin-layout {
  display: grid;
  grid-template-columns: 1fr 380px;
  gap: 24px;
  padding: 24px;
  max-width: 1100px;
}
@media (max-width: 860px) {
  .spin-layout { grid-template-columns: 1fr; }
  .spin-wheel-col { order: -1; }
}

/* ── Rueda ── */
.wheel-wrapper {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 20px;
}
#wheelCanvas {
  display: block;
  border-radius: 50%;
  box-shadow: 0 8px 40px rgba(0,0,0,.18), 0 0 0 4px rgba(255,255,255,.6);
}
.wheel-pointer {
  position: absolute;
  top: -4px;
  left: 50%;
  transform: translateX(-50%);
  width: 0;
  height: 0;
  border-left: 12px solid transparent;
  border-right: 12px solid transparent;
  border-top: 24px solid #1e293b;
  filter: drop-shadow(0 2px 4px rgba(0,0,0,.4));
  z-index: 10;
}
.wheel-container {
  position: relative;
  display: inline-block;
}

/* ── Controles ── */
.spin-btn-group {
  display: flex;
  flex-direction: column;
  gap: 10px;
  width: 100%;
}
.btn-spin-free {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 14px 20px;
  border-radius: var(--r);
  border: none;
  font-size: 15px;
  font-weight: 700;
  cursor: pointer;
  transition: all .2s;
  background: linear-gradient(135deg, #10b981, #059669);
  color: #fff;
}
.btn-spin-free:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 6px 20px rgba(16,185,129,.35);
}
.btn-spin-paid {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 14px 20px;
  border-radius: var(--r);
  border: none;
  font-size: 15px;
  font-weight: 700;
  cursor: pointer;
  transition: all .2s;
  background: linear-gradient(135deg, var(--primary), #0284c7);
  color: #fff;
}
.btn-spin-paid:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 6px 20px rgba(14,165,233,.35);
}
.btn-spin-free:disabled,
.btn-spin-paid:disabled {
  opacity: .5;
  cursor: not-allowed;
  transform: none;
}

/* ── Countdown ── */
.countdown-box {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 16px;
  background: var(--card2);
  border-radius: var(--r-sm);
  border: 1px solid var(--border);
  font-size: 13px;
  color: var(--text2);
}
.countdown-time {
  font-weight: 700;
  color: var(--text);
  font-variant-numeric: tabular-nums;
  min-width: 60px;
}

/* ── Resultado ── */
.spin-result {
  display: none;
  padding: 20px;
  border-radius: var(--r);
  text-align: center;
  animation: resultIn .4s ease;
}
.spin-result.show { display: block; }
@keyframes resultIn {
  from { opacity: 0; transform: scale(.85); }
  to   { opacity: 1; transform: scale(1); }
}
.result-tokens {
  font-size: 42px;
  font-weight: 900;
  line-height: 1;
  margin-bottom: 6px;
}
.result-label {
  font-size: 14px;
  color: var(--text2);
}

/* ── Leyenda de premios ── */
.prize-legend {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.prize-row {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 10px;
  border-radius: var(--r-sm);
  font-size: 13px;
  transition: background .15s;
}
.prize-row:hover { background: var(--card2); }
.prize-dot {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  flex-shrink: 0;
}
.prize-name { flex: 1; font-weight: 600; }
.prize-prob { color: var(--text3); font-size: 12px; min-width: 40px; text-align: right; }

/* ── Historial ── */
.spin-hist-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 0;
  border-bottom: 1px solid var(--border);
  font-size: 13px;
}
.spin-hist-item:last-child { border-bottom: none; }
.spin-hist-icon {
  width: 34px; height: 34px;
  border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  font-size: 16px;
  flex-shrink: 0;
}
</style>

<div class="spin-layout">

  <!-- ══ COLUMNA IZQUIERDA ════════════════════════════ -->
  <div>

    <!-- Saldo -->
    <div class="balance-card mb-24">
      <div class="balance-glow"></div>
      <div class="balance-label">Saldo de tokens</div>
      <div class="balance-amount"><span id="balanceDisplay"><?= number_format($user['tokens_balance']) ?></span></div>
      <div class="balance-unit">tokens ⬡</div>
    </div>

    <!-- Estadísticas del usuario -->
    <div class="stats-grid mb-24" style="grid-template-columns:repeat(3,1fr);">
      <div class="stat-card">
        <div class="stat-icon">🎰</div>
        <div class="stat-val"><?= number_format($stats['total']) ?></div>
        <div class="stat-lbl">Tiradas totales</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">📈</div>
        <div class="stat-val" style="color:var(--green);">+<?= number_format($stats['total_earned']) ?> ⬡</div>
        <div class="stat-lbl">Tokens ganados</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">📉</div>
        <div class="stat-val" style="color:var(--red);">-<?= number_format($stats['total_spent']) ?> ⬡</div>
        <div class="stat-lbl">Tokens gastados</div>
      </div>
    </div>

    <!-- Controles de tirada -->
    <div class="card mb-24">
      <div class="card-title mb-16">🎰 Jugar a la ruleta</div>

      <!-- Resultado (se muestra tras la tirada) -->
      <div id="spinResult" class="spin-result mb-16"></div>

      <div class="spin-btn-group">
        <?php if (SPIN_FREE_ENABLED): ?>
        <button id="btnFree" class="btn-spin-free" <?= !$freeAvailable ? 'disabled' : '' ?>>
          <i class="fa-solid fa-gift"></i>
          Tirada diaria gratis
        </button>
        <?php endif; ?>

        <button id="btnPaid" class="btn-spin-paid"
                <?= $user['tokens_balance'] < SPIN_COST ? 'disabled' : '' ?>>
          <i class="fa-solid fa-coins"></i>
          Tirar por <?= number_format(SPIN_COST) ?> tokens
        </button>
      </div>

      <!-- Countdown tirada gratis -->
      <?php if (SPIN_FREE_ENABLED && !$freeAvailable): ?>
      <div class="countdown-box mt-12" id="countdownBox">
        <i class="fa-solid fa-clock" style="color:var(--primary);"></i>
        <span>Próxima tirada gratis en</span>
        <span class="countdown-time" id="countdownDisplay">--:--:--</span>
      </div>
      <?php elseif (SPIN_FREE_ENABLED): ?>
      <div class="countdown-box mt-12" style="background:rgba(16,185,129,.08);border-color:rgba(16,185,129,.25);">
        <i class="fa-solid fa-circle-check" style="color:var(--green);"></i>
        <span style="color:var(--green);font-weight:600;">¡Tirada gratuita disponible!</span>
      </div>
      <?php endif; ?>

      <div class="divider" style="margin:16px 0;"></div>
      <p style="font-size:12px;color:var(--text3);text-align:center;">
        EV por tirada: <strong>58,75 ⬡</strong> · Margen ruleta: 41,25 % en tiradas pagadas
      </p>
    </div>

    <!-- Historial de tiradas -->
    <div class="card">
      <div class="card-title mb-16">📋 Historial de tiradas</div>
      <?php if (empty($history)): ?>
        <p class="text-muted text-sm">Aún no has hecho ninguna tirada.</p>
      <?php else: ?>
        <?php foreach ($history as $h):
          $isWin = $h['reward'] > 0;
          $isFree = $h['spin_type'] === 'daily';
          $net = $h['reward'] - $h['cost'];
        ?>
        <div class="spin-hist-item">
          <div class="spin-hist-icon" style="background:<?= $isWin ? 'rgba(16,185,129,.12)' : 'rgba(100,116,139,.1)' ?>;">
            <?= $isFree ? '🎁' : '💰' ?>
          </div>
          <div style="flex:1;">
            <div style="font-weight:600;"><?= htmlspecialchars($h['prize_label']) ?></div>
            <div style="font-size:11px;color:var(--text3);">
              <?= $isFree ? 'Gratis' : '-' . number_format($h['cost']) . ' ⬡' ?>
              · <?= (new DateTime($h['created_at']))->format('d M · H:i') ?>
            </div>
          </div>
          <div style="font-weight:700;font-size:14px;color:<?= $net > 0 ? 'var(--green)' : ($net < 0 ? 'var(--red)' : 'var(--text3)') ?>;">
            <?= $net > 0 ? '+' : '' ?><?= number_format($net) ?> ⬡
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

  </div>

  <!-- ══ COLUMNA DERECHA (rueda) ══════════════════════ -->
  <div class="spin-wheel-col">

    <!-- Rueda visual -->
    <div class="card mb-24">
      <div class="wheel-wrapper">
        <div class="wheel-container">
          <div class="wheel-pointer"></div>
          <canvas id="wheelCanvas" width="300" height="300"></canvas>
        </div>
      </div>
    </div>

    <!-- Leyenda de premios -->
    <div class="card">
      <div class="card-title mb-12">🏆 Tabla de premios</div>
      <div class="prize-legend">
        <?php foreach (SPIN_PRIZES as $prize): ?>
        <div class="prize-row">
          <div class="prize-dot" style="background:<?= htmlspecialchars($prize['color']) ?>;"></div>
          <span class="prize-name"><?= htmlspecialchars($prize['label']) ?></span>
          <span class="prize-prob"><?= number_format($prize['probability'], 1) ?> %</span>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="divider" style="margin:12px 0;"></div>
      <p style="font-size:11px;color:var(--text3);text-align:center;line-height:1.5;">
        Tirada pagada: <strong><?= SPIN_COST ?> ⬡</strong> ·
        Tirada gratis: cada <strong><?= SPIN_FREE_INTERVAL / 3600 ?>h</strong>
      </p>
    </div>

  </div>
</div>

<script>
(function () {
  'use strict';

  // ─── Configuración desde PHP ─────────────────────────
  const CFG = <?= json_encode($jsConfig) ?>;

  // ─── Canvas setup ────────────────────────────────────
  const canvas = document.getElementById('wheelCanvas');
  const ctx    = canvas.getContext('2d');
  const TAU    = Math.PI * 2;

  // Precalcular segmentos (ángulos en radianes, sentido horario desde arriba)
  let cumAngle = 0;
  const segments = CFG.prizes.map((p, i) => {
    const start = cumAngle;
    const span  = (p.probability / 100) * TAU;
    cumAngle += span;
    return { ...p, index: i, start, span, center: start + span / 2 };
  });

  // ─── Dibujo de la rueda ───────────────────────────────
  let currentRot = 0;

  function drawWheel(rot) {
    const cx = canvas.width / 2, cy = canvas.height / 2, r = cx - 5;
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    // Sombra exterior
    ctx.save();
    ctx.shadowColor = 'rgba(0,0,0,.25)';
    ctx.shadowBlur  = 12;
    ctx.beginPath();
    ctx.arc(cx, cy, r, 0, TAU);
    ctx.fillStyle = '#fff';
    ctx.fill();
    ctx.restore();

    // Segmentos
    segments.forEach(seg => {
      const sa = rot + seg.start - TAU / 4; // -PI/2: inicia arriba
      const ea = sa + seg.span;

      ctx.beginPath();
      ctx.moveTo(cx, cy);
      ctx.arc(cx, cy, r, sa, ea);
      ctx.closePath();
      ctx.fillStyle = seg.color;
      ctx.fill();
      ctx.strokeStyle = 'rgba(255,255,255,.7)';
      ctx.lineWidth = 2;
      ctx.stroke();

      // Etiqueta (solo si el segmento tiene espacio suficiente)
      if (seg.span > 0.08) {
        const mid = rot + seg.center - TAU / 4;
        const lx  = cx + Math.cos(mid) * r * 0.66;
        const ly  = cy + Math.sin(mid) * r * 0.66;
        ctx.save();
        ctx.translate(lx, ly);
        ctx.rotate(mid + Math.PI / 2);
        ctx.fillStyle = '#fff';
        ctx.font = `bold ${seg.span > 0.45 ? 12 : 10}px Inter, sans-serif`;
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.shadowColor  = 'rgba(0,0,0,.6)';
        ctx.shadowBlur   = 4;
        ctx.fillText(seg.label, 0, 0);
        ctx.restore();
      }
    });

    // Hub central
    ctx.beginPath();
    ctx.arc(cx, cy, 22, 0, TAU);
    const g = ctx.createRadialGradient(cx - 4, cy - 4, 2, cx, cy, 22);
    g.addColorStop(0, '#334155');
    g.addColorStop(1, '#0f172a');
    ctx.fillStyle = g;
    ctx.fill();
    ctx.strokeStyle = 'rgba(255,255,255,.25)';
    ctx.lineWidth = 2;
    ctx.stroke();

    ctx.fillStyle = 'rgba(255,255,255,.8)';
    ctx.font = 'bold 13px sans-serif';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText('⬡', cx, cy);
  }

  drawWheel(0);

  // ─── Animación de giro ────────────────────────────────
  let spinning = false;

  function spinTo(prizeIndex, onComplete) {
    const seg = segments[prizeIndex];

    // Ángulo objetivo: seg.center llega al puntero (arriba = 0)
    // Relación: rotation + seg.center ≡ 0 (mod TAU)
    // → rotation = (TAU - seg.center % TAU) % TAU
    const baseRot  = (TAU - (seg.center % TAU) + TAU) % TAU;
    const curMod   = ((currentRot % TAU) + TAU) % TAU;
    let   delta    = (baseRot - curMod + TAU) % TAU;
    if (delta < 0.01) delta += TAU;

    const finalRot = currentRot + 5 * TAU + delta; // 5 vueltas completas + aterrizaje

    const startRot  = currentRot;
    const startTime = performance.now();
    const duration  = 4500;

    function ease(t) { return 1 - Math.pow(1 - t, 3.5); }

    function frame(now) {
      const t   = Math.min((now - startTime) / duration, 1);
      const rot = startRot + (finalRot - startRot) * ease(t);
      drawWheel(rot);
      if (t < 1) {
        requestAnimationFrame(frame);
      } else {
        currentRot = finalRot;
        drawWheel(finalRot);
        spinning = false;
        onComplete();
      }
    }
    requestAnimationFrame(frame);
  }

  // ─── Resultado visual ─────────────────────────────────
  function showResult(prize, balance) {
    const el = document.getElementById('spinResult');
    const win = prize.tokens > 0;

    el.style.background = win
      ? 'linear-gradient(135deg,rgba(16,185,129,.12),rgba(16,185,129,.04))'
      : 'rgba(100,116,139,.08)';
    el.style.border = `1px solid ${win ? 'rgba(16,185,129,.3)' : 'var(--border)'}`;

    el.innerHTML = `
      <div class="result-tokens" style="color:${win ? 'var(--green)' : 'var(--text3)'}">
        ${win ? '+' + prize.tokens.toLocaleString('es') + ' ⬡' : '😕'}
      </div>
      <div class="result-label">
        ${win ? '¡Has ganado <strong>' + prize.label + '</strong>!' : 'Sin premio esta vez. ¡Suerte la próxima!'}
      </div>
      <div style="margin-top:10px;font-size:12px;color:var(--text3);">
        Saldo actual: <strong>${balance.toLocaleString('es')} ⬡</strong>
      </div>`;
    el.classList.add('show');

    // Actualizar saldo en sidebar y balance card
    document.getElementById('balanceDisplay').textContent = balance.toLocaleString('es');
    document.querySelectorAll('.nav-item [data-balance]').forEach(el => {
      el.textContent = balance.toLocaleString('es');
    });
  }

  // ─── Petición al backend ──────────────────────────────
  function doSpin(type) {
    if (spinning) return;
    spinning = true;

    const btnFree = document.getElementById('btnFree');
    const btnPaid = document.getElementById('btnPaid');
    if (btnFree) btnFree.disabled = true;
    if (btnPaid) btnPaid.disabled = true;

    const spinAnim = setInterval(() => drawWheel(currentRot += 0.03), 16);

    const form = new FormData();
    form.append('type', type);

    fetch('/citylive/api/spin.php', { method: 'POST', body: form })
      .then(r => r.json())
      .then(data => {
        clearInterval(spinAnim);

        if (data.error) {
          spinning = false;
          drawWheel(currentRot);
          alert(data.error);
          if (btnFree) btnFree.disabled = !CFG.freeAvailable;
          if (btnPaid) btnPaid.disabled = data.new_balance !== undefined && data.new_balance < CFG.spinCost;
          return;
        }

        // Animar hasta el índice correcto
        spinTo(data.prize_index, () => {
          showResult(data.prize, data.new_balance);

          // Actualizar estado botones
          if (type === 'daily') {
            CFG.freeAvailable = false;
            CFG.freeRemaining = CFG.spinFreeInterval || 86400;
            if (btnFree) btnFree.disabled = true;
            startCountdown(CFG.freeRemaining);
          }
          if (btnPaid) btnPaid.disabled = data.new_balance < CFG.spinCost;
        });
      })
      .catch(() => {
        clearInterval(spinAnim);
        spinning = false;
        drawWheel(currentRot);
        alert('Error de conexión. Intenta de nuevo.');
        if (btnFree) btnFree.disabled = !CFG.freeAvailable;
        if (btnPaid) btnPaid.disabled = false;
      });
  }

  // ─── Listeners ────────────────────────────────────────
  const btnFree = document.getElementById('btnFree');
  const btnPaid = document.getElementById('btnPaid');

  if (btnFree) btnFree.addEventListener('click', () => doSpin('daily'));
  if (btnPaid) btnPaid.addEventListener('click', () => doSpin('paid'));

  // ─── Countdown tirada gratis ──────────────────────────
  function startCountdown(seconds) {
    let remaining = Math.ceil(seconds);
    const box     = document.getElementById('countdownBox');
    const display = document.getElementById('countdownDisplay');

    if (!display) {
      // Crear el box si no existe (cuando se acaba de usar la tirada gratis)
      const controls = document.querySelector('.spin-btn-group');
      if (controls) {
        const newBox = document.createElement('div');
        newBox.id        = 'countdownBox';
        newBox.className = 'countdown-box mt-12';
        newBox.innerHTML = `
          <i class="fa-solid fa-clock" style="color:var(--primary);"></i>
          <span>Próxima tirada gratis en</span>
          <span class="countdown-time" id="countdownDisplay">--:--:--</span>`;
        controls.after(newBox);
      }
    }

    const tick = setInterval(() => {
      remaining--;
      if (remaining <= 0) {
        clearInterval(tick);
        const cd = document.getElementById('countdownBox');
        if (cd) cd.remove();
        if (btnFree) {
          btnFree.disabled = false;
          CFG.freeAvailable = true;
        }
        return;
      }
      const h = String(Math.floor(remaining / 3600)).padStart(2, '0');
      const m = String(Math.floor((remaining % 3600) / 60)).padStart(2, '0');
      const s = String(remaining % 60).padStart(2, '0');
      const el = document.getElementById('countdownDisplay');
      if (el) el.textContent = `${h}:${m}:${s}`;
    }, 1000);

    // Primer render inmediato
    const h = String(Math.floor(remaining / 3600)).padStart(2, '0');
    const m = String(Math.floor((remaining % 3600) / 60)).padStart(2, '0');
    const s = String(remaining % 60).padStart(2, '0');
    const el = document.getElementById('countdownDisplay');
    if (el) el.textContent = `${h}:${m}:${s}`;
  }

  // Arrancar countdown si ya hay tiempo de espera
  if (CFG.freeRemaining > 0) {
    startCountdown(CFG.freeRemaining);
  }

})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
