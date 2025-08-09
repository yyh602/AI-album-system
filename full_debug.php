<?php
// 完整的錯誤診斷工具
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "<h1>🔍 完整錯誤診斷</h1>";

echo "<h2>1. 基本環境檢查</h2>";
echo "PHP 版本: " . phpversion() . "<br>";
echo "伺服器軟體: " . $_SERVER['SERVER_SOFTWARE'] . "<br>";
echo "文檔根目錄: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
echo "腳本名稱: " . $_SERVER['SCRIPT_NAME'] . "<br>";
echo "請求 URI: " . $_SERVER['REQUEST_URI'] . "<br>";
echo "HTTP Host: " . $_SERVER['HTTP_HOST'] . "<br>";

echo "<h2>2. 檔案系統檢查</h2>";
$files_to_check = [
    'welcome.php',
    'login.php', 
    'index.php',
    'DB_open.php',
    'DB_helper.php',
    'DB_close.php'
];

foreach ($files_to_check as $file) {
    $full_path = $_SERVER['DOCUMENT_ROOT'] . '/' . $file;
    echo "檔案: $file<br>";
    echo "  - 存在: " . (file_exists($file) ? "✅" : "❌") . "<br>";
    echo "  - 可讀: " . (is_readable($file) ? "✅" : "❌") . "<br>";
    echo "  - 大小: " . (file_exists($file) ? filesize($file) . " bytes" : "N/A") . "<br>";
    echo "  - 完整路徑: $full_path<br>";
    echo "  - 完整路徑存在: " . (file_exists($full_path) ? "✅" : "❌") . "<br>";
    echo "<br>";
}

echo "<h2>3. Session 檢查</h2>";
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
echo "Session 狀態: " . session_status() . "<br>";
echo "Session ID: " . session_id() . "<br>";
echo "Session 變數:<br>";
print_r($_SESSION);

echo "<h2>4. 手動測試 welcome.php 包含</h2>";
try {
    // 設定一個假的 session 來測試
    $_SESSION['username'] = 'test_user';
    echo "設定測試 session: test_user<br>";
    
    echo "嘗試包含 welcome.php...<br>";
    ob_start(); // 開始輸出緩衝，防止重導向
    include 'welcome.php';
    $content = ob_get_contents();
    ob_end_clean();
    
    echo "✅ welcome.php 包含成功!<br>";
    echo "輸出長度: " . strlen($content) . " 字符<br>";
    
} catch (Exception $e) {
    echo "❌ 錯誤: " . $e->getMessage() . "<br>";
    echo "檔案: " . $e->getFile() . "<br>";
    echo "行號: " . $e->getLine() . "<br>";
}

echo "<h2>5. 直接測試重導向</h2>";
unset($_SESSION['username']); // 清除測試 session

echo "清除 session 後測試重導向邏輯...<br>";
if (!isset($_SESSION["username"])) {
    $login_url = "https://" . $_SERVER['HTTP_HOST'] . "/login.php";
    echo "應該重導向到: $login_url<br>";
} else {
    echo "Session 存在，不需要重導向<br>";
}

echo "<h2>6. 測試連結</h2>";
$base_url = "https://" . $_SERVER['HTTP_HOST'];
echo "<a href='$base_url/login.php'>測試登入頁面</a><br>";
echo "<a href='$base_url/index.php'>測試首頁</a><br>";
echo "<a href='$base_url/test_welcome.php'>測試 welcome 頁面</a><br>";

echo "<h2>7. 錯誤日誌檢查</h2>";
$error = error_get_last();
if ($error) {
    echo "最後錯誤: " . $error['message'] . "<br>";
    echo "檔案: " . $error['file'] . "<br>";
    echo "行號: " . $error['line'] . "<br>";
} else {
    echo "無最近錯誤<br>";
}

echo "<h2>8. 伺服器變數</h2>";
echo "<pre>";
print_r($_SERVER);
echo "</pre>";
?>
