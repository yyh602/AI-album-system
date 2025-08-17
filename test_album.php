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

if ($link === null) {
    echo "錯誤：資料庫連接失敗<br>";
    exit();
}

echo "資料庫連接成功<br>";

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

// 檢查 uploads 資料表是否存在
if ($link instanceof mysqli) {
    $result = mysqli_query($link, "SHOW TABLES LIKE 'uploads'");
    if (mysqli_num_rows($result) == 0) {
        echo "❌ uploads 資料表不存在<br>";
    } else {
        echo "✅ uploads 資料表已存在<br>";
    }
}

// 測試查詢相簿
$albums = [];
if ($link instanceof mysqli) {
    $sql = "SELECT * FROM albums WHERE username = ? ORDER BY created_at DESC";
    $stmt = mysqli_prepare($link, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $albums[] = $row;
        }
        mysqli_stmt_close($stmt);
        echo "✅ 相簿查詢成功，找到 " . count($albums) . " 個相簿<br>";
    } else {
        echo "❌ 相簿查詢失敗：" . mysqli_error($link) . "<br>";
    }
}

// 測試查詢照片
$photos = [];
if ($link instanceof mysqli) {
    $sql = "SELECT p.*, a.name as album_name FROM photos p LEFT JOIN albums a ON p.album_id = a.id WHERE a.username = ? ORDER BY p.datetime DESC";
    $stmt = mysqli_prepare($link, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $photos[] = $row;
        }
        mysqli_stmt_close($stmt);
        echo "✅ 照片查詢成功，找到 " . count($photos) . " 張照片<br>";
    } else {
        echo "❌ 照片查詢失敗：" . mysqli_error($link) . "<br>";
    }
}

require_once("DB_close.php");

echo "<br>測試完成！<br>";
echo "<a href='album.php'>前往相簿頁面</a>";
?>
