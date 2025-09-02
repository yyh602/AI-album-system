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
    <title>依地點分類 - AI智慧相簿管理</title>
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
        <div class="d-flex justify-content-between align-items-center mb-3">
            <a href="welcome.php" class="btn btn-outline-secondary rounded-circle"
               title="返回首頁"
               style="width: 42px; height: 42px;">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div class="d-flex gap-2 mx-auto">
              <a href="album.php" class="btn btn-light">我的相簿</a>
              <a href="album_by_time.php" class="btn btn-light">依時間分類</a>
              <a href="album_by_location.php" class="btn btn-dark">依地點分類</a>
              <a href="face_test/album_by_person_face_test.php" class="btn btn-light">依人物分類</a>
            </div>
        </div>
    </div>
    
    <div class="container mt-4">
        <div class="tab-content" id="albumTabContent">
          <div class="tab-pane fade show active" id="by-location" role="tabpanel" aria-labelledby="by-location-tab">
            
            <?php
            // 重新開啟資料庫連線
            require_once("DB_open.php");

            if ($link instanceof mysqli) {
                // 查詢所有不重複的照片地點 (location)，包含 NULL
                $sql = "SELECT DISTINCT location FROM photos WHERE username = ? ORDER BY location IS NULL DESC, location ASC";
                $stmt = mysqli_prepare($link, $sql);
                mysqli_stmt_bind_param($stmt, "s", $username);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);

                // 迴圈處理每一個地點
                while ($location_row = mysqli_fetch_assoc($result)) {
                    $location_name = $location_row['location'];
                    
                    // 根據地點名稱決定顯示標題
                    if (is_null($location_name) || empty($location_name)) {
                        $display_location = '地點不詳';
                        $sql_photos = "SELECT id, image_path FROM photos WHERE username = ? AND (location IS NULL OR location = '') ORDER BY creation_date DESC";
                        $stmt_photos = mysqli_prepare($link, $sql_photos);
                        mysqli_stmt_bind_param($stmt_photos, "s", $username);
                    } else {
                        $display_location = htmlspecialchars($location_name);
                        $sql_photos = "SELECT id, image_path FROM photos WHERE username = ? AND location = ? ORDER BY creation_date DESC";
                        $stmt_photos = mysqli_prepare($link, $sql_photos);
                        mysqli_stmt_bind_param($stmt_photos, "ss", $username, $location_name);
                    }

                    echo '<h2 class="category-title">' . $display_location . '</h2>';
                    echo '<div class="album-section-content">';
                    
                    mysqli_stmt_execute($stmt_photos);
                    $photos_result = mysqli_stmt_get_result($stmt_photos);

                    // 顯示所有照片
                    while ($photo_row = mysqli_fetch_assoc($photos_result)) {
                        echo '<a href="view_photo.php?id=' . htmlspecialchars($photo_row['id']) . '" class="album-card-preview">';
                        echo '    <div class="album-card-img-wrap">';
                        echo '        <img src="' . htmlspecialchars($photo_row['image_path']) . '" alt="照片">';
                        echo '    </div>';
                        echo '</a>';
                    }

                    mysqli_stmt_close($stmt_photos);
                    echo '</div>';
                }

                mysqli_stmt_close($stmt);
            } else {
                error_log("資料庫連線失敗或類型不正確");
            }

            require_once("DB_close.php");
            ?>
          </div>
        </div>
    </div>
</body>
</html>