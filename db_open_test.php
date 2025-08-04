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

echo "Host: $host<br>";
echo "Database: $dbname<br>";
echo "User: $db_user<br>";
echo "Port: $db_port<br>";

// 測試連接（不包含在 try-catch 中）
echo "<h2>Connection Test:</h2>";
echo "Attempting to connect...<br>";

$link = new mysqli($host, $db_user, $db_pass, $dbname, $db_port);

if ($link->connect_error) {
    echo "❌ Connection failed: " . $link->connect_error;
} else {
    echo "✅ Connection successful!";
}

echo "<p>✅ Test completed!</p>";
?> 