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

// 檢查連接類型並使用相應的查詢方式
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
    $sql = "SELECT name FROM user WHERE username = ?";
    $stmt = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $result_name);
    
    if (mysqli_stmt_fetch($stmt)) {
        $name = $result_name;
    }
    mysqli_stmt_close($stmt);
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
    border-radius: 0; /* 去除圓角 */
    max-height: none; /* 取消高度限制 */
    overflow-y: visible; /* 無需滾動 */
    width: 100vw; /* 滿版寬度 */
    margin-left: calc(-1 * (100vw - 100%) / 2); /* 左右負邊距以滿版置中 */
    margin-right: calc(-1 * (100vw - 100%) / 2);
    margin-top: 0;
    margin-bottom: 0;
    padding: 0; /* 拿掉上下左右內距 */
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
        width: 100%;
        margin-left: 0;
        margin-right: 0;
        padding: 12px;
        grid-template-columns: repeat(3, 1fr); /* 手機每排3張 */
        gap: 12px;
    }

    .album-card-title {
        font-size: 0.85rem;
    }
}
</style>

</head>
<body>
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
              <a class="nav-link active" href="album.php">相簿</a>
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

    <!-- 返回按鈕 + 標題列 -->
    <div class="container mt-4">
  <div class="d-flex align-items-center mb-3">
    <a href="welcome.php" class="btn btn-outline-secondary rounded-circle me-3"
       title="返回首頁"
       style="width: 42px; height: 42px;">
      <i class="fas fa-arrow-left"></i>
    </a>
    <h2 class="mb-0">我的相簿</h2>
  </div>

    
    <div class="container mt-4">
        <div class="album-header">
            <h2 class="add-album-title">新增相簿 <button class="album-add-btn" id="addAlbumBtn">＋</button></h2>
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
                            <input type="file" id="albumPhotoInput" accept="image/*" multiple style="display:none;">
                            <div class="upload-grid" id="albumPhotoGrid">
                                <div class="upload-add-box" id="uploadAddBox">＋</div>
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

        <div class="album-section">
            <div class="album-section-content" id="myAlbums"></div>
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
            document.getElementById('albumPhotoGrid').innerHTML = '<div class="upload-add-box" id="uploadAddBox">＋</div>';
            document.getElementById('uploadStep').style.display = '';
            document.getElementById('nameStep').style.display = 'none';
            document.getElementById('modalConfirmBtn').style.display = 'none';
            document.getElementById('albumNameInput').value = '';
        }
        // 動態載入我的相簿
        async function loadMyAlbums() {
            const container = document.getElementById('myAlbums');
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
                                    <img src="${album.cover_photo || 'img/default_album_cover.png'}" alt="${album.name}">
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
            }
        }
        // 動態載入時間區塊（每月卡片，點擊可看該月所有照片）
        async function loadPhotosByMonth() {
            const container = document.getElementById('albumsByTime');
            container.innerHTML = '<span style="color:#888;">載入中...</span>';
            try {
                const res = await fetch('get_album_photos.php?group_photos_by_month=1');
                const data = await res.json();
                if (data.status === 'success' && data.photos_by_month) {
                    container.innerHTML = '';
                    Object.keys(data.photos_by_month).forEach(month => {
                        const photos = data.photos_by_month[month];
                        if (!photos.length) return;
                        const cover = photos[0].path || 'img/default_album_cover.png';
                        const monthKey = photos[0].datetime.substr(0, 7); // YYYY-MM
                        const card = document.createElement('div');
                        card.className = 'album-card-preview';
                        card.style.cursor = 'pointer';
                        card.innerHTML = `
                            <div class="album-card-img-wrap">
                                <img src="${cover}" alt="${month}">
                            </div>
                            <div class="album-card-title">${month}</div>
                        `;
                        card.onclick = () => showMonthAlbum(month, monthKey);
                        container.appendChild(card);
                    });
                } else {
                    container.innerHTML = '<span style="color:#888;">尚無照片</span>';
                }
            } catch (e) {
                container.innerHTML = '<span style="color:#888;">載入失敗</span>';
            }
        }
        // 顯示月份相簿 Modal
        function showMonthAlbum(month, monthKey) {
            let modal = document.getElementById('monthAlbumModal');
            if (!modal) {
                modal = document.createElement('div');
                modal.id = 'monthAlbumModal';
                modal.className = 'modal fade';
                modal.tabIndex = -1;
                modal.innerHTML = `
                    <div class="modal-dialog modal-xl">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title"></h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body" id="monthAlbumPhotos" style="display:flex;flex-wrap:wrap;gap:16px;"></div>
                        </div>
                    </div>
                `;
                document.body.appendChild(modal);
            }
            modal.querySelector('.modal-title').textContent = `${month} 的所有照片`;
            // 載入該月所有照片
            fetch(`get_album_photos.php?month=${monthKey}`)
                .then(res => res.json())
                .then(data => {
                    const body = document.getElementById('monthAlbumPhotos');
                    body.innerHTML = '';
                    if (data.status === 'success' && data.photos.length) {
                        data.photos.forEach(photo => {
                            const img = document.createElement('img');
                            img.src = photo.path;
                            img.style.width = '180px';
                            img.style.height = '180px';
                            img.style.objectFit = 'cover';
                            img.style.borderRadius = '10px';
                            body.appendChild(img);
                        });
                    } else {
                        body.innerHTML = '<span style="color:#888;">尚無照片</span>';
                    }
                });
            // 顯示 Modal
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
        }
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('addAlbumBtn').onclick = function() {
                resetAlbumModal();
                const albumModal = new bootstrap.Modal(document.getElementById('albumModal'));
                albumModal.show();
            };
            // 點擊加號選照片
            document.addEventListener('click', function(e) {
                if (e.target && e.target.id === 'uploadAddBox') {
                    document.getElementById('albumPhotoInput').click();
                }
            });
            // 預覽照片
            document.getElementById('albumPhotoInput').addEventListener('change', function(e) {
                const files = Array.from(e.target.files);
                files.forEach(file => {
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
            // 確認送出（支援前端 HEIC 轉 JPG 並讀 EXIF）
            document.getElementById('modalConfirmBtn').onclick = async function() {
                const albumName = document.getElementById('albumNameInput').value.trim();
                console.log('送出相簿名稱：', albumName);
                if (!albumName) {
                    alert('請輸入相簿名稱');
                    return;
                }
                if (selectedAlbumPhotos.length === 0) {
                    alert('請先選擇照片');
                    return;
                }
                const formData = new FormData();
                
                formData.append('albumName', albumName);
                let validPhotoCount = 0;
                let errorFiles = [];
                for (let idx = 0; idx < selectedAlbumPhotos.length; idx++) {
                    let file = selectedAlbumPhotos[idx];
                    // 直接 append，無論是 JPG 還是 HEIC
                    formData.append('photo' + validPhotoCount, file);
                    validPhotoCount++;
                }
                if (errorFiles.length > 0) {
                    alert('下列檔案處理失敗：\n' + errorFiles.join('\n'));
                }
                if (validPhotoCount === 0) {
                    alert('所有檔案都無法上傳，請確認檔案格式或先轉成 JPG。');
                    return;
                }
                try {
                    const res = await fetch('save_album.php', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await res.json();
                    if (result.status === 'success') {
                        // 關閉 modal 並刷新我的相簿
                        bootstrap.Modal.getInstance(document.getElementById('albumModal')).hide();
                        resetAlbumModal();
                        loadMyAlbums();
                    } else {
                        alert('建立失敗：' + (result.message || '未知錯誤'));
                    }
                } catch (e) {
                    alert('建立失敗，請稍後再試');
                }
            };
            // 初始載入我的相簿
            loadMyAlbums();
            loadPhotosByMonth();
        });

        // 修正後的 renderAlbumPhotoGrid 函式
        function renderAlbumPhotoGrid() {
            const grid = document.getElementById('albumPhotoGrid');
            grid.innerHTML = ''; // 清除現有預覽
            selectedAlbumPhotos.forEach((file, idx) => {
                const div = document.createElement('div');
                div.className = 'upload-preview-item';
                
                const deleteBtn = document.createElement('button');
                deleteBtn.className = 'upload-delete-btn';
                deleteBtn.setAttribute('data-idx', idx);
                deleteBtn.innerHTML = '&times;';
                div.appendChild(deleteBtn); // 先添加刪除按鈕

                // 檢查是否為 HEIC/HEIF 並進行客戶端預覽轉換
                if (
                    file.type === 'image/heic' || file.type === 'image/heif' ||
                    file.name.toLowerCase().endsWith('.heic') || file.name.toLowerCase().endsWith('.heif')
                ) {
                    // 顯示一個載入或預覽中的提示
                    const loadingText = document.createElement('div');
                    loadingText.style.cssText = "width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:0.8rem;color:#888;text-align:center;";
                    loadingText.innerHTML = "HEIC<br>預覽中...";
                    div.appendChild(loadingText);
                    grid.appendChild(div); // 先將預覽框添加到網格中

                    heic2any({
                        blob: file,
                        toType: "image/jpeg", // 轉換為 JPEG 以供預覽
                        quality: 0.8
                    })
                    .then(function (resultBlob) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            // 移除載入文字，顯示圖片
                            div.removeChild(loadingText); 
                            const img = document.createElement('img');
                            img.src = e.target.result;
                            div.prepend(img); // 將圖片插入到最前面
                        };
                        reader.readAsDataURL(resultBlob); // 讀取轉換後的 Blob
                    })
                    .catch(function (x) {
                        console.error("HEIC 預覽轉換失敗:", x.code, x.message, file.name);
                        // 備用方案：如果轉換失敗，顯示通用 HEIC 佔位符和錯誤訊息
                        loadingText.innerHTML = `
                            HEIC<br>預覽失敗<br><span style="font-size:0.7em;">(${x.code || '未知錯誤'})</span>
                        `;
                        loadingText.style.color = 'red'; // 將錯誤訊息設為紅色
                    });
                } else {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        div.prepend(img); // 將圖片插入到最前面
                        grid.appendChild(div);
                    };
                    reader.readAsDataURL(file); // 處理其他圖片類型
                }
            });

            // 加號永遠在最後
            const addBox = document.createElement('div');
            addBox.className = 'upload-add-box';
            addBox.id = 'uploadAddBox';
            addBox.textContent = '＋';
            grid.appendChild(addBox);
        }
    </script>
</body>
</html>