<?php
/**
 * Health Check - Verificar que todo está configurado correctamente
 * Acceder a: http://localhost/health-check.php
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/email.php';
require_once 'config.php';

$checks = [];
$issues = [];

// ─── Verificar Conexión a BD ───────────────────────────
try {
    $db = getDB();
    $result = $db->query("SELECT 1")->fetch();
    $checks[] = ['✅ Base de datos', 'Conectado a: ' . DB_HOST . '/' . DB_NAME];
} catch (Exception $e) {
    $issues[] = ['❌ Base de datos', $e->getMessage()];
}

// ─── Verificar Tabla Users ────────────────────────────
try {
    $db = getDB();
    $columns = $db->query("SHOW COLUMNS FROM users")->fetchAll();
    $columnNames = array_column($columns, 'Field');
    
    $required = ['id', 'email', 'username', 'password_hash', 'verified', 'verification_token', 'token_created_at'];
    $missing = array_diff($required, $columnNames);
    
    if (empty($missing)) {
        $checks[] = ['✅ Tabla users', 'Todos los campos presentes'];
    } else {
        $issues[] = ['❌ Tabla users', 'Campos faltantes: ' . implode(', ', $missing)];
    }
} catch (Exception $e) {
    $issues[] = ['❌ Tabla users', $e->getMessage()];
}

// ─── Verificar Funciones de Email ──────────────────────
if (function_exists('sendVerificationEmail')) {
    $checks[] = ['✅ Función sendVerificationEmail', 'Disponible'];
} else {
    $issues[] = ['❌ Función sendVerificationEmail', 'No encontrada'];
}

if (function_exists('generateVerificationToken')) {
    $checks[] = ['✅ Función generateVerificationToken', 'Disponible'];
} else {
    $issues[] = ['❌ Función generateVerificationToken', 'No encontrada'];
}

// ─── Verificar Configuración SMTP ──────────────────────
$smtpConfigured = (SMTP_USER !== 'your-email@gmail.com');
if ($smtpConfigured) {
    $checks[] = ['✅ SMTP Configurado', 'Usando: ' . SMTP_HOST];
} else {
    $checks[] = ['⚠️  SMTP No Configurado', 'En desarrollo, los emails se simulan. Para producción, configura .env'];
}

// ─── Verificar APP_URL ─────────────────────────────────
if (strpos(APP_URL, 'localhost') !== false) {
    $checks[] = ['⚠️  APP_URL', 'Configurado para desarrollo: ' . APP_URL];
} else {
    $checks[] = ['✅ APP_URL', 'Configurado para producción: ' . APP_URL];
}

// ─── Contar Usuarios ───────────────────────────────────
try {
    $db = getDB();
    $count = $db->query("SELECT COUNT(*) as cnt FROM users")->fetch()['cnt'];
    $checks[] = ['ℹ️  Usuarios en BD', $count . ' usuario(s)'];
} catch (Exception $e) {}

// ─── Contar Usuarios Verificados ───────────────────────
try {
    $db = getDB();
    $verified = $db->query("SELECT COUNT(*) as cnt FROM users WHERE verified = 1")->fetch()['cnt'];
    $unverified = $db->query("SELECT COUNT(*) as cnt FROM users WHERE verified = 0")->fetch()['cnt'];
    if ($verified > 0 || $unverified > 0) {
        $checks[] = ['ℹ️  Verificación', $verified . ' verificado(s), ' . $unverified . ' sin verificar'];
    }
} catch (Exception $e) {}

?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Health Check — CityLive</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
      background: #f5f5f5;
      padding: 20px;
    }
    .container {
      max-width: 700px;
      margin: 0 auto;
      background: white;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      overflow: hidden;
    }
    .header {
      background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
      color: white;
      padding: 30px;
      text-align: center;
    }
    .header h1 { font-size: 28px; margin-bottom: 5px; }
    .header p { font-size: 14px; opacity: 0.9; }
    .content { padding: 30px; }
    .section {
      margin-bottom: 25px;
    }
    .section-title {
      font-size: 14px;
      font-weight: 600;
      color: #666;
      text-transform: uppercase;
      margin-bottom: 12px;
      padding-bottom: 8px;
      border-bottom: 2px solid #f0f0f0;
    }
    .item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 12px 0;
      border-bottom: 1px solid #f0f0f0;
    }
    .item:last-child { border-bottom: none; }
    .item-label { font-weight: 500; }
    .item-value {
      font-size: 13px;
      color: #666;
      text-align: right;
    }
    .success { color: #28a745; }
    .warning { color: #ffc107; }
    .error { color: #dc3545; }
    .info { color: #17a2b8; }
    .footer {
      background: #f9f9f9;
      padding: 20px 30px;
      text-align: center;
      border-top: 1px solid #f0f0f0;
      font-size: 13px;
      color: #999;
    }
    a {
      color: #007bff;
      text-decoration: none;
    }
    a:hover { text-decoration: underline; }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h1>🗺️ CityLive Health Check</h1>
      <p>Verificación del sistema</p>
    </div>
    
    <div class="content">
      <?php if (!empty($checks)): ?>
        <div class="section">
          <div class="section-title">✅ Estado del Sistema</div>
          <?php foreach ($checks as [$status, $detail]): ?>
            <div class="item">
              <div class="item-label">
                <?php 
                  if (strpos($status, '✅') !== false) echo '<span class="success">';
                  elseif (strpos($status, '⚠️') !== false) echo '<span class="warning">';
                  elseif (strpos($status, 'ℹ️') !== false) echo '<span class="info">';
                  else echo '<span>';
                ?>
                  <?= htmlspecialchars($status) ?>
                </span>
              </div>
              <div class="item-value"><?= htmlspecialchars($detail) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      
      <?php if (!empty($issues)): ?>
        <div class="section">
          <div class="section-title">⚠️  Problemas Detectados</div>
          <?php foreach ($issues as [$status, $detail]): ?>
            <div class="item">
              <div class="item-label">
                <span class="error"><?= htmlspecialchars($status) ?></span>
              </div>
              <div class="item-value" style="color: #dc3545; font-weight: 500;">
                <?= htmlspecialchars($detail) ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div class="section">
        <div class="section-title">🔗 Enlaces Útiles</div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
          <a href="/" style="display: inline-block; padding: 10px; background: #f0f0f0; border-radius: 4px; text-align: center;">
            🏠 Inicio
          </a>
          <a href="/register.php" style="display: inline-block; padding: 10px; background: #f0f0f0; border-radius: 4px; text-align: center;">
            📝 Registrarse
          </a>
          <a href="/index.php" style="display: inline-block; padding: 10px; background: #f0f0f0; border-radius: 4px; text-align: center;">
            🔑 Login
          </a>
          <a href="/phpmyadmin/" style="display: inline-block; padding: 10px; background: #f0f0f0; border-radius: 4px; text-align: center;">
            🗄️  phpMyAdmin
          </a>
        </div>
      </div>
    </div>
    
    <div class="footer">
      CityLive v1.0 • <a href="/SETUP.md">Guía de Setup</a> • <a href="/DEPLOYMENT.md">Guía de Deployment</a>
    </div>
  </div>
</body>
</html>
