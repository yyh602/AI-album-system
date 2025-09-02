<?php
ini_set('display_errors', 1);
ini_set('memory_limit', '512M');
ini_set('max_execution_time', 300);

// 處理 AJAX 請求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // 清除所有輸出緩衝
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // 設定 JSON 標頭
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');
    
    try {
        if ($_POST['action'] === 'detect_faces') {
            // 調試：記錄接收到的資料
            error_log("Simple POST data received: " . print_r($_POST, true));
            
            $selectedPhotos = $_POST['selected_photos'] ?? [];
            error_log("Simple selected photos array: " . print_r($selectedPhotos, true));
            
            if (empty($selectedPhotos)) {
                throw new Exception('請選擇至少一張照片');
            }
            
            // 模擬處理（不實際執行複雜的人臉偵測）
            $mockFaces = [];
            foreach ($selectedPhotos as $index => $photoUrl) {
                $mockFaces["face_{$index}.jpg"] = [
                    'original_image' => $photoUrl,
                    'original_name' => basename($photoUrl),
                    'azure_url' => $photoUrl, // 暫時使用原圖
                    'local_path' => '/tmp/face_' . $index . '.jpg',
                    'face_index' => $index
                ];
            }
            
            // 模擬分群結果
            $mockGroups = [
                'group_1' => [
                    'faces' => array_keys($mockFaces),
                    'count' => count($mockFaces)
                ]
            ];
            
            echo json_encode([
                'status' => 'success',
                'message' => '簡化版人臉偵測完成（模擬）',
                'data' => [
                    'faces_detected' => count($mockFaces),
                    'groups_created' => count($mockGroups),
                    'face_map' => $mockFaces,
                    'group_results' => $mockGroups,
                    'python_output' => ['簡化版本，跳過 Python 處理']
                ]
            ], JSON_UNESCAPED_UNICODE);
            
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => '無效的操作'
            ], JSON_UNESCAPED_UNICODE);
        }
    } catch (Exception $e) {
        error_log("Simple face detection error: " . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    } catch (Error $e) {
        error_log("Simple fatal error: " . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => '系統錯誤：' . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// 如果不是 POST 請求，顯示測試頁面
?>
<!DOCTYPE html>
<html>
<head>
    <title>簡化版人臉偵測測試</title>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .test-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .success { background-color: #d4edda; border-color: #28a745; }
        .error { background-color: #f8d7da; border-color: #dc3545; }
        button { padding: 10px 20px; background-color: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; }
        button:hover { background-color: #0056b3; }
        #result { margin-top: 20px; padding: 15px; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>🔧 簡化版人臉偵測測試</h1>
    
    <div class="test-section">
        <h2>📋 測試說明</h2>
        <p>這個簡化版本跳過了複雜的依賴檢查，直接模擬人臉偵測結果，用於測試 POST 請求處理是否正常。</p>
    </div>
    
    <div class="test-section">
        <h2>🧪 測試 POST 請求</h2>
        <button onclick="testPost()">測試 POST 請求</button>
        <div id="result"></div>
    </div>
    
    <div class="test-section">
        <h2>🔗 相關連結</h2>
        <ul>
            <li><a href="azure_face_dashboard.php">返回完整版人臉辨識儀表板</a></li>
            <li><a href="check_dependencies.php">檢查依賴檔案</a></li>
            <li><a href="debug_post.php">POST 調試工具</a></li>
        </ul>
    </div>
    
    <script>
        function testPost() {
            const resultDiv = document.getElementById('result');
            resultDiv.innerHTML = '<p>正在測試...</p>';
            
            const formData = new FormData();
            formData.append('action', 'detect_faces');
            formData.append('selected_photos[]', 'https://albumstorage1411131020.blob.core.windows.net/photos/68a1d58ea85ed.JPG');
            formData.append('selected_photos[]', 'https://albumstorage1411131020.blob.core.windows.net/photos/68a1d590dd067.JPG');
            
            fetch('simple_face_detection.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.text().then(text => {
                    console.log('Raw response:', text);
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('JSON parse error:', e);
                        throw new Error('Invalid JSON response: ' + e.message + '\nResponse: ' + text.substring(0, 200));
                    }
                });
            })
            .then(data => {
                resultDiv.className = 'success';
                resultDiv.innerHTML = `
                    <h3>✅ 測試成功！</h3>
                    <p><strong>狀態：</strong> ${data.status}</p>
                    <p><strong>訊息：</strong> ${data.message}</p>
                    <p><strong>偵測到的人臉數量：</strong> ${data.data.faces_detected}</p>
                    <p><strong>分群數量：</strong> ${data.data.groups_created}</p>
                    <details>
                        <summary>詳細結果</summary>
                        <pre>${JSON.stringify(data.data, null, 2)}</pre>
                    </details>
                `;
            })
            .catch(error => {
                resultDiv.className = 'error';
                resultDiv.innerHTML = `
                    <h3>❌ 測試失敗</h3>
                    <p>${error.message}</p>
                `;
            });
        }
    </script>
</body>
</html> 