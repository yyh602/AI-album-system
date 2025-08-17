<?php
session_start();

if (!isset($_SESSION["username"])) {
    header("Location: login.php");
    exit();
}

require_once("DB_open.php");

$username = $_SESSION["username"];
$name = $username;

if ($link instanceof mysqli) {
    $sql = "SELECT name FROM user WHERE username = ?";
    $stmt = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $result_name);

    if (mysqli_stmt_fetch($stmt)) {
        $name = $result_name;
    }

    mysqli_stmt_close($stmt);
} else {
    // 資料庫連線失敗處理
    error_log("資料庫連線失敗或類型不正確");
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
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/heic2any/dist/heic2any.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/exif-js"></script>
    <style>
    body {
        background: #f6f8fa;
        font-family: Arial, sans-serif;
        margin: 0;
        padding: 0;
        text-align: center;
    }

    .navbar, .custom-navbar {
        background-color: #e9d0c3 !important;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .navbar-brand, .nav-link, .navbar-username {
        color: #333 !important;
    }

    .nav-link:hover {
        color: #3498db !important;
    }

    .nav-link.active {
        color: #3498db !important;
        font-weight: bold;
    }

    .navbar-avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        object-fit: cover;
    }

    .navbar-username {
        font-size: 1.1rem;
        font-weight: 500;
        margin-left: 8px;
    }

    .album-section-content {
        display: grid;
        grid-template-columns: repeat(5, 1fr); /* 桌機每排5張 */
        gap: 18px;
        background: #f8f9fa;
        border-radius: 0;
        max-height: none;
        overflow-y: visible;
        width: 100%;
        margin: 0 auto;
        padding: 0 20px; /* 左右留縫隙 */
    }

    .album-card-preview {
        display: flex;
        flex-direction: column;
        align-items: center;
        width: 100%;
    }

    .album-card-img-wrap {
        width: 100%;
        aspect-ratio: 1 / 1;
        border-radius: 12px;
        overflow: hidden;
        background: #fff;
        box-shadow: 0 1px 4px rgba(0,0,0,0.08);
        margin-bottom: 8px;
    }

    .album-card-img-wrap img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .album-card-title {
        font-size: 0.95rem;
        font-weight: 500;
        color: #333;
        text-align: center;
    }

    .add-album-title {
        text-align: left;
        color: #1976d2;
        font-weight: bold;
        margin-left: 40px;
        margin-top: 40px;
        margin-bottom: 24px;
        font-size: 2rem;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    /* 響應式調整：手機畫面寬度下每排 3 張 */
    @media (max-width: 576px) {
        .album-section-content {
            grid-template-columns: repeat(3, 1fr); /* 手機每排3張 */
            gap: 12px;
            padding: 0 12px; /* 手機左右留縫隙 */
        }

        .album-card-title {
            font-size: 0.85rem;
        }
    }
    </style>

</head>
<body>
    <!-- 省略：navbar, modal, js (你的原始內容保持不動) -->
</body>
</html>
