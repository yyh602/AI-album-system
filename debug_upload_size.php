<?php
// 調試上傳大小
header('Content-Type: application/json');

echo json_encode([
    'php_settings' => [
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
        'max_execution_time' => ini_get('max_execution_time'),
        'memory_limit' => ini_get('memory_limit'),
        'max_file_uploads' => ini_get('max_file_uploads'),
    ],
    'server_info' => [
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'Unknown',
        'content_length' => $_SERVER['CONTENT_LENGTH'] ?? 'Unknown',
        'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'Unknown',
    ],
    'post_data_size' => strlen(file_get_contents('php://input')),
    'files_info' => [
        'files_count' => count($_FILES),
        'files_details' => $_FILES
    ],
    'post_data' => $_POST
]);
?>
