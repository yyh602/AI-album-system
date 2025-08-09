<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>AI智慧相簿與旅遊日誌應用系統</title>
    <?php
require_once("DB_open.php");    //引入資料庫連結設定檔

// 查詢所有記錄
$sql = "SELECT * FROM photo_records ORDER BY upload_time DESC";
$result = mysqli_query($link, $sql);

if (!$result) {
    error_log("查詢失敗：" . mysqli_error($link));
    echo "<p>資料庫查詢失敗</p>";
    $result = null;
}

// 顯示所有記錄
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<div class='record'>";
        echo "<p>檔案名稱：" . htmlspecialchars($row['filename']) . "</p>";
        echo "<p>上傳時間：" . htmlspecialchars($row['upload_time']) . "</p>";
        echo "</div>";
    }
}

require_once("DB_close.php");   //引入資料庫關閉設定檔
?>

    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
        }

        h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 40px;
        }

        .button-container {
            display: flex;
            gap: 30px;
        }

        .btn {
            font-weight:bold;
            padding: 15px 40px;
            font-size: 18px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            color: white;
            background: gray;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s ease, background 0.3s ease;
        }

        .btn:hover {
            transform: scale(1.05);
            background: linear-gradient(45deg, #0056b3,rgb(0, 100, 167));
        }
    </style>
</head>
<body>

    <h1>AI智慧相簿與旅遊日誌應用系統</h1>

    <div class="button-container">
        <a href="add.php" class="btn">註冊</a>
        <a href="login.php" class="btn">登入</a>
    </div>

</body>
</html>
