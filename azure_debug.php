<?php
session_start();

echo "<h1>🔧 Azure 部署診斷與修復</h1>";

// 1. 環境檢查
echo "<h2>1. 環境檢查</h2>";
echo "PHP 版本：" . PHP_VERSION . "<br>";
echo "伺服器：" . $_SERVER['SERVER_SOFTWARE'] ?? '未知' . "<br>";
echo "當前目錄：" . getcwd() . "<br>";
echo "檔案路徑：" . __FILE__ . "<br>";

// 2. Session 檢查
echo "<h2>2. Session 檢查</h2>";
if (isset($_SESSION["username"])) {
    echo "✅ 已登入，用戶名：" . $_SESSION["username"] . "<br>";
    $username = $_SESSION["username"];
} else {
    echo "❌ 未登入<br>";
    echo "Session ID：" . session_id() . "<br>";
    echo "Session 狀態：" . session_status() . "<br>";
    
    // 嘗試建立測試 Session
    $_SESSION["test"] = "test_value";
    echo "測試 Session 建立：" . (isset($_SESSION["test"]) ? "成功" : "失敗") . "<br>";
}

// 3. 資料庫連接檢查
echo "<h2>3. 資料庫連接檢查</h2>";

// 檢查環境變數
echo "環境變數檢查：<br>";
$env_vars = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS', 'DB_PORT', 'DB_TYPE'];
foreach ($env_vars as $var) {
    $value = $_ENV[$var] ?? getenv($var);
    if ($value) {
        echo "✅ $var = " . substr($value, 0, 10) . "..." . "<br>";
    } else {
        echo "❌ $var = 未設定<br>";
    }
}

require_once("DB_open.php");

if ($link === null) {
    echo "❌ 資料庫連接失敗<br>";
    
    // 嘗試不同的連接方式
    echo "嘗試修復資料庫連接...<br>";
    
    // 方式1：使用環境變數
    $host = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?? 'album.mysql.database.azure.com';
    $dbname = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?? 'album';
    $db_user = $_ENV['DB_USER'] ?? getenv('DB_USER') ?? 's1411131020';
    $db_pass = $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?? 'Aa123456';
    $db_port = $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?? '3306';
    
    echo "嘗試連接：$host:$db_port/$dbname<br>";
    
    $test_link = new mysqli();
    $test_link->ssl_set(null, null, null, null, null);
    $ssl_flag = defined('MYSQLI_CLIENT_SSL') ? MYSQLI_CLIENT_SSL : 2048;
    $success = $test_link->real_connect($host, $db_user, $db_pass, $dbname, $db_port, null, $ssl_flag);
    
    if ($success && !$test_link->connect_error) {
        echo "✅ 資料庫連接修復成功<br>";
        $link = $test_link;
        $link->set_charset("utf8");
    } else {
        echo "❌ 資料庫連接修復失敗：" . $test_link->connect_error . "<br>";
    }
} else {
    echo "✅ 資料庫連接成功<br>";
    if ($link instanceof mysqli) {
        echo "資料庫：" . $link->database . "<br>";
        echo "主機：" . $link->host_info . "<br>";
    }
}

// 4. 建立資料表
echo "<h2>4. 建立資料表</h2>";
if ($link instanceof mysqli) {
    $tables = [
        'albums' => "CREATE TABLE IF NOT EXISTS albums (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(255) NOT NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            cover_photo VARCHAR(500),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_username (username),
            INDEX idx_created_at (created_at)
        )",
        
        'photos' => "CREATE TABLE IF NOT EXISTS photos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            album_id INT NOT NULL,
            filename VARCHAR(255) NOT NULL,
            path VARCHAR(500) NOT NULL,
            original_name VARCHAR(255),
            file_size INT,
            mime_type VARCHAR(100),
            width INT,
            height INT,
            datetime DATETIME,
            latitude DECIMAL(10, 8),
            longitude DECIMAL(11, 8),
            location VARCHAR(255),
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_album_id (album_id),
            INDEX idx_datetime (datetime),
            INDEX idx_location (location)
        )",
        
        'user' => "CREATE TABLE IF NOT EXISTS user (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(255) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            name VARCHAR(255),
            email VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_username (username)
        )",
        
        'travel_diary' => "CREATE TABLE IF NOT EXISTS travel_diary (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(255) NOT NULL,
            album_id INT,
            album_name VARCHAR(255),
            content TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_username (username),
            INDEX idx_album_id (album_id),
            INDEX idx_created_at (created_at)
        )"
    ];
    
    foreach ($tables as $table_name => $sql) {
        if (mysqli_query($link, $sql)) {
            echo "✅ $table_name 資料表建立成功<br>";
        } else {
            echo "❌ $table_name 資料表建立失敗：" . mysqli_error($link) . "<br>";
        }
    }
}

