<?php
// 工作版本的 save_album.php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

    try {
        // 設定 PHP 上傳限制（增強版本）
        ini_set('upload_max_filesize', '100M');
        ini_set('post_max_size', '100M');
        ini_set('max_execution_time', '7200');
        ini_set('max_input_time', '7200');
        ini_set('memory_limit', '512M');
        ini_set('max_file_uploads', '50');
    
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
    $albumName = trim($_POST['albumName'] ?? $_GET['albumName'] ?? '');
    
    // 基本驗證
    if ($albumName === '') {
        echo json_encode([
            'status' => 'error',
            'message' => '相簿名稱不可為空'
        ]);
        exit();
    }
    
    // 檔案檢查
    if (empty($_FILES)) {
        echo json_encode([
            'status' => 'error',
            'message' => '請選擇要上傳的檔案'
        ]);
        exit();
    }
    
    // 檢查總檔案大小
    $totalSize = 0;
    $maxFileSize = 50 * 1024 * 1024; // 50MB
    $maxTotalSize = 100 * 1024 * 1024; // 100MB
    
    foreach ($_FILES as $file) {
        if (is_array($file['size'])) {
            $totalSize += array_sum($file['size']);
        } else {
            $totalSize += $file['size'];
        }
    }
    
    if ($totalSize > $maxTotalSize) {
        echo json_encode([
            'status' => 'error',
            'message' => '檔案總大小超過限制 (100MB)，目前大小：' . round($totalSize / 1024 / 1024, 2) . 'MB'
        ]);
        exit();
    }
    
    // 資料庫連線
    try {
        require_once("DB_open.php");
        require_once("DB_helper.php");
        
        // 檢查 $link 變數是否正確載入
        if (!isset($link) || $link === null) {
            throw new Exception("資料庫連線物件未定義或為 null");
        }
        
        // 測試資料庫連線
        if ($link instanceof mysqli) {
            $test_result = $link->query("SELECT 1 as test");
            if (!$test_result) {
                throw new Exception("MySQL 資料庫查詢失敗: " . mysqli_error($link));
            }
        } else {
            throw new Exception("資料庫連線類型不支援");
        }
        
    } catch (Exception $db_error) {
        error_log("save_album.php 資料庫錯誤: " . $db_error->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => '資料庫連線失敗: ' . $db_error->getMessage()
        ]);
        exit();
    }
    
    // 開始資料庫交易
    mysqli_begin_transaction($link);
    
    try {
        // 1. 建立相簿記錄
        $album_sql = "INSERT INTO album (album_name, username, created_at) VALUES (?, ?, NOW())";
        $album_stmt = mysqli_prepare($link, $album_sql);
        
        if (!$album_stmt) {
            throw new Exception("相簿建立失敗: " . mysqli_error($link));
        }
        
        mysqli_stmt_bind_param($album_stmt, "ss", $albumName, $username);
        $album_result = mysqli_stmt_execute($album_stmt);
        
        if (!$album_result) {
            throw new Exception("相簿建立執行失敗: " . mysqli_stmt_error($album_stmt));
        }
        
        $album_id = mysqli_insert_id($link);
        mysqli_stmt_close($album_stmt);
        
        // 2. 處理檔案上傳
        $uploadedFiles = [];
        $uploadDir = 'uploads/' . date('Y/m/d') . '/';
        
        // 確保目錄存在
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        foreach ($_FILES as $fieldName => $file) {
            if ($file['error'] === UPLOAD_ERR_OK) {
                $fileName = uniqid() . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
                $filePath = $uploadDir . $fileName;
                
                if (move_uploaded_file($file['tmp_name'], $filePath)) {
                    // 3. 建立照片記錄
                    $photo_sql = "INSERT INTO photo (album_id, original_name, stored_name, file_path, file_size, upload_date) VALUES (?, ?, ?, ?, ?, NOW())";
                    $photo_stmt = mysqli_prepare($link, $photo_sql);
                    
                    if ($photo_stmt) {
                        mysqli_stmt_bind_param($photo_stmt, "isssi", $album_id, $file['name'], $fileName, $filePath, $file['size']);
                        $photo_result = mysqli_stmt_execute($photo_stmt);
                        mysqli_stmt_close($photo_stmt);
                        
                        if ($photo_result) {
                            $uploadedFiles[] = [
                                'original_name' => $file['name'],
                                'stored_name' => $fileName,
                                'path' => $filePath,
                                'size' => $file['size']
                            ];
                        }
                    }
                }
            }
        }
        
        // 4. 提交交易
        mysqli_commit($link);
        
        // 5. 成功回應
        echo json_encode([
            'status' => 'success',
            'message' => '相簿建立成功',
            'data' => [
                'album_id' => $album_id,
                'album_name' => $albumName,
                'username' => $username,
                'uploaded_files' => $uploadedFiles,
                'total_files' => count($uploadedFiles)
            ]
        ]);
        
    } catch (Exception $e) {
        // 6. 回滾交易
        mysqli_rollback($link);
        throw $e;
    }
    
} catch (Exception $e) {
    // 記錄詳細錯誤到日誌
    error_log("save_album.php 致命錯誤: " . $e->getMessage());
    error_log("save_album.php 致命錯誤檔案: " . $e->getFile());
    error_log("save_album.php 致命錯誤行號: " . $e->getLine());
    
    // 避免 502 錯誤，不設定 HTTP 500 狀態碼
    echo json_encode([
        'status' => 'error',
        'message' => '伺服器錯誤: ' . $e->getMessage()
    ]);
} catch (Throwable $t) {
    // 捕獲所有可能的錯誤，包括 Fatal Error
    error_log("save_album.php 嚴重錯誤: " . $t->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => '嚴重錯誤: ' . $t->getMessage()
    ]);
} finally {
    // 確保資料庫連線關閉
    if (isset($link) && $link instanceof mysqli) {
        require_once("DB_close.php");
    }
}
?>
