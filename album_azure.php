<?php
session_start();

// 檢查登入狀態
if (!isset($_SESSION["username"])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION["username"];
$name = $username;

// 安全的資料庫連接
require_once("DB_open.php");

if ($link instanceof mysqli) {
    try {
        // 查詢用戶名稱
        $sql = "SELECT name FROM user WHERE username = ?";
        $stmt = mysqli_prepare($link, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $username);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_bind_result($stmt, $result_name);
            if (mysqli_stmt_fetch($stmt)) {
                $name = $result_name ?: $username;
            }
            mysqli_stmt_close($stmt);
        }
    } catch (Exception $e) {
        error_log("Azure 相簿頁面資料庫查詢錯誤：" . $e->getMessage());
        // 繼續執行，使用預設名稱
    }
}

require_once("DB_close.php");
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>相簿 - AI智慧相簿管理 (Azure)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: #f6f8fa;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
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
        
        .album-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin: 15px 0;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.2s ease;
        }
        
        .album-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.15);
        }
        
        .album-cover {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        
        .album-title {
            font-size: 1.2rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
        }
        
        .album-info {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 15px;
        }
        
        .loading {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .tab-content {
            padding-top: 24px;
        }
        
        .nav-tabs .nav-link {
            color: #666;
            border: none;
            border-bottom: 2px solid transparent;
        }
        
        .nav-tabs .nav-link.active {
            color: #3498db;
            border-bottom: 2px solid #3498db;
            background: none;
        }
    </style>
</head>
<body>
    <!-- 導航欄 -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-images"></i> AI智慧相簿管理
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="welcome.php">
                    <i class="fas fa-home"></i> 首頁
                </a>
                <a class="nav-link active" href="album.php">
                    <i class="fas fa-images"></i> 相簿
                </a>
                <a class="nav-link" href="ai_log.php">
                    <i class="fas fa-robot"></i> AI日誌
                </a>
                <span class="navbar-text">
                    <i class="fas fa-user"></i> 歡迎，<?php echo htmlspecialchars($name); ?>
                </span>
                <a class="nav-link" href="logout.php">
                    <i class="fas fa-sign-out-alt"></i> 登出
                </a>
            </div>
        </div>
    </nav>

    <!-- 主要內容 -->
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <h1 class="mb-4">
                    <i class="fas fa-images"></i> 我的相簿
                </h1>
                
                <!-- 標籤頁 -->
                <ul class="nav nav-tabs" id="albumTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="my-albums-tab" data-bs-toggle="tab" data-bs-target="#my-albums" type="button" role="tab">
                            <i class="fas fa-folder"></i> 我的相簿
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="time-tab" data-bs-toggle="tab" data-bs-target="#time" type="button" role="tab">
                            <i class="fas fa-calendar"></i> 依時間
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="location-tab" data-bs-toggle="tab" data-bs-target="#location" type="button" role="tab">
                            <i class="fas fa-map-marker-alt"></i> 依地點
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content" id="albumTabsContent">
                    <!-- 我的相簿 -->
                    <div class="tab-pane fade show active" id="my-albums" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h3>我的相簿</h3>
                            <a href="add.php" class="btn btn-primary">
                                <i class="fas fa-plus"></i> 建立新相簿
                            </a>
                        </div>
                        <div id="myAlbums" class="loading">
                            <i class="fas fa-spinner fa-spin"></i> 載入中...
                        </div>
                    </div>
                    
                    <!-- 依時間 -->
                    <div class="tab-pane fade" id="time" role="tabpanel">
                        <h3>依時間分類</h3>
                        <div id="albumsByTime" class="loading">
                            <i class="fas fa-spinner fa-spin"></i> 載入中...
                        </div>
                    </div>
                    
                    <!-- 依地點 -->
                    <div class="tab-pane fade" id="location" role="tabpanel">
                        <h3>依地點分類</h3>
                        <div id="albumsByLocation" class="loading">
                            <i class="fas fa-spinner fa-spin"></i> 載入中...
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 相簿載入函數
        async function loadMyAlbums() {
            const container = document.getElementById('myAlbums');
            try {
                const response = await fetch('get_album_photos.php?all_albums=1');
                const data = await response.json();
                
                if (data.status === 'success' && data.albums && data.albums.length > 0) {
                    container.innerHTML = data.albums.map(album => `
                        <div class="album-card">
                            <img src="${album.cover_photo || 'img/default_album_cover.svg'}" alt="${album.name}" class="album-cover">
                            <div class="album-title">${album.name}</div>
                            <div class="album-info">
                                <i class="fas fa-calendar"></i> 相簿 ID: ${album.id}
                            </div>
                            <div class="d-flex gap-2">
                                <a href="view_album.php?album_id=${album.id}" class="btn btn-primary btn-sm">
                                    <i class="fas fa-eye"></i> 查看相簿
                                </a>
                                <a href="edit_album.php?album_id=${album.id}" class="btn btn-outline-secondary btn-sm">
                                    <i class="fas fa-edit"></i> 編輯
                                </a>
                            </div>
                        </div>
                    `).join('');
                } else {
                    container.innerHTML = `
                        <div class="text-center">
                            <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
                            <h4>您還沒有相簿</h4>
                            <p class="text-muted">建立您的第一個相簿來開始管理照片吧！</p>
                            <a href="add.php" class="btn btn-primary">
                                <i class="fas fa-plus"></i> 建立第一個相簿
                            </a>
                        </div>
                    `;
                }
            } catch (error) {
                container.innerHTML = `
                    <div class="error">
                        <i class="fas fa-exclamation-triangle"></i>
                        載入失敗：${error.message}
                        <br><button onclick="loadMyAlbums()" class="btn btn-sm btn-outline-primary mt-2">重新載入</button>
                    </div>
                `;
                console.error('載入相簿失敗:', error);
            }
        }

        // 依時間載入
        async function loadPhotosByTime() {
            const container = document.getElementById('albumsByTime');
            try {
                const response = await fetch('get_album_photos.php?group_photos_by_month=1');
                const data = await response.json();
                
                if (data.status === 'success' && data.photos_by_month) {
                    const months = Object.keys(data.photos_by_month);
                    if (months.length > 0) {
                        container.innerHTML = months.map(month => {
                            const photos = data.photos_by_month[month];
                            const cover = photos[0]?.path || 'img/default_album_cover.svg';
                            return `
                                <div class="album-card">
                                    <img src="${cover}" alt="${month}" class="album-cover">
                                    <div class="album-title">${month}</div>
                                    <div class="album-info">
                                        <i class="fas fa-image"></i> ${photos.length} 張照片
                                    </div>
                                    <button onclick="showMonthPhotos('${month}')" class="btn btn-primary btn-sm">
                                        <i class="fas fa-eye"></i> 查看照片
                                    </button>
                                </div>
                            `;
                        }).join('');
                    } else {
                        container.innerHTML = '<div class="text-center text-muted">沒有照片</div>';
                    }
                } else {
                    container.innerHTML = '<div class="text-center text-muted">沒有照片</div>';
                }
            } catch (error) {
                container.innerHTML = `<div class="error">載入失敗：${error.message}</div>`;
            }
        }

        // 依地點載入
        async function loadPhotosByLocation() {
            const container = document.getElementById('albumsByLocation');
            try {
                const response = await fetch('get_album_photos.php?group_photos_by_location=1');
                const data = await response.json();
                
                if (data.status === 'success' && data.photos_by_location) {
                    const locations = Object.keys(data.photos_by_location);
                    if (locations.length > 0) {
                        container.innerHTML = locations.map(location => {
                            const photos = data.photos_by_location[location];
                            const cover = photos[0]?.path || 'img/default_album_cover.svg';
                            return `
                                <div class="album-card">
                                    <img src="${cover}" alt="${location}" class="album-cover">
                                    <div class="album-title">${location}</div>
                                    <div class="album-info">
                                        <i class="fas fa-image"></i> ${photos.length} 張照片
                                    </div>
                                    <button onclick="showLocationPhotos('${location}')" class="btn btn-primary btn-sm">
                                        <i class="fas fa-eye"></i> 查看照片
                                    </button>
                                </div>
                            `;
                        }).join('');
                    } else {
                        container.innerHTML = '<div class="text-center text-muted">沒有照片</div>';
                    }
                } else {
                    container.innerHTML = '<div class="text-center text-muted">沒有照片</div>';
                }
            } catch (error) {
                container.innerHTML = `<div class="error">載入失敗：${error.message}</div>`;
            }
        }

        // 標籤頁切換事件
        document.addEventListener('DOMContentLoaded', function() {
            // 初始載入
            loadMyAlbums();
            
            // 標籤頁切換事件
            const tabs = document.querySelectorAll('[data-bs-toggle="tab"]');
            tabs.forEach(tab => {
                tab.addEventListener('shown.bs.tab', function(e) {
                    const target = e.target.getAttribute('data-bs-target');
                    if (target === '#time') {
                        loadPhotosByTime();
                    } else if (target === '#location') {
                        loadPhotosByLocation();
                    }
                });
            });
        });

        // 顯示月份照片
        function showMonthPhotos(month) {
            alert(`查看 ${month} 的照片功能開發中...`);
        }

        // 顯示地點照片
        function showLocationPhotos(location) {
            alert(`查看 ${location} 的照片功能開發中...`);
        }
    </script>
</body>
</html>
