<?php
/**
 * 特徵向量儲存測試頁面
 * 用來測試 Python 腳本生成的特徵向量是否能正確儲存到資料庫
 */

session_start();
require_once '../DB_open.php';

// 設定錯誤顯示
error_reporting(E_ALL);
ini_set('display_errors', 1);

$username = $_SESSION["username"] ?? 'test_user';
$name = $username;

// 處理測試請求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    try {
        if ($_POST['action'] === 'test_python_script') {
            // 測試 Python 腳本
            $result = testPythonScript();
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            
        } elseif ($_POST['action'] === 'generate_test_vectors') {
            // 生成測試特徵向量並儲存到資料庫
            $result = generateAndStoreTestVectors($link);
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            
        } elseif ($_POST['action'] === 'check_database') {
            // 檢查資料庫中的特徵向量
            $result = checkDatabaseVectors($link);
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => '無效的操作'
            ], JSON_UNESCAPED_UNICODE);
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

/**
 * 測試 Python 腳本
 */
function testPythonScript() {
    try {
        $script_path = __DIR__ . '/test_feature_vector.py';
        
        if (!file_exists($script_path)) {
            throw new Exception("Python 測試腳本不存在: {$script_path}");
        }
        
        // 執行 Python 腳本
        $command = "cd " . __DIR__ . " && python3 " . escapeshellarg($script_path) . " 2>&1";
        $output = shell_exec($command);
        
        if ($output === null) {
            throw new Exception("Python 腳本執行失敗");
        }
        
        return [
            'status' => 'success',
            'message' => 'Python 腳本執行成功',
            'output' => $output
        ];
        
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'message' => $e->getMessage()
        ];
    }
}

/**
 * 生成並儲存測試特徵向量
 */
function generateAndStoreTestVectors($link) {
    try {
        // 確保 faces 表存在 feature_vector 欄位
        ensureFeatureVectorColumnExists($link);
        
        // 生成測試人臉資料
        $test_faces = [
            'test_face_1.jpg' => generateRandomFeatureVector(512),
            'test_face_2.jpg' => generateRandomFeatureVector(512),
            'test_face_3.jpg' => generateRandomFeatureVector(512)
        ];
        
        $saved_count = 0;
        $errors = [];
        
        foreach ($test_faces as $face_filename => $feature_vector) {
            try {
                // 檢查是否已存在
                $check_sql = "SELECT id FROM faces WHERE face_filename = ?";
                $check_stmt = mysqli_prepare($link, $check_sql);
                mysqli_stmt_bind_param($check_stmt, "s", $face_filename);
                mysqli_stmt_execute($check_stmt);
                $check_result = mysqli_stmt_get_result($check_stmt);
                
                if (mysqli_num_rows($check_result) == 0) {
                    // 插入測試資料
                    $insert_sql = "INSERT INTO faces (photo_id, face_filename, confidence, bounding_box, face_size, margin_used, crop_dimensions, original_image, feature_vector, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                    $insert_stmt = mysqli_prepare($link, $insert_sql);
                    
                    $photo_id = 1;
                    $confidence = 0.8;
                    $bounding_box = json_encode([]);
                    $face_size = 'medium';
                    $margin_used = 8;
                    $crop_dimensions = '80x80';
                    $original_image = '';
                    $feature_json = json_encode($feature_vector);
                    
                    mysqli_stmt_bind_param($insert_stmt, "isdsissss", 
                        $photo_id, $face_filename, $confidence, 
                        $bounding_box, $face_size, $margin_used, 
                        $crop_dimensions, $original_image, $feature_json);
                    
                    if (mysqli_stmt_execute($insert_stmt)) {
                        $saved_count++;
                    } else {
                        $errors[] = "儲存 {$face_filename} 失敗: " . mysqli_stmt_error($insert_stmt);
                    }
                    mysqli_stmt_close($insert_stmt);
                } else {
                    $errors[] = "{$face_filename} 已存在，跳過";
                }
                mysqli_stmt_close($check_stmt);
                
            } catch (Exception $e) {
                $errors[] = "處理 {$face_filename} 時發生錯誤: " . $e->getMessage();
            }
        }
        
        return [
            'status' => 'success',
            'message' => "測試特徵向量儲存完成",
            'data' => [
                'total_faces' => count($test_faces),
                'saved_count' => $saved_count,
                'errors' => $errors
            ]
        ];
        
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'message' => $e->getMessage()
        ];
    }
}

/**
 * 檢查資料庫中的特徵向量
 */
