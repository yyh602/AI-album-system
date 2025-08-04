<?php
echo "<h1>Database Connection Test</h1>";

// 顯示環境變數
echo "<h2>Environment Variables:</h2>";
echo "DB_HOST: " . ($_ENV['DB_HOST'] ?? 'NOT SET') . "<br>";
echo "DB_NAME: " . ($_ENV['DB_NAME'] ?? 'NOT SET') . "<br>";
echo "DB_USER: " . ($_ENV['DB_USER'] ?? 'NOT SET') . "<br>";
echo "DB_PASS: " . (strlen($_ENV['DB_PASS'] ?? '') > 0 ? 'SET' : 'NOT SET') . "<br>";
echo "DB_PORT: " . ($_ENV['DB_PORT'] ?? 'NOT SET') . "<br>";

// 測試資料庫連接
echo "<h2>Database Connection Test:</h2>";
try {
    $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
    $dbname = $_ENV['DB_NAME'] ?? 'myproject';
    $db_user = $_ENV['DB_USER'] ?? 'root';
    $db_pass = $_ENV['DB_PASS'] ?? 'MariaDB1688.';
    $db_port = $_ENV['DB_PORT'] ?? '3306';
    
    echo "Attempting to connect to: $host:$db_port<br>";
    echo "Database: $dbname<br>";
    echo "User: $db_user<br>";
    
    $link = new mysqli($host, $db_user, $db_pass, $dbname, $db_port);
    
    if ($link->connect_error) {
        echo "❌ Connection failed: " . $link->connect_error;
    } else {
        echo "✅ Connection successful!<br>";
        echo "Database: " . $link->query("SELECT DATABASE()")->fetch_row()[0] . "<br>";
        
        // 測試查詢
        $result = $link->query("SELECT COUNT(*) as count FROM user");
        if ($result) {
            $row = $result->fetch_assoc();
            echo "Users in database: " . $row['count'] . "<br>";
        } else {
            echo "❌ Query failed: " . $link->error . "<br>";
        }
    }
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage();
}
?> 