<?php
require_once 'config.php';
/**
 * Reemplazar todas las rutas /citylive/ con / en archivos PHP y JS
 * Ejecución: http://localhost/citylive/fix-all-routes.php
 */

$baseDir = __DIR__;
$filesModified = 0;

// Buscar todos los archivos .php y .js
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($baseDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

$patterns = [
    '/citylive/' => '/',
];

echo "<h2>🔧 Reemplazando rutas /citylive/ → /</h2>";
echo "<pre>";

foreach ($iterator as $file) {
    $ext = $file->getExtension();
    if (!in_array($ext, ['php', 'js', 'html'])) continue;
    
    $path = $file->getRealPath();
    
    // Skip itself, tests, and markdown
    if (strpos($path, 'fix-all-routes.php') !== false) continue;
    if (strpos($path, '.md') !== false) continue;
    if (strpos($path, 'test-') !== false && strpos($path, '.php') !== false) continue;
    
    $content = file_get_contents($path);
    $originalContent = $content;
    
    // Reemplazar
    foreach ($patterns as $search => $replace) {
        $content = str_replace($search, $replace, $content);
    }
    
    // Si cambió, guardar
    if ($content !== $originalContent) {
        file_put_contents($path, $content);
        $filesModified++;
        echo "✓ " . str_replace($baseDir, '.', $path) . "\n";
    }
}

echo "\n✅ Total archivos modificados: $filesModified\n";
echo "</pre>";

// Limpiar después
echo "<br><a href='/' class='btn btn-primary'>Volver al inicio →</a>";
?>
