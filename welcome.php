<?php
session_start();

if (!isset($_SESSION["username"])) {
    header("Location: login.php");
    exit();
}

require_once("DB_open.php");
require_once("DB_helper.php");

$username = $_SESSION["username"];
$name = $username;

// 使用統一的資料庫操作函數
if ($link instanceof PDO) {
    // PostgreSQL 查詢
    $sql = "SELECT name FROM \"user\" WHERE username = ?";
    $stmt = $link->prepare($sql);
    $stmt->execute([$username]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $name = $row['name'];
    }
} else {
    // MySQL 查詢
    if ($link instanceof mysqli) {
        $sql = "SELECT name FROM \"user\" WHERE username = ?";
        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $result_name);
        
        if (mysqli_stmt_fetch($stmt)) {
            $name = $result_name;
        }
        mysqli_stmt_close($stmt);
    } else {
        // 如果是 PDOWrapper，使用 PDO 方式查詢
        $sql = "SELECT name FROM \"user\" WHERE username = ?";
        $stmt = $link->prepare($sql);
        $stmt->execute([$username]);
        $result = $stmt->fetch();
        
        if ($result) {
            $name = $result['name'];
        }
    }
}
require_once("DB_close.php");
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>照片上傳系統</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
    <script src="https://cdn.jsdelivr.net/npm/heic2any/dist/heic2any.min.js"></script>
    <script src="https://unpkg.com/exifr/dist/lite.umd.js"></script>
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        body {
            background: #f6f8fa;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            text-align: center;
        }
        .navbar {
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
            border: none;
            box-shadow: none;
        }
        .navbar-username {
            color: #fff;
            font-size: 1.1rem;
            font-weight: 500;
            letter-spacing: 1px;
            margin-left: 8px;
        }
        .main-content {
            margin: 0 auto;
            max-width: 900px;
            padding-top: 32px;
        }
        .welcome-message {
            font-size: 1.15rem;
            color: #333;
            font-weight: 600;
            text-align: center;
            margin-bottom: 32px;
            line-height: 1.7;
        }
        .upload-section, .map-section {
            margin: 0 auto 36px auto;
            max-width: 700px;
            background: #fff;
            border-radius: 12px;
            padding: 24px 24px 32px 24px;
            box-sizing: border-box;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .upload-label, .map-label {
            text-align: left;
            font-weight: 600;
            color: #444;
            margin-bottom: 10px;
            margin-left: 6px;
        }
        .upload-drop-area {
            border: 2px dashed #888;
            border-radius: 10px;
            min-height: 180px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
        }
        .add-box {
            width: 70px;
            height: 70px;
            background: #8b98a8;
            color: #fff;
            font-size: 3rem;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        #fileInput {
            display: none;
        }
        #map {
            height: 400px;
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
            border-radius: 12px;
            box-shadow: 0 2px 16px rgba(0,0,0,0.08);
            background: #fff;
        }
        .links {
            margin-top: 20px;
        }
        .links a {
            margin: 0 10px;
            text-decoration: none;
            color: #007BFF;
        }
        /* 手機 RWD 加強，像 app 畫面，兩側留白 */
        @media (max-width: 800px) {
            .main-content, .upload-section, .map-section {
                max-width: 100vw;
                padding: 12px 4vw 18px 4vw;
            }
            #map { max-width: 100vw; }
            .upload-section, .map-section { border-radius: 8px; box-shadow: none; }
        }
        @media (max-width: 576px) {
            .main-content, .upload-section, .map-section { padding: 8px 8px 12px 8px; }
            .welcome-message { font-size: 1rem; }
            .add-box { width: 56px; height: 56px; font-size: 2.2rem; }
            #map { height: 240px; }
            .upload-section, .map-section { border-radius: 8px; }
            .navbar { border-radius: 0; }
        }
        #memoryCarousel .carousel-control-prev,
        #memoryCarousel .carousel-control-next {
          width: 48px;
          height: 48px;
          top: 50%;
          transform: translateY(-50%);
          opacity: 1 !important;
          background: #e0e0e0 !important;
          border-radius: 50%;
          display: flex;
          align-items: center;
          justify-content: center;
          transition: background 0.2s;
        }
        #memoryCarousel .carousel-control-prev:hover,
        #memoryCarousel .carousel-control-next:hover {
          background: #bdbdbd !important;
        }
        #memoryCarousel .carousel-control-prev-icon,
        #memoryCarousel .carousel-control-next-icon {
          background-size: 80% 80%;
          filter: none;
          background-color: transparent;
          mask-image: none;
          -webkit-mask-image: none;
          /* 讓箭頭顏色變深灰 */
          filter: invert(30%) sepia(0%) saturate(0%) hue-rotate(0deg) brightness(60%) contrast(90%);
        }
    </style>
