<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/spin_config.php';

// ─── Auth guard ───────────────────────────────────────
if (!isLoggedIn()) {
    http_response_code(401);
    exit(json_encode(['error' => 'No autenticado']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'Método no permitido']));
}

$spinType = trim($_POST['type'] ?? '');
if (!in_array($spinType, ['daily', 'paid'], true)) {
    http_response_code(400);
    exit(json_encode(['error' => 'Tipo de tirada inválido']));
}

$userId = (int)$_SESSION['user_id'];
$db     = getDB();

try {
    $db->beginTransaction();

    // ─── Lock user row (bloquea tiradas simultáneas) ──
    $stmt = $db->prepare('SELECT tokens_balance FROM users WHERE id = ? FOR UPDATE');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    if ($row === false) {
        $db->rollBack();
        http_response_code(403);
        exit(json_encode(['error' => 'Usuario no encontrado']));
    }
    $balance = (int)$row['tokens_balance'];

    // ─── Rate limiting: máx 20 tiradas pagadas/minuto ─
    if ($spinType === 'paid') {
        $rateStmt = $db->prepare(
            "SELECT COUNT(*) FROM spin_history
             WHERE user_id = ? AND spin_type = 'paid'
               AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)"
        );
        $rateStmt->execute([$userId]);
        if ((int)$rateStmt->fetchColumn() >= 20) {
            $db->rollBack();
            http_response_code(429);
            exit(json_encode(['error' => 'Demasiadas tiradas en poco tiempo. Espera un momento.']));
        }
    }

    $cost = 0;

    if ($spinType === 'daily') {
        // ─── Validar tirada diaria (siempre en backend) ───
        if (!SPIN_FREE_ENABLED) {
            $db->rollBack();
            exit(json_encode(['error' => 'Las tiradas gratuitas están desactivadas temporalmente.']));
        }

        $lastStmt = $db->prepare(
            "SELECT MAX(created_at) FROM spin_history
             WHERE user_id = ? AND spin_type = 'daily'"
        );
        $lastStmt->execute([$userId]);
        $lastFree = $lastStmt->fetchColumn();

        if ($lastFree !== null && $lastFree !== false) {
            $elapsed = time() - strtotime($lastFree);
            if ($elapsed < SPIN_FREE_INTERVAL) {
                $db->rollBack();
                exit(json_encode([
                    'error'     => 'Tirada gratuita no disponible todavía.',
                    'remaining' => SPIN_FREE_INTERVAL - $elapsed,
                ]));
            }
        }

    } else {
        // ─── Validar saldo y descontar costo ──────────────
        $cost = SPIN_COST;
        if ($balance < $cost) {
            $db->rollBack();
            exit(json_encode([
                'error' => 'Saldo insuficiente. Necesitas ' . $cost . ' tokens para jugar.',
            ]));
        }

        $db->prepare('UPDATE users SET tokens_balance = tokens_balance - ? WHERE id = ?')
           ->execute([$cost, $userId]);
        $db->prepare(
            'INSERT INTO token_transactions (user_id, amount, type, description) VALUES (?, ?, "spin", ?)'
        )->execute([$userId, -$cost, 'Tirada de ruleta']);
    }

    // ─── Determinar premio (lógica en backend) ────────
    $prize  = rollPrize();
    $reward = $prize['tokens'];

    // ─── Acreditar premio ─────────────────────────────
    if ($reward > 0) {
        $db->prepare('UPDATE users SET tokens_balance = tokens_balance + ? WHERE id = ?')
           ->execute([$reward, $userId]);
        $db->prepare(
            'INSERT INTO token_transactions (user_id, amount, type, description) VALUES (?, ?, "spin", ?)'
        )->execute([$userId, $reward, 'Premio ruleta: ' . $prize['label']]);
    }

    // ─── Registrar tirada (auditoría) ─────────────────
    $db->prepare(
        'INSERT INTO spin_history (user_id, spin_type, cost, reward, prize_label) VALUES (?, ?, ?, ?, ?)'
    )->execute([$userId, $spinType, $cost, $reward, $prize['label']]);

    // ─── Saldo actualizado ────────────────────────────
    $balStmt = $db->prepare('SELECT tokens_balance FROM users WHERE id = ?');
    $balStmt->execute([$userId]);
    $newBalance = (int)$balStmt->fetchColumn();

    $db->commit();

    echo json_encode([
        'success'     => true,
        'prize_index' => $prize['index'],
        'prize'       => ['tokens' => $reward, 'label' => $prize['label'], 'color' => $prize['color']],
        'new_balance' => $newBalance,
        'cost'        => $cost,
        'spin_type'   => $spinType,
    ]);

} catch (PDOException $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('[spin] DB error user=' . $userId . ': ' . $e->getMessage());
    http_response_code(500);
    exit(json_encode(['error' => 'Error interno. Intenta de nuevo.']));
}

// ─── Selección del premio por probabilidad ponderada ──
function rollPrize(): array {
    $rand       = mt_rand(1, 10000) / 100.0; // 0.01 – 100.00
    $cumulative = 0.0;
    foreach (SPIN_PRIZES as $i => $prize) {
        $cumulative += $prize['probability'];
        if ($rand <= $cumulative) {
            return $prize + ['index' => $i];
        }
    }
    return SPIN_PRIZES[0] + ['index' => 0]; // fallback: sin premio
}
