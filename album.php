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

    .tab-content {
      padding-top: 24px;
    }

    .album-section-content {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 18px;
        background: #f8f9fa;
        border-radius: 0;
        max-height: none;
        overflow-y: visible;
        width: 100%;
        margin-left: calc(-1 * (100vw - 100%) / 2); /* 左右負邊距以滿版置中 */
        margin-right: calc(-1 * (100vw - 100%) / 2);
        padding: 0 40px;
        justify-content: start;
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
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        max-width: 100%;
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
    .album-add-btn {
        background-color: #1976d2;
        color: white;
        border: none;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        font-size: 1.5rem;
        cursor: pointer;
        transition: background-color 0.3s;
    }
    .album-add-btn:hover {
        background-color: #1565c0;
    }

    .category-title {
        text-align: left;
        color: #333;
        font-weight: bold;
        margin-left: 40px;
        margin-top: 40px;
        margin-bottom: 24px;
        font-size: 1.8rem;
    }

    /* 響應式調整：手機畫面寬度下每排 3 張 */
    @media (max-width: 576px) {
        .album-section-content {
            width: 100%;
            margin-left: 0;
            margin-right: 0;
            padding: 0 12px;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 12px;
        }

        .album-card-title {
            font-size: 0.85rem;
        }

        .category-title {
            margin-left: 12px;
            font-size: 1.5rem;
        }
        .add-album-title {
             margin-left: 12px;
             font-size: 1.8rem;
        }
    }

    .upload-add-btn {
        width: 100%;
        height: 100%;
        border-radius: 12px;
        border: 2px dashed #ccc;
        background-color: #f8f9fa;
        color: #0d6efd;
        font-size: 1.25rem;
        font-weight: bold;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s ease-in-out;
    }

    .upload-add-btn:hover {
        background-color: #e9ecef;
        border-color: #0d6efd;
    }

    .upload-add-btn .fas {
        font-size: 2rem;
        margin-bottom: 8px;
    }
    .upload-preview-item {
        position: relative;
        width: 100%;
        aspect-ratio: 1 / 1;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 1px 4px rgba(0,0,0,0.08);
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: #f0f2f5;
    }
    .upload-preview-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .upload-delete-btn {
        position: absolute;
        top: 8px;
        right: 8px;
        background-color: rgba(0,0,0,0.5);
        color: white;
        border: none;
        border-radius: 50%;
        width: 24px;
        height: 24px;
        font-size: 1rem;
        cursor: pointer;
    }
    .modal-body .upload-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
        gap: 12px;
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
              <a class="nav-link active" href="album.php">相簿</a>
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

    <div class="container mt-4">
        <div class="d-flex align-items-center mb-3">
            <a href="welcome.php" class="btn btn-outline-secondary rounded-circle me-3"
               title="返回首頁"
               style="width: 42px; height: 42px;">
                <i class="fas fa-arrow-left"></i>
            </a>
            <h2 class="mb-0">我的相簿</h2>
        </div>
    </div>
    
    <div class="container mt-4">
        <ul class="nav nav-tabs justify-content-center" id="albumTabs" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active" id="my-albums-tab" data-bs-toggle="tab" data-bs-target="#my-albums" type="button" role="tab" aria-controls="my-albums" aria-selected="true">
              我的相簿
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="by-time-tab" data-bs-toggle="tab" data-bs-target="#by-time" type="button" role="tab" aria-controls="by-time" aria-selected="false">
              依時間分類
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="by-location-tab" data-bs-toggle="tab" data-bs-target="#by-location" type="button" role="tab" aria-controls="by-location" aria-selected="false">
              依地點分類
            </button>
          </li>
        </ul>
        
        <div class="tab-content" id="albumTabContent">
          <div class="tab-pane fade show active" id="my-albums" role="tabpanel" aria-labelledby="my-albums-tab">
            <h2 class="add-album-title">新增相簿 <button class="album-add-btn" id="addAlbumBtn">＋</button></h2>
            <div class="album-section-content" id="myAlbums"></div>
          </div>
          
          <div class="tab-pane fade" id="by-time" role="tabpanel" aria-labelledby="by-time-tab">
            <div class="album-section-content" id="albumsByTime"></div>
          </div>
          
          <div class="tab-pane fade" id="by-location" role="tabpanel" aria-labelledby="by-location-tab">
            <div class="album-section-content" id="albumsByLocation"></div>
          </div>
        </div>
    </div>

    <div class="modal fade" id="albumModal" tabindex="-1" aria-labelledby="albumModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title w-100 text-center" id="albumModalLabel">建立新相簿</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="關閉" id="modalCloseBtn"></button>
                </div>
                <div class="modal-body">
                    <div id="uploadStep">
                        <label class="form-label">請選擇要加入相簿的照片</label>
                        <input type="file" id="albumPhotoInput" accept="image/*,.heic,.heif" multiple style="display:none;">
                        <div class="upload-grid" id="albumPhotoGrid">
                            <button class="btn btn-outline-primary upload-add-btn" id="uploadAddBtn">
                                <i class="fas fa-plus"></i> 新增照片
                            </button>
                        </div>
                    </div>
                    <div id="nameStep" style="display:none; margin-top:24px;">
                        <label for="modalAlbumName" class="form-label">相簿名稱</label>
                        <input type="text" class="form-control form-control-lg" id="albumNameInput" name="albumName" placeholder="請輸入相簿名稱">
                    </div>
                </div>
                <div class="modal-footer d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" id="modalCancelBtn">取消</button>
                    <button type="button" class="btn btn-primary px-4" id="modalConfirmBtn" style="display:none;">確認</button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="monthAlbumModal" tabindex="-1" aria-labelledby="monthAlbumModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="monthAlbumModalLabel"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="monthAlbumPhotosGrid" class="album-section-content" style="padding:0;"></div>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="locationAlbumModal" tabindex="-1" aria-labelledby="locationAlbumModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="locationAlbumModalLabel"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="locationAlbumPhotosGrid" class="album-section-content" style="padding:0;"></div>
                </div>
            </div>
        </div>
    </div>


    <script>
    // 高亮新建相簿
    const urlParams = new URLSearchParams(window.location.search);
    const newAlbumId = urlParams.get('new_album_id');
    if (newAlbumId) {
        const card = document.getElementById('album-' + newAlbumId);
        if (card) {
            card.scrollIntoView({behavior: 'smooth', block: 'center'});
            card.classList.add('highlight');
            setTimeout(() => card.classList.remove('highlight'), 2000);
        }
    }

    // 新增相簿 modal 互動
    let selectedAlbumPhotos = [];
    function resetAlbumModal() {
        selectedAlbumPhotos = [];
        const albumPhotoGrid = document.getElementById('albumPhotoGrid');
        if (albumPhotoGrid) {
            albumPhotoGrid.innerHTML = '<button class="btn btn-outline-primary upload-add-btn" id="uploadAddBtn"><i class="fas fa-plus"></i> 新增照片</button>';
        }
        const uploadStep = document.getElementById('uploadStep');
        if (uploadStep) uploadStep.style.display = '';
        const nameStep = document.getElementById('nameStep');
        if (nameStep) nameStep.style.display = 'none';
        const modalConfirmBtn = document.getElementById('modalConfirmBtn');
        if (modalConfirmBtn) modalConfirmBtn.style.display = 'none';
        const albumNameInput = document.getElementById('albumNameInput');
        if (albumNameInput) albumNameInput.value = '';
    }
    
    // 動態載入我的相簿
    async function loadMyAlbums() {
        const container = document.getElementById('myAlbums');
        if (!container) {
            console.error('myAlbums element not found');
            return;
        }
        container.innerHTML = '<span style="color:#888;">載入中...</span>';
        try {
            const res = await fetch('get_album_photos.php?all_albums=1');
            const data = await res.json();
            if (data.status === 'success' && data.albums) {
                container.innerHTML = '';
                data.albums.forEach(album => {
                    const card = document.createElement('div');
                    card.className = 'album-card-preview';
                    card.innerHTML = `
                        <a href="view_album.php?album_id=${album.id}" style="text-decoration:none;color:inherit;">
                            <div class="album-card-img-wrap">
                                <img src="${album.cover_photo || 'img/default_album_cover.svg'}" alt="${album.name}">
                            </div>
                            <div class="album-card-title">${album.name}</div>
                        </a>
                    `;
                    container.appendChild(card);
                });
            } else {
                container.innerHTML = '<span style="color:#888;">尚無相簿</span>';
            }
        } catch (e) {
            container.innerHTML = '<span style="color:#888;">載入失敗</span>';
            console.error('載入我的相簿失敗:', e);
        }
    }

    // 動態載入時間區塊（每月卡片，點擊可看該月所有照片）
    async function loadPhotosByMonth() {
        const container = document.getElementById('albumsByTime');
        if (!container) {
            console.error('albumsByTime element not found');
            return;
        }
        container.innerHTML = '<span style="color:#888;">載入中...</span>';
        try {
            const res = await fetch('get_album_photos.php?group_photos_by_month=1');
            const data = await res.json();
            if (data.status === 'success' && data.photos_by_month) {
                container.innerHTML = '';
                Object.keys(data.photos_by_month).forEach(month => {
                    const photos = data.photos_by_month[month];
                    if (!photos.length) return;
                    const cover = photos[0].path || 'img/default_album_cover.svg';
                    const monthKey = photos[0].datetime.substr(0, 7); // YYYY-MM
                    const card = document.createElement('div');
                    card.className = 'album-card-preview';
                    card.style.cursor = 'pointer';
                    card.innerHTML = `
                        <div class="album-card-img-wrap">
                            <img src="${cover}" alt="${month}">
                        </div>
                        <div class="album-card-title">${month} (${photos.length})</div>
                    `;
                    card.onclick = () => showMonthAlbum(month, monthKey);
                    container.appendChild(card);
                });
            } else {
                container.innerHTML = '<span style="color:#888;">尚無照片</span>';
            }
        } catch (e) {
            container.innerHTML = '<span style="color:#888;">載入失敗</span>';
            console.error('載入月份相簿失敗:', e);
        }
    }
    
    // 動態載入地點區塊（依地點分類的卡片，點擊可看所有照片）
    async function loadPhotosByLocation() {
        const container = document.getElementById('albumsByLocation');
        if (!container) {
            console.error('albumsByLocation element not found');
            return;
        }
        container.innerHTML = '<span style="color:#888;">載入中...</span>';
        try {
            const res = await fetch('get_album_photos.php?group_photos_by_location=1');
            const data = await res.json();
            if (data.status === 'success' && data.photos_by_location) {
                container.innerHTML = '';
                Object.keys(data.photos_by_location).forEach(location => {
                    const photos = data.photos_by_location[location];
                    const cover = photos.path || 'img/default_album_cover.svg';
                    const card = document.createElement('div');
                    card.className = 'album-card-preview';
                    card.style.cursor = 'pointer';
                    card.innerHTML = `
                        <div class="album-card-img-wrap">
                            <img src="${cover}" alt="${location}">
                        </div>
                        <div class="album-card-title">${location} (${photos.count})</div>
                    `;
                    card.onclick = () => showLocationAlbum(location);
                    container.appendChild(card);
                });
            } else {
                container.innerHTML = '<span style="color:#888;">尚無地點資訊</span>';
            }
        } catch (e) {
            container.innerHTML = '<span style="color:#888;">載入失敗</span>';
            console.error('載入地點相簿失敗:', e);
        }
    }

    // 顯示月份相簿 Modal
    function showMonthAlbum(month, monthKey) {
        const modal = new bootstrap.Modal(document.getElementById('monthAlbumModal'));
        document.getElementById('monthAlbumModalLabel').textContent = `${month} 的所有照片`;
        
        fetch(`get_album_photos.php?month=${monthKey}`)
            .then(res => res.json())
            .then(data => {
                const body = document.getElementById('monthAlbumPhotosGrid');
                body.innerHTML = '';
                if (data.status === 'success' && data.photos.length) {
                    data.photos.forEach(photo => {
                        const card = document.createElement('div');
                        card.className = 'album-card-preview';
                        card.innerHTML = `<a href="photo_detail.php?id=${photo.id}" style="text-decoration:none;color:inherit;">
                                <div class="album-card-img-wrap">
                                    <img src="${photo.path}" alt="照片">
                                </div>
                                <div class="album-card-title">${photo.datetime.substr(11, 5)}</div>
                            </a>`;
                        body.appendChild(card);
                    });
                } else {
                    body.innerHTML = '<span style="color:#888;">尚無照片</span>';
                }
            });
        modal.show();
    }
    // 顯示地點相簿 Modal
    function showLocationAlbum(location) {
        const modal = new bootstrap.Modal(document.getElementById('locationAlbumModal'));
        document.getElementById('locationAlbumModalLabel').textContent = `${location} 的所有照片`;
        
        fetch(`get_album_photos.php?location=${encodeURIComponent(location)}`)
            .then(res => res.json())
            .then(data => {
                const body = document.getElementById('locationAlbumPhotosGrid');
                body.innerHTML = '';
                if (data.status === 'success' && data.photos.length) {
                    data.photos.forEach(photo => {
                         const card = document.createElement('div');
                         card.className = 'album-card-preview';
                         card.innerHTML = `<a href="photo_detail.php?id=${photo.id}" style="text-decoration:none;color:inherit;">
                                 <div class="album-card-img-wrap">
                                     <img src="${photo.path}" alt="照片">
                                 </div>
                                 <div class="album-card-title">${photo.datetime.substr(0, 10)}</div>
                             </a>`;
                         body.appendChild(card);
                    });
                } else {
                    body.innerHTML = '<span style="color:#888;">尚無照片</span>';
                }
            });
        modal.show();
    }


    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('addAlbumBtn').onclick = function() {
            resetAlbumModal();
            const albumModal = new bootstrap.Modal(document.getElementById('albumModal'));
            albumModal.show();
        };
        // 點擊加號選照片
        document.addEventListener('click', function(e) {
            if (e.target && e.target.id === 'uploadAddBtn') {
                document.getElementById('albumPhotoInput').click();
            }
        });
        // 預覽照片
        document.getElementById('albumPhotoInput').addEventListener('change', function(e) {
            const files = Array.from(e.target.files);
            
            // 檢查檔案大小和數量限制
            const maxFileSize = 10 * 1024 * 1024; // 10MB per file
            const maxTotalSize = 80 * 1024 * 1024; // 80MB total
            let totalSize = 0;
            let validFiles = [];
            
            for (let file of files) {
                if (file.size > maxFileSize) {
                    alert(`檔案 "${file.name}" 過大，單個檔案不能超過 10MB`);
                    continue;
                }
                totalSize += file.size;
                if (totalSize > maxTotalSize) {
                    alert('總檔案大小超過 80MB 限制，請減少檔案數量或壓縮檔案');
                    break;
                }
                validFiles.push(file);
            }
            
            // 加入有效檔案
            validFiles.forEach(file => {
                selectedAlbumPhotos.push(file);
            });
            
            renderAlbumPhotoGrid();
            if (selectedAlbumPhotos.length > 0) {
                document.getElementById('nameStep').style.display = '';
                document.getElementById('modalConfirmBtn').style.display = '';
            }
        });
        // 刪除預覽
        document.addEventListener('click', function(e) {
            if (e.target && e.target.classList.contains('upload-delete-btn')) {
                const idx = parseInt(e.target.getAttribute('data-idx'));
                selectedAlbumPhotos.splice(idx, 1);
                renderAlbumPhotoGrid();
                if (selectedAlbumPhotos.length === 0) {
                    document.getElementById('nameStep').style.display = 'none';
                    document.getElementById('modalConfirmBtn').style.display = 'none';
                }
            }
        });
        // 取消時清空
        document.getElementById('modalCancelBtn').onclick = resetAlbumModal;
        document.getElementById('modalCloseBtn').onclick = resetAlbumModal;
        // 確認送出（直接上傳到 Azure Storage）
        document.getElementById('modalConfirmBtn').onclick = async function() {
            const albumName = document.getElementById('albumNameInput').value.trim();
            if (!albumName) {
                alert('請輸入相簿名稱');
                return;
            }
            if (selectedAlbumPhotos.length === 0) {
                alert('請先選擇照片');
                return;
            }
            
            try {
                const confirmBtn = document.getElementById('modalConfirmBtn');
                const originalText = confirmBtn.textContent;
                confirmBtn.textContent = '上傳中...';
                confirmBtn.disabled = true;
                
                const blobUrls = [];
                const fileNames = [];
                
                for (let i = 0; i < selectedAlbumPhotos.length; i++) {
                    const file = selectedAlbumPhotos[i];
                    console.log(`上傳檔案 ${i + 1}/${selectedAlbumPhotos.length}: ${file.name}`);
                    
                    const sasResponse = await fetch('generate_sas_token_final.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `extension=${file.name.split('.').pop()}`
                    });
                    const sasResult = await sasResponse.json();
                    if (!sasResult.success) throw new Error('取得上傳權限失敗: ' + sasResult.error);
                    
                    const uploadResponse = await fetch(sasResult.uploadUrl, {
                        method: 'PUT',
                        body: file,
                        headers: {
                            'x-ms-blob-type': 'BlockBlob',
                            'Content-Type': file.type,
                            'Content-Length': file.size
                        }
                    });
                    
                    if (!uploadResponse.ok) throw new Error(`檔案上傳失敗: ${uploadResponse.status} ${uploadResponse.statusText}`);
                    
                    blobUrls.push(sasResult.blobUrl);
                    fileNames.push(file.name);
                    
                    confirmBtn.textContent = `上傳中... ${i + 1}/${selectedAlbumPhotos.length}`;
                }
                
                const formData = new FormData();
                formData.append('albumName', albumName);
                blobUrls.forEach(url => formData.append('blobUrls[]', url));
                fileNames.forEach(name => formData.append('fileNames[]', name));
                
                const saveResponse = await fetch('save_album_final_fix.php', {
                    method: 'POST',
                    body: formData
                });
                
                const responseText = await saveResponse.text();
                let result;
                try {
                    result = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('JSON 解析失敗：', parseError);
                    alert('伺服器回應格式錯誤：' + responseText);
                    return;
                }
                
                if (result.status === 'success') {
                    bootstrap.Modal.getInstance(document.getElementById('albumModal')).hide();
                    resetAlbumModal();
                    loadMyAlbums();
                } else {
                    console.error('相簿建立失敗：', result.message);
                    alert('建立失敗：' + (result.message || '未知錯誤'));
                }
            } catch (e) {
                console.error('上傳過程發生錯誤：', e);
                alert('建立失敗，請稍後再試');
            }
        };

        // 頁面載入時，載入所有相簿和分類
        loadMyAlbums();
        loadPhotosByMonth();
        loadPhotosByLocation();
    });

    // 修正後的 renderAlbumPhotoGrid 函式
    function renderAlbumPhotoGrid() {
        const grid = document.getElementById('albumPhotoGrid');
        if (!grid) {
            console.error('albumPhotoGrid element not found');
            return;
        }

        grid.innerHTML = ''; // 清除現有預覽

        // 先渲染所有預覽照片
        selectedAlbumPhotos.forEach((file, idx) => {
            const div = document.createElement('div');
            div.className = 'upload-preview-item';

            const deleteBtn = document.createElement('button');
            deleteBtn.className = 'upload-delete-btn';
            deleteBtn.setAttribute('data-idx', idx);
            deleteBtn.innerHTML = '&times;';
            div.appendChild(deleteBtn);

            if (
                file.type === 'image/heic' || file.type === 'image/heif' ||
                file.name.toLowerCase().endsWith('.heic') || file.name.toLowerCase().endsWith('.heif')
            ) {
                const loadingText = document.createElement('div');
                loadingText.style.cssText = "width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:0.8rem;color:#888;text-align:center;";
                loadingText.innerHTML = "HEIC<br>預覽中...";
                div.appendChild(loadingText);
                grid.appendChild(div);

                heic2any({
                    blob: file,
                    toType: "image/jpeg",
                    quality: 0.8
                })
                .then(function (resultBlob) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        if (loadingText.parentNode) {
                            loadingText.parentNode.removeChild(loadingText);
                        }
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        div.prepend(img);
                    };
                    reader.readAsDataURL(resultBlob);
                })
                .catch(function (x) {
                    console.error("HEIC 預覽轉換失敗:", x.code, x.message, file.name);
                    loadingText.innerHTML = `HEIC<br>預覽失敗<br><span style="font-size:0.7em;">(${x.code || '未知錯誤'})</span>`;
                    loadingText.style.color = 'red';
                });
            } else {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    div.prepend(img);
                    grid.appendChild(div);
                };
                reader.readAsDataURL(file);
            }
        });

        // 最後再添加加號按鈕，並保持正確的 ID
        const addBtn = document.createElement('button');
        addBtn.className = 'btn btn-outline-primary upload-add-btn';
        addBtn.id = 'uploadAddBtn';
        addBtn.innerHTML = '<i class="fas fa-plus"></i> 新增照片';
        grid.appendChild(addBtn);
    }
    </script>
</body>
</html>