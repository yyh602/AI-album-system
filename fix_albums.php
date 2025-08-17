<?php
session_start();

if (!isset($_SESSION["username"])) {
    echo "請先登入";
    exit();
}

$username = $_SESSION["username"];
echo "<h2>修復相簿系統</h2>";
echo "用戶：$username<br><br>";

require_once("DB_open.php");

if ($link === null) {
    echo "❌ 資料庫連接失敗<br>";
    exit();
}

echo "✅ 資料庫連接成功<br><br>";

// 建立資料表的 SQL 語句
$create_tables_sql = [
    // 建立 albums 資料表
    "CREATE TABLE IF NOT EXISTS albums (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(255) NOT NULL,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        cover_photo VARCHAR(500),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_username (username),
        INDEX idx_created_at (created_at)
    )",
    
    // 建立 photos 資料表
    "CREATE TABLE IF NOT EXISTS photos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        album_id INT NOT NULL,
        filename VARCHAR(255) NOT NULL,
        path VARCHAR(500) NOT NULL,
        original_name VARCHAR(255),
        file_size INT,
        mime_type VARCHAR(100),
        width INT,
        height INT,
        datetime DATETIME,
        latitude DECIMAL(10, 8),
        longitude DECIMAL(11, 8),
        location VARCHAR(255),
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_album_id (album_id),
        INDEX idx_datetime (datetime),
        INDEX idx_location (location)
    )",
    
    // 建立 uploads 資料表
    "CREATE TABLE IF NOT EXISTS uploads (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(255) NOT NULL,
        filename VARCHAR(255) NOT NULL,
        path VARCHAR(500) NOT NULL,
        original_name VARCHAR(255),
        file_size INT,
        mime_type VARCHAR(100),
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_username (username),
        INDEX idx_uploaded_at (uploaded_at)
    )"
];

// 執行建立資料表
echo "<h3>建立資料表：</h3>";
foreach ($create_tables_sql as $sql) {
    if ($link instanceof mysqli) {
        if (mysqli_query($link, $sql)) {
            echo "✅ 資料表建立成功<br>";
        } else {
            echo "❌ 資料表建立失敗：" . mysqli_error($link) . "<br>";
        }
    }
}

echo "<br><h3>檢查資料表：</h3>";

// 檢查資料表是否存在
$tables = ['albums', 'photos', 'uploads'];
foreach ($tables as $table) {
    if ($link instanceof mysqli) {
        $result = mysqli_query($link, "SHOW TABLES LIKE '$table'");
        if (mysqli_num_rows($result) > 0) {
            echo "✅ $table 資料表存在<br>";
            
            // 檢查資料數量
            $count_result = mysqli_query($link, "SELECT COUNT(*) as count FROM $table");
            $count_row = mysqli_fetch_assoc($count_result);
            echo "&nbsp;&nbsp;&nbsp;&nbsp;資料數量：{$count_row['count']}<br>";
        } else {
            echo "❌ $table 資料表不存在<br>";
        }
    }
}

// 檢查用戶的相簿
echo "<br><h3>檢查您的相簿：</h3>";
if ($link instanceof mysqli) {
    $sql = "SELECT COUNT(*) as count FROM albums WHERE username = ?";
    $stmt = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $album_count);
    mysqli_stmt_fetch($stmt);
    
    echo "您的相簿數量：$album_count<br>";
    
    if ($album_count > 0) {
        // 顯示相簿列表
        $sql = "SELECT id, name, created_at FROM albums WHERE username = ? ORDER BY created_at DESC";
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
}

require_once("DB_close.php");

echo "<br><h3>修復完成！</h3>";
echo "<a href='album.php' class='btn btn-primary'>前往相簿頁面</a>";
echo "<br><br>";
echo "<a href='add.php' class='btn btn-success'>建立新相簿</a>";
?>
