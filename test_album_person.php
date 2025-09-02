<?php
session_start();
if (!isset($_SESSION["username"])) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>測試人臉辨識按鈕</title>
</head>
<body>
    <h1>測試人臉辨識按鈕</h1>
    <button id="faceDetectionBtn">開始人臉辨識</button>
    
    <script>
    console.log('=== JavaScript 開始執行 ===');
    
    function debugLog(message) {
        console.log('[DEBUG]', message);
    }
    
    window.addEventListener('load', function() {
        debugLog('頁面完全載入完成');
        
        const faceDetectionBtn = document.getElementById('faceDetectionBtn');
        debugLog('按鈕元素:', faceDetectionBtn);
        
        if (faceDetectionBtn) {
            faceDetectionBtn.addEventListener('click', function() {
                debugLog('按鈕被點擊！');
                alert('按鈕被點擊了！');
            });
            debugLog('事件監聽器綁定完成');
        } else {
            console.error('找不到按鈕！');
        }
    });
    
    console.log('JavaScript 檔案載入完成');
    </script>
</body>
</html>
