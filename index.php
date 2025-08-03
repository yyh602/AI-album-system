<?php
session_start();

// 檢查是否已經登入
if (isset($_SESSION["login_session"]) && $_SESSION["login_session"] === true) {
    // 已登入，重定向到歡迎頁面
    header("Location: welcome.php");
    exit();
} else {
    // 未登入，重定向到登入頁面
    header("Location: login.php");
    exit();
}
?> 