<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION["username"])) {
    echo json_encode(['status' => 'error', 'message' => '未登入']);
    exit();
}

require_once("DB_open.php");

if (!$link instanceof mysqli) {
    echo json_encode(['status' => 'error', 'message' => '資料庫連線失敗']);
    exit();
}

$username = $_SESSION["username"];
$user_id = null;

$sql_user_id = "SELECT id FROM user WHERE username = ?";
$stmt_user_id = mysqli_prepare($link, $sql_user_id);
mysqli_stmt_bind_param($stmt_user_id, "s", $username);
mysqli_stmt_execute($stmt_user_id);
mysqli_stmt_bind_result($stmt_user_id, $user_id);
mysqli_stmt_fetch($stmt_user_id);
mysqli_stmt_close($stmt_user_id);

if (!$user_id) {
    echo json_encode(['status' => 'error', 'message' => '找不到使用者']);
    exit();
}

// 處理「所有相簿」請求
if (isset($_GET['all_albums'])) {
    $sql = "SELECT id, name, cover_photo FROM album WHERE user_id = ? ORDER BY created_at DESC";
    $stmt = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $albums = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $albums[] = $row;
    }
    mysqli_stmt_close($stmt);
    echo json_encode(['status' => 'success', 'albums' => $albums]);
}
// 處理「依月份分類」請求
else if (isset($_GET['group_photos_by_month'])) {
    $sql = "SELECT path, datetime FROM photo WHERE user_id = ? ORDER BY datetime DESC";
    $stmt = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $photos_by_month = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $month = date('Y年m月', strtotime($row['datetime']));
        if (!isset($photos_by_month[$month])) {
            $photos_by_month[$month] = [];
        }
        $photos_by_month[$month][] = $row;
    }
    mysqli_stmt_close($stmt);
    echo json_encode(['status' => 'success', 'photos_by_month' => $photos_by_month]);
}
// 處理「依地點分類」請求
else if (isset($_GET['group_photos_by_location'])) {
    $sql = "SELECT path, place, COUNT(*) AS photo_count FROM photo WHERE user_id = ? AND place IS NOT NULL AND place != '' GROUP BY place ORDER BY photo_count DESC";
    $stmt = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $photos_by_location = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $photos_by_location[$row['place']] = ['path' => $row['path'], 'count' => $row['photo_count']];
    }
    mysqli_stmt_close($stmt);
    echo json_encode(['status' => 'success', 'photos_by_location' => $photos_by_location]);
}
// 處理「取得指定月份照片」請求
else if (isset($_GET['month'])) {
    $month = $_GET['month'] . '%';
    $sql = "SELECT id, path, datetime FROM photo WHERE user_id = ? AND datetime LIKE ? ORDER BY datetime ASC";
    $stmt = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($stmt, "is", $user_id, $month);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $photos = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $photos[] = $row;
    }
    mysqli_stmt_close($stmt);
    echo json_encode(['status' => 'success', 'photos' => $photos]);
}
// 處理「取得指定地點照片」請求
else if (isset($_GET['location'])) {
    $location = urldecode($_GET['location']);
    $sql = "SELECT id, path, datetime FROM photo WHERE user_id = ? AND place = ? ORDER BY datetime ASC";
    $stmt = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($stmt, "is", $user_id, $location);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $photos = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $photos[] = $row;
    }
    mysqli_stmt_close($stmt);
    echo json_encode(['status' => 'success', 'photos' => $photos]);
}
else {
    echo json_encode(['status' => 'error', 'message' => '無效的請求']);
}

require_once("DB_close.php");
?>