function checkDatabaseVectors($link) {
    try {
        $sql = "SELECT id, face_filename, feature_vector, created_at FROM faces WHERE feature_vector IS NOT NULL ORDER BY created_at DESC LIMIT 10";
        $result = mysqli_query($link, $sql);
        
        if (!$result) {
            throw new Exception("查詢失敗: " . mysqli_error($link));
        }
        
        $vectors = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $feature_data = json_decode($row['feature_vector'], true);
            $vectors[] = [
                'id' => $row['id'],
                'face_filename' => $row['face_filename'],
                'feature_dimension' => $feature_data ? count($feature_data) : 0,
                'feature_preview' => $feature_data ? array_slice($feature_data, 0, 5) : [],
                'created_at' => $row['created_at']
            ];
        }
        
        return [
            'status' => 'success',
            'message' => "資料庫查詢成功",
            'data' => [
                'total_vectors' => count($vectors),
                'vectors' => $vectors
            ]
        ];
        
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'message' => $e->getMessage()
        ];
    }
}

/**
 * 確保 faces 表存在 feature_vector 欄位
 */
function ensureFeatureVectorColumnExists($link) {
    try {
        // 檢查欄位是否存在
        $sql = "SHOW COLUMNS FROM faces LIKE 'feature_vector'";
        $result = mysqli_query($link, $sql);
        
        if (!$result) {
            throw new Exception("檢查欄位失敗: " . mysqli_error($link));
        }
        
        if (mysqli_num_rows($result) == 0) {
            // 欄位不存在，新增它
            $sql = "ALTER TABLE faces ADD COLUMN feature_vector TEXT";
            if (!mysqli_query($link, $sql)) {
                throw new Exception("新增 feature_vector 欄位失敗: " . mysqli_error($link));
            }
            error_log("已新增 feature_vector 欄位到 faces 表");
        }
        
    } catch (Exception $e) {
        throw new Exception("確保 feature_vector 欄位存在失敗: " . $e->getMessage());
    }
}

/**
 * 生成隨機特徵向量
 */