</head>
<body>
    <!-- 導覽列 -->
    <nav class="navbar navbar-expand-lg navbar-dark sticky-top">
      <div class="container-fluid px-3">
        <a class="navbar-brand d-flex align-items-center" href="#">
          <img src="img/logo.png" width="32" height="32" class="me-2">
          <span style="font-weight:bold;letter-spacing:1px;">AI智慧相簿管理系統</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavDropdown" aria-controls="navbarNavDropdown" aria-expanded="false" aria-label="Toggle navigation">
          <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNavDropdown">
          <ul class="navbar-nav me-auto mb-2 mb-lg-0">
            <li class="nav-item">
              <a class="nav-link" href="welcome.php">首頁</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="album.php">相簿</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="ai_log.php">AI生成日誌</a>
            </li>
          </ul>
          <div class="d-flex align-items-center ms-auto">
            <img src="img/avatar.png" alt="avatar" class="navbar-avatar">
            <span class="navbar-username"><?php echo htmlspecialchars($name); ?></span>
          </div>
        </div>
      </div>
    </nav>

    <div class="container main-content">
      <div class="welcome-message">
        <div>準備好了嘛!! 新增照片來集滿世界地圖!!!!!</div>
        <div>我們將為您智慧化整理照片，並提供AI生成日誌功能</div>
      </div>
    </div>
    <!-- 回憶旅程幻燈片區塊（獨立區塊） -->
    <div class="container" style="max-width: 700px; margin-top:32px; margin-bottom:32px;">
      <div class="album-section" style="background:#fff; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.04); padding:24px 24px 32px 24px;">
        <div class="map-label" style="margin-bottom:18px;">回憶旅程</div>
        <div id="memoryCarouselWrap">
          <div id="memoryCarousel" class="carousel slide" data-bs-ride="carousel" data-bs-interval="2000" style="max-width:420px;margin:0 auto;">
            <div class="carousel-inner" id="memoryCarouselInner">
              <div class="carousel-item active">
                <div style="height:220px;display:flex;align-items:center;justify-content:center;color:#888;">載入中...</div>
              </div>
            </div>
            <button class="carousel-control-prev" type="button" data-bs-target="#memoryCarousel" data-bs-slide="prev">
              <span class="carousel-control-prev-icon" aria-hidden="true"></span>
              <span class="visually-hidden">Previous</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#memoryCarousel" data-bs-slide="next">
              <span class="carousel-control-next-icon" aria-hidden="true"></span>
              <span class="visually-hidden">Next</span>
            </button>
          </div>
        </div>
      </div>
    </div>
    <!-- 地圖總覽區塊（獨立區塊） -->
    <div class="container" style="max-width: 700px; margin-bottom:32px;">
      <div class="map-section" style="background:#fff; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.04); padding:24px 24px 32px 24px;">
        <div class="map-label">地圖總覽</div>
        <div id="map" style="height: 400px; width: 100%; max-width: 800px; margin: 0 auto; border-radius: 12px; box-shadow: 0 2px 16px rgba(0,0,0,0.08); background: #fff;"></div>
      </div>
    </div>

    <div class="links">
        <a href="records.php">📂 查看上傳紀錄</a>
        <a href="open.php">🚪 登出</a>
    </div>

    <script>
        // 初始化 Leaflet 地圖
        const map = L.map('map').setView([23.6978, 120.9605], 2);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        // 動態載入回憶旅程（我的相簿）
        async function loadMemoryCarousel() {
          const carouselInner = document.getElementById('memoryCarouselInner');
          try {
            const res = await fetch('get_album_photos.php?all_albums=1');
            const data = await res.json();
            if (data.status === 'success' && data.albums && data.albums.length > 0) {
              carouselInner.innerHTML = '';
              data.albums.forEach((album, idx) => {
                const item = document.createElement('div');
                item.className = 'carousel-item' + (idx === 0 ? ' active' : '');
                item.innerHTML = `
                  <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:220px;">
                    <a href="view_album.php?album_id=${album.id}" style="text-decoration:none;color:inherit;">
                      <img src="${album.cover_photo || 'img/default_album_cover.png'}" alt="${album.name}" style="width:180px;height:180px;object-fit:cover;border-radius:12px;box-shadow:0 1px 4px rgba(0,0,0,0.08);">
                      <div style="margin-top:10px;font-size:1.1rem;font-weight:bold;color:#1976d2;text-align:center;">${album.name}</div>
                    </a>
                  </div>
                `;
                carouselInner.appendChild(item);
              });
            } else {
              carouselInner.innerHTML = '<div class="carousel-item active"><div style="height:220px;display:flex;align-items:center;justify-content:center;color:#888;">尚無相簿</div></div>';
            }
          } catch (e) {
            carouselInner.innerHTML = '<div class="carousel-item active"><div style="height:220px;display:flex;align-items:center;justify-content:center;color:#888;">載入失敗</div></div>';
          }
        }
        loadMemoryCarousel();
    </script>
</body>
</html>
