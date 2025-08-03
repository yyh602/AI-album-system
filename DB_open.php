<?php

// 從環境變數獲取資料庫配置
$host = $_ENV['DB_HOST'] ?? '127.0.0.1';
$dbname = $_ENV['DB_NAME'] ?? 'myproject';
$db_user = $_ENV['DB_USER'] ?? 'root';
$db_pass = $_ENV['DB_PASS'] ?? 'MariaDB1688.';
$db_port = $_ENV['DB_PORT'] ?? '3306';

// 使用 mysqli 連接（保持與現有程式碼相容）
$link = new mysqli($host, $db_user, $db_pass, $dbname, $db_port);

// 檢查連線是否成功
if ($link->connect_error) {
    die("❌ 資料庫連線失敗：" . $link->connect_error);
}

// 設定字符集
$link->set_charset("utf8");

// 紀錄目前使用的資料庫
$currentDb = $link->query("SELECT DATABASE()");
$row = $currentDb->fetch_row();
error_log('【目前連線資料庫】：' . $row[0]);
?>
