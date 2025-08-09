<?php
session_start();
require_once("DB_open.php");
require_once("DB_helper.php");
header('Content-Type: application/json');

$username = $_SESSION['username'] ?? '';
$diary_id = $_GET['diary_id'] ?? '';

if (!$username || !$diary_id) {
    echo json_encode(['status' => 'error', 'message' => '缺少必要參數']);
    exit;
}

if ($link instanceof PgSQLWrapper || $link instanceof PDO) {
    // 獲取日誌詳情
    $diary_sql = "SELECT d.*, a.name as album_name FROM travel_diary d LEFT JOIN albums a ON d.album_id = a.id WHERE d.id = ? AND d.username = ?";
    $diary_stmt = $link->prepare($diary_sql);
    $diary_stmt->execute([$diary_id, $username]);
    $diary = $diary_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$diary) {
        echo json_encode(['status' => 'error', 'message' => '找不到日誌']);
        exit;
    }

    // 獲取相簿照片
    $photos = [];
    if ($diary['album_id']) {
        $photo_sql = "SELECT filename, path, datetime, latitude, longitude FROM photos WHERE album_id = ? ORDER BY datetime ASC";
        $photo_stmt = $link->prepare($photo_sql);
        $photo_stmt->execute([$diary['album_id']]);
        while ($photo = $photo_stmt->fetch(PDO::FETCH_ASSOC)) {
            $photos[] = $photo;
        }
    }
} else {
    if ($link instanceof mysqli) {
        // 獲取日誌詳情
        $diary_sql = "SELECT d.*, a.name as album_name FROM travel_diary d LEFT JOIN albums a ON d.album_id = a.id WHERE d.id = ? AND d.username = ?";
        $diary_stmt = mysqli_prepare($link, $diary_sql);
        mysqli_stmt_bind_param($diary_stmt, "is", $diary_id, $username);
        mysqli_stmt_execute($diary_stmt);
        $diary_result = mysqli_stmt_get_result($diary_stmt);
        $diary = mysqli_fetch_assoc($diary_result);
        mysqli_stmt_close($diary_stmt);

        if (!$diary) {
            echo json_encode(['status' => 'error', 'message' => '找不到日誌']);
            exit;
        }

        // 獲取相簿照片
        $photos = [];
        if ($diary['album_id']) {
            $photo_sql = "SELECT filename, path, datetime, latitude, longitude FROM photos WHERE album_id = ? ORDER BY datetime ASC";
            $photo_stmt = mysqli_prepare($link, $photo_sql);
            mysqli_stmt_bind_param($photo_stmt, "i", $diary['album_id']);
            mysqli_stmt_execute($photo_stmt);
            $photo_result = mysqli_stmt_get_result($photo_stmt);
            while ($photo = mysqli_fetch_assoc($photo_result)) {
                $photos[] = $photo;
            }
            mysqli_stmt_close($photo_stmt);
        }
    } else {
        // 如果是 PDOWrapper，使用 PDO 方式查詢
        // 獲取日誌詳情
        $diary_sql = "SELECT d.*, a.name as album_name FROM travel_diary d LEFT JOIN albums a ON d.album_id = a.id WHERE d.id = ? AND d.username = ?";
        $diary_stmt = $link->prepare($diary_sql);
        $diary_stmt->execute([$diary_id, $username]);
        $diary = $diary_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$diary) {
            echo json_encode(['status' => 'error', 'message' => '找不到日誌']);
            exit;
        }

        // 獲取相簿照片
        $photos = [];
        if ($diary['album_id']) {
            $photo_sql = "SELECT filename, path, datetime, latitude, longitude FROM photos WHERE album_id = ? ORDER BY datetime ASC";
            $photo_stmt = $link->prepare($photo_sql);
            $photo_stmt->execute([$diary['album_id']]);
            while ($photo = $photo_stmt->fetch(PDO::FETCH_ASSOC)) {
                $photos[] = $photo;
            }
        }
    }
}

require_once("DB_close.php");

echo json_encode([
    'status' => 'success',
    'id' => $diary['id'],
    'album_id' => $diary['album_id'],
    'album_name' => $diary['album_name'],
    'content' => $diary['content'],
    'created_at' => $diary['created_at'],
    'photos' => $photos
]);
?>
