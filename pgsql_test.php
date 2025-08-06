<?php
echo "<h1>PostgreSQL Connection Test</h1>";

// 檢查 PDO 擴展
echo "<h2>PHP Extensions:</h2>";
echo "pdo: " . (extension_loaded('pdo') ? '✅ Loaded' : '❌ Not loaded') . "<br>";
echo "pdo_pgsql: " . (extension_loaded('pdo_pgsql') ? '✅ Loaded' : '❌ Not loaded') . "<br>";

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

// 測試 PostgreSQL 連接
echo "<h2>PostgreSQL Connection Test:</h2>";
try {
    // 從主機名稱提取 endpoint ID (移除查詢參數)
    $cleanHost = explode('?', $host)[0];
    $endpointId = explode('.', $cleanHost)[0];
    
    $dsn = "pgsql:host=$cleanHost;port=$db_port;dbname=$dbname;sslmode=require;options=endpoint%3D$endpointId;user=$db_user;password=$db_pass";
    echo "DSN: $dsn<br>";
    
    $pdo = new PDO($dsn);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ PostgreSQL connection successful!<br>";
    
    // 測試查詢
    $stmt = $pdo->query("SELECT current_database() as db_name");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Current database: " . $row['db_name'] . "<br>";
    
    // 檢查 user 表是否存在
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM \"user\"");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Users in database: " . $row['count'] . "<br>";
    
} catch (PDOException $e) {
    echo "❌ PostgreSQL connection failed: " . $e->getMessage();
}

echo "<p>✅ Test completed!</p>";
?> 