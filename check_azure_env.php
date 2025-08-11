<?php
// 檢查 Azure App Service 環境
header('Content-Type: application/json; charset=utf-8');

$result = [
    'php_version' => PHP_VERSION,
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
    'environment_variables' => [
        'DB_HOST' => getenv('DB_HOST') ?: '未設定',
        'DB_NAME' => getenv('DB_NAME') ?: '未設定',
        'DB_USER' => getenv('DB_USER') ?: '未設定',
        'DB_TYPE' => getenv('DB_TYPE') ?: '未設定',
        'WEBSITE_REQUEST_SIZE_LIMIT' => getenv('WEBSITE_REQUEST_SIZE_LIMIT') ?: '未設定',
        'WEBSITE_UPLOAD_MAX_SIZE' => getenv('WEBSITE_UPLOAD_MAX_SIZE') ?: '未設定',
        'NGINX_CLIENT_MAX_BODY_SIZE' => getenv('NGINX_CLIENT_MAX_BODY_SIZE') ?: '未設定',
        'PHP_INI_SCAN_DIR' => getenv('PHP_INI_SCAN_DIR') ?: '未設定'
    ],
    'php_extensions' => [
        'gd' => extension_loaded('gd'),
        'exif' => extension_loaded('exif'),
        'mysqli' => extension_loaded('mysqli'),
        'imagick' => extension_loaded('imagick'),
        'curl' => extension_loaded('curl')
    ],
    'php_settings' => [
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
        'max_execution_time' => ini_get('max_execution_time'),
        'memory_limit' => ini_get('memory_limit')
    ],
    'tools_available' => [
        'imagemagick' => [
            'which_convert' => shell_exec('which convert') ?: '不可用',
            'convert_version' => shell_exec('convert --version 2>&1 | head -1') ?: '不可用'
        ],
        'exiftool' => [
            'which_exiftool' => shell_exec('which exiftool') ?: '不可用',
            'exiftool_version' => shell_exec('exiftool -ver 2>&1') ?: '不可用'
        ]
    ],
    'permissions' => [
        'uploads_dir_writable' => is_writable('uploads'),
        'current_dir_writable' => is_writable('.')
    ],
    'user_ini_file' => [
        'exists' => file_exists('.user.ini'),
        'content' => file_exists('.user.ini') ? file_get_contents('.user.ini') : '檔案不存在'
    ],
    'nginx_config' => [
        'client_max_body_size' => '需要檢查 Nginx 設定'
    ]
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
