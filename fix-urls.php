<?php
/**
 * Fix URLs - Cambiar todas las rutas / a /
 * Acceder desde navegador: http://localhost/fix-urls.php
 * O en servidor: https://miapp.infinityfree.com/fix-urls.php
 */
require_once 'config.php';
$baseDir = __DIR__;
$findStr = '/';
$replaceStr = '/';

// Archivos a procesar (incluyendo subdirectorios)
$files = glob($baseDir . '/*.php');
$files = array_merge($files, glob($baseDir . '/includes/*.php'));
$files = array_merge($files, glob($baseDir . '/api/*.php'));
$files = array_merge($files, glob($baseDir . '/api/*/*.php'));

// Filtrar archivos que realmente existen y no es este mismo archivo
$files = array_filter($files, function($f) {
    return $f !== __FILE__ && file_exists($f);
});

$count = 0;
$results = [];

foreach ($files as $filePath) {
    $relPath = str_replace($baseDir . '/', '', $filePath);
    
    $content = file_get_contents($filePath);
    
    // Reemplazar / por /
    $newContent = str_replace($findStr, $replaceStr, $content);
    
    if ($newContent !== $content) {
        file_put_contents($filePath, $newContent);
        $changes = substr_count($content, $findStr);
        $results[] = "✅ $relPath: $changes cambios";
        $count += $changes;
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix URLs - CityLive</title>
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
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .result {
            background: #f8f9fa;
            padding: 10px 15px;
            border-left: 3px solid #28a745;
            margin: 8px 0;
            font-family: monospace;
            font-size: 12px;
        }
        .summary {
            background: #cfe2ff;
            color: #084298;
            padding: 15px;
            border-radius: 4px;
            margin-top: 20px;
            text-align: center;
            font-weight: bold;
        }
        .warning {
            background: #fff3cd;
            color: #664d03;
            padding: 15px;
            border-radius: 4px;
            margin-top: 20px;
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
        <h1>🔧 Fix URLs - CityLive</h1>
        
        <div class="success">
            <strong>✅ ¡Proceso completado!</strong><br>
            Se corrigieron todas las rutas de / a /
        </div>

        <div>
            <?php foreach ($results as $result): ?>
                <div class="result"><?= htmlspecialchars($result) ?></div>
            <?php endforeach; ?>
        </div>

        <div class="summary">
            Total de cambios: <strong><?= $count ?></strong>
        </div>

        <div class="warning">
            ⚠️ <strong>IMPORTANTE:</strong> Ahora debes eliminar este archivo (fix-urls.php) del servidor por seguridad.
            <br><br>
            En Infinityfree: File Manager → Buscar fix-urls.php → Borrar
        </div>

        <div style="margin-top: 30px; text-align: center;">
            <a href="/index.php" style="display: inline-block; background: #007bff; color: white; padding: 10px 30px; border-radius: 4px;">
                Volver a la aplicación
            </a>
        </div>
    </div>
</body>
</html>

