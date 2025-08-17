<?php
echo "<h2>資料庫狀態檢查</h2>";

// 1. 檢查 PHP MySQL 擴展
echo "<h3>1. PHP MySQL 擴展檢查：</h3>";
if (extension_loaded('mysqli')) {
    echo "✅ mysqli 擴展已載入<br>";
} else {
    echo "❌ mysqli 擴展未載入<br>";
}

if (extension_loaded('pdo_mysql')) {
    echo "✅ pdo_mysql 擴展已載入<br>";
} else {
    echo "❌ pdo_mysql 擴展未載入<br>";
}

// 2. 檢查資料庫連接
echo "<h3>2. 資料庫連接檢查：</h3>";
require_once("DB_open.php");

if ($link === null) {
    echo "❌ 資料庫連接失敗<br>";
    echo "可能原因：<br>";
    echo "- MySQL 服務未啟動<br>";
    echo "- 資料庫連接參數錯誤<br>";
    echo "- 資料庫不存在<br>";
    exit();
} else {
    echo "✅ 資料庫連接成功<br>";
    
    // 檢查資料庫資訊
    if ($link instanceof mysqli) {
        echo "資料庫主機：" . $link->host_info . "<br>";
        echo "資料庫版本：" . $link->server_info . "<br>";
        echo "當前資料庫：" . $link->database . "<br>";
    }
}

// 3. 檢查資料表
echo "<h3>3. 資料表檢查：</h3>";
$tables = ['albums', 'photos', 'uploads', 'user', 'travel_diary'];
foreach ($tables as $table) {
    if ($link instanceof mysqli) {
        $result = mysqli_query($link, "SHOW TABLES LIKE '$table'");
        if (mysqli_num_rows($result) > 0) {
            echo "✅ $table 資料表存在<br>";
        } else {
            echo "❌ $table 資料表不存在<br>";
        }
    }
}

// 4. 檢查資料庫服務狀態
echo "<h3>4. 資料庫服務狀態：</h3>";
if ($link instanceof mysqli) {
    $result = mysqli_query($link, "SELECT VERSION() as version, NOW() as current_time");
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        echo "資料庫版本：" . $row['version'] . "<br>";
        echo "當前時間：" . $row['current_time'] . "<br>";
        echo "✅ 資料庫服務正常運行<br>";
    } else {
        echo "❌ 無法查詢資料庫狀態<br>";
    }
}

require_once("DB_close.php");

echo "<br><h3>總結：</h3>";
echo "如果看到「資料庫連接成功」，表示資料庫服務正在運行。<br>";
echo "如果看到「資料庫連接失敗」，表示需要啟動 MySQL 服務。<br>";

echo "<br><h3>如何啟動 MySQL 服務：</h3>";
echo "1. 如果您使用 XAMPP：<br>";
echo "   - 開啟 XAMPP Control Panel<br>";
echo "   - 點擊 MySQL 旁邊的「Start」按鈕<br>";
echo "   - 等待狀態變為綠色<br><br>";

echo "2. 如果您使用 WAMP：<br>";
echo "   - 開啟 WAMP<br>";
echo "   - 等待系統托盤圖示變為綠色<br><br>";

echo "3. 如果您使用獨立 MySQL：<br>";
echo "   - 開啟服務管理員（services.msc）<br>";
echo "   - 找到 MySQL 服務<br>";
echo "   - 右鍵選擇「啟動」<br>";

echo "<br><a href='album.php'>前往相簿頁面</a>";
?>
