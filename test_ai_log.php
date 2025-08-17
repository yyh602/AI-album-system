<?php
session_start();

// 檢查 session
if (!isset($_SESSION["username"])) {
    echo "錯誤：未登入，請先登入";
    exit();
}

$username = $_SESSION["username"];
echo "歡迎，{$username}！<br>";

// 檢查資料庫連接
require_once("DB_open.php");
require_once("DB_helper.php");

if ($link === null) {
    echo "錯誤：資料庫連接失敗<br>";
    exit();
}

echo "資料庫連接成功<br>";

// 檢查 travel_diary 資料表是否存在
if ($link instanceof mysqli) {
    $result = mysqli_query($link, "SHOW TABLES LIKE 'travel_diary'");
    if (mysqli_num_rows($result) == 0) {
        echo "警告：travel_diary 資料表不存在，正在建立...<br>";
        
        $create_table_sql = "
        CREATE TABLE IF NOT EXISTS travel_diary (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(255) NOT NULL,
            album_id INT,
            album_name VARCHAR(255),
            content TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_username (username),
            INDEX idx_album_id (album_id),
            INDEX idx_created_at (created_at)
        )";
        
        if (mysqli_query($link, $create_table_sql)) {
            echo "✅ travel_diary 資料表建立成功<br>";
        } else {
            echo "❌ travel_diary 資料表建立失敗：" . mysqli_error($link) . "<br>";
        }
    } else {
        echo "✅ travel_diary 資料表已存在<br>";
    }
}

// 檢查 albums 資料表是否存在
if ($link instanceof mysqli) {
    $result = mysqli_query($link, "SHOW TABLES LIKE 'albums'");
    if (mysqli_num_rows($result) == 0) {
        echo "❌ albums 資料表不存在<br>";
    } else {
        echo "✅ albums 資料表已存在<br>";
    }
}

// 檢查 photos 資料表是否存在
if ($link instanceof mysqli) {
    $result = mysqli_query($link, "SHOW TABLES LIKE 'photos'");
    if (mysqli_num_rows($result) == 0) {
        echo "❌ photos 資料表不存在<br>";
    } else {
        echo "✅ photos 資料表已存在<br>";
    }
}

// 測試查詢歷史日誌
$diaries = [];
if ($link instanceof mysqli) {
    $diary_sql = "SELECT d.*, a.cover_photo, a.name as album_name FROM travel_diary d LEFT JOIN albums a ON d.album_id = a.id WHERE d.username = ? ORDER BY d.created_at DESC";
    $diary_stmt = mysqli_prepare($link, $diary_sql);
    if ($diary_stmt) {
        mysqli_stmt_bind_param($diary_stmt, "s", $username);
        mysqli_stmt_execute($diary_stmt);
        $diary_result = mysqli_stmt_get_result($diary_stmt);
        while ($row = mysqli_fetch_assoc($diary_result)) {
            $diaries[] = $row;
        }
        mysqli_stmt_close($diary_stmt);
        echo "✅ 歷史日誌查詢成功，找到 " . count($diaries) . " 筆記錄<br>";
    } else {
        echo "❌ 歷史日誌查詢失敗：" . mysqli_error($link) . "<br>";
    }
}

require_once("DB_close.php");

echo "<br>測試完成！<br>";
echo "<a href='ai_log.php'>前往 AI 日誌頁面</a>";
?>
