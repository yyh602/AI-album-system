<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);
?>

<?php
session_start();
if (!isset($_SESSION["username"])) {
    header("Location: login.php");
    exit();
}

require_once("DB_open.php");    //引入資料庫連結設定檔

$username = $_SESSION["username"];

// 查詢使用者的上傳記錄
$sql = "SELECT filename, datetime, latitude, longitude, uploaded_at 
        FROM uploads 
        WHERE username = ? 
        ORDER BY uploaded_at DESC";

$stmt = mysqli_prepare($link, $sql);
if (!$stmt) {
    error_log("查詢準備失敗：" . mysqli_error($link)); return;
}

mysqli_stmt_bind_param($stmt, "s", $username);
if (!mysqli_stmt_execute($stmt)) {
    error_log("查詢執行失敗：" . mysqli_stmt_error($stmt)); return;
}

$result = mysqli_stmt_get_result($stmt);
if (!$result) {
    error_log("獲取結果失敗：" . mysqli_error($link)); return;
}

require_once("DB_close.php");   //引入資料庫關閉設定檔
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>上傳紀錄</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; text-align: center; }
        table { margin: 0 auto; border-collapse: collapse; width: 90%; }
        th, td { border: 1px solid #ccc; padding: 10px; }
        th { background-color: #f0f0f0; }
        a { text-decoration: none; color: #007BFF; display: inline-block; margin-top: 20px; }
    </style>
</head>
<body>
    <h2><?php echo htmlspecialchars($username); ?> 的上傳紀錄</h2>
    <table>
        <thead>
            <tr>
                <th>照片檔名</th>
                <th>拍攝日期</th>
                <th>GPS 座標</th>
                <th>上傳時間</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($row = mysqli_fetch_assoc($result)): ?>
            <tr>
                <td><?php echo htmlspecialchars($row['filename']); ?></td>
                <td><?php echo htmlspecialchars($row['datetime']); ?></td>
                <td><?php echo htmlspecialchars($row['latitude']) . ", " . htmlspecialchars($row['longitude']); ?></td>
                <td><?php echo htmlspecialchars($row['uploaded_at']); ?></td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>

    <a href="welcome.php">⬅️ 返回</a>
</body>
</html>
