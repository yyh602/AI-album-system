<?php
session_start();
$username = $_SESSION['username'] ?? 'guest';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>建立相簿</title>
</head>
<body>
    <h2>建立新相簿</h2>
    <form action="save_album.php" method="post">
        <label>相簿名稱：
            <input type="text" name="album_name" required>
        </label>
        <br><br>
        <input type="submit" value="建立相簿">
    </form>
</body>
</html>
