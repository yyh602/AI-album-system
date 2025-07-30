<?php

$host = '127.0.0.1';
$dbname = 'myproject';
$db_user = 'root';
$db_pass = 'MariaDB1688.';

// 改成 $link
$link = new mysqli($host, $db_user, $db_pass, $dbname);

// 檢查連線是否成功
if ($link->connect_error) {
    die("❌ 連線失敗：" . $link->connect_error);
}

// 紀錄目前使用的資料庫
$currentDb = $link->query("SELECT DATABASE()");
$row = $currentDb->fetch_row();
error_log('【目前連線資料庫】：' . $row[0]);
?>
