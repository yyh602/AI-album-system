<?php
header('Content-Type: application/json');

$health = [
    'status' => 'healthy',
    'timestamp' => date('Y-m-d H:i:s'),
    'php_version' => phpversion(),
    'extensions' => [
        'pdo' => extension_loaded('pdo'),
        'pdo_pgsql' => extension_loaded('pdo_pgsql'),
        'mysqli' => extension_loaded('mysqli'),
        'gd' => extension_loaded('gd'),
        'exif' => extension_loaded('exif')
    ]
];

echo json_encode($health, JSON_PRETTY_PRINT);
?> 