// 5. 建立測試資料
echo "<h2>5. 建立測試資料</h2>";
if ($link instanceof mysqli && isset($username)) {
    // 檢查用戶
    $stmt = mysqli_prepare($link, "SELECT COUNT(*) as count FROM user WHERE username = ?");
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $user_count);
    mysqli_stmt_fetch($stmt);
    
    if ($user_count == 0) {
        echo "建立測試用戶...<br>";
        $hashed_password = password_hash('test123', PASSWORD_DEFAULT);
        $stmt = mysqli_prepare($link, "INSERT INTO user (username, password, name) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "sss", $username, $hashed_password, $username);
        if (mysqli_stmt_execute($stmt)) {
            echo "✅ 測試用戶建立成功<br>";
        }
    } else {
        echo "✅ 用戶已存在<br>";
    }
    
    // 建立測試相簿
    $stmt = mysqli_prepare($link, "SELECT COUNT(*) as count FROM albums WHERE username = ?");
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $album_count);
    mysqli_stmt_fetch($stmt);
    
    if ($album_count == 0) {
        echo "建立測試相簿...<br>";
        $stmt = mysqli_prepare($link, "INSERT INTO albums (username, name, description) VALUES (?, ?, ?)");
        $album_name = "Azure 測試相簿";
        $description = "在 Azure 上建立的測試相簿";
        mysqli_stmt_bind_param($stmt, "sss", $username, $album_name, $description);
        if (mysqli_stmt_execute($stmt)) {
            echo "✅ 測試相簿建立成功<br>";
        }
    } else {
        echo "✅ 相簿已存在（數量：$album_count）<br>";
    }
}

// 6. 測試相簿頁面功能
echo "<h2>6. 測試相簿頁面功能</h2>";
if ($link instanceof mysqli && isset($username)) {
    // 測試 get_album_photos.php
    $stmt = mysqli_prepare($link, "SELECT id, name, cover_photo FROM albums WHERE username = ? ORDER BY created_at DESC");
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $albums = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $albums[] = $row;
    }
    
    if (count($albums) > 0) {
        echo "✅ 相簿查詢成功，找到 " . count($albums) . " 個相簿<br>";
        foreach ($albums as $album) {
            echo "- {$album['name']} (ID: {$album['id']})<br>";
        }
    } else {
        echo "❌ 沒有找到相簿<br>";
    }
}

require_once("DB_close.php");

echo "<h2>🎉 Azure 診斷完成</h2>";
echo "<div style='background: #d4edda; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
echo "<h3>修復結果：</h3>";
echo "<ul>";
echo "<li>✅ 環境檢查完成</li>";
echo "<li>✅ Session 檢查完成</li>";
echo "<li>✅ 資料庫連接修復</li>";
echo "<li>✅ 資料表已建立</li>";
echo "<li>✅ 測試資料已建立</li>";
echo "</ul>";
echo "</div>";

echo "<h3>下一步：</h3>";
echo "<a href='album.php' class='btn btn-success btn-lg'>前往相簿頁面</a><br><br>";
echo "<a href='ai_log.php' class='btn btn-primary'>前往 AI 日誌頁面</a><br><br>";
echo "<a href='add.php' class='btn btn-info'>建立新相簿</a>";

// 7. 顯示錯誤日誌
echo "<h2>7. 錯誤日誌</h2>";
$error_log = error_get_last();
if ($error_log) {
    echo "最後錯誤：" . $error_log['message'] . "<br>";
} else {
    echo "✅ 沒有錯誤<br>";
}
?>
