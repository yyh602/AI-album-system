<?php
// 檢查 Azure 環境設定
header('Content-Type: application/json');

$environmentInfo = [
    'php_version' => phpversion(),
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
    
    // 環境變數
    'environment_variables' => [
        'DB_HOST' => $_ENV['DB_HOST'] ?? '未設定',
        'DB_NAME' => $_ENV['DB_NAME'] ?? '未設定', 
        'DB_USER' => $_ENV['DB_USER'] ?? '未設定',
        'DB_TYPE' => $_ENV['DB_TYPE'] ?? '未設定',
        'WEBSITE_REQUEST_SIZE_LIMIT' => $_ENV['WEBSITE_REQUEST_SIZE_LIMIT'] ?? '未設定',
    ],
    
    // PHP 擴展
    'php_extensions' => [
        'gd' => extension_loaded('gd'),
        'exif' => extension_loaded('exif'),
        'mysqli' => extension_loaded('mysqli'),
        'imagick' => extension_loaded('imagick'),
        'curl' => extension_loaded('curl'),
    ],
    
    // PHP 設定
    'php_settings' => [
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
        'max_execution_time' => ini_get('max_execution_time'),
        'memory_limit' => ini_get('memory_limit'),
    ],
    
    // 工具檢查
    'tools_available' => [
        'imagemagick' => [
            'which_convert' => trim(shell_exec('which convert 2>/dev/null') ?: '不可用'),
            'convert_version' => trim(shell_exec('convert -version 2>/dev/null | head -1') ?: '不可用'),
        ],
        'exiftool' => [
            'which_exiftool' => trim(shell_exec('which exiftool 2>/dev/null') ?: '不可用'),
            'exiftool_version' => trim(shell_exec('exiftool -ver 2>/dev/null') ?: '不可用'),
        ]
    ],
    
    // 檔案權限
    'permissions' => [
        'uploads_dir_writable' => is_writable(__DIR__ . '/uploads') || mkdir(__DIR__ . '/uploads', 0755, true),
        'current_dir_writable' => is_writable(__DIR__),
    ]
];

echo json_encode($environmentInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
