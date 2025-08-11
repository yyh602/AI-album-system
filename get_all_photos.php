<?php
// 檢查 session 狀態，避免重複啟動
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION["username"])) {
    header("Location: login.php");
    exit();
}

require_once("DB_open.php");
require_once("DB_helper.php");

$username = $_SESSION["username"];

// 獲取用戶的所有照片，不按相簿分組
if ($link instanceof mysqli) {
    $sql = "SELECT p.id, p.filename, p.path, p.latitude, p.longitude, p.datetime, a.name as album_name
            FROM photos p
            INNER JOIN albums a ON p.album_id = a.id
            WHERE a.username = ?
            ORDER BY p.datetime DESC";
    $stmt = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $photos = mysqli_fetch_all($result, MYSQLI_ASSOC);
} else {
    // 如果是 PDOWrapper，使用 PDO 方式查詢
    $sql = "SELECT p.id, p.filename, p.path, p.latitude, p.longitude, p.datetime, a.name as album_name
            FROM photos p
            INNER JOIN albums a ON p.album_id = a.id
            WHERE a.username = ?
            ORDER BY p.datetime DESC";
    $stmt = $link->prepare($sql);
    $stmt->execute([$username]);
    $photos = $stmt->fetchAll('ASSOC');
}

echo json_encode([
    "status" => "success",
    "photos" => $photos
]);

require_once("DB_close.php");
?>
