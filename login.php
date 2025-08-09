<?php
session_start();    //啟用交談期
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
        
        // 檢查連接類型並使用相應的查詢方式
        if ($link instanceof PgSQLWrapper || $link instanceof PDO) {
            // PostgreSQL 查詢
            $sql = "SELECT * FROM \"user\" WHERE password = ? AND username = ?";
            $stmt = $link->prepare($sql);
            $stmt->execute([$password, $username]);
            
            // 為了兼容性，使用不同的方法計算記錄數
            if ($link instanceof PgSQLWrapper) {
                // 對於 PgSQLWrapper，需要重新查詢計算記錄數
                $count_sql = "SELECT COUNT(*) FROM \"user\" WHERE password = '" . pg_escape_string($password) . "' AND username = '" . pg_escape_string($username) . "'";
                $count_result = $link->query($count_sql);
                $count_row = $count_result->fetch_row();
                $total_records = $count_row[0];
            } else {
                $total_records = $stmt->rowCount();
            }
            
            if($total_records > 0){
                // 成功登入, 指定Session變數
                $_SESSION["login_session"] = true;
                $_SESSION["username"] = $username;
                header("Location: welcome.php");
                exit();
            } else {    // 登入失敗
                $login_error = true;
                $_SESSION["login_session"] = false;
            }
        } else {
            // MySQL 查詢
            if ($link instanceof mysqli) {
                $sql = "SELECT * FROM \"user\" WHERE password='";
                $sql.= $password."' AND username='".$username."'";
                $result = mysqli_query($link, $sql);
                if ($result) {
                    $total_records = mysqli_num_rows($result);
                    if($total_records > 0){
                        $_SESSION["login_session"] = true;
                        $_SESSION["username"] = $username;
                        header("Location: welcome.php");
                        exit();
                    } else {
                        $login_error = true;
                        $_SESSION["login_session"] = false;
                    }
                } else {
                    $login_error = true;
                    error_log("SQL 查詢失敗: " . mysqli_error($link));
                }
            } else {
                // 如果是 PDOWrapper，使用 PDO 方式查詢
                $sql = "SELECT * FROM \"user\" WHERE password = ? AND username = ?";
                $stmt = $link->prepare($sql);
                $stmt->execute([$password, $username]);
                $total_records = $stmt->rowCount();
                
                if($total_records > 0){
                    $_SESSION["login_session"] = true;
                    $_SESSION["username"] = $username;
                    header("Location: welcome.php");
                    exit();
                } else {
                    $login_error = true;
                    $_SESSION["login_session"] = false;
                }
            }
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
    <?php if(isset($login_error) && $login_error): ?>
      <div class="alert alert-danger py-2 text-center mb-3" role="alert">
        <i class="fa fa-exclamation-circle me-1"></i> 使用者名稱或密碼錯誤！
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

