<?php
// 測試版本的 welcome.php - 不檢查 session
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🎉 測試成功！welcome.php 檔案正常！</h1>";
echo "<p>✅ PHP 運行正常</p>";
echo "<p>✅ 檔案路徑正確</p>";
echo "<p>✅ 伺服器回應正常</p>";

echo "<h2>🔗 測試其他頁面</h2>";
echo '<p><a href="login.php">🔐 測試登入頁面</a></p>';
echo '<p><a href="debug_welcome.php">🔧 返回除錯工具</a></p>';

// 簡單的資料庫測試
require_once("DB_open.php");
if ($link) {
    echo "<p>✅ 資料庫連線正常</p>";
    echo "<p>連線類型：" . get_class($link) . "</p>";
} else {
    echo "<p>❌ 資料庫連線失敗</p>";
}

echo "<hr>";
echo "<h2>📋 解決方案</h2>";
echo "<p><strong>404 原因：</strong>welcome.php 因為沒有 session 而重導向到 login.php</p>";
echo "<p><strong>解決方法：</strong>先登入，然後訪問 welcome.php</p>";
echo "<p><strong>登入頁面：</strong><a href='login.php'>點擊這裡登入</a></p>";
?>
