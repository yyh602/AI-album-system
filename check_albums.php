<?php
session_start();

if (!isset($_SESSION["username"])) {
    echo "請先登入";
    exit();
}

$username = $_SESSION["username"];
echo "用戶：$username<br><br>";

require_once("DB_open.php");

// 檢查 albums 資料表
echo "<h3>檢查 albums 資料表</h3>";
if ($link instanceof mysqli) {
    $result = mysqli_query($link, "SHOW TABLES LIKE 'albums'");
    if (mysqli_num_rows($result) > 0) {
        echo "✅ albums 資料表存在<br>";
        
        // 檢查相簿資料
        $sql = "SELECT COUNT(*) as count FROM albums WHERE username = ?";
        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $count);
        mysqli_stmt_fetch($stmt);
        echo "您的相簿數量：$count<br>";
        
        if ($count > 0) {
            // 顯示相簿列表
            $sql = "SELECT id, name, cover_photo, created_at FROM albums WHERE username = ? ORDER BY created_at DESC";
            $stmt = mysqli_prepare($link, $sql);
            mysqli_stmt_bind_param($stmt, "s", $username);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            echo "<h4>您的相簿：</h4>";
            while ($row = mysqli_fetch_assoc($result)) {
                echo "- {$row['name']} (ID: {$row['id']}) - {$row['created_at']}<br>";
            }
        } else {
            echo "您還沒有相簿<br>";
        }
        
    } else {
        echo "❌ albums 資料表不存在<br>";
        echo "需要建立 albums 資料表<br>";
    }
}

// 檢查 photos 資料表
echo "<h3>檢查 photos 資料表</h3>";
if ($link instanceof mysqli) {
    $result = mysqli_query($link, "SHOW TABLES LIKE 'photos'");
    if (mysqli_num_rows($result) > 0) {
        echo "✅ photos 資料表存在<br>";
        
        // 檢查照片數量
        $sql = "SELECT COUNT(*) as count FROM photos p JOIN albums a ON p.album_id = a.id WHERE a.username = ?";
        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $count);
        mysqli_stmt_fetch($stmt);
        echo "您的照片數量：$count<br>";
        
    } else {
        echo "❌ photos 資料表不存在<br>";
    }
}

require_once("DB_close.php");

echo "<br><a href='album.php'>前往相簿頁面</a>";
?>
