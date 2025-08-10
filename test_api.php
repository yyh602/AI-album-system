<?php
// 簡化的 API 測試
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // 檢查 session 狀態，避免重複啟動
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    echo json_encode([
        'status' => 'success',
        'message' => 'API 正常運作',
        'session_status' => session_status(),
        'session_id' => session_id(),
        'username' => $_SESSION['username'] ?? 'guest',
        'request_method' => $_SERVER['REQUEST_METHOD'],
        'get_params' => $_GET,
        'post_params' => $_POST,
        'files' => isset($_FILES) ? array_keys($_FILES) : [],
        'php_version' => phpversion(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT);
}
?>
