<?php
header('Content-Type: application/json');

$logFiles = [
    '/tmp/php_errors.log',
    '/home/site/wwwroot/php_errors.log',
    '/tmp/logs/php_errors.log'
];

$result = [
    'log_files' => [],
    'error_log_setting' => ini_get('error_log'),
    'log_errors_setting' => ini_get('log_errors')
];

foreach ($logFiles as $logFile) {
    if (file_exists($logFile)) {
        $content = file_get_contents($logFile);
        $result['log_files'][$logFile] = [
            'exists' => true,
            'size' => filesize($logFile),
            'last_modified' => date('Y-m-d H:i:s', filemtime($logFile)),
            'last_50_lines' => array_slice(explode("\n", $content), -50)
        ];
    } else {
        $result['log_files'][$logFile] = [
            'exists' => false
        ];
    }
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
