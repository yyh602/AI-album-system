<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    // 檢查 session 狀態
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
    $blobUrls = $_POST['blobUrls'] ?? [];
    $fileNames = $_POST['fileNames'] ?? [];
    
    // 基本驗證
    if ($albumName === '') {
        echo json_encode([
            'status' => 'error',
            'message' => '相簿名稱不可為空'
        ]);
        exit();
    }
    
    if (empty($blobUrls)) {
        echo json_encode([
            'status' => 'error',
            'message' => '沒有上傳的檔案'
        ]);
        exit();
    }
    
    // 資料庫連線
    try {
        require_once("DB_open.php");
        require_once("DB_helper.php");
        
        if (!isset($link) || $link === null) {
            throw new Exception("資料庫連線物件未定義或為 null");
        }
        
        if ($link instanceof mysqli) {
            $test_result = $link->query("SELECT 1 as test");
            if (!$test_result) {
                throw new Exception("MySQL 資料庫查詢失敗: " . mysqli_error($link));
            }
        } else {
            throw new Exception("資料庫連線類型不支援");
        }
        
    } catch (Exception $db_error) {
        error_log("save_album_blob.php 資料庫錯誤: " . $db_error->getMessage());
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
        $album_sql = "INSERT INTO albums (name, username, created_at) VALUES (?, ?, NOW())";
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
        
        // 2. 儲存 Blob URL 到資料庫
        $uploadedFiles = [];
        
        for ($i = 0; $i < count($blobUrls); $i++) {
            $blobUrl = $blobUrls[$i];
            $fileName = $fileNames[$i] ?? 'unknown.jpg';
            
            // 建立照片記錄
            $photo_sql = "INSERT INTO photos (album_id, filename, path, username, datetime, created_at) VALUES (?, ?, ?, ?, NOW(), NOW())";
            $photo_stmt = mysqli_prepare($link, $photo_sql);
            
            if ($photo_stmt) {
                mysqli_stmt_bind_param($photo_stmt, "isss", $album_id, $fileName, $blobUrl, $username);
                $photo_result = mysqli_stmt_execute($photo_stmt);
                mysqli_stmt_close($photo_stmt);
                
                if ($photo_result) {
                    $uploadedFiles[] = [
                        'original_name' => $fileName,
                        'blob_url' => $blobUrl
                    ];
                }
            }
        }
        
        // 3. 更新相簿封面（使用第一張照片）
        if (!empty($uploadedFiles)) {
            $firstPhotoBlobUrl = $uploadedFiles[0]['blob_url'];
            $update_cover_sql = "UPDATE albums SET cover_photo = ? WHERE id = ?";
            $update_cover_stmt = mysqli_prepare($link, $update_cover_sql);
            
            if ($update_cover_stmt) {
                mysqli_stmt_bind_param($update_cover_stmt, "si", $firstPhotoBlobUrl, $album_id);
                mysqli_stmt_execute($update_cover_stmt);
                mysqli_stmt_close($update_cover_stmt);
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
    error_log("save_album_blob.php 致命錯誤: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => '伺服器錯誤: ' . $e->getMessage()
    ]);
} catch (Throwable $t) {
    error_log("save_album_blob.php 嚴重錯誤: " . $t->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => '嚴重錯誤: ' . $t->getMessage()
    ]);
} finally {
    if (isset($link) && $link instanceof mysqli) {
        require_once("DB_close.php");
    }
}
?>
