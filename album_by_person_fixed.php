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
            
            // 自動獲取用戶所有照片（不需要選擇）
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
                $photos_to_process[] = $row['path']; // 直接使用路徑，與 azure_face_dashboard.php 一致
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
            
            if (!file_exists('face_test/vendor/autoload.php')) {
                throw new Exception('vendor/autoload.php 檔案不存在，請檢查 Composer 依賴');
            }
            
            // 載入 azure_face_detection.php
            require_once("face_test/azure_face_detection.php");
            
            // 檢查類別是否存在
            if (!class_exists('AzureFaceDetection')) {
                throw new Exception('AzureFaceDetection 類別載入失敗');
            }
            
            $detector = new AzureFaceDetection();
            
            // 執行人臉偵測和分群 - 與 azure_face_dashboard.php 完全一致
            $faces = $detector->processImages($photos_to_process);
            
            // 執行人臉分群 - 使用修正版腳本
            $groupOutput = $detector->groupFacesWithFixedScript();
            
            // 檢查分群結果
            $groupResults = [];
            $groupResultsPath = __DIR__ . '/face_test/group_results.json';
            if (file_exists($groupResultsPath)) {
                $groupResults = json_decode(file_get_contents($groupResultsPath), true) ?: [];
            }
            
            // 更新資料庫中的 person 欄位
            $updated_count = 0;
            if (!empty($groupResults)) {
                foreach ($groupResults as $group) {
                    if (isset($group['group_name']) && isset($group['faces'])) {
                        $person_name = $group['group_name'];
                        
                        foreach ($group['faces'] as $face) {
                            if (isset($face['filename'])) {
                                foreach ($faces as $face_file => $face_info) {
                                    if ($face_file === $face['filename']) {
                                        $original_image = $face_info['original_image'];
                                        foreach ($photos_to_process as $photo_path) {
                                            if (strpos($original_image, basename($photo_path)) !== false) {
                                                // 根據路徑找到照片 ID
                                                $find_sql = "SELECT p.id FROM photos p JOIN albums a ON p.album_id = a.id WHERE p.path = ? AND a.username = ?";
                                                $find_stmt = mysqli_prepare($link, $find_sql);
                                                mysqli_stmt_bind_param($find_stmt, "ss", $photo_path, $username);
                                                mysqli_stmt_execute($find_stmt);
                                                $find_result = mysqli_stmt_get_result($find_stmt);
                                                
                                                if ($photo_row = mysqli_fetch_assoc($find_result)) {
                                                    $update_sql = "UPDATE photos SET person = ? WHERE id = ?";
                                                    $update_stmt = mysqli_prepare($link, $update_sql);
                                                    mysqli_stmt_bind_param($update_stmt, "si", $person_name, $photo_row['id']);
                                                    if (mysqli_stmt_execute($update_stmt)) {
                                                        $updated_count++;
                                                    }
                                                    mysqli_stmt_close($update_stmt);
                                                }
                                                mysqli_stmt_close($find_stmt);
                                                break;
                                            }
                                        }
                                        break;
                                    }
                                }
                            }
                        }
                    }
                }
            }
            
            require_once("DB_close.php");
            
            echo json_encode([
                'status' => 'success',
                'message' => '人臉偵測和分群完成',
                'data' => [
                    'total_photos' => count($photos_to_process),
                    'faces_detected' => count($faces),
                    'groups_created' => count($groupResults),
                    'updated_photos' => $updated_count,
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
    <title>依人物分類 - AI智慧相簿管理</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
    body {
        background: #f6f8fa;
        font-family: Arial, sans-serif;
        margin: 0;
        padding: 0;
        text-align: center;
    }

    .navbar {
        background-color: #e9d0c3 !important;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .navbar-brand, .nav-link, .navbar-username {
        color: #333 !important;
    }

    .nav-link:hover {
        color: #3498db !important;
    }

    .nav-link.active {
        color: #3498db !important;
        font-weight: bold;
    }

    .navbar-avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        object-fit: cover;
    }

    .navbar-username {
        font-size: 1.1rem;
        font-weight: 500;
        margin-left: 8px;
    }

    .face-detection-section {
        background: #fff;
        border-radius: 12px;
        padding: 24px;
        margin: 24px 40px;
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

    .stats-container {
        display: none;
        margin-top: 16px;
        padding: 16px;
        background: #f8f9fa;
        border-radius: 8px;
        border-left: 4px solid #667eea;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 16px;
        text-align: center;
    }

    .stat-item {
        padding: 8px;
    }

    .stat-number {
        font-size: 1.5rem;
        font-weight: bold;
        color: #667eea;
    }

    .stat-label {
        font-size: 0.8rem;
        color: #666;
        margin-top: 4px;
    }

    /* 分群結果顯示樣式 */
    .group-results {
        display: none;
        margin-top: 24px;
        padding: 24px;
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .group-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }

    .group-card {
        background: #f8f9fa;
        border-radius: 8px;
        padding: 16px;
        border: 1px solid #dee2e6;
    }

    .group-header {
        display: flex;
        align-items: center;
        margin-bottom: 16px;
        gap: 12px;
    }

    .group-icon {
        width: 32px;
        height: 32px;
        background: #667eea;
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 14px;
    }

    .group-title {
        font-weight: bold;
        color: #333;
        flex: 1;
    }

    .group-count {
        background: #e3f2fd;
        color: #1976d2;
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 500;
    }

    .face-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
        gap: 8px;
    }

    .face-item {
        text-align: center;
    }

    .face-item img {
        width: 100%;
        height: 80px;
        object-fit: cover;
        border-radius: 4px;
        border: 1px solid #dee2e6;
    }

    .face-filename {
        font-size: 10px;
        color: #666;
        margin-top: 4px;
        word-break: break-all;
    }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark sticky-top">
      <div class="container-fluid px-3">
        <a class="navbar-brand d-flex align-items-center" href="#">
          <img src="img/logo.svg" width="32" height="32" class="me-2">
          <span style="font-weight:bold;letter-spacing:1px;">AI智慧相簿管理系統</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavDropdown" aria-controls="navbarNavDropdown" aria-expanded="false" aria-label="Toggle navigation">
          <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNavDropdown">
          <ul class="navbar-nav me-auto mb-2 mb-lg-0">
            <li class="nav-item">
              <a class="nav-link" href="welcome.php">首頁</a>
            </li>
            <li class="nav-item">
              <a class="nav-link active" href="album.php">相簿</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="ai_log.php">AI生成日誌</a>
            </li>
          </ul>
          <div class="d-flex align-items-center ms-auto">
            <img src="img/avatar.svg" alt="avatar" class="navbar-avatar">
            <span class="navbar-username"><?php echo htmlspecialchars($name); ?></span>
          </div>
        </div>
      </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <a href="welcome.php" class="btn btn-outline-secondary rounded-circle"
               title="返回首頁"
               style="width: 42px; height: 42px;">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div class="d-flex gap-2 mx-auto">
              <a href="album.php" class="btn btn-light">我的相簿</a>
              <a href="album_by_time.php" class="btn btn-light">依時間分類</a>
              <a href="album_by_location.php" class="btn btn-light">依地點分類</a>
              <a href="album_by_person_fixed.php" class="btn btn-dark">依人物分類</a>
            </div>
        </div>
    </div>

    <!-- 人臉辨識功能區塊 -->
    <div class="face-detection-section">
        <h3 style="margin-bottom: 16px; color: #333;">
            <i class="fas fa-user-friends me-2"></i>AI 人臉辨識與分類
        </h3>
        <p style="color: #666; margin-bottom: 20px;">
            點擊下方按鈕開始偵測您相簿中的所有照片，AI 將自動識別照片中的人物並進行分類。
        </p>
        
        <button id="faceDetectionBtn" class="face-detection-btn">
            <i class="fas fa-magic"></i>
            開始人臉辨識
        </button>

        <!-- 進度條 -->
        <div id="progressContainer" class="progress-container">
            <div class="progress">
                <div id="progressBar" class="progress-bar" role="progressbar" style="width: 0%"></div>
            </div>
            <div id="statusText" class="status-text">準備中...</div>
        </div>

        <!-- 統計結果 -->
        <div id="statsContainer" class="stats-container">
            <h5 style="margin-bottom: 16px; color: #333;">處理結果</h5>
            <div class="stats-grid">
                <div class="stat-item">
                    <div id="totalPhotos" class="stat-number">0</div>
                    <div class="stat-label">總照片數</div>
                </div>
                <div class="stat-item">
                    <div id="facesDetected" class="stat-number">0</div>
                    <div class="stat-label">偵測到人臉</div>
                </div>
                <div class="stat-item">
                    <div id="groupsCreated" class="stat-number">0</div>
                    <div class="stat-label">人物分群</div>
                </div>
                <div class="stat-item">
                    <div id="updatedPhotos" class="stat-number">0</div>
                    <div class="stat-label">已更新</div>
                </div>
            </div>
        </div>
    </div>

    <!-- 分群結果顯示區域 -->
    <div id="groupResults" class="group-results">
        <h4 style="margin-bottom: 20px; color: #333;">
            <i class="fas fa-users me-2"></i>人臉偵測與分群結果
        </h4>
        <div id="groupGrid" class="group-grid">
            <!-- 分群結果將在這裡動態顯示 -->
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
        const statsContainer = document.getElementById('statsContainer');
        const groupResults = document.getElementById('groupResults');
        
        debugLog('按鈕元素:', faceDetectionBtn);
        
        if (!faceDetectionBtn) {
            console.error('找不到人臉辨識按鈕！');
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
                
                if (statsContainer) statsContainer.style.display = 'none';
                if (groupResults) groupResults.style.display = 'none';

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
                
                fetch('album_by_person_fixed.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=detect_faces'
                })
                .then(response => {
                    debugLog('收到回應:', response);
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
                            if (statsContainer) statsContainer.style.display = 'block';
                            
                            const totalPhotos = document.getElementById('totalPhotos');
                            const facesDetected = document.getElementById('facesDetected');
                            const groupsCreated = document.getElementById('groupsCreated');
                            const updatedPhotos = document.getElementById('updatedPhotos');
                            
                            if (totalPhotos) totalPhotos.textContent = result.data.total_photos || 0;
                            if (facesDetected) facesDetected.textContent = result.data.faces_detected || 0;
                            if (groupsCreated) groupsCreated.textContent = result.data.groups_created || 0;
                            if (updatedPhotos) updatedPhotos.textContent = result.data.updated_photos || 0;
                            
                            // 顯示分群結果
                            displayGroupResults();
                            
                            alert('人臉辨識完成！');
                            
                            setTimeout(() => {
                                location.reload();
                            }, 3000);
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
                    alert('處理失敗：' + error.message);
                })
                .finally(() => {
                    faceDetectionBtn.disabled = false;
                    faceDetectionBtn.innerHTML = '<i class="fas fa-magic"></i> 開始人臉辨識';
                });
                
            } catch (error) {
                debugLog('JavaScript 錯誤:', error);
                if (statusText) statusText.textContent = '處理失敗：' + error.message;
                if (progressBar) progressBar.style.width = '0%';
                alert('處理失敗：' + error.message);
                
                faceDetectionBtn.disabled = false;
                faceDetectionBtn.innerHTML = '<i class="fas fa-magic"></i> 開始人臉辨識';
            }
        });

        debugLog('事件監聽器綁定完成');
    });

    // 顯示分群結果的函數（與 azure_face_dashboard.php 一致）
    function displayGroupResults() {
        const groupGrid = document.getElementById('groupGrid');
        const groupResults = document.getElementById('groupResults');
        
        console.log('開始顯示分群結果...');
        groupGrid.innerHTML = '';
        
        // 讀取分群結果
        console.log('正在讀取 group_results.json...');
        fetch('face_test/group_results.json')
            .then(response => {
                console.log('fetch response status:', response.status);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('分群結果:', data);
                
                // 檢查陣列格式 (新格式)
                if (Array.isArray(data) && data.length > 0) {
                    console.log('使用陣列格式顯示分群結果，共', data.length, '個群組');
                    data.forEach((group, index) => {
                        const groupCard = document.createElement('div');
                        groupCard.className = 'group-card';
                        
                        const facesHtml = group.faces.map(face => {
                            const imagePath = `face_test/faces/${face.filename}`;
                            
                            return `<div class="face-item">
                                <img src="${imagePath}" alt="${face.filename}" 
                                     onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iODAiIGhlaWdodD0iODAiIHZpZXdCb3g9IjAgMCA4MCA4MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjgwIiBoZWlnaHQ9IjgwIiBmaWxsPSIjRjBGNEY2Ii8+CjxwYXRoIGQ9Ik0yMCAyMEg2MFY2MEgyMFYyMFoiIGZpbGw9IiNEN0Q5RDAiLz4KPHBhdGggZD0iTTI1IDI1SDU1VjU1SDI1VjI1WiIgZmlsbD0iI0Y4RjlGQSIvPgo8L3N2Zz4K'">
                                <div class="face-filename">${face.filename}</div>
                            </div>`;
                        }).join('');
                        
                        groupCard.innerHTML = `
                            <div class="group-header">
                                <div class="group-icon">${index + 1}</div>
                                <div class="group-title">${group.group_name}</div>
                                <div class="group-count">${group.faces.length} 張</div>
                            </div>
                            <div class="face-grid">
                                ${facesHtml}
                            </div>
                        `;
                        
                        groupGrid.appendChild(groupCard);
                    });
                } else {
                    console.log('沒有找到分群結果，顯示偵測到的人臉');
                    displayDetectedFaces();
                }
                
                console.log('顯示分群結果區域');
                groupResults.style.display = 'block';
            })
            .catch(error => {
                console.error('讀取分群結果失敗:', error);
                console.log('錯誤詳情:', error.message);
                displayDetectedFaces();
                groupResults.style.display = 'block';
            });
    }
    
    function displayDetectedFaces() {
        const groupGrid = document.getElementById('groupGrid');
        
        // 讀取 face_map.json 來顯示偵測到的人臉
        fetch('face_test/face_map.json')
            .then(response => response.json())
            .then(data => {
                if (Object.keys(data).length > 0) {
                    const groupCard = document.createElement('div');
                    groupCard.className = 'group-card';
                    
                    const facesHtml = Object.entries(data).map(([faceName, faceInfo]) => {
                        const imagePath = `face_test/faces/${faceName}`;
                        
                        return `<div class="face-item">
                            <img src="${imagePath}" alt="${faceName}" 
                                 onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iODAiIGhlaWdodD0iODAiIHZpZXdCb3g9IjAgMCA4MCA4MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjgwIiBoZWlnaHQ9IjgwIiBmaWxsPSIjRjBGNEY2Ii8+CjxwYXRoIGQ9Ik0yMCAyMEg2MFY2MEgyMFYyMFoiIGZpbGw9IiNEN0Q5RDAiLz4KPHBhdGggZD0iTTI1IDI1SDU1VjU1SDI1VjI1WiIgZmlsbD0iI0Y4RjlGQSIvPgo8L3N2Zz4K'">
                            <div class="face-filename">${faceName}</div>
                        </div>`;
                    }).join('');
                    
                    groupCard.innerHTML = `
                        <div class="group-header">
                            <div class="group-icon">👥</div>
                            <div class="group-title">偵測到的人臉</div>
                            <div class="group-count">${Object.keys(data).length} 張</div>
                        </div>
                        <div class="face-grid">
                            ${facesHtml}
                        </div>
                    `;
                    
                    groupGrid.appendChild(groupCard);
                } else {
                    groupGrid.innerHTML = '<p class="text-center text-muted">暫無偵測到的人臉</p>';
                }
            })
            .catch(error => {
                console.error('讀取人臉對應關係失敗:', error);
                groupGrid.innerHTML = '<p class="text-center text-muted">無法讀取人臉資料</p>';
            });
    }

    console.log('JavaScript 檔案載入完成');
    </script>
</body>
</html>
