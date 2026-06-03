<?php
/**
 * Setup .env - Configura automáticamente para Infinityfree
 * Acceder: https://miapp.infinityfree.com/setup-env.php
 */

$envFile = __DIR__ . '/.env';

// Datos de Infinityfree que proporciona el usuario
$dbHost = 'sql210.infinityfree.com';
$dbName = 'if0_42055847_citylive_db';
$dbUser = 'if0_42055847';
$dbPass = '30072004Abel';
$dbPort = '3306';

// Tu dominio en Infinityfree
$appUrl = isset($_SERVER['HTTP_HOST']) 
    ? 'https://' . $_SERVER['HTTP_HOST'] 
    : 'https://miapp.infinityfree.com';

// Email (déjalo vacío si no lo tienes)
$smtpUser = 'your-email@gmail.com';
$smtpPass = 'your-app-password';

// Generar contenido del .env
$envContent = <<<EOT
DB_HOST=$dbHost
DB_NAME=$dbName
DB_USER=$dbUser
DB_PASS=$dbPass
DB_CHAR=utf8mb4
DB_PORT=$dbPort

SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=$smtpUser
SMTP_PASS=$smtpPass
SMTP_FROM=noreply@miapp.infinityfree.com
SMTP_FROM_NAME=CityLive

APP_URL=$appUrl
EOT;

// Crear el .env
if (file_put_contents($envFile, $envContent)) {
    $created = true;
} else {
    $created = false;
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup .env - CityLive</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f5f5f5;
            padding: 40px 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 { color: #333; margin-bottom: 20px; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 4px; margin-bottom: 20px; border-left: 4px solid #28a745; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px; margin-bottom: 20px; border-left: 4px solid #f5c6cb; }
        .code {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 13px;
            line-height: 1.6;
            margin: 15px 0;
            border-left: 3px solid #007bff;
            overflow-x: auto;
        }
        .button {
            display: inline-block;
            background: #007bff;
            color: white;
            padding: 10px 30px;
            border-radius: 4px;
            text-decoration: none;
            margin-top: 20px;
            cursor: pointer;
            border: none;
            font-size: 14px;
        }
        .button:hover { background: #0056b3; }
    </style>
</head>
<body>
    <div class="container">
        <h1>⚙️ Configurar .env - CityLive</h1>
        
        <?php if ($created): ?>
            <div class="success">
                <strong>✅ ¡Éxito!</strong><br>
                El archivo .env ha sido creado con los datos de Infinityfree
            </div>

            <div class="code">
DB_HOST=<?= $dbHost ?>
DB_NAME=<?= $dbName ?>
DB_USER=<?= $dbUser ?>
DB_PASS=<?= str_repeat('*', strlen($dbPass)) ?>
DB_PORT=<?= $dbPort ?>
APP_URL=<?= $appUrl ?>
            </div>

            <p style="color: #666; font-size: 14px; line-height: 1.6; margin: 20px 0;">
                <strong>Próximos pasos:</strong><br>
                1. Recarga la web (Ctrl+F5)<br>
                2. Intenta crear una cuenta nuevamente<br>
                3. Si ves errores, verifica que importaste el schema.sql en phpMyAdmin<br>
                4. Después de confirmar que funciona, <strong>BORRA este archivo</strong> por seguridad
            </p>

            <a href="/" class="button">Volver a CityLive</a>

        <?php else: ?>
            <div class="error">
                <strong>❌ Error</strong><br>
                No se pudo crear el archivo .env. Intenta manualmente:
            </div>

            <p style="margin: 15px 0; color: #666;">
                <strong>Opción: File Manager Manual</strong>
            </p>

            <ol style="color: #666; line-height: 1.8;">
                <li>Abre File Manager de Infinityfree</li>
                <li>Navega a htdocs/</li>
                <li>Crea nuevo archivo: <strong>.env</strong></li>
                <li>Copia este contenido exacto:</li>
            </ol>

            <div class="code">
DB_HOST=sql210.infinityfree.com
DB_NAME=if0_42055847_citylive_db
DB_USER=if0_42055847
DB_PASS=30072004Abel
DB_CHAR=utf8mb4
DB_PORT=3306

SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=your-email@gmail.com
SMTP_PASS=your-app-password
SMTP_FROM=noreply@miapp.infinityfree.com
SMTP_FROM_NAME=CityLive

APP_URL=<?= $appUrl ?>
            </div>

            <a href="/" class="button">Volver a CityLive</a>
        <?php endif; ?>
    </div>
</body>
</html>
