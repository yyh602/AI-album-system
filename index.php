<?php
// 路由處理
$request_uri = $_SERVER['REQUEST_URI'] ?? '';
$path = parse_url($request_uri, PHP_URL_PATH);

// 如果是 save_album_blob.php 的請求
if (strpos($path, 'save_album_blob.php') !== false) {
    require_once 'save_album_blob.php';
    exit;
}

// 其他請求繼續正常處理
if (file_exists('login.php') && !isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

// 預設顯示相簿頁面
if (file_exists('album.php')) {
    include 'album.php';
} else {
    echo "AI Album System - 檔案未找到";
}
?> 