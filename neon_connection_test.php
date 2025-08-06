<?php
echo "<h1>Neon 資料庫連線測試</h1>";

// 顯示環境變數
echo "<h2>環境變數檢查：</h2>";
echo "DB_HOST: " . ($_ENV['DB_HOST'] ?? 'NOT SET') . "<br>";
echo "DB_NAME: " . ($_ENV['DB_NAME'] ?? 'NOT SET') . "<br>";
echo "DB_USER: " . ($_ENV['DB_USER'] ?? 'NOT SET') . "<br>";
echo "DB_PASS: " . (strlen($_ENV['DB_PASS'] ?? '') > 0 ? 'SET' : 'NOT SET') . "<br>";
echo "DB_PORT: " . ($_ENV['DB_PORT'] ?? 'NOT SET') . "<br>";
echo "DB_TYPE: " . ($_ENV['DB_TYPE'] ?? 'NOT SET') . "<br>";

// 測試資料庫連接
echo "<h2>資料庫連線測試：</h2>";

try {
    $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
    $dbname = $_ENV['DB_NAME'] ?? 'myproject';
    $db_user = $_ENV['DB_USER'] ?? 'root';
    $db_pass = $_ENV['DB_PASS'] ?? 'MariaDB1688.';
    $db_port = $_ENV['DB_PORT'] ?? '3306';
    $db_type = $_ENV['DB_TYPE'] ?? 'postgresql';
    
    echo "嘗試連線到：$host:$db_port<br>";
    echo "資料庫：$dbname<br>";
    echo "使用者：$db_user<br>";
    echo "類型：$db_type<br>";
    
    if ($db_type === 'postgresql' || $db_type === 'pgsql') {
        // 修正 Neon 資料庫連線
        // 如果主機名稱不完整，添加完整的域名
        if (strpos($host, '.neon.tech') === false) {
            $host = $host . '.neon.tech';
            echo "修正後的主機名稱：$host<br>";
        }
        
        // 從主機名稱提取 endpoint ID
        $endpointId = explode('.', $host)[0];
        echo "Endpoint ID：$endpointId<br>";
        
        // 使用正確的 Neon 連線字串格式，包含 channel_binding 參數
        $dsn = "pgsql:host=$host;port=$db_port;dbname=$dbname;sslmode=require;channel_binding=require;options=endpoint%3D$endpointId;user=$db_user;password=$db_pass";
        echo "DSN：$dsn<br>";
        
        $pdo = new PDO($dsn);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        echo "<h3 style='color: green;'>✅ PostgreSQL 連線成功！</h3>";
        
        // 測試查詢
        $stmt = $pdo->query("SELECT current_database() as db_name, version() as version");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "目前資料庫：" . $row['db_name'] . "<br>";
        echo "PostgreSQL 版本：" . $row['version'] . "<br>";
        
        // 檢查 user 表是否存在
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM \"user\"");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "資料庫中的使用者數量：" . $row['count'] . "<br>";
        } catch (Exception $e) {
            echo "⚠️ user 表不存在或無法查詢：" . $e->getMessage() . "<br>";
        }
        
    } else {
        echo "❌ 不支援的資料庫類型：$db_type<br>";
    }
    
} catch (PDOException $e) {
    echo "<h3 style='color: red;'>❌ PostgreSQL 連線失敗：</h3>";
    echo "錯誤訊息：" . $e->getMessage() . "<br>";
    
    // 提供修正建議
    echo "<h3>修正建議：</h3>";
    echo "1. 確保 DB_HOST 包含完整的域名（例如：ep-sweet-band-a1bt630p-pooler.ap-southeast-1.aws.neon.tech）<br>";
    echo "2. 檢查 DB_USER 和 DB_PASS 是否正確<br>";
    echo "3. 確保 DB_PORT 設定為 5432<br>";
    echo "4. 檢查網路連線是否正常<br>";
}

echo "<hr>";
echo "<p><strong>測試完成時間：</strong>" . date('Y-m-d H:i:s') . "</p>";
?> 