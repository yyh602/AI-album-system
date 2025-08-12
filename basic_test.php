<?php
header('Content-Type: application/json');

echo json_encode([
    'status' => 'ok',
    'message' => '基本測試成功',
    'php_version' => PHP_VERSION,
    'exif_loaded' => extension_loaded('exif'),
    'time' => date('Y-m-d H:i:s')
]);
?>
