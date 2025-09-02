<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

if (!isset($_SESSION["username"])) {
    header("Location: login.php");
    exit();
}

require_once("DB_open.php");

$username = $_SESSION["username"];
$name = $username;
$grouped_albums = [];

if ($link instanceof mysqli) {
    $sql_user = "SELECT name FROM user WHERE username = ?";
    $stmt_user = mysqli_prepare($link, $sql_user);
    mysqli_stmt_bind_param($stmt_user, "s", $username);
    mysqli_stmt_execute($stmt_user);
    mysqli_stmt_bind_result($stmt_user, $result_name);

    if (mysqli_stmt_fetch($stmt_user)) {
        $name = $result_name;
    }
    mysqli_stmt_close($stmt_user);

    $sql_photos = "SELECT path, datetime FROM photos WHERE username = ? ORDER BY datetime DESC";
    $stmt_photos = mysqli_prepare($link, $sql_photos);
    mysqli_stmt_bind_param($stmt_photos, "s", $username);
    mysqli_stmt_execute($stmt_photos);
    $result_photos = mysqli_stmt_get_result($stmt_photos);

    if ($result_photos) {
        while ($row = mysqli_fetch_assoc($result_photos)) {
            $date = new DateTime($row['datetime']);
            $year = $date->format('Y');
            $month = $date->format('m');
            
            if (!isset($grouped_albums[$year])) {
                $grouped_albums[$year] = [];
            }
            $month_key = $year . '/' . $month;
            if (!isset($grouped_albums[$year][$month_key])) {
                $grouped_albums[$year][$month_key] = [
                    'preview_images' => [],
                    'title' => $date->format('n') . '月',
                    'year' => $year,
                    'month' => $month
                ];
            }
            $grouped_albums[$year][$month_key]['preview_images'][] = $row['path'];
        }
        mysqli_stmt_close($stmt_photos);
    } else {
        error_log("Photo query failed: " . mysqli_error($link));
    }
} else {
    error_log("Database connection failed or is of the incorrect type.");
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>依時間分類 - AI智慧相簿管理</title>
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
    grid-template-columns: repeat(5, 1fr);
    gap: 18px;
    background: #f8f9fa;
    border-radius: 0;
    max-height: none;
    overflow-y: visible;
    width: 100vw;
    margin-left: calc(-1 * (100vw - 100%) / 2);
    margin-right: calc(-1 * (100vw - 100%) / 2);
    margin-top: 0;
    margin-bottom: 0;
    padding: 0 25px;
    box-sizing: border-box;
    }

    .album-card-preview {
        display: flex;
        flex-direction: column;
        align-items: center;
        width: 100%;
        text-decoration: none;
        font-weight: bold;

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

    .category-title {
        text-align: left;
        color: #333;
        font-weight: bold;
        margin-left: 40px;
        margin-top: 40px;
        margin-bottom: 24px;
        font-size: 1.8rem;
    }

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
        <div class="d-flex justify-content-between align-items-center mb-3">
            <a href="welcome.php" class="btn btn-outline-secondary rounded-circle"
               title="返回首頁"
               style="width: 42px; height: 42px;">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div class="d-flex gap-2 mx-auto">
              <a href="album.php" class="btn btn-light">我的相簿</a>
              <a href="album_by_time.php" class="btn btn-dark">依時間分類</a>
              <a href="album_by_location.php" class="btn btn-light">依地點分類</a>
              <a href="face_test/album_by_person_face_test.php" class="btn btn-light">依人物分類</a>
            </div>
        </div>
    </div>

    <div class="container mt-4">
        <div class="tab-content" id="albumTabContent">
          <div class="tab-pane fade show active" id="by-time" role="tabpanel" aria-labelledby="by-time-tab">
            <?php
            if (!empty($grouped_albums)) {
                foreach ($grouped_albums as $year => $months) {
                    echo '<h2 class="category-title">' . htmlspecialchars($year) . '年</h2>';
                    echo '<div class="album-section-content">';
                    
                    foreach ($months as $month_key => $album) {
                        $photo_count = count($album['preview_images']);
                        
                        // 修改連結為動態傳輸年月
                        echo '<a href="view_album_time.php?year=' . urlencode($album['year']) . '&month=' . urlencode($album['month']) . '" class="album-card-preview">';
                        
                        // 統一顯示該相簿的第一張照片
                        echo '<div class="album-card-img-wrap">';
                        if ($photo_count > 0) {
                            echo '<img src="' . htmlspecialchars($album['preview_images'][0]) . '" alt="相簿預覽">';
                        }
                        echo '</div>';
                        
                        echo '<h3 class="album-card-title">' . htmlspecialchars($album['title']) . '</h3>';
                        echo '</a>';
                    }
                    echo '</div>';
                }
            } else {
                echo '<p class="text-center">目前沒有可供顯示的照片。</p>';
            }
            ?>
          </div>
        </div>
    </div>
    
    <?php
    require_once("DB_close.php");
    ?>
</body>
</html>