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
    $db_type = $_ENV['DB_TYPE'] ?? 'postgresql';
    
    echo "Attempting to connect to: $host:$db_port<br>";
    echo "Database: $dbname<br>";
    echo "User: $db_user<br>";
    echo "Type: $db_type<br>";
    
    if ($db_type === 'postgresql' || $db_type === 'pgsql') {
        // 修正 Neon 資料庫連線 - 使用正確的連線格式
        // 如果主機名稱不完整，添加完整的域名
        if (strpos($host, '.neon.tech') === false) {
            $host = $host . '.neon.tech';
            echo "修正後的主機名稱：$host<br>";
        }
        
        // 從主機名稱提取 endpoint ID - 移除 -pooler 後綴
        $hostParts = explode('.', $host);
        $endpointId = str_replace('-pooler', '', $hostParts[0]);
        echo "Endpoint ID：$endpointId<br>";
        
        // 使用正確的 Neon 連線字串格式，將 channel_binding 放在 options 參數中
        $dsn = "pgsql:host=$host;port=$db_port;dbname=$dbname;sslmode=require;options=endpoint%3D$endpointId&channel_binding%3Drequire;user=$db_user;password=$db_pass";
        echo "DSN: $dsn<br>";
        
        $pdo = new PDO($dsn);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        echo "✅ PostgreSQL connection successful!<br>";
        $stmt = $pdo->query("SELECT current_database() as db_name, version() as version");
        $row = $stmt->fetch('ASSOC');
        echo "Database: " . $row['db_name'] . "<br>";
        echo "PostgreSQL version: " . $row['version'] . "<br>";
        
        // 測試查詢
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM \"user\"");
            $row = $stmt->fetch('ASSOC');
            echo "Users in database: " . $row['count'] . "<br>";
        } catch (Exception $e) {
            echo "⚠️ user 表不存在或無法查詢：" . $e->getMessage() . "<br>";
        }
    } else {
        // MySQL 連接
        $link = new mysqli($host, $db_user, $db_pass, $dbname, $db_port);
        
        if ($link->connect_error) {
            echo "❌ MySQL connection failed: " . $link->connect_error;
        } else {
            echo "✅ MySQL connection successful!<br>";
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
    }
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage();
}
?> 