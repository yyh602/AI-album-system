<?php
session_start();

if (!isset($_SESSION["username"])) {
    header("Location: login.php");
    exit();
}

require_once("DB_open.php");

$albumId = $_GET["album_id"] ?? 0;
$username = $_SESSION["username"];

// 驗證相簿所有權並獲取相簿資訊
if ($link instanceof mysqli) {
    $sql = "SELECT id, name, username FROM albums WHERE id = ? AND username = ?";
    $stmt = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($stmt, "is", $albumId, $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $album = mysqli_fetch_assoc($result);

    if (!$album) {
        header("Location: album.php");
        exit();
    }

    // 獲取相簿中的所有照片
    $sql = "SELECT * FROM uploads WHERE album_id = ?";
    $stmt = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($stmt, "i", $albumId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $photos = mysqli_fetch_all($result, MYSQLI_ASSOC);
} else {
    // 如果是 PDOWrapper，使用 PDO 方式查詢
    $sql = "SELECT id, name, username FROM albums WHERE id = ? AND username = ?";
    $stmt = $link->prepare($sql);
    $stmt->execute([$albumId, $username]);
    $album = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$album) {
        header("Location: album.php");
        exit();
    }

    // 獲取相簿中的所有照片
    $sql = "SELECT * FROM uploads WHERE album_id = ?";
    $stmt = $link->prepare($sql);
    $stmt->execute([$albumId]);
    $photos = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 計算旅遊期間
$dates = array_column($photos, 'datetime');
$startDate = $endDate = null;
if (!empty($dates)) {
    sort($dates);
    $startDate = $dates[0];
    $endDate = end($dates);
}

require_once("DB_close.php");
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($album['name']); ?> - AI智慧相簿管理</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
  <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
  <style>
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
        color: #333 !important;
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
    .photo-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 15px;
        margin-top: 20px;
    }
    .photo-item {
        position: relative;
        overflow: hidden;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    .photo-item img {
        width: 100%;
        height: 180px;
        object-fit: cover;
        display: block;
    }
    .photo-info {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        background: rgba(0, 0, 0, 0.6);
        color: white;
        font-size: 12px;
        padding: 5px;
        text-align: left;
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    .photo-item:hover .photo-info {
        opacity: 1;
    }
    #albumMap {
        height: 400px;
        width: 100%;
        margin-top: 30px;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    .button-container {
        margin-top: 30px;
        text-align: center;
    }
    @media (max-width: 576px) {
        .photo-grid {
            grid-template-columns: repeat(3, 1fr);
        }
    }
    .travel-period {
  font-size: 1.4rem;
  font-weight: bold;
  color: silver;
}

@media (max-width: 576px) {
  .travel-period {
    font-size: 1rem;
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
          <img src="img/avatar.png" alt="avatar" class="navbar-avatar">
          <span class="navbar-username ms-2"><?php echo htmlspecialchars($username); ?></span>
        </div>
      </div>
    </div>
  </nav>

  <div class="container mt-4">
    <!-- 相簿名稱與返回按鈕 -->
    <div class="d-flex align-items-center mb-3">
      <button class="btn btn-outline-secondary rounded-circle me-3" onclick="window.history.back();" title="返回上一頁" style="width: 42px; height: 42px;">
        <i class="fas fa-arrow-left"></i>
      </button>
      <h2 class="mb-0"><?php echo htmlspecialchars($album['name']); ?></h2>
    </div>

    <!-- 旅遊期間 -->
    <?php if ($startDate && $endDate): ?>
        <div class="text-start mb-4 travel-period">
  旅遊期間：<?php echo date('Y/m/d', strtotime($startDate)); ?> ～ <?php echo date('Y/m/d', strtotime($endDate)); ?>
</div>

    <?php endif; ?>

    <!-- 照片 -->
    <div class="photo-grid">
      <?php if (empty($photos)): ?>
        <div class="col-12 text-center text-muted">此相簿暫無照片</div>
      <?php else: ?>
        <?php foreach ($photos as $photo): ?>
          <div class="photo-item">
            <a href="photo_detail.php?photo_id=<?php echo $photo['id']; ?>">
              <img src="<?php echo htmlspecialchars($photo['filename']); ?>" alt="<?php echo htmlspecialchars(basename($photo['filename'])); ?>">
            </a>
            <div class="photo-info">
              檔案: <?php echo htmlspecialchars(basename($photo['filename'])); ?><br>
              <?php if ($photo['latitude'] && $photo['longitude']): ?>
                GPS: <?php echo htmlspecialchars(number_format($photo['latitude'], 6)); ?>, <?php echo htmlspecialchars(number_format($photo['longitude'], 6)); ?><br>
              <?php endif; ?>
              時間: <?php echo htmlspecialchars($photo['datetime'] ?? '無時間資訊'); ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- 地圖 -->
    <div id="albumMap"></div>

    <!-- 按鈕 -->
    <div class="button-container">
      <button class="btn btn-secondary me-3" onclick="window.history.back();">
        <i class="fas fa-arrow-left me-1"></i>回前頁
      </button>
      <button class="btn btn-danger" id="deleteAlbumBtn">
        <i class="fas fa-trash-alt me-1"></i>刪除相簿
      </button>
    </div>
  </div>

  <script>
    const albumId = <?php echo json_encode($albumId); ?>;
    const photos = <?php echo json_encode($photos); ?>;

    const gpsPhotos = photos.filter(photo => photo.latitude && photo.longitude);
    if (gpsPhotos.length > 0) {
      const map = L.map('albumMap').setView([gpsPhotos[0].latitude, gpsPhotos[0].longitude], 13);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
      }).addTo(map);

      const bounds = [];
      gpsPhotos.forEach(photo => {
        const marker = L.marker([photo.latitude, photo.longitude])
          .bindPopup(`<img src="${photo.filename}" style="max-width: 200px;"><br>${photo.datetime}`)
          .addTo(map);
        bounds.push([photo.latitude, photo.longitude]);
      });

      if (bounds.length > 0) {
        map.fitBounds(bounds);
      }
    } else {
      document.getElementById('albumMap').style.display = 'none';
      const noGpsMessage = document.createElement('div');
      noGpsMessage.className = 'text-center text-muted mt-3';
      noGpsMessage.textContent = '此相簿無帶有 GPS 的照片';
      document.querySelector('.container.mt-4').insertBefore(noGpsMessage, document.getElementById('albumMap'));
    }

    document.getElementById('deleteAlbumBtn').addEventListener('click', async function () {
      if (confirm('確定要刪除整個相簿嗎？此操作不可恢復！')) {
        try {
          const formData = new FormData();
          formData.append('album_id', albumId);

          const response = await fetch('delete_album.php', {
            method: 'POST',
            body: formData
          });

          const result = await response.json();

          if (result.status === 'success') {
            alert('相簿已刪除');
            window.location.href = 'album.php';
          } else {
            alert('刪除失敗：' + (result.message || '未知錯誤'));
          }
        } catch (error) {
          console.error('刪除相簿錯誤：', error);
          alert('刪除過程中發生錯誤');
        }
      }
    });
  </script>
</body>
</html>
