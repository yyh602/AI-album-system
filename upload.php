<?php
session_start();
header("Content-Type: application/json");

// 檢查是否已登入
if (!isset($_SESSION["username"])) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "未登入"]);
    exit();
}

// 引入資料庫連線
require_once("DB_open.php");

// === JSON 資料接收與寫入處理（如從 JS 發送 fetch POST）===
if (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') === 0) {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    // 驗證必要欄位
    if (!isset($data['filename']) || !isset($data['datetime']) || !isset($data['lat']) || !isset($data['lon'])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "缺少必要欄位"]);
        exit();
    }

    $sql = "INSERT INTO uploads (username, filename, datetime, latitude, longitude) VALUES (?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($link, $sql);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "資料庫錯誤: " . mysqli_error($link)]);
        exit();
    }

    mysqli_stmt_bind_param($stmt, "sssdd", $_SESSION["username"], $data['filename'], $data['datetime'], $data['lat'], $data['lon']);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    echo json_encode(["status" => "ok"]);
    require_once("DB_close.php");
    exit();
}

// === 表單方式的檔案上傳處理 ===
if (isset($_FILES['photo'])) {
    $upload_dir = 'uploads/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $file_name = $_FILES['photo']['name'];
    $upload_path = $upload_dir . basename($file_name);

    if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_path)) {
        // 儲存檔案資訊到資料表
        $stmt = mysqli_prepare($link, "INSERT INTO photo_records (filename, upload_time) VALUES (?, NOW())");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $file_name);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }

        echo json_encode(['status' => 'success', 'message' => '檔案上傳成功', 'filename' => $file_name]);
    } else {
        echo json_encode(['status' => 'error', 'message' => '檔案上傳失敗']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => '沒有收到資料']);
}

require_once("DB_close.php");
?>
