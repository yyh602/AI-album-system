<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '/tmp/php_errors.log');

// 檢查 session 狀態
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$result = [
    'status' => 'debug',
    'session_status' => session_status(),
    'username' => $_SESSION['username'] ?? 'not_set',
    'post_data' => $_POST,
    'files' => $_FILES,
    'server_info' => [
        'php_version' => PHP_VERSION,
        'extensions' => [
            'exif' => extension_loaded('exif'),
            'imagick' => extension_loaded('imagick'),
            'curl' => extension_loaded('curl'),
            'mysqli' => extension_loaded('mysqli')
        ],
        'current_dir' => getcwd(),
        'script_path' => __FILE__,
        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'not_set'
    ]
];

// 檢查 save_album_blob.php 是否存在
$save_album_blob_path = __DIR__ . '/save_album_blob.php';
$result['file_check'] = [
    'save_album_blob_exists' => file_exists($save_album_blob_path),
    'save_album_blob_path' => $save_album_blob_path,
    'save_album_blob_readable' => is_readable($save_album_blob_path),
    'current_files' => array_slice(scandir(__DIR__), 0, 10) // 只顯示前10個檔案
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
