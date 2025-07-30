<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION["username"])) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "未登入"]);
    exit();
}

require_once("DB_open.php");

$username = $_SESSION["username"];
$sql = "SELECT filename, datetime, latitude, longitude FROM uploads WHERE username = ? ORDER BY uploaded_at DESC";
$stmt = mysqli_prepare($link, $sql);

if (!$stmt) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "資料庫錯誤"]);
    exit();
}

mysqli_stmt_bind_param($stmt, "s", $username);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$photos = [];
while ($row = mysqli_fetch_assoc($result)) {
    $photos[] = [
        'filename' => $row['filename'],
        'datetime' => $row['datetime'],
        'latitude' => $row['latitude'],
        'longitude' => $row['longitude']
    ];
}

mysqli_stmt_close($stmt);
require_once("DB_close.php");

echo json_encode([
    "status" => "ok",
    "photos" => $photos
]);
?> 