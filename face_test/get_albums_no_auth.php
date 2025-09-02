<?php
header('Content-Type: application/json; charset=utf-8');

// 引入資料庫連接
require_once '../DB_open.php';

try {
    // 檢查資料庫連接
    if (!isset($link) || !($link instanceof mysqli)) {
        throw new Exception('資料庫連接失敗');
    }

    // 獲取請求參數
    $allAlbums = isset($_GET['all_albums']) && $_GET['all_albums'] == '1';
    $albumId = isset($_GET['album_id']) ? intval($_GET['album_id']) : null;

    if ($allAlbums) {
        // 查詢所有相簿
        $query = "SELECT id, name, username, cover_photo, description, created_at, updated_at FROM albums ORDER BY created_at DESC";
        $result = $link->query($query);
        
        if (!$result) {
            throw new Exception('查詢相簿失敗: ' . $link->error);
        }

        $albums = [];
        while ($row = $result->fetch_assoc()) {
            $albums[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'username' => $row['username'],
                'cover_photo' => $row['cover_photo'] ?: '../img/default_album_cover.svg',
                'description' => $row['description'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at']
            ];
        }

        echo json_encode([
            'status' => 'success',
            'albums' => $albums
        ], JSON_UNESCAPED_UNICODE);

    } elseif ($albumId) {
        // 查詢特定相簿的照片
        $query = "SELECT id, filename, path, datetime, album_id FROM photos WHERE album_id = ? ORDER BY created_at DESC";
        $stmt = $link->prepare($query);
        
        if (!$stmt) {
            throw new Exception('準備查詢失敗: ' . $link->error);
        }

        $stmt->bind_param('i', $albumId);
        $stmt->execute();
        $result = $stmt->get_result();

        if (!$result) {
            throw new Exception('執行查詢失敗: ' . $stmt->error);
        }

        $photos = [];
        while ($row = $result->fetch_assoc()) {
            $photos[] = [
                'id' => $row['id'],
                'filename' => $row['filename'],
                'path' => $row['path'],
                'datetime' => $row['datetime'],
                'album_id' => $row['album_id']
            ];
        }

        echo json_encode([
            'status' => 'success',
            'photos' => $photos
        ], JSON_UNESCAPED_UNICODE);

    } else {
        throw new Exception('缺少必要參數');
    }

} catch (Exception $e) {
    error_log('get_albums_no_auth.php 錯誤: ' . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?> 