<?php
// 測試登入頁面 - 顯示詳細錯誤信息
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

echo "<h1>登入測試頁面</h1>";

// 顯示 PHP 版本和擴展信息
echo "<h2>PHP 環境：</h2>";
echo "PHP 版本: " . phpversion() . "<br>";
echo "mysqli 擴展: " . (extension_loaded('mysqli') ? '✅ 已載入' : '❌ 未載入') . "<br>";
echo "MYSQLI_CLIENT_SSL 常數: " . (defined('MYSQLI_CLIENT_SSL') ? '✅ 支援' : '❌ 不支援') . "<br>";
echo "MYSQLI_OPT_SSL_VERIFY_SERVER_CERT 常數: " . (defined('MYSQLI_OPT_SSL_VERIFY_SERVER_CERT') ? '✅ 支援' : '❌ 不支援') . "<br>";

// 測試環境變數
echo "<h2>環境變數：</h2>";
echo "DB_HOST: " . ($_ENV['DB_HOST'] ?? '未設定') . "<br>";
echo "DB_NAME: " . ($_ENV['DB_NAME'] ?? '未設定') . "<br>";
echo "DB_USER: " . ($_ENV['DB_USER'] ?? '未設定') . "<br>";
echo "DB_TYPE: " . ($_ENV['DB_TYPE'] ?? '未設定') . "<br>";

// 測試資料庫連線
echo "<h2>資料庫連線測試：</h2>";
try {
    require_once("DB_open.php");
    
    if ($link instanceof mysqli && $link !== null) {
        echo "✅ MySQL 連線成功！<br>";
        
        // 測試用戶表查詢
        $test_sql = "SELECT COUNT(*) as count FROM user";
        $result = $link->query($test_sql);
        if ($result) {
            $row = $result->fetch_assoc();
            echo "✅ 用戶表查詢成功，共有 " . $row['count'] . " 個用戶<br>";
        } else {
            echo "❌ 用戶表查詢失敗: " . $link->error . "<br>";
        }
        
        // 測試特定用戶
        $username = 'admin';
        $password = 'admin123';
        $sql = "SELECT * FROM user WHERE password = ? AND username = ?";
        $stmt = mysqli_prepare($link, $sql);
        
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ss", $password, $username);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if ($result && mysqli_num_rows($result) > 0) {
                echo "✅ 測試用戶 (admin/admin123) 驗證成功！<br>";
                $user_data = mysqli_fetch_assoc($result);
                echo "用戶資料: " . json_encode($user_data) . "<br>";
            } else {
                echo "❌ 測試用戶驗證失敗<br>";
            }
            mysqli_stmt_close($stmt);
        } else {
            echo "❌ SQL prepare 失敗: " . mysqli_error($link) . "<br>";
        }
    } else {
        echo "❌ 資料庫連線失敗！<br>";
        var_dump($link);
    }
} catch (Exception $e) {
    echo "❌ 錯誤: " . $e->getMessage() . "<br>";
}

// 處理登入表單
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    echo "<h2>登入嘗試：</h2>";
    echo "用戶名: $username<br>";
    echo "密碼: $password<br>";
    
    if ($username && $password && isset($link) && $link instanceof mysqli) {
        $sql = "SELECT * FROM user WHERE password = ? AND username = ?";
        $stmt = mysqli_prepare($link, $sql);
        
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ss", $password, $username);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if ($result && mysqli_num_rows($result) > 0) {
                echo "✅ 登入成功！<br>";
                $_SESSION["login_session"] = true;
                $_SESSION["username"] = $username;
                echo '<a href="welcome.php">前往 welcome.php</a><br>';
            } else {
                echo "❌ 用戶名或密碼錯誤<br>";
            }
            mysqli_stmt_close($stmt);
        }
    }
}
?>

<h2>測試登入表單：</h2>
<form method="POST">
    用戶名: <input type="text" name="username" value="admin"><br><br>
    密碼: <input type="password" name="password" value="admin123"><br><br>
    <input type="submit" value="測試登入">
</form>

<h2>快速連結：</h2>
<a href="login.php">原始 login.php</a><br>
<a href="welcome.php">welcome.php</a><br>
<a href="test_mysql_connection.php">MySQL 連線測試</a>