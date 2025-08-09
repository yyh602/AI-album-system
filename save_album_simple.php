<?php
// 簡化版本的 save_album.php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0); // 避免干擾 JSON 輸出

try {
    // 檢查 session 狀態，避免重複啟動
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    // 檢查登入狀態
    if (!isset($_SESSION['username'])) {
        echo json_encode([
            'status' => 'error',
            'message' => '請先登入'
        ]);
        exit();
    }
    
    $username = $_SESSION['username'];
    $albumName = trim($_POST['albumName'] ?? '');
    
    // 基本驗證
    if ($albumName === '') {
        echo json_encode([
            'status' => 'error',
            'message' => '相簿名稱不可為空'
        ]);
        exit();
    }
    
    if (empty($_FILES)) {
        echo json_encode([
            'status' => 'error',
            'message' => '請選擇要上傳的檔案'
        ]);
        exit();
    }
    
    // 模擬成功回應（不實際處理資料庫和檔案）
    echo json_encode([
        'status' => 'success',
        'message' => '相簿建立成功（測試模式）',
        'data' => [
            'album_name' => $albumName,
            'username' => $username,
            'file_count' => count($_FILES),
            'files' => array_keys($_FILES)
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>
