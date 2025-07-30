<?php
session_start();

if (!isset($_SESSION["username"])) {
    header("Location: login.php");
    exit();
}

require_once("DB_open.php");

$albumId = $_GET["album_id"] ?? 0;
$username = $_SESSION["username"];

// 新增：支援 all_albums=1，回傳所有相簿
if (isset($_GET['all_albums']) && $_GET['all_albums'] == 1) {
    $sql = "SELECT id, name, cover_photo FROM albums WHERE username = ? ORDER BY created_at DESC";
    $stmt = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $albums = mysqli_fetch_all($result, MYSQLI_ASSOC);
    echo json_encode([
        "status" => "success",
        "albums" => $albums
    ]);
    require_once("DB_close.php");
    exit();
}

// 新增：支援 group_by_month=1，依照片最早拍攝日期分組回傳相簿
if (isset($_GET['group_by_month']) && $_GET['group_by_month'] == 1) {
    $sql = "SELECT a.id, a.name, a.cover_photo, DATE_FORMAT(MIN(p.datetime), '%Y年%m月') as month
            FROM albums a
            LEFT JOIN photos p ON a.id = p.album_id
            WHERE a.username = ?
            GROUP BY a.id
            ORDER BY month DESC, a.created_at DESC";
    $stmt = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $albums_by_month = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $month = $row['month'] ?: '未知日期';
        unset($row['month']);
        $albums_by_month[$month][] = $row;
    }
    echo json_encode([
        "status" => "success",
        "albums_by_month" => $albums_by_month
    ]);
    require_once("DB_close.php");
    exit();
}

// 新增：支援 group_photos_by_month=1，依照片拍攝月份分組且去重複
if (isset($_GET['group_photos_by_month']) && $_GET['group_photos_by_month'] == 1) {
    $sql = "SELECT filename, path, datetime
            FROM photos
            WHERE datetime IS NOT NULL AND datetime != ''
              AND album_id IN (SELECT id FROM albums WHERE username = ?)
            ORDER BY datetime DESC";
    $stmt = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $photos_by_month = [];
    $seen = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $month = date('Y年m月', strtotime($row['datetime']));
        $photo_key = md5($row['path']); // 以路徑為唯一標識去重複
        if (isset($seen[$photo_key])) continue;
        $seen[$photo_key] = true;
        $photos_by_month[$month][] = $row;
    }
    echo json_encode([
        "status" => "success",
        "photos_by_month" => $photos_by_month
    ]);
    require_once("DB_close.php");
    exit();
}

// 新增：支援 month=YYYY-MM，回傳該月份所有不重複照片
if (isset($_GET['month'])) {
    $month = $_GET['month']; // 格式: YYYY-MM
    $sql = "SELECT filename, path, datetime
            FROM photos
            WHERE datetime IS NOT NULL AND datetime != ''
              AND album_id IN (SELECT id FROM albums WHERE username = ?)
              AND DATE_FORMAT(datetime, '%Y-%m') = ?
            ORDER BY datetime DESC";
    $stmt = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($stmt, "ss", $username, $month);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $photos = [];
    $seen = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $photo_key = md5($row['path']);
        if (isset($seen[$photo_key])) continue;
        $seen[$photo_key] = true;
        $photos[] = $row;
    }
    echo json_encode([
        "status" => "success",
        "photos" => $photos
    ]);
    require_once("DB_close.php");
    exit();
}

// 驗證相簿所有權
$sql = "SELECT id FROM albums WHERE id = ? AND username = ?";
$stmt = mysqli_prepare($link, $sql);
mysqli_stmt_bind_param($stmt, "is", $albumId, $username);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 0) {
    echo json_encode(["status" => "error", "message" => "無權訪問此相簿"]);
    exit();
}

// 獲取相簿中的照片
$sql = "SELECT id, filename, path, latitude, longitude, datetime 
        FROM photos 
        WHERE album_id = ? 
        ORDER BY datetime DESC";
$stmt = mysqli_prepare($link, $sql);
mysqli_stmt_bind_param($stmt, "i", $albumId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$photos = mysqli_fetch_all($result, MYSQLI_ASSOC);

echo json_encode([
    "status" => "success",
    "photos" => $photos
]);

require_once("DB_close.php");
?> 