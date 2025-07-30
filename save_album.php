<?php
session_start();
require_once("DB_open.php");
mysqli_set_charset($link, "utf8mb4");
header('Content-Type: application/json');

error_log("FILES array: " . print_r($_FILES, true));
error_log("POST array: " . print_r($_POST, true));

$username = $_SESSION['username'] ?? 'guest';
$albumName = trim($_POST['albumName'] ?? '');

if ($albumName === '') {
    echo json_encode(["status" => "error", "message" => "相簿名稱不可為空"]);
    exit();
}
if (empty($_FILES)) {
    echo json_encode(["status" => "error", "message" => "請選擇要上傳的照片"]);
    exit();
}

$uploadedPhotoIds = [];
$uploadedPhotoPaths = [];
$coverPhoto = null;

foreach ($_FILES as $key => $file) {
    if ($file['error'] === UPLOAD_ERR_OK) {
        $ch = curl_init();
        $cfile = new CURLFile($file['tmp_name'], $file['type'], $file['name']);
        $postData = [
            'username' => $username,
            'photo1' => $cfile
        ];
        curl_setopt($ch, CURLOPT_URL, 'http://localhost/uploads.php'); // 路徑依實際情況調整
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $response = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($response, true);
        if ($result && isset($result['status']) && $result['status'] === 'success') {
            $uploadedPhotoIds[] = $result['id'];
            $uploadedPhotoPaths[] = $result['filename'];
            if (!$coverPhoto) $coverPhoto = $result['filename'];
        }
    }
}

if (count($uploadedPhotoIds) === 0) {
    echo json_encode(["status" => "error", "message" => "所有圖片上傳失敗"]);
    exit();
}

// 建立相簿
$stmt = mysqli_prepare($link, "INSERT INTO albums (name, cover_photo, username, created_at) VALUES (?, ?, ?, NOW())");
mysqli_stmt_bind_param($stmt, "sss", $albumName, $coverPhoto, $username);
mysqli_stmt_execute($stmt);
$albumId = mysqli_insert_id($link);
mysqli_stmt_close($stmt);

// 更新剛剛上傳的圖片 album_id
foreach ($uploadedPhotoIds as $photoId) {
    // 更新 uploads
    $stmt = mysqli_prepare($link, "UPDATE uploads SET album_id = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $albumId, $photoId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    // 同步更新 photos
    $stmt2 = mysqli_prepare($link, "UPDATE photos SET album_id = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt2, "ii", $albumId, $photoId);
    mysqli_stmt_execute($stmt2);
    mysqli_stmt_close($stmt2);
}

echo json_encode([
    "status" => "success",
    "message" => "相簿「{$albumName}」已建立，共上傳 " . count($uploadedPhotoIds) . " 張圖片"
]);
require_once("DB_close.php");
exit();
?>
