<?php
// 自動安裝 Python 套件檢查
require_once 'auto_install_check.php';

// 原有的 session 和資料庫連接邏輯
session_start();
require_once '../DB_open.php';

// 設定更長的執行時間和記憶體限制
ini_set('max_execution_time', 600); // 10 分鐘
ini_set('memory_limit', '1024M');   // 1GB 記憶體
set_time_limit(600);                // 10 分鐘超時

// 完全關閉錯誤顯示和警告
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 0);

// 啟動輸出緩衝
ob_start();

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

// 處理人臉辨識 AJAX 請求
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
            error_log("POST data received: " . print_r($_POST, true));
            
            // 只處理前 5 張照片進行測試
            $sql = "SELECT p.id, p.path, p.filename, a.username 
                    FROM photos p 
                    JOIN albums a ON p.album_id = a.id 
                    WHERE a.username = ? 
                    LIMIT 5"; // 限制只處理 5 張照片
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
            
            error_log("測試模式：只處理 " . count($photos_to_process) . " 張照片");
            
            // 檢查必要檔案是否存在
            if (!file_exists('azure_face_detection.php')) {
                throw new Exception('azure_face_detection.php 檔案不存在');
            }
            
            if (!file_exists('vendor/autoload.php')) {
                throw new Exception('vendor/autoload.php 檔案不存在，請檢查 Composer 依賴');
            }
            
            // 載入 azure_face_detection.php
            require_once 'azure_face_detection.php';
            
            // 檢查類別是否存在
            if (!class_exists('AzureFaceDetection')) {
                throw new Exception('AzureFaceDetection 類別載入失敗');
            }
            
            $detector = new AzureFaceDetection();
            
            // 執行人臉偵測 - 輕量級測試
            error_log("開始處理照片，共 " . count($photos_to_process) . " 張");
            
            try {
                $faces = $detector->processImages($photos_to_process);
                error_log("照片處理完成，偵測到 " . count($faces) . " 個人臉");
                
                // 執行人臉分群 - 使用修正版腳本
                error_log("開始進行人臉分群...");
                $groupOutput = $detector->groupFacesWithFixedScript();
                error_log("人臉分群完成");
                
                // 檢查分群結果
                $groupResults = [];
                $groupResultsPath = __DIR__ . '/group_results.json';
                if (file_exists($groupResultsPath)) {
                    $groupResults = json_decode(file_get_contents($groupResultsPath), true) ?: [];
                }
                
                echo json_encode([
                    'status' => 'success',
                    'message' => '輕量級測試完成',
                    'data' => [
                        'total_photos' => count($photos_to_process),
                        'faces_detected' => count($faces),
                        'groups_created' => count($groupResults),
                        'face_map' => $faces,
                        'group_results' => $groupResults,
                        'python_output' => $groupOutput,
                        'test_mode' => true
                    ]
                ], JSON_UNESCAPED_UNICODE);
                
            } catch (Exception $e) {
                error_log("處理過程發生錯誤：" . $e->getMessage());
                throw new Exception('處理過程發生錯誤：' . $e->getMessage());
            }
            
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => '無效的操作'
            ], JSON_UNESCAPED_UNICODE);
        }
    } catch (Exception $e) {
        error_log("Face detection error: " . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    } catch (Error $e) {
        error_log("Fatal error: " . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => '系統錯誤：' . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>輕量級人臉偵測測試</title>
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

    .test-section {
        background: #fff;
        border-radius: 12px;
        padding: 24px;
        margin: 24px auto;
        max-width: 600px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .test-btn {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
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

    .test-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        color: white;
    }

    .test-btn:disabled {
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
        background: linear-gradient(90deg, #28a745, #20c997);
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
        border-left: 4px solid #28a745;
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
    <div class="test-section">
        <h3 style="margin-bottom: 16px; color: #333;">
            <i class="fas fa-flask me-2"></i>輕量級人臉偵測測試
        </h3>
        <p style="color: #666; margin-bottom: 20px;">
            <strong>測試模式：</strong>只處理前 5 張照片，快速驗證功能是否正常
        </p>
        
        <button id="testBtn" class="test-btn">
            <i class="fas fa-play"></i>
            開始輕量級測試
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
    console.log('=== 輕量級測試 JavaScript 開始執行 ===');
    
    function debugLog(message) {
        console.log('[DEBUG]', message);
    }

    window.addEventListener('load', function() {
        debugLog('頁面完全載入完成');
        
        const testBtn = document.getElementById('testBtn');
        const progressContainer = document.getElementById('progressContainer');
        const progressBar = document.getElementById('progressBar');
        const statusText = document.getElementById('statusText');
        const results = document.getElementById('results');
        
        debugLog('按鈕元素:', testBtn);
        
        if (!testBtn) {
            console.error('找不到測試按鈕！');
            return;
        }
        
        testBtn.addEventListener('click', function() {
            debugLog('按鈕被點擊！');
            
            try {
                testBtn.disabled = true;
                testBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 測試中...';
                
                if (progressContainer) progressContainer.style.display = 'block';
                if (progressBar) progressBar.style.width = '0%';
                if (statusText) statusText.textContent = '開始測試...';
                
                if (results) results.style.display = 'none';

                let progress = 0;
                const progressInterval = setInterval(() => {
                    progress += Math.random() * 20;
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
                }, 300);

                debugLog('發送輕量級測試 AJAX 請求...');
                
                fetch('test_lightweight.php', {
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
                        if (statusText) statusText.textContent = '測試完成！';
                        
                        setTimeout(() => {
                            if (progressContainer) progressContainer.style.display = 'none';
                            if (results) {
                                results.className = 'results success';
                                results.innerHTML = `
                                    <h4>✅ 輕量級測試完成</h4>
                                    <p><strong>測試照片數：</strong> ${result.data.total_photos}</p>
                                    <p><strong>偵測到的人臉數量：</strong> ${result.data.faces_detected}</p>
                                    <p><strong>分群數量：</strong> ${result.data.groups_created}</p>
                                    <p><em>這是輕量級測試，只處理了前 5 張照片</em></p>
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
                    
                    if (statusText) statusText.textContent = '測試失敗：' + error.message;
                    if (progressBar) progressBar.style.width = '0%';
                    
                    if (results) {
                        results.className = 'results error';
                        results.innerHTML = `
                            <h4>❌ 測試失敗</h4>
                            <p>${error.message}</p>
                        `;
                        results.style.display = 'block';
                    }
                })
                .finally(() => {
                    testBtn.disabled = false;
                    testBtn.innerHTML = '<i class="fas fa-play"></i> 開始輕量級測試';
                });
                
            } catch (error) {
                debugLog('JavaScript 錯誤:', error);
                if (statusText) statusText.textContent = '測試失敗：' + error.message;
                if (progressBar) progressBar.style.width = '0%';
                
                if (results) {
                    results.className = 'results error';
                    results.innerHTML = `
                        <h4>❌ 測試失敗</h4>
                        <p>${error.message}</p>
                    `;
                    results.style.display = 'block';
                }
                
                testBtn.disabled = false;
                testBtn.innerHTML = '<i class="fas fa-play"></i> 開始輕量級測試';
            }
        });

        debugLog('事件監聽器綁定完成');
    });

    console.log('輕量級測試 JavaScript 檔案載入完成');
    </script>
</body>
</html>
