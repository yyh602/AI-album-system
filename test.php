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
    $db_type = $_ENV['DB_TYPE'] ?? 'postgresql';
    
    if ($db_type === 'postgresql' || $db_type === 'pgsql') {
        // PostgreSQL 連接
        $cleanHost = explode('?', $host)[0];
        $endpointId = explode('.', $cleanHost)[0];
        
        $dsn = "pgsql:host=$cleanHost;port=$db_port;dbname=$dbname;sslmode=require;options=endpoint%3D$endpointId;user=$db_user;password=$db_pass";
        $pdo = new PDO($dsn);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        echo "✅ PostgreSQL 資料庫連接成功！<br>";
        $stmt = $pdo->query("SELECT current_database() as db_name");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "資料庫名稱: " . $row['db_name'];
    } else {
        // MySQL 連接
        $link = new mysqli($host, $db_user, $db_pass, $dbname, $db_port);
        
        if ($link->connect_error) {
            echo "❌ MySQL 資料庫連接失敗: " . $link->connect_error;
        } else {
            echo "✅ MySQL 資料庫連接成功！<br>";
            echo "資料庫名稱: " . $link->query("SELECT DATABASE()")->fetch_row()[0];
        }
    }
} catch (Exception $e) {
    echo "❌ 連接異常: " . $e->getMessage();
}
?> 