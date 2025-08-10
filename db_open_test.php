<?php
echo "<h1>DB_open.php Test</h1>";

// 檢查 mysqli 擴展
echo "<h2>PHP Extensions:</h2>";
echo "mysqli: " . (extension_loaded('mysqli') ? '✅ Loaded' : '❌ Not loaded') . "<br>";
echo "pdo_mysql: " . (extension_loaded('pdo_mysql') ? '✅ Loaded' : '❌ Not loaded') . "<br>";

// 測試環境變數
echo "<h2>Environment Variables:</h2>";
$host = $_ENV['DB_HOST'] ?? '127.0.0.1';
$dbname = $_ENV['DB_NAME'] ?? 'myproject';
$db_user = $_ENV['DB_USER'] ?? 'root';
$db_pass = $_ENV['DB_PASS'] ?? 'MariaDB1688.';
$db_port = $_ENV['DB_PORT'] ?? '3306';
$db_type = $_ENV['DB_TYPE'] ?? 'postgresql';

echo "Host: $host<br>";
echo "Database: $dbname<br>";
echo "User: $db_user<br>";
echo "Port: $db_port<br>";
echo "Type: $db_type<br>";

// 測試連接
echo "<h2>Connection Test:</h2>";
echo "Attempting to connect...<br>";

try {
    if ($db_type === 'postgresql' || $db_type === 'pgsql') {
        // PostgreSQL 連接
        $cleanHost = explode('?', $host)[0];
        $endpointId = explode('.', $cleanHost)[0];
        
        $dsn = "pgsql:host=$cleanHost;port=$db_port;dbname=$dbname;sslmode=require;options=endpoint%3D$endpointId;user=$db_user;password=$db_pass";
        $pdo = new PDO($dsn);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        echo "✅ PostgreSQL connection successful!";
    } else {
        // MySQL 連接
        $link = new mysqli($host, $db_user, $db_pass, $dbname, $db_port);
        
        if ($link->connect_error) {
            echo "❌ MySQL connection failed: " . $link->connect_error;
        } else {
            echo "✅ MySQL connection successful!";
        }
    }
} catch (Exception $e) {
    echo "❌ Connection failed: " . $e->getMessage();
}

echo "<p>✅ Test completed!</p>";
?> 