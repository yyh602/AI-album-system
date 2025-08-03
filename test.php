<?php
// 簡單的測試檔案
echo "PHP 版本: " . phpversion() . "<br>";
echo "mysqli 擴展: " . (extension_loaded('mysqli') ? '已安裝' : '未安裝') . "<br>";
echo "pdo_mysql 擴展: " . (extension_loaded('pdo_mysql') ? '已安裝' : '未安裝') . "<br>";

// 測試環境變數
echo "DB_HOST: " . ($_ENV['DB_HOST'] ?? '未設定') . "<br>";
echo "DB_NAME: " . ($_ENV['DB_NAME'] ?? '未設定') . "<br>";
echo "DB_USER: " . ($_ENV['DB_USER'] ?? '未設定') . "<br>";

// 測試資料庫連接
try {
    $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
    $dbname = $_ENV['DB_NAME'] ?? 'myproject';
    $db_user = $_ENV['DB_USER'] ?? 'root';
    $db_pass = $_ENV['DB_PASS'] ?? 'MariaDB1688.';
    $db_port = $_ENV['DB_PORT'] ?? '3306';
    
    $link = new mysqli($host, $db_user, $db_pass, $dbname, $db_port);
    
    if ($link->connect_error) {
        echo "❌ 資料庫連接失敗: " . $link->connect_error;
    } else {
        echo "✅ 資料庫連接成功！<br>";
        echo "資料庫名稱: " . $link->query("SELECT DATABASE()")->fetch_row()[0];
    }
} catch (Exception $e) {
    echo "❌ 連接異常: " . $e->getMessage();
}
?> 