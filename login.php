<?php
// 檢查 session 狀態，避免重複啟動
if (session_status() == PHP_SESSION_NONE) {
    session_start();    //啟用交談期
}
$username = "";     $password = "";
$login_error = false;

// 取得表單欄位值
if(isset($_POST["Username"]))
    $username = $_POST["Username"];
if(isset($_POST["Password"]))
    $password = $_POST["Password"];

// 檢查是否輸入使用者名稱和密碼
if($username != "" && $password != ""){
    try {
        // 設定連接超時
        set_time_limit(10);
        require_once("DB_open.php");    //引入資料庫連結設定檔
        
        // 統一使用 MySQL 語法 (因為現在環境變數設定為 mysql)
        if ($link instanceof mysqli && $link !== null) {
            // MySQL 查詢 - 使用 prepared statement 避免 SQL 注入
            $sql = "SELECT * FROM user WHERE password = ? AND username = ?";
            $stmt = mysqli_prepare($link, $sql);
            
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "ss", $password, $username);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                
                if ($result && mysqli_num_rows($result) > 0) {
                    // 成功登入
                    $_SESSION["login_session"] = true;
                    $_SESSION["username"] = $username;
                    
                    // 使用絕對 URL 跳轉
                    $welcome_url = "https://" . $_SERVER['HTTP_HOST'] . "/welcome.php";
                    header("Location: " . $welcome_url);
                    exit();
                } else {
                    // 登入失敗
                    $login_error = true;
                    $_SESSION["login_session"] = false;
                }
                mysqli_stmt_close($stmt);
            } else {
                $login_error = true;
                error_log("SQL prepare 失敗: " . mysqli_error($link));
            }
        } else {
            // 資料庫連線失敗
            $login_error = true;
            error_log("資料庫連線失敗: link 物件為空或非 mysqli 實例");
        }
        require_once("DB_close.php");   //引入資料庫關閉設定檔
    } catch (Exception $e) {
        $login_error = true;
        error_log("資料庫連接錯誤: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="UTF-8">
  <title>會員登入 | AI智慧相簿管理系統</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Bootstrap 5 CDN -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background-color: #f8f9fa;
    }
    .navbar {
      background-color: #f3d6c6;
    }
    .login-wrapper {
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 90vh;
    }
    .login-box {
      background-color: white;
      padding: 2rem;
      border-radius: 12px;
      box-shadow: 0 4px 24px rgba(0, 0, 0, 0.1);
      width: 100%;
      max-width: 380px;
    }
    .form-label {
      font-weight: 500;
    }
    .btn-login {
      background-color: #495057;
      color: white;
    }
    .btn-login:hover {
      background-color: #343a40;
    }
    @media (max-width: 576px) {
      .login-box {
        padding: 1rem;
        border-radius: 0;
        box-shadow: none;
        min-height: 100vh;
      }
      .login-wrapper {
        min-height: 100vh;
        padding: 0;
      }
    }
  </style>
</head>
<body>
<!-- 導覽列 -->
<nav class="navbar navbar-expand-lg">
  <div class="container">
    <a class="navbar-brand fw-bold" href="#" style="display: flex; align-items: center;">
      <img src="logo.png" width="30" style="margin-right: 8px;">
      AI智慧相簿管理系統
    </a>
  </div>
</nav>

<!-- 登入表單 -->
<div class="container login-wrapper">
  <div class="login-box">
    <h5 class="text-center mb-4">請登入會員</h5>
    <?php if ($login_error): ?>
      <div class="alert alert-danger py-2 text-center mb-3" role="alert">
        <i class="fa fa-exclamation-circle me-1"></i> 驗證碼或帳號或密碼錯誤！
      </div>
    <?php endif; ?>
    <form action="login.php" method="post">
      <div class="mb-3">
        <label for="Username" class="form-label">帳號</label>
        <input type="text" class="form-control" id="Username" name="Username" maxlength="10" required autofocus value="<?php echo htmlspecialchars($username); ?>">
      </div>
      <div class="mb-3">
        <label for="Password" class="form-label">密碼</label>
        <input type="password" class="form-control" id="Password" name="Password" maxlength="10" required>
      </div>

      <div class="d-grid">
        <button type="submit" class="btn btn-login">登入系統</button>
      </div>
    </form>
  </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
