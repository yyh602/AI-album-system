<?php
// 檢查 session 狀態，避免重複啟動
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION["username"])) {
    // 使用絕對 URL 避免重導向循環
    $login_url = "https://" . $_SERVER['HTTP_HOST'] . "/login.php";
    header("Location: " . $login_url);
    exit();
}

require_once("DB_open.php");
require_once("DB_helper.php");

$username = $_SESSION["username"];
$name = $username;

// MySQL 查詢用戶名稱
if ($link instanceof mysqli && $link !== null) {
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
}

// 查詢歷史日誌
$diaries = [];
if ($link instanceof mysqli && $link !== null) {
    $diary_sql = "SELECT d.*, a.cover_photo, a.name as album_name FROM travel_diary d LEFT JOIN albums a ON d.album_id = a.id WHERE d.username = ? ORDER BY d.created_at DESC LIMIT 5";
    $diary_stmt = mysqli_prepare($link, $diary_sql);
    if ($diary_stmt) {
        mysqli_stmt_bind_param($diary_stmt, "s", $username);
        mysqli_stmt_execute($diary_stmt);
        $diary_result = mysqli_stmt_get_result($diary_stmt);
        if ($diary_result) {
            while ($row = mysqli_fetch_assoc($diary_result)) {
                $diaries[] = $row;
            }
        }
        mysqli_stmt_close($diary_stmt);
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
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster/dist/MarkerCluster.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster/dist/MarkerCluster.Default.css" />
    <script src="https://cdn.jsdelivr.net/npm/heic2any/dist/heic2any.min.js"></script>
    <script src="https://unpkg.com/exifr/dist/lite.umd.js"></script>
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet.markercluster/dist/leaflet.markercluster.js"></script>
    <script src="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/noUiSlider/15.7.0/nouislider.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/noUiSlider/15.7.0/nouislider.min.js"></script>
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
        
        /* 自定義地圖標記樣式 */
        .custom-photo-marker {
            background: transparent !important;
            border: none !important;
        }
        
        .custom-photo-marker div {
            transition: transform 0.2s ease;
        }
        
        .custom-photo-marker:hover div {
            transform: scale(1.2);
        }
        
        /* Leaflet 彈出視窗樣式 */
        .leaflet-popup-content-wrapper {
            border-radius: 8px !important;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important;
        }
        
        .leaflet-popup-content {
            margin: 0 !important;
            padding: 0 !important;
        }
        
        .leaflet-popup-tip {
            background: white !important;
        }
        
        /* 圖層控制樣式 */
        .leaflet-control-layers {
            background: rgba(255,255,255,0.95) !important;
            border-radius: 8px !important;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1) !important;
            padding: 8px !important;
        }
        
        .leaflet-control-layers-toggle {
            background: #1976d2 !important;
            color: white !important;
            border: none !important;
            border-radius: 4px !important;
        }
        
        .leaflet-control-layers-expanded {
            min-width: 150px !important;
        }
        
        .leaflet-control-layers label {
            margin-bottom: 4px !important;
            font-size: 0.9rem !important;
        }
        .links {
            margin-top: 20px;
        }
        .links a {
            margin: 0 10px;
            text-decoration: none;
            color: #007BFF;
        }
        
        /* 回憶旅程幻燈片控制按鈕 */
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

        /* 調整箭頭向左和向右的距離 */
        #memoryCarousel .carousel-control-prev {
            left: -80px;
        }

        #memoryCarousel .carousel-control-next {
            right: -80px;
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
          filter: invert(30%) sepia(0%) saturate(0%) hue-rotate(0deg) brightness(60%) contrast(90%);
        }
        
        /* 歷史日誌樣式 */
        .history-item:hover {
          transform: translateY(-2px);
          box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .history-grid {
          display: flex;
          flex-wrap: wrap;
          gap: 12px;
          justify-content: center;
        }
        
        /* 手機 RWD 修正與加強 */
        /* 針對 800px 以下螢幕，移除區塊左右留白，並移除陰影和圓角 */
        @media (max-width: 800px) {
            .main-content, .upload-section, .map-section {
                max-width: 100vw;
                padding: 12px 0 18px 0;
            }
            #map { max-width: 100vw; }
            .upload-section, .map-section { border-radius: 0; box-shadow: none; }

            /* 確保 Bootstrap 的 .container 類別在手機版無左右留白 */
            .container {
                max-width: 100% !important;
                padding-left: 0 !important;
                padding-right: 0 !important;
            }

            /* 確保相簿區塊在手機版無左右留白 */
            .album-section {
                border-radius: 0 !important;
                margin-left: 0 !important;
                margin-right: 0 !important;
            }
        }
        /* 針對 768px 以下螢幕，修正幻燈片左右按鈕溢出問題 */
        @media (max-width: 768px) {
            #memoryCarousel .carousel-control-prev {
                left: 8px;
                background: rgba(0,0,0,0.5) !important;
            }
            #memoryCarousel .carousel-control-next {
                right: 8px;
                background: rgba(0,0,0,0.5) !important;
            }
        }
        /* 針對 576px 以下螢幕，再次調整確保無留白 */
        @media (max-width: 576px) {
            .main-content, .upload-section, .map-section { padding: 8px 0 12px 0; }
            .welcome-message { font-size: 1rem; }
            .add-box { width: 56px; height: 56px; font-size: 2.2rem; }
            #map { height: 240px; }
            .upload-section, .map-section { border-radius: 0; }
            .navbar { border-radius: 0; }
            .history-item {
              width: 100px !important;
            }
            .history-item img {
              width: 100px !important;
              height: 100px !important;
            }
            /* 確保 Bootstrap 的 .container 類別在手機版無左右留白 */
            .container {
                max-width: 100% !important;
                padding-left: 0 !important;
                padding-right: 0 !important;
            }

            /* 確保相簿區塊在手機版無左右留白 */
            .album-section {
                border-radius: 0 !important;
                margin-left: 0 !important;
                margin-right: 0 !important;
            }
        }
        /* 時間軸樣式 */
        .timeline-container {
            width: 90%;
            margin: 20px auto;
            padding: 20px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
            max-width: 700px;
            text-align: center;
        }
        .timeline-title {
            font-weight: 600;
            color: #444;
            margin-bottom: 20px;
        }
        .timeline-labels {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
            font-size: 0.8rem;
            color: #666;
        }
        .noUi-horizontal {
            height: 8px;
            background: #e0e0e0;
            border-radius: 4px;
        }
        .noUi-horizontal .noUi-handle {
            width: 18px;
            height: 18px;
            top: -5px;
            border-radius: 50%;
            background: #1976d2;
            border: 2px solid #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            cursor: pointer;
        }
        .noUi-connect {
            background: #1976d2;
        }
        .noUi-target.noUi-connect, .noUi-tooltip {
            background: none;
        }
        .noUi-tooltip {
            display: none;
        }
        .noUi-handle.noUi-active .noUi-tooltip {
            display: block;
            background: #1976d2;
            color: #fff;
            padding: 4px 8px;
            border-radius: 4px;
            bottom: 120%;
            transform: translateX(-50%);
            white-space: nowrap;
        }
        .noUi-value {
            font-size: 0.7rem;
            color: #888;
        }
        
        /* 自定義群集樣式 */
        .marker-cluster-small {
            background-color: rgba(25, 118, 210, 0.6);
        }
        .marker-cluster-small div {
            background-color: rgba(25, 118, 210, 0.8);
        }
        
        .marker-cluster-medium {
            background-color: rgba(255, 152, 0, 0.6);
        }
        .marker-cluster-medium div {
            background-color: rgba(255, 152, 0, 0.8);
        }
        
        .marker-cluster-large {
            background-color: rgba(244, 67, 54, 0.6);
        }
        .marker-cluster-large div {
            background-color: rgba(244, 67, 54, 0.8);
        }
        
        .marker-cluster {
            background-clip: padding-box;
            border-radius: 20px;
        }
        .marker-cluster div {
            width: 30px;
            height: 30px;
            margin-left: 5px;
            margin-top: 5px;
            text-align: center;
            border-radius: 15px;
            font: 12px "Helvetica Neue", Arial, Helvetica, sans-serif;
            color: white;
            font-weight: bold;
        }
        .marker-cluster span {
            line-height: 30px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark sticky-top">
      <div class="container-fluid px-3">
        <a class="navbar-brand d-flex align-items-center" href="#">
          <img src="img/logo.svg" width="32" height="32" class="me-2">
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
            <img src="img/avatar.svg" alt="avatar" class="navbar-avatar">
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
    <div class="container" style="max-width: 1000px; margin-top:32px; margin-bottom:32px;">
      <div class="album-section" style="background:#fff; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.04); padding:24px 24px 32px 24px;">
        <div class="map-label" style="margin-bottom:18px;">回憶旅程</div>
        <div id="memoryCarouselWrap">
          <div id="memoryCarousel" class="carousel slide" data-bs-ride="carousel" data-bs-interval="2000" style="max-width:500px;margin:0 auto;">
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

    <div class="container" style="max-width: 700px; margin-bottom:32px;">
      <div class="album-section" style="background:#fff; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.04); padding:24px 24px 32px 24px;">
        <div class="map-label" style="margin-bottom:18px;">歷史日誌</div>
        <div id="historyLogList" class="history-grid" style="display:flex;flex-wrap:wrap;gap:12px;justify-content:center;">
          <?php if (empty($diaries)): ?>
            <div style="height:120px;display:flex;align-items:center;justify-content:center;color:#888;width:100%;">尚無日誌</div>
          <?php else: ?>
            <?php foreach ($diaries as $d): ?>
              <div class="history-item" onclick="showDiaryDetail(<?php echo $d['id']; ?>)" style="width:120px;cursor:pointer;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.1);transition:transform 0.2s;">
                <img src="img/default_album_cover.svg" 
                     style="width:120px;height:120px;object-fit:cover;" 
                     alt="<?php echo htmlspecialchars($d['album_name']); ?>">
                <div style="padding:8px;background:#fff;">
                  <div style="font-size:0.9rem;font-weight:bold;color:#333;text-align:center;margin-bottom:4px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo htmlspecialchars($d['album_name']); ?></div>
                  <div style="font-size:0.8rem;color:#666;text-align:center;"><?php echo date('Y/m/d', strtotime($d['created_at'])); ?></div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="container" style="max-width: 700px; margin-bottom:32px;">
      <div class="map-section" style="background:#fff; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.04); padding:24px 24px 32px 24px;">
        <div class="map-label">地圖總覽與照片時間軸</div>
        <div id="map" style="height: 400px; width: 100%; max-width: 800px; margin: 0 auto; border-radius: 12px; box-shadow: 0 2px 16px rgba(0,0,0,0.08); background: #fff;"></div>
        
        <div class="timeline-container" style="margin-top: 20px; padding: 0; background: none; box-shadow: none;">
            <div class="timeline-title">照片時間軸</div>
            <div id="timeline"></div>
            <div class="timeline-labels">
                <span id="timeline-start-date"></span>
                <span id="timeline-end-date"></span>
            </div>
        </div>
      </div>
    </div>

    <div class="links">
        <a href="records.php">📂 查看上傳紀錄</a>
        <a href="open.php">🚪 登出</a>
    </div>

    <script>
        // 初始化 Leaflet 地圖
        const map = L.map('map').setView([23.6978, 120.9605], 6.5);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        // 建立群集圖層與熱力圖層
        const markers = L.markerClusterGroup({
            chunkedLoading: true,
            maxClusterRadius: 80,
            spiderfyOnMaxZoom: true,
            showCoverageOnHover: true,
            zoomToBoundsOnClick: true,
            disableClusteringAtZoom: 18,
            removeOutsideVisibleBounds: true
        });
        const heatLayer = L.heatLayer([]);
        let allGpsPhotos = []; // 全域變數，儲存所有有 GPS 資訊的照片

        // 定義地圖上方的圖層控制選項
        const overlayMaps = {
            "照片點位": markers,
            "熱力圖": heatLayer
        };

        // 將圖層控制加入地圖，讓使用者可以開關圖層
        L.control.layers(null, overlayMaps, { collapsed: false }).addTo(map);

        // 初始化時間軸變數
        let timelineSlider;

        // 載入所有照片的 GPS 點位和熱力圖
        async function loadMapData() {
            try {
                const res = await fetch('get_all_photos.php');
                const data = await res.json();
                
                // 清空舊圖層
                markers.clearLayers();
                heatLayer.setLatLngs([]);
                
                if (data.status === 'success' && data.photos && data.photos.length > 0) {
                    allGpsPhotos = data.photos.filter(photo => photo.latitude && photo.longitude);
                    
                    if (allGpsPhotos.length > 0) {
                        // 處理點位圖層 (Marker Cluster Layer)
                        const bounds = [];
                        allGpsPhotos.forEach(photo => {
                            const lat = parseFloat(photo.latitude);
                            const lng = parseFloat(photo.longitude);
                            if (!isNaN(lat) && !isNaN(lng)) {
                                const marker = L.marker([lat, lng], { 
                                    timestamp: photo.datetime ? new Date(photo.datetime).getTime() : 0 
                                })
                                    .bindPopup(`
                                        <div style="text-align: center; min-width: 200px;">
                                            <img src="${photo.path}" alt="${photo.filename}" style="width: 150px; height: 150px; object-fit: cover; border-radius: 8px; margin-bottom: 8px; cursor: pointer;" onclick="window.open('photo_detail.php?id=${photo.id}', '_blank')">
                                            <div style="font-weight: bold; color: #333; margin-bottom: 4px;">${photo.album_name || '未分類'}</div>
                                            <div style="font-size: 0.9rem; color: #666;">${photo.datetime ? new Date(photo.datetime).toLocaleDateString('zh-TW') : '未知日期'}</div>
                                            <div style="font-size: 0.8rem; color: #888; margin-top: 4px; word-break: break-all;">${photo.filename}</div>
                                            <div style="margin-top: 8px;">
                                                <a href="photo_detail.php?id=${photo.id}" target="_blank" style="background: #1976d2; color: white; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 0.8rem;">查看詳情</a>
                                            </div>
                                        </div>
                                    `);
                                markers.addLayer(marker);
                                bounds.push([lat, lng]);
                            }
                        });

                        // 處理熱力圖圖層 (Heatmap Layer)
                        const gpsPoints = allGpsPhotos.map(photo => [parseFloat(photo.latitude), parseFloat(photo.longitude)]);
                        heatLayer.setLatLngs(gpsPoints);

                        // 將兩個圖層都加入地圖，但預設熱力圖層可能被關閉
                        map.addLayer(markers);
                        
                        // 調整地圖視角以顯示所有點位
                        if (bounds.length > 0) {
                            map.fitBounds(bounds, { padding: [20, 20] });
                        }

                        // 顯示統計資訊
                        const mapContainer = document.getElementById('map');
                        let statsDiv = mapContainer.querySelector('.map-stats');
                        if (!statsDiv) {
                            statsDiv = document.createElement('div');
                            statsDiv.className = 'map-stats';
                            statsDiv.style.cssText = 'position: absolute; top: 10px; left: 10px; background: rgba(255,255,255,0.95); padding: 8px 12px; border-radius: 6px; font-size: 0.9rem; z-index: 1000; box-shadow: 0 2px 8px rgba(0,0,0,0.1); display: flex; align-items: center; gap: 8px;';
                            mapContainer.style.position = 'relative';
                            mapContainer.appendChild(statsDiv);
                        }
                        statsDiv.innerHTML = `
                            <span>📍 共 ${allGpsPhotos.length} 張照片有位置資訊</span>
                            <button onclick="refreshMap()" style="background: #1976d2; color: white; border: none; padding: 4px 8px; border-radius: 4px; font-size: 0.8rem; cursor: pointer;">重新整理</button>
                        `;

                        // 初始化時間軸
                        initTimeline();

                    } else {
                        // 沒有 GPS 資料時的提示
                        showNoDataMessage();
                    }
                } else {
                    showNoDataMessage();
                }
            } catch (e) {
                console.error('載入地圖資料失敗:', e);
                showErrorMessage();
            }
        }

        // 顯示沒有數據的提示訊息。
        function showNoDataMessage() {
            const mapContainer = document.getElementById('map');
            let noDataDiv = mapContainer.querySelector('.no-data-message');
            if (!noDataDiv) {
                noDataDiv = document.createElement('div');
                noDataDiv.className = 'no-data-message';
                noDataDiv.style.cssText = 'position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: rgba(255,255,255,0.9); padding: 20px; border-radius: 8px; text-align: center; z-index: 1000; box-shadow: 0 2px 8px rgba(0,0,0,0.1);';
                noDataDiv.innerHTML = `<div style="color: #666; margin-bottom: 8px;">📷</div><div style="color: #333; font-weight: bold;">尚無照片位置資訊</div><div style="color: #888; font-size: 0.9rem; margin-top: 4px;">上傳包含 GPS 資訊的照片即可在地圖上顯示</div>`;
                mapContainer.style.position = 'relative';
                mapContainer.appendChild(noDataDiv);
            }
        }

        // 顯示錯誤訊息
        function showErrorMessage() {
            const mapContainer = document.getElementById('map');
            let errorDiv = mapContainer.querySelector('.error-message');
            if (!errorDiv) {
                errorDiv = document.createElement('div');
                errorDiv.className = 'error-message';
                errorDiv.style.cssText = 'position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: rgba(255,255,255,0.9); padding: 20px; border-radius: 8px; text-align: center; z-index: 1000; box-shadow: 0 2px 8px rgba(0,0,0,0.1);';
                errorDiv.innerHTML = `<div style="color: #666; margin-bottom: 8px;">⚠️</div><div style="color: #333; font-weight: bold;">載入失敗</div><div style="color: #888; font-size: 0.9rem; margin-top: 4px;">請稍後再試</div>`;
                mapContainer.style.position = 'relative';
                mapContainer.appendChild(errorDiv);
            }
        }

        // 初始化時間軸
        function initTimeline() {
            if (allGpsPhotos.length === 0) return;

            const timestamps = allGpsPhotos
                .filter(p => p.datetime)
                .map(p => new Date(p.datetime).getTime())
                .sort((a, b) => a - b);
            
            if (timestamps.length === 0) return;

            const minTime = timestamps[0];
            const maxTime = timestamps[timestamps.length - 1];

            const timelineElement = document.getElementById('timeline');
            if (!timelineElement) return;
            
            if (timelineSlider) {
                timelineSlider.destroy();
            }

            try {
                timelineSlider = noUiSlider.create(timelineElement, {
                    start: [minTime, maxTime],
                    connect: true,
                    range: {
                        'min': minTime,
                        'max': maxTime
                    },
                    tooltips: [
                        { to: value => new Date(value).toLocaleDateString('zh-TW') },
                        { to: value => new Date(value).toLocaleDateString('zh-TW') }
                    ]
                });

                // 更新時間軸標籤
                const startDateElement = document.getElementById('timeline-start-date');
                const endDateElement = document.getElementById('timeline-end-date');
                if (startDateElement) startDateElement.innerText = new Date(minTime).toLocaleDateString('zh-TW');
                if (endDateElement) endDateElement.innerText = new Date(maxTime).toLocaleDateString('zh-TW');

                // 監聽滑動事件
                timelineSlider.on('slide', (values) => {
                    const startTime = parseFloat(values[0]);
                    const endTime = parseFloat(values[1]);
                    filterMarkersByTime(startTime, endTime);
                });
            } catch (error) {
                console.error('時間軸初始化失敗:', error);
            }
        }
        
        // 根據時間範圍篩選地圖點位
        function filterMarkersByTime(startTime, endTime) {
            markers.clearLayers();
            const filteredPhotos = allGpsPhotos.filter(photo => {
                const photoTime = new Date(photo.datetime).getTime();
                return photoTime >= startTime && photoTime <= endTime;
            });
            
            filteredPhotos.forEach(photo => {
                const lat = parseFloat(photo.latitude);
                const lng = parseFloat(photo.longitude);
                if (!isNaN(lat) && !isNaN(lng)) {
                    const marker = L.marker([lat, lng], { 
                        timestamp: photo.datetime ? new Date(photo.datetime).getTime() : 0 
                    })
                        .bindPopup(`
                            <div style="text-align: center; min-width: 200px;">
                                <img src="${photo.path}" alt="${photo.filename}" style="width: 150px; height: 150px; object-fit: cover; border-radius: 8px; margin-bottom: 8px; cursor: pointer;" onclick="window.open('photo_detail.php?id=${photo.id}', '_blank')">
                                <div style="font-weight: bold; color: #333; margin-bottom: 4px;">${photo.album_name || '未分類'}</div>
                                <div style="font-size: 0.9rem; color: #666;">${photo.datetime ? new Date(photo.datetime).toLocaleDateString('zh-TW') : '未知日期'}</div>
                                <div style="font-size: 0.8rem; color: #888; margin-top: 4px; word-break: break-all;">${photo.filename}</div>
                                <div style="margin-top: 8px;">
                                    <a href="photo_detail.php?id=${photo.id}" target="_blank" style="background: #1976d2; color: white; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 0.8rem;">查看詳情</a>
                                </div>
                            </div>
                        `);
                    markers.addLayer(marker);
                }
            });
        }

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
                  <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;">
                    <a href="view_album.php?album_id=${album.id}" style="text-decoration:none;color:inherit;">
                                     <img src="${album.cover_photo || 'img/default_album_cover.svg'}" alt="${album.name}" style="width:320px;height:320px;object-fit:cover;border-radius:12px;box-shadow:0 1px 4px rgba(0,0,0,0.08);">
                      <div style="margin-top:15px;font-size:1.2rem;font-weight:bold;color:#1976d2;text-align:center;">${album.name}</div>
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

        // 重新整理地圖函數
        function refreshMap() {
            // 清除所有現有的標記和圖層
            markers.clearLayers();
            heatLayer.setLatLngs([]);
            
            // 清除統計資訊和錯誤訊息
            const mapContainer = document.getElementById('map');
            const existingElements = mapContainer.querySelectorAll('.map-stats, .no-data-message, .error-message');
            existingElements.forEach(el => el.remove());
            
            // 重新載入地圖資料
            loadMapData();
        }

        // 頁面載入時執行
        loadMapData();
        loadMemoryCarousel();

        // 顯示日誌詳情
        async function showDiaryDetail(diaryId) {
          try {
            const response = await fetch('get_diary_detail.php?diary_id=' + diaryId);
            const data = await response.json();
            
            if (data.status === 'success') {
              // 建立模態框
              const modalHtml = `
                <div class="modal fade" id="diaryDetailModal" tabindex="-1" aria-hidden="true">
                  <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title">日誌詳情</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                      </div>
                      <div class="modal-body">
                        <div class="mb-3">
                          <label class="form-label fw-bold">相簿名稱</label>
                          <div class="form-control-plaintext">${data.album_name || '未指定相簿'}</div>
                        </div>
                        <div class="mb-3">
                          <label class="form-label fw-bold">日誌內容</label>
                          <textarea class="form-control" rows="8" readonly>${data.content || ''}</textarea>
                        </div>
                        <div class="mb-3">
                          <label class="form-label fw-bold">建立時間</label>
                          <div class="form-control-plaintext">${data.created_at || ''}</div>
                        </div>
                      </div>
                      <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">關閉</button>
                        <a href="ai_log.php" class="btn btn-primary">前往AI日誌頁面</a>
                      </div>
                    </div>
                  </div>
                </div>
              `;
              
              // 移除舊的模態框（如果存在）
              const oldModal = document.getElementById('diaryDetailModal');
              if (oldModal) {
                oldModal.remove();
              }
              
              // 新增新的模態框
              document.body.insertAdjacentHTML('beforeend', modalHtml);
              
              // 顯示模態框
              const modal = new bootstrap.Modal(document.getElementById('diaryDetailModal'));
              modal.show();
            } else {
              alert('載入日誌詳情失敗');
            }
          } catch (error) {
            console.error('Error:', error);
            alert('載入日誌詳情時發生錯誤');
          }
        }
    </script>
</body>
</html>