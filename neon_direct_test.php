<?php
echo "<h1>Neon 直接連線測試</h1>";

// 顯示環境變數
echo "<h2>環境變數檢查：</h2>";
echo "DB_HOST: " . ($_ENV['DB_HOST'] ?? 'NOT SET') . "<br>";
echo "DB_NAME: " . ($_ENV['DB_NAME'] ?? 'NOT SET') . "<br>";
echo "DB_USER: " . ($_ENV['DB_USER'] ?? 'NOT SET') . "<br>";
echo "DB_PASS: " . (strlen($_ENV['DB_PASS'] ?? '') > 0 ? 'SET' : 'NOT SET') . "<br>";
echo "DB_PORT: " . ($_ENV['DB_PORT'] ?? 'NOT SET') . "<br>";

// 測試資料庫連接
echo "<h2>Neon 直接連線測試：</h2>";

try {
    $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
    $dbname = $_ENV['DB_NAME'] ?? 'myproject';
    $db_user = $_ENV['DB_USER'] ?? 'root';
    $db_pass = $_ENV['DB_PASS'] ?? 'MariaDB1688.';
    $db_port = $_ENV['DB_PORT'] ?? '3306';
    
    echo "嘗試連線到：$host:$db_port<br>";
    echo "資料庫：$dbname<br>";
    echo "使用者：$db_user<br>";
    
    // 方法 1：使用 Neon 官方建議的連線字串
    echo "<h3>方法 1：Neon 官方連線字串</h3>";
    $dsn1 = "postgresql://$db_user:$db_pass@$host:$db_port/$dbname?sslmode=require&options=endpoint%3Dep-sweet-band-a1bt630p";
    echo "DSN: $dsn1<br>";
    
    try {
        $pdo1 = new PDO($dsn1);
        $pdo1->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "✅ 方法 1 連線成功！<br>";
        
        $stmt = $pdo1->query("SELECT current_database() as db_name, version() as version");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "目前資料庫：" . $row['db_name'] . "<br>";
        echo "PostgreSQL 版本：" . $row['version'] . "<br>";
        
    } catch (PDOException $e) {
        echo "❌ 方法 1 失敗：" . $e->getMessage() . "<br>";
    }
    
    // 方法 2：使用 pgsql DSN 格式，不包含 options
    echo "<h3>方法 2：簡化 pgsql DSN</h3>";
    $dsn2 = "pgsql:host=$host;port=$db_port;dbname=$dbname;sslmode=require;user=$db_user;password=$db_pass";
    echo "DSN: $dsn2<br>";
    
    try {
        $pdo2 = new PDO($dsn2);
        $pdo2->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "✅ 方法 2 連線成功！<br>";
        
        $stmt = $pdo2->query("SELECT current_database() as db_name, version() as version");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "目前資料庫：" . $row['db_name'] . "<br>";
        echo "PostgreSQL 版本：" . $row['version'] . "<br>";
        
    } catch (PDOException $e) {
        echo "❌ 方法 2 失敗：" . $e->getMessage() . "<br>";
    }
    
    // 方法 3：使用原生 pgsql 函數
    echo "<h3>方法 3：原生 pgsql 函數</h3>";
    try {
        $connection_string = "host=$host port=$db_port dbname=$dbname user=$db_user password=$db_pass sslmode=require";
        echo "連線字串: $connection_string<br>";
        
        $pg_conn = pg_connect($connection_string);
        if ($pg_conn) {
            echo "✅ 方法 3 連線成功！<br>";
            
            $result = pg_query($pg_conn, "SELECT current_database() as db_name, version() as version");
            $row = pg_fetch_assoc($result);
            echo "目前資料庫：" . $row['db_name'] . "<br>";
            echo "PostgreSQL 版本：" . $row['version'] . "<br>";
            
            pg_close($pg_conn);
        } else {
            echo "❌ 方法 3 失敗：無法建立連線<br>";
        }
        
    } catch (Exception $e) {
        echo "❌ 方法 3 失敗：" . $e->getMessage() . "<br>";
    }
    
    // 方法 4：檢查 libpq 版本
    echo "<h3>方法 4：檢查 libpq 版本</h3>";
    try {
        $result = pg_parameter_status($pg_conn ?? null, 'server_version');
        if ($result) {
            echo "PostgreSQL 伺服器版本：$result<br>";
        } else {
            echo "無法取得伺服器版本<br>";
        }
    } catch (Exception $e) {
        echo "無法檢查版本：" . $e->getMessage() . "<br>";
    }
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>❌ 測試失敗：</h3>";
    echo "錯誤訊息：" . $e->getMessage() . "<br>";
}

echo "<hr>";
echo "<p><strong>測試完成時間：</strong>" . date('Y-m-d H:i:s') . "</p>";
?> 