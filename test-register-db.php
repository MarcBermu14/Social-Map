<?php
echo "<h2>🧪 Test config/db.php</h2>";
echo "<pre>";

// Cargar config
require_once __DIR__ . '/config/db.php';

echo "📍 Constantes definidas:\n";
echo "DB_HOST = " . DB_HOST . "\n";
echo "DB_NAME = " . DB_NAME . "\n";
echo "DB_USER = " . DB_USER . "\n";
echo "DB_PORT = " . DB_PORT . "\n";
echo "DB_CHAR = " . DB_CHAR . "\n\n";

echo "--- Intentando getDB() ---\n";
try {
    $db = getDB();
    echo "✅ getDB() funcionó!\n";
    echo "PDO: " . get_class($db) . "\n";
    
    // Test query
    $result = $db->query("SELECT COUNT(*) as count FROM users");
    $row = $result->fetch(PDO::FETCH_ASSOC);
    echo "✅ Registros en users: " . $row['count'] . "\n";
} catch (Exception $e) {
    echo "❌ Error en getDB():\n";
    echo $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

echo "</pre>";
?>
