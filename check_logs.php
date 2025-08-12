<?php
header('Content-Type: application/json');

$result = [
    'php_error_log' => ini_get('error_log'),
    'log_errors' => ini_get('log_errors'),
    'display_errors' => ini_get('display_errors'),
    'error_reporting' => error_reporting(),
    'temp_dir' => sys_get_temp_dir(),
    'current_dir' => __DIR__,
    'possible_log_locations' => [
        'D:\home\LogFiles\Application\',
        'D:\home\LogFiles\http\RawLogs\',
        'D:\home\site\wwwroot\logs\',
        sys_get_temp_dir() . '\logs\',
        __DIR__ . '\logs\'
    ]
];

// 測試寫入日誌
error_log("測試日誌訊息 - " . date('Y-m-d H:i:s'));

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
