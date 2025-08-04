<?php
session_start();
require_once("DB_open.php");
require_once("DB_helper.php");
header('Content-Type: application/json');

$username = $_SESSION['username'] ?? '';
$album_id = $_POST['album_id'] ?? '';
$album_name = $_POST['album_name'] ?? '';
$content = $_POST['content'] ?? '';

if (!$username || !$album_id || !$content) {
    echo json_encode(['status' => 'error', 'message' => '缺少必要欄位']);
    exit;
}

if ($link instanceof PDO) {
    $stmt = $link->prepare("INSERT INTO travel_diary (username, album_id, album_name, content, created_at) VALUES (?, ?, ?, ?, NOW())");
    if ($stmt->execute([$username, $album_id, $album_name, $content])) {
        echo json_encode(['status' => 'success']);
    } else {
        $error = $stmt->errorInfo();
        echo json_encode(['status' => 'error', 'message' => $error[2] ?? '未知錯誤']);
    }
} else {
    $stmt = mysqli_prepare($link, "INSERT INTO travel_diary (username, album_id, album_name, content, created_at) VALUES (?, ?, ?, ?, NOW())");
    mysqli_stmt_bind_param($stmt, "siss", $username, $album_id, $album_name, $content);
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => mysqli_stmt_error($stmt)]);
    }
    mysqli_stmt_close($stmt);
}

require_once("DB_close.php");