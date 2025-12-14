<?php
// Simple migration runner for development only
require_once __DIR__ . '/../includes/database.php';

$dir = __DIR__ . '/../migrations';
$files = array_values(array_filter(scandir($dir), function($f) use ($dir) { return is_file($dir . '/' . $f) && preg_match('/^\d+_.*\.sql$/', $f); }));

foreach ($files as $file) {
    $path = $dir . '/' . $file;
    echo "Applying migration: $file\n";
    try {
        $sql = file_get_contents($path);
        $pdo->exec($sql);
        echo "OK\n";
    } catch (Exception $e) {
        echo "Failed: " . $e->getMessage() . "\n";
    }
}

echo "Done\n";