function generateRandomFeatureVector($dimension) {
    $vector = [];
    for ($i = 0; $i < $dimension; $i++) {
        $vector[] = (float)(rand(-1000, 1000) / 1000); // 生成 -1.0 到 1.0 之間的隨機數
    }
    return $vector;
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>特徵向量儲存測試 - AI智慧相簿管理</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f6f8fa; font-family: Arial, sans-serif; }
        .test-section { background: #fff; border-radius: 12px; padding: 24px; margin: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .test-btn { margin: 8px; }
        .result-box { background: #f8f9fa; border-radius: 8px; padding: 16px; margin: 16px 0; border-left: 4px solid #007bff; }
        .error-box { border-left-color: #dc3545; }
        .success-box { border-left-color: #28a745; }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <a href="../welcome.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> 返回首頁
            </a>
            <h2>🧪 特徵向量儲存測試工具</h2>
            <div></div>
        </div>
        
        <div class="test-section">
            <h4><i class="fas fa-python me-2"></i>Python 腳本測試</h4>
            <p class="text-muted">測試 Python 腳本是否能正常執行並生成特徵向量</p>
            <button id="testPythonBtn" class="btn btn-primary test-btn">
                <i class="fas fa-play"></i> 測試 Python 腳本
            </button>
            <div id="pythonResult" class="result-box" style="display: none;"></div>
        </div>
        
        <div class="test-section">
            <h4><i class="fas fa-database me-2"></i>資料庫儲存測試</h4>
            <p class="text-muted">生成測試特徵向量並儲存到資料庫</p>
            <button id="generateVectorsBtn" class="btn btn-success test-btn">
                <i class="fas fa-magic"></i> 生成測試特徵向量
            </button>
            <div id="generateResult" class="result-box" style="display: none;"></div>
        </div>
        
        <div class="test-section">
            <h4><i class="fas fa-search me-2"></i>資料庫檢查</h4>
            <p class="text-muted">檢查資料庫中已儲存的特徵向量</p>
            <button id="checkDatabaseBtn" class="btn btn-info test-btn">
                <i class="fas fa-eye"></i> 檢查資料庫
            </button>
            <div id="checkResult" class="result-box" style="display: none;"></div>
        </div>
    </div>

    <script>
        // 測試 Python 腳本
        document.getElementById('testPythonBtn').addEventListener('click', function() {
            const btn = this;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 測試中...';
            
            fetch('test_feature_storage.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=test_python_script'
            })
            .then(response => response.json())
            .then(result => {
                const resultDiv = document.getElementById('pythonResult');
                resultDiv.style.display = 'block';
                
                if (result.status === 'success') {
                    resultDiv.className = 'result-box success-box';
                    resultDiv.innerHTML = `
                        <h5>✅ Python 腳本測試成功</h5>
                        <p><strong>訊息:</strong> ${result.message}</p>
                        <details>
                            <summary>點擊查看詳細輸出</summary>
                            <pre style="background: #f1f3f4; padding: 10px; border-radius: 4px; margin-top: 10px;">${result.output}</pre>
                        </details>
                    `;
                } else {
                    resultDiv.className = 'result-box error-box';
                    resultDiv.innerHTML = `
                        <h5>❌ Python 腳本測試失敗</h5>
                        <p><strong>錯誤:</strong> ${result.message}</p>
                    `;
                }
            })
            .catch(error => {
                const resultDiv = document.getElementById('pythonResult');
                resultDiv.style.display = 'block';
                resultDiv.className = 'result-box error-box';
                resultDiv.innerHTML = `
                    <h5>❌ 測試執行失敗</h5>
                    <p><strong>錯誤:</strong> ${error.message}</p>
                `;
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-play"></i> 測試 Python 腳本';
            });
        });
        
        // 生成測試特徵向量
        document.getElementById('generateVectorsBtn').addEventListener('click', function() {
            const btn = this;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 生成中...';
            
            fetch('test_feature_storage.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=generate_test_vectors'
            })
            .then(response => response.json())
            .then(result => {
                const resultDiv = document.getElementById('generateResult');
                resultDiv.style.display = 'block';
                
                if (result.status === 'success') {
                    resultDiv.className = 'result-box success-box';
                    resultDiv.innerHTML = `
                        <h5>✅ 測試特徵向量生成成功</h5>
                        <p><strong>訊息:</strong> ${result.message}</p>
                        <p><strong>總數:</strong> ${result.data.total_faces}</p>
                        <p><strong>成功儲存:</strong> ${result.data.saved_count}</p>
                        ${result.data.errors.length > 0 ? `<p><strong>錯誤:</strong></p><ul>${result.data.errors.map(err => `<li>${err}</li>`).join('')}</ul>` : ''}
                    `;
                } else {
                    resultDiv.className = 'result-box error-box';
                    resultDiv.innerHTML = `
                        <h5>❌ 測試特徵向量生成失敗</h5>
                        <p><strong>錯誤:</strong> ${result.message}</p>
                    `;
                }
            })
            .catch(error => {
                const resultDiv = document.getElementById('generateResult');
                resultDiv.style.display = 'block';
                resultDiv.className = 'result-box error-box';
                resultDiv.innerHTML = `
                    <h5>❌ 生成執行失敗</h5>
                    <p><strong>錯誤:</strong> ${error.message}</p>
                `;
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-magic"></i> 生成測試特徵向量';
            });
        });
        
        // 檢查資料庫
        document.getElementById('checkDatabaseBtn').addEventListener('click', function() {
            const btn = this;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 檢查中...';
            
            fetch('test_feature_storage.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=check_database'
            })
            .then(response => response.json())
            .then(result => {
                const resultDiv = document.getElementById('checkResult');
                resultDiv.style.display = 'block';
                
                if (result.status === 'success') {
                    resultDiv.className = 'result-box success-box';
                    resultDiv.innerHTML = `
                        <h5>✅ 資料庫檢查成功</h5>
                        <p><strong>訊息:</strong> ${result.message}</p>
                        <p><strong>特徵向量總數:</strong> ${result.data.total_vectors}</p>
                        ${result.data.vectors.length > 0 ? `
                            <details>
                                <summary>點擊查看詳細資料</summary>
                                <div style="margin-top: 10px;">
                                    ${result.data.vectors.map(vector => `
                                        <div style="background: #e9ecef; padding: 8px; margin: 5px 0; border-radius: 4px;">
                                            <strong>ID:</strong> ${vector.id} | 
                                            <strong>檔案:</strong> ${vector.face_filename} | 
                                            <strong>維度:</strong> ${vector.feature_dimension} | 
                                            <strong>前5個數值:</strong> [${vector.feature_preview.join(', ')}] | 
                                            <strong>建立時間:</strong> ${vector.created_at}
                                        </div>
                                    `).join('')}
                                </div>
                            </details>
                        ` : '<p>資料庫中沒有特徵向量</p>'}
                    `;
                } else {
                    resultDiv.className = 'result-box error-box';
                    resultDiv.innerHTML = `
                        <h5>❌ 資料庫檢查失敗</h5>
                        <p><strong>錯誤:</strong> ${result.message}</p>
                    `;
                }
            })
            .catch(error => {
                const resultDiv = document.getElementById('checkResult');
                resultDiv.style.display = 'block';
                resultDiv.className = 'result-box error-box';
                resultDiv.innerHTML = `
                    <h5>❌ 檢查執行失敗</h5>
                    <p><strong>錯誤:</strong> ${error.message}</p>
                `;
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-eye"></i> 檢查資料庫';
            });
        });
    </script>
</body>
</html>
