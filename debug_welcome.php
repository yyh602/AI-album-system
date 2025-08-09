<?php
// 開啟錯誤報告
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "<h1>Welcome.php 除錯資訊</h1>";

// 檢查 Session
session_start();
echo "<h2>1. Session 檢查</h2>";
if (!isset($_SESSION["username"])) {
    echo "❌ Session username 不存在，將重導向到 login.php<br>";
} else {
    echo "✅ Session username 存在：" . htmlspecialchars($_SESSION["username"]) . "<br>";
}

// 檢查檔案是否存在
echo "<h2>2. 檔案存在性檢查</h2>";
$files = ['DB_open.php', 'DB_helper.php', 'DB_close.php', 'welcome.php'];
foreach ($files as $file) {
    if (file_exists($file)) {
        echo "✅ $file 存在<br>";
    } else {
        echo "❌ $file 不存在<br>";
    }
}

// 嘗試包含檔案
echo "<h2>3. 包含檔案測試</h2>";
try {
    echo "正在包含 DB_open.php...<br>";
    require_once("DB_open.php");
    echo "✅ DB_open.php 包含成功<br>";
    
    if (isset($link) && $link) {
        echo "✅ 資料庫連線物件存在<br>";
        echo "連線類型：" . get_class($link) . "<br>";
    } else {
        echo "❌ 資料庫連線物件不存在<br>";
    }
    
    echo "正在包含 DB_helper.php...<br>";
    require_once("DB_helper.php");
    echo "✅ DB_helper.php 包含成功<br>";
    
} catch (Exception $e) {
    echo "❌ 錯誤：" . $e->getMessage() . "<br>";
    echo "錯誤檔案：" . $e->getFile() . " 第 " . $e->getLine() . " 行<br>";
}

// 檢查環境變數
echo "<h2>4. 環境變數檢查</h2>";
$env_vars = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS', 'DB_PORT', 'DB_TYPE'];
foreach ($env_vars as $var) {
    $value = $_ENV[$var] ?? 'undefined';
    if ($var === 'DB_PASS') {
        $value = $value === 'undefined' ? 'undefined' : '[已設定]';
    }
    echo "$var: $value<br>";
}

// 檢查 PHP 擴展
echo "<h2>5. PHP 擴展檢查</h2>";
$extensions = ['pgsql', 'mysqli', 'pdo', 'pdo_pgsql', 'pdo_mysql'];
foreach ($extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "✅ $ext 已載入<br>";
    } else {
        echo "❌ $ext 未載入<br>";
    }
}

// 檢查 PHP 錯誤日誌
echo "<h2>6. 最近的 PHP 錯誤</h2>";
$error_log = error_get_last();
if ($error_log) {
    echo "最後錯誤：" . $error_log['message'] . "<br>";
    echo "檔案：" . $error_log['file'] . " 第 " . $error_log['line'] . " 行<br>";
    echo "類型：" . $error_log['type'] . "<br>";
} else {
    echo "✅ 無最近錯誤<br>";
}

echo "<h2>7. PHP 資訊</h2>";
echo "PHP 版本：" . phpversion() . "<br>";
echo "系統：" . php_uname() . "<br>";
echo "記憶體限制：" . ini_get('memory_limit') . "<br>";
echo "最大執行時間：" . ini_get('max_execution_time') . "<br>";

// 測試基本 DB 查詢
if (isset($link) && $link) {
    echo "<h2>8. 資料庫測試</h2>";
    try {
        if ($link instanceof PgSQLWrapper) {
            $result = $link->query("SELECT 1 as test");
            if ($result) {
                $row = $result->fetch_assoc();
                echo "✅ PostgreSQL 查詢成功：" . json_encode($row) . "<br>";
            }
        } elseif ($link instanceof mysqli) {
            $result = $link->query("SELECT 1 as test");
            if ($result) {
                $row = $result->fetch_assoc();
                echo "✅ MySQL 查詢成功：" . json_encode($row) . "<br>";
            }
        }
    } catch (Exception $e) {
        echo "❌ 資料庫查詢錯誤：" . $e->getMessage() . "<br>";
    }
}

echo "<hr><p><a href='welcome.php'>返回 welcome.php</a></p>";
?>
