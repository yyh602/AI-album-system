<?php
// 自動安裝 Python 套件檢查
require_once 'auto_install_check.php';

// 原有的 session 和資料庫連接邏輯
session_start();
require_once '../DB_open.php';

// 完全關閉錯誤顯示和警告
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 0);

// 啟動輸出緩衝
ob_start();

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
            error_log("POST data received: " . print_r($_POST, true));
            
            $selectedPhotos = $_POST['selected_photos'] ?? [];
            error_log("Selected photos array: " . print_r($selectedPhotos, true));
            
            if (empty($selectedPhotos)) {
                throw new Exception('請選擇至少一張照片');
            }
            
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
            
            // 執行人臉偵測和分群 - 使用簡化版本
            $faces = $detector->processImages($selectedPhotos);
            
            // 執行人臉分群 - 使用修正版腳本
            $groupOutput = $detector->groupFacesWithFixedScript();
            
            // 檢查分群結果
            $groupResults = [];
            $groupResultsPath = __DIR__ . '/group_results.json';
            if (file_exists($groupResultsPath)) {
                $groupResults = json_decode(file_get_contents($groupResultsPath), true) ?: [];
            }
            
            echo json_encode([
                'status' => 'success',
                'message' => '人臉偵測和分群完成',
                'data' => [
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

// 清除輸出緩衝，準備輸出 HTML
ob_clean();

// 獲取真實相簿資料
function getRealAlbums() {
    $albums = [];
    try {
        // 使用新的無驗證 API
        $ch = curl_init();
        $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
        $url = $baseUrl . dirname($_SERVER['REQUEST_URI']) . '/get_albums_no_auth.php?all_albums=1';
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            if ($data && $data['status'] === 'success' && isset($data['albums'])) {
                $albums = $data['albums'];
            }
        }
        
    } catch (Exception $e) {
        error_log("獲取相簿失敗: " . $e->getMessage());
    }
    return $albums;
}

// 獲取相簿照片
function getRealAlbumPhotos($albumId) {
    $photos = [];
    try {
        // 使用新的無驗證 API
        $ch = curl_init();
        $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
        $url = $baseUrl . dirname($_SERVER['REQUEST_URI']) . "/get_albums_no_auth.php?album_id=$albumId";
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            if ($data && $data['status'] === 'success' && isset($data['photos'])) {
                $photos = $data['photos'];
            }
        }
        
    } catch (Exception $e) {
        error_log("獲取相簿照片失敗: " . $e->getMessage());
    }
    return $photos;
}

// 模擬相簿資料（備用）
function getMockAlbums() {
    return [
        [
            'id' => 1,
            'name' => '測試相簿 1',
            'cover_photo' => '../img/default_album_cover.svg'
        ],
        [
            'id' => 2,
            'name' => '測試相簿 2',
            'cover_photo' => '../img/default_album_cover.svg'
        ]
    ];
}

// 模擬照片資料（備用）
function getMockPhotos($albumId) {
    $testPhotos = [];
    
    // 檢查是否有實際的測試照片
    $testImagePaths = [
        '../IMG_1234.jpg',
        '../uploads/',
        '../img/'
    ];
    
    foreach ($testImagePaths as $path) {
        if (is_dir($path)) {
            $files = glob($path . '*.{jpg,jpeg,png,gif}', GLOB_BRACE);
            foreach ($files as $file) {
                $testPhotos[] = [
                    'id' => count($testPhotos) + 1,
                    'filename' => basename($file),
                    'path' => $file,
                    'datetime' => date('Y-m-d H:i:s', filemtime($file))
                ];
            }
        } elseif (file_exists($path)) {
            $testPhotos[] = [
                'id' => count($testPhotos) + 1,
                'filename' => basename($path),
                'path' => $path,
                'datetime' => date('Y-m-d H:i:s', filemtime($path))
            ];
        }
    }
    
    // 如果沒有找到實際照片，使用預設測試照片
    if (empty($testPhotos)) {
        $testPhotos = [
            [
                'id' => 1,
                'filename' => 'IMG_1234.jpg',
                'path' => '../IMG_1234.jpg',
                'datetime' => '2024-01-01 12:00:00'
            ],
            [
                'id' => 2,
                'filename' => 'test_photo.jpg',
                'path' => '../uploads/test_photo.jpg',
                'datetime' => '2024-01-01 13:00:00'
            ]
        ];
    }
    
    return $testPhotos;
}

// 嘗試獲取真實相簿資料，如果失敗則使用模擬資料
$albums = getRealAlbums();
$useRealData = !empty($albums);

if (empty($albums)) {
    $albums = getMockAlbums();
}

$selectedAlbumId = $_GET['album_id'] ?? null;
$photos = [];
if ($selectedAlbumId) {
    if ($useRealData) {
        $photos = getRealAlbumPhotos($selectedAlbumId);
    }
    if (empty($photos)) {
        $photos = getMockPhotos($selectedAlbumId);
    }
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Azure 人臉偵測系統</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 2.5em;
            font-weight: 300;
        }
        .content {
            padding: 30px;
        }
        .album-selector {
            margin-bottom: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        .album-selector h3 {
            margin-top: 0;
            color: #333;
        }
        .album-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        .album-item {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
        }
        .album-item:hover {
            border-color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .album-item.selected {
            border-color: #667eea;
            background: #f0f4ff;
        }
        .album-item img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 10px;
        }
        .album-item .album-name {
            font-weight: 500;
            color: #333;
            font-size: 14px;
        }
        .photo-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .photo-item {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            overflow: hidden;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
        }
        .photo-item:hover {
            border-color: #667eea;
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .photo-item.selected {
            border-color: #667eea;
            background: #f0f4ff;
        }
        .photo-item img {
            width: 100%;
            height: 150px;
            object-fit: cover;
        }
        .photo-info {
            padding: 10px;
            font-size: 12px;
            color: #666;
        }
        .photo-checkbox {
            position: absolute;
            top: 10px;
            right: 10px;
            width: 20px;
            height: 20px;
            accent-color: #667eea;
        }
        .controls {
            text-align: center;
            margin: 30px 0;
        }
        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 25px;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin: 0 10px;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .results {
            margin-top: 30px;
            padding: 20px;
            border-radius: 10px;
            display: none;
        }
        .success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .loading {
            text-align: center;
            padding: 20px;
            display: none;
        }
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 10px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .select-all {
            margin-bottom: 20px;
            text-align: right;
        }
        .no-album-selected {
            text-align: center;
            padding: 50px;
            color: #666;
        }
        .test-notice {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
        .data-source {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .group-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .group-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
            border: 2px solid #f0f0f0;
        }
        
        .group-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
            border-color: #667eea;
        }
        
        .group-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .group-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            margin-right: 15px;
        }
        
        .group-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #333;
        }
        
        .group-count {
            background: #667eea;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            margin-left: auto;
        }
        
        .face-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
            gap: 10px;
        }
        
        .face-item {
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
            position: relative;
        }
        
        .face-item:hover {
            transform: scale(1.1);
            z-index: 10;
        }
        
        .face-item img {
            width: 100%;
            height: 80px;
            object-fit: cover;
        }
        
        .face-filename {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            font-size: 10px;
            padding: 2px 4px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Azure 人臉偵測系統</h1>
            <p>選擇相簿進行人臉偵測和分群</p>
        </div>
        
        <div class="content">
            <?php if ($useRealData): ?>
                <div class="data-source">
                    <strong>📁 真實相簿資料：</strong> 從 Azure Storage Account 讀取相簿和照片
                </div>
            <?php else: ?>
                <div class="test-notice">
                    <strong>🧪 測試模式：</strong> 使用模擬資料，無法連接到真實相簿
                </div>
            <?php endif; ?>
            
            <!-- 相簿選擇器 -->
            <div class="album-selector">
                <h3>📁 選擇相簿</h3>
                <div class="album-grid" id="albumGrid">
                    <?php foreach ($albums as $album): ?>
                        <div class="album-item <?php echo ($selectedAlbumId == $album['id']) ? 'selected' : ''; ?>" 
                             onclick="selectAlbum(<?php echo $album['id']; ?>, '<?php echo htmlspecialchars($album['name']); ?>')">
                            <img src="<?php echo htmlspecialchars($album['cover_photo']); ?>" 
                                 alt="<?php echo htmlspecialchars($album['name']); ?>"
                                 onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iODAiIGhlaWdodD0iODAiIHZpZXdCb3g9IjAgMCA4MCA4MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjgwIiBoZWlnaHQ9IjgwIiBmaWxsPSIjRjBGNEY2Ii8+CjxwYXRoIGQ9Ik0yMCAyMEg2MFY2MEgyMFYyMFoiIGZpbGw9IiNEN0Q5RDAiLz4KPHBhdGggZD0iTTI1IDI1SDU1VjU1SDI1VjI1WiIgZmlsbD0iI0Y4RjlGQSIvPgo8L3N2Zz4K'">
                            <div class="album-name"><?php echo htmlspecialchars($album['name']); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- 照片選擇區域 -->
            <?php if ($selectedAlbumId && !empty($photos)): ?>
                <div class="select-all">
                    <label>
                        <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                        全選
                    </label>
                </div>
                
                <div class="photo-grid" id="photoGrid">
                    <?php foreach ($photos as $photo): ?>
                        <div class="photo-item" onclick="togglePhoto(this, '<?php echo htmlspecialchars($photo['path']); ?>')">
                            <input type="checkbox" class="photo-checkbox" value="<?php echo htmlspecialchars($photo['path']); ?>">
                            <img src="<?php echo htmlspecialchars($photo['path']); ?>" alt="<?php echo htmlspecialchars($photo['filename']); ?>"
                                 onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjE1MCIgdmlld0JveD0iMCAwIDIwMCAxNTAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIyMDAiIGhlaWdodD0iMTUwIiBmaWxsPSIjRjBGNEY2Ii8+CjxwYXRoIGQ9Ik0yMCAyMEgxODBWMzBIMjBWMjBaIiBmaWxsPSIjRDdEOUQwIi8+CjxwYXRoIGQ9Ik0yNSA0MEgxNzVWMTMwSDI1VjQwWiIgZmlsbD0iI0Y4RjlGQSIvPgo8L3N2Zz4K'">
                            <div class="photo-info">
                                <div><strong><?php echo htmlspecialchars($photo['filename']); ?></strong></div>
                                <?php if (!empty($photo['datetime'])): ?>
                                    <div><?php echo date('Y-m-d H:i', strtotime($photo['datetime'])); ?></div>
                                <?php endif; ?>
                                <div style="font-size: 10px; color: #999;"><?php echo htmlspecialchars($photo['path']); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="controls">
                    <button class="btn" onclick="detectFaces()" id="detectBtn">
                        開始人臉偵測
                    </button>
                </div>
            <?php elseif ($selectedAlbumId && empty($photos)): ?>
                <div class="no-album-selected">
                    <h3>相簿中沒有照片</h3>
                    <p>請先上傳照片到此相簿</p>
                </div>
            <?php else: ?>
                <div class="no-album-selected">
                    <h3>請選擇相簿</h3>
                    <p>點擊上方的相簿開始人臉偵測</p>
                </div>
            <?php endif; ?>
            
            <div class="loading" id="loading">
                <div class="spinner"></div>
                <p>正在處理中，請稍候...</p>
            </div>
            
            <div class="results" id="results"></div>
            
            <!-- 分群結果顯示區域 -->
            <div id="groupResults" style="display: none;">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-users"></i> 人臉偵測與分群結果
                    </div>
                    <div class="card-body">
                        <div id="groupGrid" class="group-grid"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let selectedPhotos = [];
        let currentAlbumId = <?php echo $selectedAlbumId ?: 'null'; ?>;
        
        function selectAlbum(albumId, albumName) {
            window.location.href = `azure_face_dashboard.php?album_id=${albumId}`;
        }
        

        
        function togglePhoto(element, photoUrl) {
            const checkbox = element.querySelector('.photo-checkbox');
            checkbox.checked = !checkbox.checked;
            
            if (checkbox.checked) {
                element.classList.add('selected');
                if (!selectedPhotos.includes(photoUrl)) {
                    selectedPhotos.push(photoUrl);
                }
            } else {
                element.classList.remove('selected');
                selectedPhotos = selectedPhotos.filter(url => url !== photoUrl);
            }
            
            console.log('Selected photos after toggle:', selectedPhotos);
            updateSelectAll();
        }
        
        function toggleSelectAll() {
            const selectAllCheckbox = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.photo-checkbox');
            const photoItems = document.querySelectorAll('.photo-item');
            
            selectedPhotos = [];
            
            checkboxes.forEach((checkbox, index) => {
                checkbox.checked = selectAllCheckbox.checked;
                if (selectAllCheckbox.checked) {
                    photoItems[index].classList.add('selected');
                    // 確保使用完整的 URL
                    const photoUrl = checkbox.value;
                    if (photoUrl && !selectedPhotos.includes(photoUrl)) {
                        selectedPhotos.push(photoUrl);
                    }
                } else {
                    photoItems[index].classList.remove('selected');
                }
            });
            
            console.log('Selected photos after toggle:', selectedPhotos);
        }
        
        function updateSelectAll() {
            const selectAllCheckbox = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.photo-checkbox');
            const checkedCount = document.querySelectorAll('.photo-checkbox:checked').length;
            
            selectAllCheckbox.checked = checkedCount === checkboxes.length && checkboxes.length > 0;
        }
        
        function detectFaces() {
            console.log('Selected photos before detection:', selectedPhotos);
            
            if (selectedPhotos.length === 0) {
                alert('請選擇至少一張照片');
                return;
            }
            
            const loading = document.getElementById('loading');
            const results = document.getElementById('results');
            const detectBtn = document.getElementById('detectBtn');
            
            loading.style.display = 'block';
            results.style.display = 'none';
            detectBtn.disabled = true;
            
            const formData = new FormData();
            formData.append('action', 'detect_faces');
            selectedPhotos.forEach(photo => {
                formData.append('selected_photos[]', photo);
            });
            
            fetch('azure_face_dashboard.php', {
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
                        console.error('Response text:', text);
                        throw new Error('Invalid JSON response: ' + e.message + '\nResponse: ' + text.substring(0, 200));
                    }
                });
            })
            .then(data => {
                loading.style.display = 'none';
                detectBtn.disabled = false;
                
                if (data.status === 'success') {
                    results.className = 'results success';
                    results.innerHTML = `
                        <h3>✅ 處理完成</h3>
                        <p><strong>偵測到的人臉數量：</strong> ${data.data.faces_detected}</p>
                        <p><strong>分群數量：</strong> ${data.data.groups_created}</p>
                        <details>
                            <summary>詳細結果</summary>
                            <pre>${JSON.stringify(data.data, null, 2)}</pre>
                        </details>
                    `;
                    
                    // 顯示分群結果
                    displayGroupResults();
                } else {
                    results.className = 'results error';
                    results.innerHTML = `
                        <h3>❌ 處理失敗</h3>
                        <p>${data.message}</p>
                    `;
                }
                
                results.style.display = 'block';
            })
            .catch(error => {
                loading.style.display = 'none';
                detectBtn.disabled = false;
                
                results.className = 'results error';
                results.innerHTML = `
                    <h3>❌ 網路錯誤</h3>
                    <p>${error.message}</p>
                `;
                results.style.display = 'block';
            });
        }
        
        function displayGroupResults() {
            const groupGrid = document.getElementById('groupGrid');
            const groupResults = document.getElementById('groupResults');
            
            console.log('開始顯示分群結果...');
            groupGrid.innerHTML = '';
            
            // 首先嘗試讀取分群結果
            console.log('正在讀取 group_results.json...');
            fetch('group_results.json')
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
                        // 陣列格式：每個元素是一個群組
                        data.forEach((group, index) => {
                            const groupCard = document.createElement('div');
                            groupCard.className = 'group-card';
                            
                            const facesHtml = group.faces.map(face => {
                                // 使用簡單的相對路徑
                                const imagePath = `faces/${face.filename}`;
                                
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
                    }
                    // 檢查新格式 (data.data.groups)
                    else if (data && data.data && data.data.groups && data.data.groups.length > 0) {
                        // 新格式：顯示分群
                        data.data.groups.forEach((group, index) => {
                            const groupCard = document.createElement('div');
                            groupCard.className = 'group-card';
                            
                            const facesHtml = group.faces.map(face => {
                                // 使用簡單的相對路徑
                                const imagePath = `faces/${face.face_name || 'face'}`;
                                
                                return `<div class="face-item">
                                    <img src="${imagePath}" alt="${face.face_name || 'face'}" 
                                         onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iODAiIGhlaWdodD0iODAiIHZpZXdCb3g9IjAgMCA4MCA4MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjgwIiBoZWlnaHQ9IjgwIiBmaWxsPSIjRjBGNEY2Ii8+CjxwYXRoIGQ9Ik0yMCAyMEg2MFY2MEgyMFYyMFoiIGZpbGw9IiNEN0Q5RDAiLz4KPHBhdGggZD0iTTI1IDI1SDU1VjU1SDI1VjI1WiIgZmlsbD0iI0Y4RjlGQSIvPgo8L3N2Zz4K'">
                                    <div class="face-filename">${face.face_name || 'face'}</div>
                                </div>`;
                            }).join('');
                            
                            groupCard.innerHTML = `
                                <div class="group-header">
                                    <div class="group-icon">${index + 1}</div>
                                    <div class="group-title">people_${index + 1}</div>
                                    <div class="group-count">${group.faces.length} 張</div>
                                </div>
                                <div class="face-grid">
                                    ${facesHtml}
                                </div>
                            `;
                            
                            groupGrid.appendChild(groupCard);
                        });
                    } 
                    // 檢查舊格式 (people_X)
                    else if (data && Object.keys(data).length > 0 && data.people_0) {
                        console.log('使用舊格式顯示分群結果，共', Object.keys(data).length, '個群組');
                        // 舊格式：每個人都是一個群組
                        Object.entries(data).forEach(([groupName, groupInfo], index) => {
                            const groupCard = document.createElement('div');
                            groupCard.className = 'group-card';
                            
                            const facesHtml = groupInfo.images.map(imageName => {
                                const imagePath = `faces/${imageName}`;
                                
                                return `<div class="face-item">
                                    <img src="${imagePath}" alt="${imageName}" 
                                         onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iODAiIGhlaWdodD0iODAiIHZpZXdCb3g9IjAgMCA4MCA4MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjgwIiBoZWlnaHQ9IjgwIiBmaWxsPSIjRjBGNEY2Ii8+CjxwYXRoIGQ9Ik0yMCAyMEg2MFY2MEgyMFYyMFoiIGZpbGw9IiNEN0Q5RDAiLz4KPHBhdGggZD0iTTI1IDI1SDU1VjU1SDI1VjI1WiIgZmlsbD0iI0Y4RjlGQSIvPgo8L3N2Zz4K'">
                                    <div class="face-filename">${imageName}</div>
                                </div>`;
                            }).join('');
                            
                            groupCard.innerHTML = `
                                <div class="group-header">
                                    <div class="group-icon">${index + 1}</div>
                                    <div class="group-title">${groupName}</div>
                                    <div class="group-count">${groupInfo.count} 張</div>
                                </div>
                                <div class="face-grid">
                                    ${facesHtml}
                                </div>
                            `;
                            
                            groupGrid.appendChild(groupCard);
                        });
                    } else {
                        console.log('沒有找到分群結果，顯示偵測到的人臉');
                        // 沒有分群結果，顯示偵測到的人臉
                        displayDetectedFaces();
                    }
                    
                    console.log('顯示分群結果區域');
                    groupResults.style.display = 'block';
                })
                .catch(error => {
                    console.error('讀取分群結果失敗:', error);
                    console.log('錯誤詳情:', error.message);
                    // 讀取失敗時，也顯示偵測到的人臉
                    displayDetectedFaces();
                    groupResults.style.display = 'block';
                });
        }
        
        function displayDetectedFaces() {
            const groupGrid = document.getElementById('groupGrid');
            
            // 讀取 face_map.json 來顯示偵測到的人臉
            fetch('face_map.json')
                .then(response => response.json())
                .then(data => {
                    if (Object.keys(data).length > 0) {
                        const groupCard = document.createElement('div');
                        groupCard.className = 'group-card';
                        
                        const facesHtml = Object.entries(data).map(([faceName, faceInfo]) => {
                            // 使用簡單的相對路徑
                            const imagePath = `faces/${faceName}`;
                            
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
    </script>
</body>
</html>
