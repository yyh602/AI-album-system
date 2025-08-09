<?php
// 快速登入測試工具
header('Content-Type: application/json');

// 檢查 session 狀態，避免重複啟動
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if ($_GET['action'] == 'login') {
    // 模擬登入
    $_SESSION['username'] = 'test_user';
    echo json_encode([
        'status' => 'success',
        'message' => '登入成功',
        'username' => $_SESSION['username'],
        'session_id' => session_id()
    ]);
} elseif ($_GET['action'] == 'logout') {
    // 登出
    session_destroy();
    echo json_encode([
        'status' => 'success',
        'message' => '登出成功'
    ]);
} elseif ($_GET['action'] == 'check') {
    // 檢查登入狀態
    echo json_encode([
        'status' => 'success',
        'logged_in' => isset($_SESSION['username']),
        'username' => $_SESSION['username'] ?? null,
        'session_id' => session_id(),
        'session_data' => $_SESSION
    ]);
} else {
    echo json_encode([
        'status' => 'info',
        'message' => '快速登入測試工具',
        'actions' => [
            'login' => '?action=login',
            'logout' => '?action=logout', 
            'check' => '?action=check'
        ],
        'current_session' => $_SESSION
    ]);
}
?>
