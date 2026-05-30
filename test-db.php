<?php
echo "<h2>🧪 Test de Conexión MySQL</h2>";
echo "<pre>";

// Cargar .env
$envPath = __DIR__ . '/.env';
$env = [];
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $env[trim($key)] = trim($value);
        }
    }
}

echo "📍 Intentando conectar a:\n";
echo "Host: {$env['DB_HOST']}\n";
echo "Puerto: {$env['DB_PORT']}\n";
echo "Usuario: {$env['DB_USER']}\n";
echo "Base de datos: {$env['DB_NAME']}\n\n";

// Test 1: Conectar al servidor (sin base de datos)
echo "--- Test 1: Conectar al servidor MySQL ---\n";
try {
    $pdo = new PDO(
        "mysql:host={$env['DB_HOST']};port={$env['DB_PORT']}",
        $env['DB_USER'],
        $env['DB_PASS'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "✅ Conexión al servidor OK!\n\n";
} catch (PDOException $e) {
    echo "❌ Error conectando al servidor:\n";
    echo "Error Code: " . $e->getCode() . "\n";
    echo "Error Message: " . $e->getMessage() . "\n\n";
}

// Test 2: Conectar con base de datos
echo "--- Test 2: Conectar con base de datos ---\n";
try {
    $pdo = new PDO(
        "mysql:host={$env['DB_HOST']};port={$env['DB_PORT']};dbname={$env['DB_NAME']};charset=utf8mb4",
        $env['DB_USER'],
        $env['DB_PASS'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "✅ Conexión a base de datos OK!\n\n";
    
    // Test 3: Verificar tabla users
    echo "--- Test 3: Verificar tabla users ---\n";
    $result = $pdo->query("SELECT COUNT(*) as count FROM users");
    $row = $result->fetch(PDO::FETCH_ASSOC);
    echo "✅ Tabla users encontrada. Registros: " . $row['count'] . "\n";
} catch (PDOException $e) {
    echo "❌ Error conectando a base de datos:\n";
    echo "Error Code: " . $e->getCode() . "\n";
    echo "Error Message: " . $e->getMessage() . "\n\n";
}

echo "</pre>";
echo "<hr>";
echo "<a href='/'>← Volver al inicio</a>";
?>
