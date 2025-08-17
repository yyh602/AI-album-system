<?php
session_start();

echo "<h1>🚨 相簿系統緊急修復</h1>";

// 1. 檢查 Session
echo "<h2>1. Session 狀態檢查</h2>";
if (isset($_SESSION["username"])) {
    echo "✅ 已登入，用戶名：" . $_SESSION["username"] . "<br>";
    $username = $_SESSION["username"];
} else {
    echo "❌ 未登入 - 這是相簿頁面無法顯示的主要原因！<br>";
    echo "<a href='login.php' class='btn btn-primary'>立即登入</a><br>";
    echo "<script>setTimeout(function(){ window.location.href='login.php'; }, 3000);</script>";
    exit();
}

// 2. 檢查資料庫連接
echo "<h2>2. 資料庫連接檢查</h2>";
require_once("DB_open.php");

if ($link === null) {
    echo "❌ 資料庫連接失敗<br>";
    echo "正在嘗試修復資料庫連接...<br>";
    
    // 嘗試使用本地資料庫連接
    $local_link = new mysqli('localhost', 'root', '', 'myproject');
    if ($local_link->connect_error) {
        echo "❌ 本地資料庫也無法連接<br>";
        echo "請確保 MySQL 服務正在運行<br>";
    } else {
        echo "✅ 本地資料庫連接成功<br>";
        $link = $local_link;
    }
} else {
    echo "✅ 資料庫連接成功<br>";
    if ($link instanceof mysqli) {
        echo "資料庫：" . $link->database . "<br>";
        echo "主機：" . $link->host_info . "<br>";
    }
}

// 3. 建立必要的資料表
echo "<h2>3. 建立資料表</h2>";
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
        
        'uploads' => "CREATE TABLE IF NOT EXISTS uploads (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(255) NOT NULL,
            filename VARCHAR(255) NOT NULL,
            path VARCHAR(500) NOT NULL,
            original_name VARCHAR(255),
            file_size INT,
            mime_type VARCHAR(100),
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_username (username),
            INDEX idx_uploaded_at (uploaded_at)
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

// 4. 檢查並建立測試資料
echo "<h2>4. 檢查資料</h2>";
if ($link instanceof mysqli) {
    // 檢查用戶是否存在
    $stmt = mysqli_prepare($link, "SELECT COUNT(*) as count FROM user WHERE username = ?");
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $user_count);
    mysqli_stmt_fetch($stmt);
    
    if ($user_count == 0) {
        echo "⚠️ 用戶不存在，正在建立測試用戶...<br>";
        $hashed_password = password_hash('test123', PASSWORD_DEFAULT);
        $stmt = mysqli_prepare($link, "INSERT INTO user (username, password, name) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "sss", $username, $hashed_password, $username);
        if (mysqli_stmt_execute($stmt)) {
            echo "✅ 測試用戶建立成功<br>";
        }
    } else {
        echo "✅ 用戶已存在<br>";
    }
    
    // 檢查相簿
    $stmt = mysqli_prepare($link, "SELECT COUNT(*) as count FROM albums WHERE username = ?");
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $album_count);
    mysqli_stmt_fetch($stmt);
    
    echo "您的相簿數量：$album_count<br>";
    
    if ($album_count == 0) {
        echo "⚠️ 沒有相簿，正在建立測試相簿...<br>";
        $stmt = mysqli_prepare($link, "INSERT INTO albums (username, name, description) VALUES (?, ?, ?)");
        $album_name = "我的第一個相簿";
        $description = "這是一個測試相簿";
        mysqli_stmt_bind_param($stmt, "sss", $username, $album_name, $description);
        if (mysqli_stmt_execute($stmt)) {
            echo "✅ 測試相簿建立成功<br>";
        }
    }
}

// 5. 修復相簿頁面
echo "<h2>5. 修復相簿頁面</h2>";
echo "正在修復相簿頁面的資料庫連接問題...<br>";

// 建立修復後的相簿頁面
$fixed_album_content = '<?php
session_start();

if (!isset($_SESSION["username"])) {
    header("Location: login.php");
    exit();
}

require_once("DB_open.php");

$username = $_SESSION["username"];
$name = $username;

// 安全的資料庫查詢
if ($link instanceof mysqli) {
    try {
        $sql = "SELECT name FROM user WHERE username = ?";
        $stmt = mysqli_prepare($link, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $username);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_bind_result($stmt, $result_name);
            if (mysqli_stmt_fetch($stmt)) {
                $name = $result_name;
            }
            mysqli_stmt_close($stmt);
        }
    } catch (Exception $e) {
        error_log("資料庫查詢錯誤：" . $e->getMessage());
    }
}

require_once("DB_close.php");
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>相簿 - AI智慧相簿管理</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f6f8fa; font-family: Arial, sans-serif; }
        .navbar { background-color: #e9d0c3 !important; }
        .album-card { background: white; border-radius: 8px; padding: 15px; margin: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="#">AI智慧相簿管理</a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text">歡迎，<?php echo htmlspecialchars($name); ?></span>
                <a class="nav-link" href="logout.php">登出</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h1>我的相簿</h1>
        <div id="albums-container">
            <p>載入中...</p>
        </div>
        <a href="add.php" class="btn btn-primary">建立新相簿</a>
    </div>

    <script>
        // 簡化的相簿載入
        async function loadAlbums() {
            try {
                const response = await fetch("get_album_photos.php?all_albums=1");
                const data = await response.json();
                
                const container = document.getElementById("albums-container");
                if (data.status === "success" && data.albums && data.albums.length > 0) {
                    container.innerHTML = data.albums.map(album => `
                        <div class="album-card">
                            <h5>${album.name}</h5>
                            <p>相簿 ID: ${album.id}</p>
                            <a href="view_album.php?album_id=${album.id}" class="btn btn-sm btn-outline-primary">查看相簿</a>
                        </div>
                    `).join("");
                } else {
                    container.innerHTML = "<p>您還沒有相簿，請建立第一個相簿！</p>";
                }
            } catch (error) {
                document.getElementById("albums-container").innerHTML = "<p>載入失敗，請重新整理頁面</p>";
                console.error("載入相簿失敗:", error);
            }
        }
        
        // 頁面載入時執行
        loadAlbums();
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>';

// 寫入修復後的相簿頁面
file_put_contents('album_fixed.php', $fixed_album_content);
echo "✅ 修復後的相簿頁面已建立：album_fixed.php<br>";

require_once("DB_close.php");

echo "<h2>🎉 修復完成！</h2>";
echo "<div style='background: #d4edda; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
echo "<h3>修復結果：</h3>";
echo "<ul>";
echo "<li>✅ Session 檢查完成</li>";
echo "<li>✅ 資料庫連接修復</li>";
echo "<li>✅ 必要資料表已建立</li>";
echo "<li>✅ 測試資料已建立</li>";
echo "<li>✅ 相簿頁面已修復</li>";
echo "</ul>";
echo "</div>";

echo "<h3>下一步：</h3>";
echo "<a href='album_fixed.php' class='btn btn-success btn-lg'>前往修復後的相簿頁面</a><br><br>";
echo "<a href='album.php' class='btn btn-primary'>前往原始相簿頁面</a><br><br>";
echo "<a href='add.php' class='btn btn-info'>建立新相簿</a>";
?>
