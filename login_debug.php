<?php
session_start();
header('Content-Type: text/html; charset=utf-8');

echo "<h1>登入除錯</h1>";

// 檢查 POST 資料
echo "<h2>POST 資料：</h2>";
echo "<pre>";
print_r($_POST);
echo "</pre>";

if(isset($_POST["Username"]) && isset($_POST["Password"])) {
    $username = $_POST["Username"];
    $password = $_POST["Password"];
    
    echo "<h2>接收到的資料：</h2>";
    echo "帳號: " . htmlspecialchars($username) . "<br>";
    echo "密碼: " . htmlspecialchars($password) . "<br>";
    
    // 測試資料庫連接
    require_once("DB_open.php");
    
    echo "<h2>資料庫連接測試：</h2>";
    if ($link) {
        echo "✅ 資料庫連接成功<br>";
        
        // 測試查詢
        if ($link instanceof PDO) {
            $sql = "SELECT * FROM \"user\" WHERE username = ?";
            $stmt = $link->prepare($sql);
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo "<h2>用戶查詢結果：</h2>";
            echo "<pre>";
            print_r($user);
            echo "</pre>";
            
            if ($user) {
                echo "<h2>密碼比較：</h2>";
                echo "資料庫中的密碼: " . htmlspecialchars($user['password']) . "<br>";
                echo "輸入的密碼: " . htmlspecialchars($password) . "<br>";
                echo "密碼是否匹配: " . ($user['password'] === $password ? "✅ 是" : "❌ 否") . "<br>";
            } else {
                echo "❌ 找不到用戶<br>";
            }
        } else {
            $sql = "SELECT * FROM \"user\" WHERE username = ?";
            $stmt = mysqli_prepare($link, $sql);
            mysqli_stmt_bind_param($stmt, "s", $username);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $user = mysqli_fetch_assoc($result);
            
            echo "<h2>用戶查詢結果：</h2>";
            echo "<pre>";
            print_r($user);
            echo "</pre>";
            
            if ($user) {
                echo "<h2>密碼比較：</h2>";
                echo "資料庫中的密碼: " . htmlspecialchars($user['password']) . "<br>";
                echo "輸入的密碼: " . htmlspecialchars($password) . "<br>";
                echo "密碼是否匹配: " . ($user['password'] === $password ? "✅ 是" : "❌ 否") . "<br>";
            } else {
                echo "❌ 找不到用戶<br>";
            }
        }
    } else {
        echo "❌ 資料庫連接失敗<br>";
    }
    
    require_once("DB_close.php");
} else {
    echo "<h2>請先提交登入表單</h2>";
    echo '<form method="post">';
    echo '帳號: <input type="text" name="Username" value="1411131016"><br>';
    echo '密碼: <input type="password" name="Password" value="8745"><br>';
    echo '<input type="submit" value="測試登入">';
    echo '</form>';
}
?> 