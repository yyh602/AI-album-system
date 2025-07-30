<?php
require_once("DB_open.php");

$nameVal = "";
$userVal = "";
$passVal = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nameVal = isset($_POST["name"]) ? $_POST["name"] : "";
    $userVal = isset($_POST["username"]) ? $_POST["username"] : "";
    $passVal = isset($_POST["password"]) ? $_POST["password"] : "";

    $stmt = mysqli_prepare($link, "SELECT COUNT(*) FROM user WHERE username = ?");
    mysqli_stmt_bind_param($stmt, "s", $userVal);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $userCount);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);

    if ($userCount > 0) {
        echo "<script>alert('帳號已存在，請重新輸入');</script>";
        $userVal = "";
    } else {
        $stmt = mysqli_prepare($link, "INSERT INTO user (name, username, password) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "sss", $nameVal, $userVal, $passVal);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        echo "<script>alert('註冊成功');</script>";
        header("Location: login.php");
        $nameVal = $userVal = $passVal = "";
    }
}
require_once("DB_close.php");
?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="UTF-8">
  <title>會員註冊 | AI智慧相簿管理系統</title>
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

<!-- 註冊表單 -->
<div class="container login-wrapper">
  <div class="login-box">
    <h5 class="text-center mb-4">會員註冊</h5>
    <form action="add.php" method="post">
      <div class="mb-3">
        <label for="name" class="form-label">姓名</label>
        <input type="text" class="form-control" id="name" name="name" required value="<?php echo htmlspecialchars($nameVal, ENT_QUOTES, 'UTF-8'); ?>">
      </div>
      <div class="mb-3">
        <label for="username" class="form-label">帳號</label>
        <input type="text" class="form-control" id="username" name="username" maxlength="10" required value="<?php echo htmlspecialchars($userVal, ENT_QUOTES, 'UTF-8'); ?>">
      </div>
      <div class="mb-3">
        <label for="password" class="form-label">密碼</label>
        <input type="password" class="form-control" id="password" name="password" maxlength="10" required value="<?php echo htmlspecialchars($passVal, ENT_QUOTES, 'UTF-8'); ?>">
      </div>
      <div class="d-grid">
        <button type="submit" class="btn btn-login">註冊</button>
      </div>
    </form>
  </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
