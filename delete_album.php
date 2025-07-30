<?php
session_start();
header("Content-Type: application/json");
error_reporting(E_ALL);
ini_set("display_errors", 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

if (!isset($_SESSION["username"])) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "未登入或 session 遺失"]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "不支援的請求方法"]);
    exit();
}

$albumId = $_POST['album_id'] ?? 0;
$username = $_SESSION["username"];

if ($albumId <= 0) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "無效的相簿 ID"]);
    exit();
}

require_once("DB_open.php");

// 驗證相簿所有權
$sql = "SELECT id, name FROM albums WHERE id = ? AND username = ?";
$stmt = mysqli_prepare($link, $sql);
mysqli_stmt_bind_param($stmt, "is", $albumId, $username);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$album = mysqli_fetch_assoc($result);

if (!$album) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "無權刪除此相簿或相簿不存在"]);
    exit();
}

// 開始事務
mysqli_begin_transaction($link);

try {
    // 獲取相簿中所有照片的路徑以便刪除檔案
    $sql = "SELECT path FROM photos WHERE album_id = ?";
    $stmt = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($stmt, "i", $albumId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $photos_to_delete = mysqli_fetch_all($result, MYSQLI_ASSOC);

    // 刪除相簿中的所有照片資料庫記錄
    $sql = "DELETE FROM photos WHERE album_id = ?";
    $stmt = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($stmt, "i", $albumId);
    mysqli_stmt_execute($stmt);

    // 刪除相簿資料庫記錄
    $sql = "DELETE FROM albums WHERE id = ?";
    $stmt = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($stmt, "i", $albumId);
    mysqli_stmt_execute($stmt);

    // 提交事務
    mysqli_commit($link);

    // 刪除實際的檔案和目錄 (在提交資料庫事務後執行，避免資料庫刪成功但檔案刪失敗)
    foreach ($photos_to_delete as $photo) {
        if (file_exists($photo['path'])) {
            unlink($photo['path']);
        }
    }
    // 嘗試刪除相簿目錄 (只有在為空時才會成功)
    $album_dir = 'uploads/' . $username . '/' . $albumId;
     if (is_dir($album_dir)) {
         // 檢查目錄是否為空
         if (count(scandir($album_dir)) == 2) { // . 和 ..
             rmdir($album_dir);
         }
     }
    
    // 嘗試刪除用戶/年/月/日目錄 (如果為空)
    $date_dir = 'uploads/' . $username . '/' . date('Y/m/d', strtotime($album['created_at'] ?? 'now'));
    if (is_dir($date_dir)) {
         if (count(scandir($date_dir)) == 2) {
             rmdir($date_dir);
         }
     }
     $month_dir = dirname($date_dir);
     if (is_dir($month_dir)) {
         if (count(scandir($month_dir)) == 2) {
             rmdir($month_dir);
         }
     }
     $year_dir = dirname($month_dir);
     if (is_dir($year_dir)) {
         if (count(scandir($year_dir)) == 2) {
             rmdir($year_dir);
         }
     }
     $user_dir = dirname($year_dir);
     if (is_dir($user_dir)) {
         if (count(scandir($user_dir)) == 2) {
             rmdir($user_dir);
         }
     }

    echo json_encode(["status" => "success", "message" => "相簿已刪除"]);

} catch (Exception $e) {
    // 回滾事務
    mysqli_rollback($link);
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "刪除失敗：" . $e->getMessage()]);
}

require_once("DB_close.php");
?> 