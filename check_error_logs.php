<?php
header('Content-Type: application/json');

// 檢查錯誤日誌
function getErrorLogs() {
    $logs = [];
    
    // 檢查 PHP 錯誤日誌
    $errorLog = ini_get('error_log');
    if ($errorLog && file_exists($errorLog)) {
        $logs['php_error_log'] = [
            'path' => $errorLog,
            'exists' => true,
            'size' => filesize($errorLog),
            'last_modified' => date('Y-m-d H:i:s', filemtime($errorLog)),
            'recent_lines' => []
        ];
        
        // 讀取最近的 20 行
        $lines = file($errorLog);
        if ($lines) {
            $logs['php_error_log']['recent_lines'] = array_slice($lines, -20);
        }
    } else {
        $logs['php_error_log'] = [
            'path' => $errorLog,
            'exists' => false,
            'message' => 'PHP 錯誤日誌不存在或無法訪問'
        ];
    }
    
    // 檢查系統日誌
    $systemLogs = [
        '/var/log/apache2/error.log',
        '/var/log/nginx/error.log',
        '/var/log/httpd/error_log',
        '/tmp/php_errors.log'
    ];
    
    $logs['system_logs'] = [];
    foreach ($systemLogs as $logPath) {
        if (file_exists($logPath)) {
            $logs['system_logs'][$logPath] = [
                'exists' => true,
                'size' => filesize($logPath),
                'last_modified' => date('Y-m-d H:i:s', filemtime($logPath))
            ];
        }
    }
    
    // 檢查當前目錄的日誌檔案
    $currentDirLogs = glob('*.log');
    $logs['current_dir_logs'] = [];
    foreach ($currentDirLogs as $logFile) {
        $logs['current_dir_logs'][$logFile] = [
            'size' => filesize($logFile),
            'last_modified' => date('Y-m-d H:i:s', filemtime($logFile))
        ];
    }
    
    return $logs;
}

// 搜尋特定關鍵字的日誌
function searchLogs($keyword) {
    $results = [];
    
    // 搜尋 PHP 錯誤日誌
    $errorLog = ini_get('error_log');
    if ($errorLog && file_exists($errorLog)) {
        $lines = file($errorLog);
        if ($lines) {
            foreach ($lines as $lineNum => $line) {
                if (stripos($line, $keyword) !== false) {
                    $results[] = [
                        'file' => $errorLog,
                        'line' => $lineNum + 1,
                        'content' => trim($line)
                    ];
                }
            }
        }
    }
    
    return $results;
}

// 主程式
$action = $_GET['action'] ?? 'check';

if ($action === 'search' && isset($_GET['keyword'])) {
    $keyword = $_GET['keyword'];
    $results = searchLogs($keyword);
    echo json_encode([
        'action' => 'search',
        'keyword' => $keyword,
        'results' => $results,
        'count' => count($results)
    ]);
} else {
    $logs = getErrorLogs();
    echo json_encode([
        'action' => 'check',
        'logs' => $logs,
        'usage' => [
            'check_logs' => '?action=check',
            'search_exif' => '?action=search&keyword=EXIF',
            'search_error' => '?action=search&keyword=error',
            'search_save_album' => '?action=search&keyword=save_album'
        ]
    ]);
}
?>
