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

/* 相簿區塊 */
.album-section {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 16px; /* 左右保留小縫隙 */
    box-sizing: border-box;
}

.album-section-content {
    display: grid;
    grid-template-columns: repeat(5, 1fr); /* 桌機每排5張 */
    gap: 12px;
    width: 100%;
    box-sizing: border-box;
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
    display: block;
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

/* 手機版 */
@media (max-width: 576px) {
    .album-section-content {
        grid-template-columns: repeat(3, 1fr); /* 每排3張 */
        gap: 12px;
        padding-left: 16px;
        padding-right: 16px;
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
      <img src="img/logo.svg" width="32" height="32" class="me-2" alt="logo">
      <span style="font-weight:bold;letter-spacing:1px;">AI智慧相簿管理系統</span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavDropdown">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNavDropdown">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link" href="welcome.php">首頁</a></li>
        <li class="nav-item"><a class="nav-link active" href="album.php">相簿</a></li>
        <li class="nav-item"><a class="nav-link" href="ai_log.php">AI生成日誌</a></li>
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
    <a href="welcome.php" class="btn btn-outline-secondary rounded-circle me-3" style="width: 42px; height: 42px;">
      <i class="fas fa-arrow-left"></i>
    </a>
    <h2 class="mb-0">我的相簿</h2>
  </div>

  <div class="container mt-4">
    <div class="album-header">
        <h2 class="add-album-title">新增相簿 <button class="album-add-btn" id="addAlbumBtn">＋</button></h2>
    </div>

    <!-- 相簿區塊 -->
    <div class="album-section">
        <div class="album-section-content" id="myAlbums"></div>
    </div>
  </div>
</div>

<script>
// 初始載入相簿
async function loadMyAlbums() {
    const container = document.getElementById('myAlbums');
    if (!container) return;
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
    }
}

document.addEventListener('DOMContentLoaded', () => {
    loadMyAlbums();
});
</script>
</body>
</html>
