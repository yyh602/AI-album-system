<?php
session_start();
if (!isset($_SESSION["username"])) {
    header("Location: login.php");
    exit();
}

require_once("DB_open.php");

$photoId = $_GET["photo_id"] ?? 0;
$username = $_SESSION["username"];

if ($link instanceof mysqli) {
    $sql = "SELECT u.*, a.username AS album_owner, a.name AS album_name 
            FROM uploads u 
            JOIN albums a ON u.album_id = a.id 
            WHERE u.id = ? AND a.username = ?";
    $stmt = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($stmt, "is", $photoId, $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $photo = mysqli_fetch_assoc($result);

    if (!$photo) {
        echo "找不到照片或您沒有權限查看此照片。";
        exit();
    }
} else {
    // 如果是 PDOWrapper，使用 PDO 方式查詢
    $sql = "SELECT u.*, a.username AS album_owner, a.name AS album_name 
            FROM uploads u 
            JOIN albums a ON u.album_id = a.id 
            WHERE u.id = ? AND a.username = ?";
    $stmt = $link->prepare($sql);
    $stmt->execute([$photoId, $username]);
    $photo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$photo) {
        echo "找不到照片或您沒有權限查看此照片。";
        exit();
    }
}

require_once("DB_close.php");
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>照片詳細資訊</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- 資源 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>

    <style>
        body {
            background-color: #f5f5f8;
        }
        .navbar {
            background-color: #e9d0c3 !important;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .navbar-brand, .nav-link, .navbar-username {
            color: #333 !important;
        }
        .navbar-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
        }
        .photo-container {
            max-width: 900px;
            margin: auto;
        }
        .photo-img {
            max-width: 100%;
            height: auto;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        #map {
            width: 100%;
            height: 400px;
            margin: 20px auto;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        #location-label {
            font-size: 1.75rem;
            font-weight: bold;
            text-align: left;
            margin-top: 40px;
        }
        .header-row {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            margin-top: 40px;
            margin-bottom: 32px;
            gap: 16px;
        }
        .back-circle {
            width: 48px;
            height: 48px;
            background-color: #ddd;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            text-decoration: none;
            transition: background-color 0.3s;
        }
        .back-circle:hover {
            background-color: #ccc;
            cursor: pointer;
        }
        .back-circle i {
            font-size: 20px;
            color: #333;
        }
        .timestamp {
            font-size: 2rem;
            font-weight: bold;
            color: #222;
        }
        .gps-warning {
            background-color: #fff3cd;
            border: 1px solid #ffeeba;
            color: #856404;
            padding: 15px;
            border-radius: 8px;
            margin-top: 30px;
        }
        @media (max-width: 576px) {
            .header-row {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>

<!-- 導覽列 -->
<nav class="navbar navbar-expand-lg navbar-dark sticky-top">
    <div class="container-fluid px-3">
        <a class="navbar-brand d-flex align-items-center" href="#">
            <img src="img/logo.png" width="32" height="32" class="me-2">
            <span style="font-weight:bold;">AI智慧相簿管理系統</span>
        </a>
        <div class="collapse navbar-collapse">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="welcome.php">首頁</a></li>
                <li class="nav-item"><a class="nav-link" href="album.php">相簿</a></li>
                <li class="nav-item"><a class="nav-link" href="ai_log.php">AI生成日誌</a></li>
            </ul>
            <div class="d-flex align-items-center ms-auto">
                <img src="img/avatar.png" alt="avatar" class="navbar-avatar">
                <span class="navbar-username ms-2"><?php echo htmlspecialchars($username); ?></span>
            </div>
        </div>
    </div>
</nav>

<!-- 主要內容 -->
<div class="container px-4 photo-container">
    <!-- 返回 + 拍攝時間 -->
    <div class="header-row">
    <button class="btn btn-outline-secondary rounded-circle me-3" onclick="window.history.back();" title="返回上一頁" style="width: 42px; height: 42px;">
        <i class="fas fa-arrow-left"></i>
    </button>
    <div class="timestamp">
        <?php
            if (!empty($photo['datetime'])) {
                $dt = new DateTime($photo['datetime']);
                echo $dt->format("Y/n/j") . '　' . $dt->format("H : i");
            } else {
                echo "無時間資訊";
            }
        ?>
    </div>
</div>


    <!-- 照片 -->
    <div class="text-center mb-5">
        <img src="<?php echo htmlspecialchars($photo['filename']); ?>" class="photo-img" alt="photo">
    </div>

    <!-- 拍攝地點與地圖 / 或警告 -->
    <?php if ($photo['latitude'] && $photo['longitude']): ?>
        <div id="location-label">拍攝地點：<span id="location-text">載入中...</span></div>
        <div id="map"></div>
    <?php else: ?>
        <div class="gps-warning">
            ⚠️ 此照片無 GPS 資訊，無法顯示地點與地圖。
        </div>
    <?php endif; ?>
</div>

<!-- JS：地點與地圖 -->
<script>
    const latitude = <?php echo json_encode($photo['latitude']); ?>;
    const longitude = <?php echo json_encode($photo['longitude']); ?>;

    if (latitude && longitude) {
        const map = L.map('map').setView([latitude, longitude], 5);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        L.marker([latitude, longitude]).addTo(map);

        fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${latitude}&lon=${longitude}`)
            .then(res => res.json())
            .then(data => {
                const address = data.address;
                const city = address.city || address.town || address.village || '';
                const country = address.country || '';
                const fullLocation = country ? `${country}　${city}` : city;
                document.getElementById('location-text').textContent = fullLocation;
            })
            .catch(() => {
                document.getElementById('location-text').textContent = "無法取得地點名稱";
            });
    }
</script>

</body>
</html>
