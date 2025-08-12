<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$result = [
    'status' => 'path_test',
    'current_url' => $_SERVER['REQUEST_URI'] ?? 'not_set',
    'script_name' => $_SERVER['SCRIPT_NAME'] ?? 'not_set',
    'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'not_set',
    'current_dir' => getcwd(),
    'files_in_current_dir' => array_slice(scandir('.'), 0, 15),
    'save_album_blob_exists' => file_exists('save_album_blob.php'),
    'test_save_album_exists' => file_exists('test_save_album.php'),
    'debug_upload_exists' => file_exists('debug_upload.php'),
    'album_exists' => file_exists('album.php')
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
