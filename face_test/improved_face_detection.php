<?php
/**
 * 改進的人臉偵測腳本
 * 將偵測結果保存到資料庫，確保結果持久化
 */

session_start();
require_once '../DB_open.php';

// 檢查登入狀態
if (!isset($_SESSION["username"])) {
    echo "請先登入";
    exit;
}

$username = $_SESSION["username"];

// 設定錯誤顯示
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 設定執行時間限制
ini_set('max_execution_time', 600);
ini_set('memory_limit', '1024M');
set_time_limit(600);

// 處理 AJAX 請求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    try {
        if ($_POST['action'] === 'detect_faces') {
            // 獲取用戶照片
            $sql = "SELECT p.id, p.path, p.filename, a.username 
                    FROM photos p 
                    JOIN albums a ON p.album_id = a.id 
                    WHERE a.username = ? 
                    LIMIT 10"; // 測試模式：只處理前 10 張照片
            $stmt = mysqli_prepare($link, $sql);
            mysqli_stmt_bind_param($stmt, "s", $username);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            $photos_to_process = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $photos_to_process[] = $row;
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
            
            // 載入人臉偵測類別
            require_once 'azure_face_detection.php';
            $detector = new AzureFaceDetection();
            
            // 清空舊的人臉資料（可選）
            $clear_sql = "DELETE FROM faces WHERE photo_id IN (SELECT id FROM photos WHERE album_id IN (SELECT id FROM albums WHERE username = ?))";
            $clear_stmt = mysqli_prepare($link, $clear_sql);
            mysqli_stmt_bind_param($clear_stmt, "s", $username);
            mysqli_stmt_execute($clear_stmt);
            mysqli_stmt_close($clear_stmt);
            
            // 處理照片
            $total_faces = 0;
            $processed_photos = 0;
            
            foreach ($photos_to_process as $photo) {
                try {
                    // 偵測人臉
                    $faces = $detector->processImages([$photo['path']]);
                    
                    if (!empty($faces)) {
                        // 將人臉資訊保存到資料庫
                        foreach ($faces as $face_filename => $face_info) {
                            $insert_sql = "INSERT INTO faces (
                                photo_id, face_filename, person_group, confidence, 
                                face_size, margin_used, crop_dimensions, original_image
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                            
                            $insert_stmt = mysqli_prepare($link, $insert_sql);
                            mysqli_stmt_bind_param($insert_stmt, 
                                "isdsisss", 
                                $photo['id'],
                                $face_filename,
                                'people_1', // 暫時分組，後續會更新
                                0.95, // 預設信心度
                                $face_info['face_size'] ?? 0,
                                $face_info['margin_used'] ?? 0,
                                $face_info['crop_dimensions'] ?? '',
                                $face_info['original_image'] ?? ''
                            );
                            
                            if (mysqli_stmt_execute($insert_stmt)) {
                                $total_faces++;
                            }
                            mysqli_stmt_close($insert_stmt);
                        }
                        $processed_photos++;
                    }
                    
                } catch (Exception $e) {
                    error_log("處理照片失敗: " . $e->getMessage());
                    continue;
                }
            }
            
            // 執行人臉分群
            $groupOutput = $detector->groupFacesWithFixedScript();
            
            // 讀取分群結果並更新資料庫
            $groupResults = [];
            $groupResultsPath = __DIR__ . '/group_results.json';
            if (file_exists($groupResultsPath)) {
                $groupResults = json_decode(file_get_contents($groupResultsPath), true) ?: [];
                
                // 更新資料庫中的人臉分組
                foreach ($groupResults as $group) {
                    if (isset($group['group_name']) && isset($group['faces'])) {
                        foreach ($group['faces'] as $face) {
                            if (isset($face['filename'])) {
                                $update_sql = "UPDATE faces SET person_group = ? WHERE face_filename = ?";
                                $update_stmt = mysqli_prepare($link, $update_sql);
                                mysqli_stmt_bind_param($update_stmt, "ss", $group['group_name'], $face['filename']);
                                mysqli_stmt_execute($update_stmt);
                                mysqli_stmt_close($update_stmt);
                            }
                        }
                    }
                }
            }
            
            // 更新人物群組統計
            $update_stats_sql = "UPDATE person_groups pg SET 
                total_faces = (SELECT COUNT(*) FROM faces WHERE person_group = pg.group_name)";
            mysqli_query($link, $update_stats_sql);
            
            echo json_encode([
                'status' => 'success',
                'message' => '人臉偵測和分群完成',
                'data' => [
                    'total_photos' => count($photos_to_process),
                    'processed_photos' => $processed_photos,
                    'faces_detected' => $total_faces,
                    'groups_created' => count($groupResults),
                    'python_output' => $groupOutput
                ]
            ], JSON_UNESCAPED_UNICODE);
            
        } elseif ($_POST['action'] === 'get_faces') {
            // 從資料庫獲取人臉資訊
            $sql = "SELECT 
                        f.id, f.face_filename, f.person_group, f.face_size, 
                        f.crop_dimensions, f.original_image, f.created_at,
                        p.filename as photo_filename,
                        pg.display_name
                    FROM faces f
                    LEFT JOIN photos p ON f.photo_id = p.id
                    LEFT JOIN person_groups pg ON f.person_group = pg.group_name
                    WHERE p.id IN (
                        SELECT p2.id FROM photos p2 
                        JOIN albums a2 ON p2.album_id = a2.id 
                        WHERE a2.username = ?
                    )
                    ORDER BY f.created_at DESC";
            
            $stmt = mysqli_prepare($link, $sql);
            mysqli_stmt_bind_param($stmt, "s", $username);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            $faces = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $faces[] = $row;
            }
            mysqli_stmt_close($stmt);
            
            echo json_encode([
                'status' => 'success',
                'data' => $faces
            ], JSON_UNESCAPED_UNICODE);
            
        } elseif ($_POST['action'] === 'get_statistics') {
            // 獲取統計資訊
            $sql = "SELECT 
                        COUNT(*) as total_faces,
                        COUNT(DISTINCT person_group) as total_groups,
                        COUNT(DISTINCT photo_id) as total_photos_with_faces,
                        AVG(face_size) as average_face_size
                    FROM faces f
                    WHERE photo_id IN (
                        SELECT p.id FROM photos p 
                        JOIN albums a ON p.album_id = a.id 
                        WHERE a.username = ?
                    )";
            
            $stmt = mysqli_prepare($link, $sql);
            mysqli_stmt_bind_param($stmt, "s", $username);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $stats = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);
            
            echo json_encode([
                'status' => 'success',
                'data' => $stats
            ], JSON_UNESCAPED_UNICODE);
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => '處理失敗: ' . $e->getMessage()
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
    <title>改進的人臉偵測</title>
    <style>
        body {
            font-family: 'Microsoft JhengHei', Arial, sans-serif;
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
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .content {
            padding: 40px;
        }
        .btn {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border: none;
            padding: 15px 30px;
            margin: 10px;
            border-radius: 25px;
            cursor: pointer;
            font-size: 1.1em;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4);
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .stat-item {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            border-left: 4px solid #667eea;
        }
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #667eea;
        }
        .faces-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .face-item {
            background: white;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            text-align: center;
        }
        .face-item img {
            width: 100%;
            height: 120px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 10px;
        }
        .face-info {
            font-size: 0.9em;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔍 改進的人臉偵測系統</h1>
            <p>偵測結果將保存到資料庫，確保持久化</p>
        </div>
        
        <div class="content">
            <div style="text-align: center; margin-bottom: 30px;">
                <button class="btn" onclick="detectFaces()">開始人臉偵測</button>
                <button class="btn" onclick="loadFaces()">載入已偵測的人臉</button>
                <button class="btn" onclick="loadStatistics()">載入統計資訊</button>
            </div>
            
            <div id="statsContainer" class="stats-grid" style="display: none;">
                <!-- 統計資訊將在這裡顯示 -->
            </div>
            
            <div id="facesContainer" class="faces-grid" style="display: none;">
                <!-- 人臉圖片將在這裡顯示 -->
            </div>
            
            <div id="message" style="text-align: center; margin: 20px 0; padding: 15px; border-radius: 8px; display: none;">
                <!-- 訊息將在這裡顯示 -->
            </div>
        </div>
    </div>

    <script>
        function showMessage(message, type = 'info') {
            const msgDiv = document.getElementById('message');
            msgDiv.textContent = message;
            msgDiv.className = `alert alert-${type}`;
            msgDiv.style.display = 'block';
            
            if (type === 'success') {
                msgDiv.style.background = '#d4edda';
                msgDiv.style.color = '#155724';
                msgDiv.style.border = '1px solid #c3e6cb';
            } else if (type === 'error') {
                msgDiv.style.background = '#f8d7da';
                msgDiv.style.color = '#721c24';
                msgDiv.style.border = '1px solid #f5c6cb';
            } else {
                msgDiv.style.background = '#d1ecf1';
                msgDiv.style.color = '#0c5460';
                msgDiv.style.border = '1px solid #bee5eb';
            }
        }
        
        function detectFaces() {
            showMessage('開始人臉偵測...', 'info');
            
            fetch('improved_face_detection.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=detect_faces'
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    showMessage(`人臉偵測完成！偵測到 ${data.data.faces_detected} 個人臉，建立 ${data.data.groups_created} 個群組`, 'success');
                    loadStatistics();
                    loadFaces();
                } else {
                    showMessage('偵測失敗: ' + data.message, 'error');
                }
            })
            .catch(error => {
                showMessage('錯誤: ' + error.message, 'error');
            });
        }
        
        function loadStatistics() {
            fetch('improved_face_detection.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_statistics'
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    const stats = data.data;
                    const statsContainer = document.getElementById('statsContainer');
                    
                    statsContainer.innerHTML = `
                        <div class="stat-item">
                            <div class="stat-number">${stats.total_faces || 0}</div>
                            <div>總人臉數</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number">${stats.total_groups || 0}</div>
                            <div>人物群組</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number">${stats.total_photos_with_faces || 0}</div>
                            <div>包含人臉的照片</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number">${Math.round(stats.average_face_size || 0)}</div>
                            <div>平均人臉尺寸(px)</div>
                        </div>
                    `;
                    
                    statsContainer.style.display = 'grid';
                }
            })
            .catch(error => {
                showMessage('載入統計資訊失敗: ' + error.message, 'error');
            });
        }
        
        function loadFaces() {
            fetch('improved_face_detection.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_faces'
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    const faces = data.data;
                    const facesContainer = document.getElementById('facesContainer');
                    
                    if (faces.length === 0) {
                        facesContainer.innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 40px; color: #666;">還沒有偵測到人臉</div>';
                    } else {
                        facesContainer.innerHTML = faces.map(face => `
                            <div class="face-item">
                                <img src="faces/${face.face_filename}" alt="${face.face_filename}" 
                                     onerror="this.style.display='none'">
                                <div class="face-info">
                                    <strong>${face.face_filename}</strong><br>
                                    群組: ${face.person_group || '未分組'}<br>
                                    尺寸: ${face.crop_dimensions || '未知'}<br>
                                    來源: ${face.photo_filename || '未知'}
                                </div>
                            </div>
                        `).join('');
                    }
                    
                    facesContainer.style.display = 'grid';
                }
            })
            .catch(error => {
                showMessage('載入人臉失敗: ' + error.message, 'error');
            });
        }
        
        // 頁面載入時自動載入統計資訊
        window.addEventListener('load', function() {
            loadStatistics();
        });
    </script>
</body>
</html>
