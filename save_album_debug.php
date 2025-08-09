<?php
// 完全獨立的 save_album 測試版本
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// 捕獲所有錯誤
ob_start();

try {
    // 檢查 session
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    // 基本調試信息
    $debug_info = [
        'session_status' => session_status(),
        'session_data' => $_SESSION ?? 'No session',
        'post_data' => $_POST,
        'files_data' => $_FILES,
        'server_info' => [
            'PHP_VERSION' => phpversion(),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'max_execution_time' => ini_get('max_execution_time'),
        ]
    ];
    
    // 檢查登入（簡化）
    $username = $_SESSION['username'] ?? 'test_user';
    $albumName = trim($_POST['albumName'] ?? $_GET['albumName'] ?? '測試相簿');
    
    // 檔案檢查
    if (empty($_FILES)) {
        echo json_encode([
            'status' => 'error',
            'message' => '無檔案上傳',
            'debug' => $debug_info
        ]);
        exit();
    }
    
    // 處理檔案上傳（不依賴資料庫）
    $uploadedFiles = [];
    $uploadDir = 'uploads/' . date('Y/m/d') . '/';
    
    // 確保目錄存在
    if (!file_exists($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            throw new Exception("無法創建上傳目錄：$uploadDir");
        }
    }
    
    foreach ($_FILES as $fieldName => $file) {
        $fileInfo = [
            'field_name' => $fieldName,
            'original_name' => $file['name'],
            'size' => $file['size'],
            'type' => $file['type'],
            'error' => $file['error'],
            'tmp_name' => $file['tmp_name']
        ];
        
        if ($file['error'] === UPLOAD_ERR_OK) {
            $fileName = uniqid() . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
            $filePath = $uploadDir . $fileName;
            
            if (move_uploaded_file($file['tmp_name'], $filePath)) {
                $fileInfo['status'] = 'success';
                $fileInfo['stored_name'] = $fileName;
                $fileInfo['stored_path'] = $filePath;
            } else {
                $fileInfo['status'] = 'move_failed';
                $fileInfo['error_msg'] = 'move_uploaded_file 失敗';
            }
        } else {
            $fileInfo['status'] = 'upload_error';
            $fileInfo['error_msg'] = 'Upload error: ' . $file['error'];
        }
        
        $uploadedFiles[] = $fileInfo;
    }
    
    // 成功回應
    echo json_encode([
        'status' => 'success',
        'message' => '相簿建立成功（除錯模式）',
        'data' => [
            'album_name' => $albumName,
            'username' => $username,
            'uploaded_files' => $uploadedFiles,
            'upload_dir' => $uploadDir,
            'debug' => $debug_info
        ]
    ]);
    
} catch (Exception $e) {
    $output = ob_get_clean();
    
    echo json_encode([
        'status' => 'error',
        'message' => '伺服器錯誤: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
        'output' => $output,
        'debug' => $debug_info ?? null
    ]);
}

ob_end_clean();
?>
