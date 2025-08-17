<?php
session_start();

echo "<h2>相簿頁面測試</h2>";

// 1. 檢查 Session
echo "<h3>1. Session 檢查：</h3>";
if (isset($_SESSION["username"])) {
    echo "✅ 已登入，用戶名：" . $_SESSION["username"] . "<br>";
} else {
    echo "❌ 未登入<br>";
    echo "這是相簿頁面無法顯示的原因！<br>";
    echo "<a href='login.php'>前往登入頁面</a><br>";
    exit();
}

// 2. 檢查基本 PHP 功能
echo "<h3>2. PHP 基本功能檢查：</h3>";
echo "✅ PHP 版本：" . PHP_VERSION . "<br>";
echo "✅ 當前時間：" . date('Y-m-d H:i:s') . "<br>";

// 3. 檢查檔案是否存在
echo "<h3>3. 檔案檢查：</h3>";
$files = ['DB_open.php', 'DB_helper.php', 'get_album_photos.php'];
foreach ($files as $file) {
    if (file_exists($file)) {
        echo "✅ $file 存在<br>";
    } else {
        echo "❌ $file 不存在<br>";
    }
}

// 4. 模擬相簿頁面的基本結構
echo "<h3>4. 相簿頁面基本結構：</h3>";
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>相簿測試 - AI智慧相簿管理</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f6f8fa; font-family: Arial, sans-serif; }
        .navbar { background-color: #e9d0c3 !important; }
        .test-content { padding: 20px; margin: 20px; background: white; border-radius: 8px; }
    </style>
</head>
<body>
    <!-- 導航欄 -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="#">AI智慧相簿管理</a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text">歡迎，<?php echo $_SESSION["username"]; ?></span>
            </div>
        </div>
    </nav>

    <!-- 主要內容 -->
    <div class="container">
        <div class="test-content">
            <h1>相簿頁面測試</h1>
            <p>如果您能看到這個頁面，表示：</p>
            <ul>
                <li>✅ Session 正常</li>
                <li>✅ PHP 正常運行</li>
                <li>✅ HTML 結構正常</li>
                <li>✅ CSS 樣式正常</li>
            </ul>
            
            <h3>下一步測試：</h3>
            <a href="album.php" class="btn btn-primary">前往完整相簿頁面</a>
            <a href="check_database.php" class="btn btn-info">檢查資料庫</a>
            <a href="fix_albums.php" class="btn btn-success">修復相簿系統</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
