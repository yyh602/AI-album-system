<?php
// 簡單的診斷檔案
echo "<h2>相簿頁面診斷</h2>";

// 1. 檢查 Session
session_start();
echo "<p><strong>1. Session 檢查：</strong></p>";
if (isset($_SESSION["username"])) {
    echo "✅ 已登入，用戶名：" . $_SESSION["username"] . "<br>";
} else {
    echo "❌ 未登入，請先登入<br>";
    echo "<a href='login.php'>前往登入頁面</a><br>";
    exit();
}

// 2. 檢查資料庫連接
echo "<p><strong>2. 資料庫連接檢查：</strong></p>";
require_once("DB_open.php");
if ($link === null) {
    echo "❌ 資料庫連接失敗<br>";
    exit();
} else {
    echo "✅ 資料庫連接成功<br>";
}

// 3. 檢查資料表
echo "<p><strong>3. 資料表檢查：</strong></p>";
if ($link instanceof mysqli) {
    $tables = ['albums', 'photos', 'uploads', 'user'];
    foreach ($tables as $table) {
        $result = mysqli_query($link, "SHOW TABLES LIKE '$table'");
        if (mysqli_num_rows($result) > 0) {
            echo "✅ $table 資料表存在<br>";
        } else {
            echo "❌ $table 資料表不存在<br>";
        }
    }
}

// 4. 檢查相簿資料
echo "<p><strong>4. 相簿資料檢查：</strong></p>";
$username = $_SESSION["username"];
$sql = "SELECT COUNT(*) as count FROM albums WHERE username = ?";
$stmt = mysqli_prepare($link, $sql);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $count);
    mysqli_stmt_fetch($stmt);
    echo "✅ 找到 $count 個相簿<br>";
    mysqli_stmt_close($stmt);
} else {
    echo "❌ 相簿查詢失敗：" . mysqli_error($link) . "<br>";
}

// 5. 檢查照片資料
echo "<p><strong>5. 照片資料檢查：</strong></p>";
$sql = "SELECT COUNT(*) as count FROM photos p JOIN albums a ON p.album_id = a.id WHERE a.username = ?";
$stmt = mysqli_prepare($link, $sql);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $count);
    mysqli_stmt_fetch($stmt);
    echo "✅ 找到 $count 張照片<br>";
    mysqli_stmt_close($stmt);
} else {
    echo "❌ 照片查詢失敗：" . mysqli_error($link) . "<br>";
}

require_once("DB_close.php");

echo "<p><strong>6. 建議：</strong></p>";
echo "<a href='album.php'>前往相簿頁面</a><br>";
echo "<a href='welcome.php'>前往首頁</a><br>";
?>
