<?php
header('Content-Type: application/json');

// 步驟 1：基本測試
$result = [
    'step' => 1,
    'status' => 'ok',
    'php_version' => PHP_VERSION,
    'exif_loaded' => extension_loaded('exif'),
    'post_data' => $_POST,
    'request_method' => $_SERVER['REQUEST_METHOD']
];

echo json_encode($result);
?>
