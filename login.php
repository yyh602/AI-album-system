<?php
session_start();

// 初始化變數
$username = "";
$password = "";
$captcha_input = "";
$login_error = false;

// 取得表單欄位值
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST["Username"]))
        $username = trim($_POST["Username"]);

    if (isset($_POST["Password"]))
        $password = $_POST["Password"];

    if (isset($_POST["Captcha"]))
        $captcha_input = strtoupper(trim($_POST["Captcha"]));

    // 驗證碼比對
    if (!isset($_SESSION["captcha"]) || $captcha_input !== $_SESSION["captcha"]) {
        $login_error = true;
    } else {
        // 檢查帳號與密碼
        if ($username !== "" && $password !== "") {
            require_once("DB_open.php");

            // 使用 prepared statements 避免 SQL Injection
            $stmt = mysqli_prepare($link, "SELECT username, password FROM user WHERE username = ?");
            mysqli_stmt_bind_param($stmt, "s", $username);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);

            if (mysqli_stmt_num_rows($stmt) > 0) {
                mysqli_stmt_bind_result($stmt, $db_username, $db_hashed_password);
                mysqli_stmt_fetch($stmt);

                // 驗證密碼
                if (password_verify($password, $db_hashed_password)) {
                    $_SESSION["login_session"] = true;
                    $_SESSION["username"] = $db_username;
                    header("Location: welcome.php");
                    exit;
                } else {
                    $login_error = true;
                }
            } else {
                $login_error = true;
            }

            mysqli_stmt_close($stmt);
            require_once("DB_close.php");
        } else {
            $login_error = true;
        }
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
      <div class="mb-3">
        <label for="Captcha" class="form-label">驗證碼</label>
        <div class="d-flex align-items-center">
          <input type="text" class="form-control me-2" id="Captcha" name="Captcha" maxlength="5" required>
          <img src="captcha.php" alt="CAPTCHA" onclick="this.src='captcha.php?'+Math.random()" style="cursor: pointer;" title="點擊圖片可重新產生">
        </div>
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
