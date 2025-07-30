<?php
session_start();
require_once("DB_open.php");
header('Content-Type: application/json');
$username = $_SESSION['username'] ?? '';
$album_id = $_POST['album_id'] ?? '';
$album_name = $_POST['album_name'] ?? '';
$content = $_POST['content'] ?? '';
if (!$username || !$album_id || !$content) {
    echo json_encode(['status' => 'error', 'message' => '缺少必要欄位']);
    exit;
}
$stmt = mysqli_prepare($link, "INSERT INTO travel_diary (username, album_id, album_name, content, created_at) VALUES (?, ?, ?, ?, NOW())");
mysqli_stmt_bind_param($stmt, "siss", $username, $album_id, $album_name, $content);
if (mysqli_stmt_execute($stmt)) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => mysqli_stmt_error($stmt)]);
}
mysqli_stmt_close($stmt);
require_once("DB_close.php");