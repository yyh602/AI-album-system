<?php
// 不需要登入的測試版本
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    // 檢查 session 狀態，避免重複啟動
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    // 模擬登入用戶（測試用）
    $username = 'test_user';
    $albumName = trim($_POST['albumName'] ?? $_GET['albumName'] ?? 'Test Album');
    
    // 基本驗證
    if ($albumName === '') {
        echo json_encode([
            'status' => 'error',
            'message' => '相簿名稱不可為空'
        ]);
        exit();
    }
    
    // 測試資料庫連線
    require_once("DB_open.php");
    
    if (!$link) {
        echo json_encode([
            'status' => 'error',
            'message' => '資料庫連線失敗'
        ]);
        exit();
    }
    
    // 測試簡單查詢
    if ($link instanceof PgSQLWrapper) {
        $result = $link->query("SELECT 1 as test");
        $test_row = $result->fetch_assoc();
    } else {
        $result = $link->query("SELECT 1 as test");
        $test_row = $result->fetch_assoc();
    }
    
    echo json_encode([
        'status' => 'success',
        'message' => '測試成功',
        'data' => [
            'album_name' => $albumName,
            'username' => $username,
            'database_test' => $test_row,
            'database_type' => get_class($link),
            'files_received' => count($_FILES),
            'post_data' => $_POST,
            'get_data' => $_GET
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>
