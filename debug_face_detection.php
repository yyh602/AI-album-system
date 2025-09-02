<?php
session_start();

if (!isset($_SESSION["username"])) {
    header("Location: login.php");
    exit();
}

// 處理人臉辨識 AJAX 請求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    try {
        if ($_POST['action'] === 'detect_faces') {
            echo json_encode([
                'status' => 'success',
                'message' => '測試成功！AJAX 請求正常',
                'data' => [
                    'total_photos' => 5,
                    'faces_detected' => 3,
                    'groups_created' => 2,
                    'updated_photos' => 3
                ]
            ], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => '無效的操作'
            ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => '處理失敗：' . $e->getMessage()
        ]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>人臉辨識調試</title>
    <style>
        .test-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 12px 24px;
            font-size: 1.1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            margin: 20px;
        }
        .test-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        .log {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 10px;
            margin: 10px;
            font-family: monospace;
            max-height: 300px;
            overflow-y: auto;
        }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
    </style>
</head>
<body>
    <h1>人臉辨識功能調試</h1>
    <p>使用者：<?php echo htmlspecialchars($_SESSION["username"]); ?></p>
    
    <button id="testBtn" class="test-btn">測試人臉辨識</button>
    
    <div class="log" id="log"></div>
    
    <script>
        function log(message, type = 'info') {
            const logDiv = document.getElementById('log');
            const timestamp = new Date().toLocaleTimeString();
            const className = type === 'success' ? 'success' : type === 'error' ? 'error' : 'info';
            logDiv.innerHTML += `<span class="${className}">[${timestamp}] ${message}</span><br>`;
            logDiv.scrollTop = logDiv.scrollHeight;
            // 修復：確保 type 是字串
            const safeType = (type || 'info').toString();
            console.log(`[${safeType.toUpperCase()}]`, message);
        }
        
        window.addEventListener('load', function() {
            log('頁面載入完成', 'success');
            
            const testBtn = document.getElementById('testBtn');
            log('按鈕元素:', testBtn ? '找到' : '未找到', testBtn ? 'success' : 'error');
            
            if (testBtn) {
                testBtn.addEventListener('click', function() {
                    log('按鈕被點擊！', 'success');
                    
                    // 測試 AJAX 請求
                    log('發送測試 AJAX 請求...', 'info');
                    
                    fetch('debug_face_detection.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'action=detect_faces'
                    })
                    .then(response => {
                        log(`收到回應: ${response.status} ${response.statusText}`, 'info');
                        if (!response.ok) {
                            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                        }
                        return response.json();
                    })
                    .then(result => {
                        log('處理結果:', result, 'success');
                        log(`狀態: ${result.status}`, 'success');
                        log(`訊息: ${result.message}`, 'success');
                        if (result.data) {
                            log(`總照片數: ${result.data.total_photos}`, 'info');
                            log(`偵測到人臉: ${result.data.faces_detected}`, 'info');
                            log(`人物分群: ${result.data.groups_created}`, 'info');
                            log(`已更新: ${result.data.updated_photos}`, 'info');
                        }
                    })
                    .catch(error => {
                        log(`AJAX 錯誤: ${error.message}`, 'error');
                    });
                });
                
                log('事件監聽器綁定完成', 'success');
            } else {
                log('錯誤：找不到按鈕元素', 'error');
            }
        });
    </script>
</body>
</html>
