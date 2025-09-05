<?php
session_start();
require_once("DB_open.php");
require_once("DB_helper.php");
header('Content-Type: application/json');

$username = $_SESSION['username'] ?? '';
$album_id = $_POST['album_id'] ?? '';
$album_name = $_POST['album_name'] ?? '';
$content = $_POST['content'] ?? '';
$cover_photo = $_POST['cover_photo'] ?? '';
$cover_photo_album = $_POST['cover_photo_album'] ?? '';
$cover_photo_path = $_POST['cover_photo_path'] ?? ''; // 新增這行

// 調試輸出
error_log("儲存日誌請求 - 用戶: $username, 相簿ID: $album_id, 相簿名: $album_name, 內容長度: " . strlen($content));

if (!$username || $album_id === '' || !$content) {
    echo json_encode(['status' => 'error', 'message' => '缺少必要欄位']);
    exit;
}

if (false) { // 停用 PostgreSQL 邏輯
    $stmt = $link->prepare("INSERT INTO travel_diary (username, album_id, album_name, content, cover_photo, cover_photo_album, cover_photo_path, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    if ($stmt->execute([$username, $album_id, $album_name, $content, $cover_photo, $cover_photo_album, $cover_photo_path])) {
        echo json_encode(['status' => 'success']);
    } else {
        $error = $stmt->errorInfo();
        echo json_encode(['status' => 'error', 'message' => $error[2] ?? '未知錯誤']);
    }
} else {
    if ($link instanceof mysqli) {
        // 檢查 travel_diary 表是否有 cover_photo_path 欄位
        $result = mysqli_query($link, "SHOW COLUMNS FROM travel_diary LIKE 'cover_photo_path'");
        $has_cover_path_field = mysqli_num_rows($result) > 0;
        
        if ($has_cover_path_field) {
            // 有 cover_photo_path 欄位，使用完整插入
            $stmt = mysqli_prepare($link, "INSERT INTO travel_diary (username, album_id, album_name, content, cover_photo, cover_photo_album, cover_photo_path, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            mysqli_stmt_bind_param($stmt, "sisssss", $username, $album_id, $album_name, $content, $cover_photo, $cover_photo_album, $cover_photo_path);
        } else {
            // 沒有 cover_photo_path 欄位，使用基本插入
            $stmt = mysqli_prepare($link, "INSERT INTO travel_diary (username, album_id, album_name, content, cover_photo, cover_photo_album, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            mysqli_stmt_bind_param($stmt, "sissss", $username, $album_id, $album_name, $content, $cover_photo, $cover_photo_album);
        }
        
        if (mysqli_stmt_execute($stmt)) {
            $new_id = mysqli_insert_id($link);
            error_log("日誌儲存成功，ID: $new_id");
            echo json_encode(['status' => 'success', 'id' => $new_id]);
        } else {
            $error = mysqli_stmt_error($stmt);
            error_log("日誌儲存失敗: $error");
            echo json_encode(['status' => 'error', 'message' => $error]);
        }
        mysqli_stmt_close($stmt);
    } else {
        echo json_encode(['status' => 'error', 'message' => '資料庫連接類型不支援']);
    }
}

require_once("DB_close.php");