<?php
// Debug: Ver qué valores está leyendo .env
echo "<h2>🔍 Debug .env</h2>";
echo "<pre>";

// Ruta del .env
$envPath = __DIR__ . '/.env';
echo "📁 Buscando .env en: $envPath\n";
echo "✓ Existe: " . (file_exists($envPath) ? "SÍ" : "NO") . "\n";

if (file_exists($envPath)) {
    echo "\n📄 Contenido del .env:\n";
    echo file_get_contents($envPath);
} else {
    echo "\n❌ ERROR: El archivo .env NO existe!\n";
}

echo "\n\n📊 Variables que se cargan:\n";
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            echo "$key = " . (strpos($key, 'PASS') !== false ? '***HIDDEN***' : $value) . "\n";
        }
    }
}

echo "</pre>";
echo "<hr>";
echo "<a href='/'>← Volver al inicio</a>";
?>
