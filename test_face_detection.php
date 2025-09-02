<?php
session_start();

if (!isset($_SESSION["username"])) {
    header("Location: login.php");
    exit();
}

require_once("DB_open.php");

$username = $_SESSION["username"];
$name = $username;

if ($link instanceof mysqli) {
    $sql = "SELECT name FROM user WHERE username = ?";
    $stmt = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $result_name);

    if (mysqli_stmt_fetch($stmt)) {
        $name = $result_name;
    }

    mysqli_stmt_close($stmt);
} else {
    error_log("資料庫連線失敗或類型不正確");
}
require_once("DB_close.php");

// 處理人臉辨識 AJAX 請求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    try {
        if ($_POST['action'] === 'detect_faces') {
            require_once("DB_open.php");
            
            // 自動獲取用戶所有照片
            $sql = "SELECT p.id, p.path, p.filename, a.username 
                    FROM photos p 
                    JOIN albums a ON p.album_id = a.id 
                    WHERE a.username = ?";
            $stmt = mysqli_prepare($link, $sql);
            mysqli_stmt_bind_param($stmt, "s", $username);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            $photos_to_process = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $photos_to_process[] = $row['path'];
            }
            mysqli_stmt_close($stmt);
            
            if (empty($photos_to_process)) {
                echo json_encode([
                    'status' => 'success',
                    'message' => '沒有找到照片',
                    'data' => ['processed' => 0]
                ]);
                exit;
            }
            
            // 檢查必要檔案是否存在
            if (!file_exists('face_test/azure_face_detection.php')) {
                throw new Exception('azure_face_detection.php 檔案不存在');
            }
            
            // 載入 azure_face_detection.php
            require_once("face_test/azure_face_detection.php");
            
            // 檢查類別是否存在
            if (!class_exists('AzureFaceDetection')) {
                throw new Exception('AzureFaceDetection 類別載入失敗');
            }
            
            $detector = new AzureFaceDetection();
            
            // 執行人臉偵測和分群
            $faces = $detector->processImages($photos_to_process);
            
            // 執行人臉分群
            $groupOutput = $detector->groupFacesWithFixedScript();
            
            // 檢查分群結果
            $groupResults = [];
            $groupResultsPath = __DIR__ . '/face_test/group_results.json';
            if (file_exists($groupResultsPath)) {
                $groupResults = json_decode(file_get_contents($groupResultsPath), true) ?: [];
            }
            
            echo json_encode([
                'status' => 'success',
                'message' => '人臉偵測和分群完成',
                'data' => [
                    'total_photos' => count($photos_to_process),
                    'faces_detected' => count($faces),
                    'groups_created' => count($groupResults),
                    'face_map' => $faces,
                    'group_results' => $groupResults,
                    'python_output' => $groupOutput
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
    <title>測試人臉偵測</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
    body {
        background: #f6f8fa;
        font-family: Arial, sans-serif;
        margin: 0;
        padding: 20px;
        text-align: center;
    }

    .face-detection-section {
        background: #fff;
        border-radius: 12px;
        padding: 24px;
        margin: 24px auto;
        max-width: 600px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .face-detection-btn {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        border-radius: 8px;
        padding: 12px 24px;
        font-size: 1.1rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .face-detection-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        color: white;
    }

    .face-detection-btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }

    .progress-container {
        display: none;
        margin-top: 16px;
    }

    .progress {
        height: 8px;
        border-radius: 4px;
        background-color: #e9ecef;
    }

    .progress-bar {
        background: linear-gradient(90deg, #667eea, #764ba2);
        transition: width 0.3s ease;
    }

    .status-text {
        margin-top: 8px;
        font-size: 0.9rem;
        color: #666;
    }

    .results {
        display: none;
        margin-top: 16px;
        padding: 16px;
        background: #f8f9fa;
        border-radius: 8px;
        border-left: 4px solid #667eea;
        text-align: left;
    }

    .results.success {
        border-left-color: #28a745;
    }

    .results.error {
        border-left-color: #dc3545;
    }
    </style>
</head>
<body>
    <div class="face-detection-section">
        <h3 style="margin-bottom: 16px; color: #333;">
            <i class="fas fa-user-friends me-2"></i>測試人臉偵測
        </h3>
        <p style="color: #666; margin-bottom: 20px;">
            點擊下方按鈕開始偵測您相簿中的所有照片
        </p>
        
        <button id="faceDetectionBtn" class="face-detection-btn">
            <i class="fas fa-magic"></i>
            開始人臉偵測
        </button>

        <!-- 進度條 -->
        <div id="progressContainer" class="progress-container">
            <div class="progress">
                <div id="progressBar" class="progress-bar" role="progressbar" style="width: 0%"></div>
            </div>
            <div id="statusText" class="status-text">準備中...</div>
        </div>

        <!-- 結果顯示 -->
        <div id="results" class="results">
            <!-- 結果將在這裡顯示 -->
        </div>
    </div>

    <script>
    console.log('=== JavaScript 開始執行 ===');
    
    function debugLog(message) {
        console.log('[DEBUG]', message);
    }

    window.addEventListener('load', function() {
        debugLog('頁面完全載入完成');
        
        const faceDetectionBtn = document.getElementById('faceDetectionBtn');
        const progressContainer = document.getElementById('progressContainer');
        const progressBar = document.getElementById('progressBar');
        const statusText = document.getElementById('statusText');
        const results = document.getElementById('results');
        
        debugLog('按鈕元素:', faceDetectionBtn);
        
        if (!faceDetectionBtn) {
            console.error('找不到人臉偵測按鈕！');
            return;
        }
        
        faceDetectionBtn.addEventListener('click', function() {
            debugLog('按鈕被點擊！');
            
            try {
                faceDetectionBtn.disabled = true;
                faceDetectionBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 處理中...';
                
                if (progressContainer) progressContainer.style.display = 'block';
                if (progressBar) progressBar.style.width = '0%';
                if (statusText) statusText.textContent = '開始處理...';
                
                if (results) results.style.display = 'none';

                let progress = 0;
                const progressInterval = setInterval(() => {
                    progress += Math.random() * 15;
                    if (progress > 90) progress = 90;
                    if (progressBar) progressBar.style.width = progress + '%';
                    
                    if (statusText) {
                        if (progress < 30) {
                            statusText.textContent = '正在分析照片...';
                        } else if (progress < 60) {
                            statusText.textContent = '偵測人臉中...';
                        } else if (progress < 90) {
                            statusText.textContent = '進行人物分群...';
                        }
                    }
                }, 500);

                debugLog('發送 AJAX 請求...');
                
                fetch('test_face_detection.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=detect_faces'
                })
                .then(response => {
                    debugLog('收到回應:', response);
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    return response.json();
                })
                .then(result => {
                    debugLog('處理結果:', result);
                    
                    clearInterval(progressInterval);
                    
                    if (result.status === 'success') {
                        if (progressBar) progressBar.style.width = '100%';
                        if (statusText) statusText.textContent = '處理完成！';
                        
                        setTimeout(() => {
                            if (progressContainer) progressContainer.style.display = 'none';
                            if (results) {
                                results.className = 'results success';
                                results.innerHTML = `
                                    <h4>✅ 處理完成</h4>
                                    <p><strong>總照片數：</strong> ${result.data.total_photos}</p>
                                    <p><strong>偵測到的人臉數量：</strong> ${result.data.faces_detected}</p>
                                    <p><strong>分群數量：</strong> ${result.data.groups_created}</p>
                                    <details>
                                        <summary>詳細結果</summary>
                                        <pre>${JSON.stringify(result.data, null, 2)}</pre>
                                    </details>
                                `;
                                results.style.display = 'block';
                            }
                        }, 1000);
                        
                    } else {
                        throw new Error(result.message);
                    }
                    
                })
                .catch(error => {
                    debugLog('AJAX 錯誤:', error);
                    clearInterval(progressInterval);
                    
                    if (statusText) statusText.textContent = '處理失敗：' + error.message;
                    if (progressBar) progressBar.style.width = '0%';
                    
                    if (results) {
                        results.className = 'results error';
                        results.innerHTML = `
                            <h4>❌ 處理失敗</h4>
                            <p>${error.message}</p>
                        `;
                        results.style.display = 'block';
                    }
                })
                .finally(() => {
                    faceDetectionBtn.disabled = false;
                    faceDetectionBtn.innerHTML = '<i class="fas fa-magic"></i> 開始人臉偵測';
                });
                
            } catch (error) {
                debugLog('JavaScript 錯誤:', error);
                if (statusText) statusText.textContent = '處理失敗：' + error.message;
                if (progressBar) progressBar.style.width = '0%';
                
                if (results) {
                    results.className = 'results error';
                    results.innerHTML = `
                        <h4>❌ 處理失敗</h4>
                        <p>${error.message}</p>
                    `;
                    results.style.display = 'block';
                }
                
                faceDetectionBtn.disabled = false;
                faceDetectionBtn.innerHTML = '<i class="fas fa-magic"></i> 開始人臉偵測';
            }
        });

        debugLog('事件監聽器綁定完成');
    });

    console.log('JavaScript 檔案載入完成');
    </script>
</body>
</html>
