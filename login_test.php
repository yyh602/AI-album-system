<?php
// 超詳細的登入測試頁面
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

echo "<h1>登入詳細測試</h1>";

// 顯示所有 POST 資料
echo "<h2>POST 資料：</h2>";
echo "<pre>";
var_dump($_POST);
echo "</pre>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['Username'] ?? $_POST['username'] ?? '';
    $password = $_POST['Password'] ?? $_POST['password'] ?? '';
    
    echo "<h2>接收到的資料：</h2>";
    echo "用戶名長度: " . strlen($username) . "<br>";
    echo "用戶名內容: '" . htmlspecialchars($username) . "'<br>";
    echo "密碼長度: " . strlen($password) . "<br>";
    echo "密碼內容: '" . htmlspecialchars($password) . "'<br>";
    
    // 測試資料庫連線
    try {
        require_once("DB_open.php");
        
        if ($link instanceof mysqli && $link !== null) {
            echo "<h3>✅ 資料庫連線成功</h3>";
            
            // 先查詢所有用戶看看資料庫中實際的內容
            $all_users_sql = "SELECT id, username, password, name FROM user";
            $all_result = $link->query($all_users_sql);
            
            echo "<h3>資料庫中所有用戶：</h3>";
            echo "<table border='1'>";
            echo "<tr><th>ID</th><th>用戶名</th><th>密碼</th><th>姓名</th></tr>";
            
            if ($all_result) {
                while ($row = $all_result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . $row['id'] . "</td>";
                    echo "<td>'" . htmlspecialchars($row['username']) . "'</td>";
                    echo "<td>'" . htmlspecialchars($row['password']) . "'</td>";
                    echo "<td>" . htmlspecialchars($row['name']) . "</td>";
                    echo "</tr>";
                }
            }
            echo "</table>";
            
            // 測試精確匹配
            if ($username && $password) {
                echo "<h3>測試精確匹配：</h3>";
                
                // 方法1: 直接 SQL 查詢
                $sql1 = "SELECT * FROM user WHERE username = ? AND password = ?";
                $stmt1 = mysqli_prepare($link, $sql1);
                mysqli_stmt_bind_param($stmt1, "ss", $username, $password);
                mysqli_stmt_execute($stmt1);
                $result1 = mysqli_stmt_get_result($stmt1);
                
                echo "查詢: username='$username' AND password='$password'<br>";
                echo "結果筆數: " . mysqli_num_rows($result1) . "<br>";
                
                if (mysqli_num_rows($result1) > 0) {
                    echo "✅ <strong>匹配成功！</strong><br>";
                    $user_data = mysqli_fetch_assoc($result1);
                    echo "匹配的用戶: " . json_encode($user_data) . "<br>";
                } else {
                    echo "❌ 沒有匹配的用戶<br>";
                    
                    // 分別測試用戶名和密碼
                    echo "<h4>分別測試：</h4>";
                    
                    $username_test = "SELECT * FROM user WHERE username = ?";
                    $stmt_u = mysqli_prepare($link, $username_test);
                    mysqli_stmt_bind_param($stmt_u, "s", $username);
                    mysqli_stmt_execute($stmt_u);
                    $result_u = mysqli_stmt_get_result($stmt_u);
                    
                    if (mysqli_num_rows($result_u) > 0) {
                        echo "✅ 用戶名 '$username' 存在<br>";
                        $user_data = mysqli_fetch_assoc($result_u);
                        echo "實際密碼: '" . $user_data['password'] . "'<br>";
                        echo "輸入密碼: '" . $password . "'<br>";
                        echo "密碼匹配: " . ($user_data['password'] === $password ? "✅ 是" : "❌ 否") . "<br>";
                        
                        // 檢查是否有不可見字符
                        echo "實際密碼長度: " . strlen($user_data['password']) . "<br>";
                        echo "輸入密碼長度: " . strlen($password) . "<br>";
                        echo "實際密碼 hex: " . bin2hex($user_data['password']) . "<br>";
                        echo "輸入密碼 hex: " . bin2hex($password) . "<br>";
                    } else {
                        echo "❌ 用戶名 '$username' 不存在<br>";
                    }
                }
                
                mysqli_stmt_close($stmt1);
            }
        } else {
            echo "❌ 資料庫連線失敗";
        }
    } catch (Exception $e) {
        echo "❌ 錯誤: " . $e->getMessage();
    }
}
?>

<h2>測試表單：</h2>
<form method="POST">
    <label>用戶名:</label><br>
    <input type="text" name="Username" value="admin" style="width:200px;"><br><br>
    
    <label>密碼:</label><br>
    <input type="text" name="Password" value="admin123" style="width:200px;"><br><br>
    
    <input type="submit" value="測試登入">
</form>

<h3>快速測試連結：</h3>
<a href="?test=admin">測試 admin 帳戶</a><br>
<a href="login.php">返回原始登入頁</a>

<?php
// 快速測試
if (isset($_GET['test']) && $_GET['test'] === 'admin') {
    $_POST['Username'] = 'admin';
    $_POST['Password'] = 'admin123';
    $_SERVER['REQUEST_METHOD'] = 'POST';
    echo "<script>document.forms[0].Username.value='admin'; document.forms[0].Password.value='admin123';</script>";
}
?>
