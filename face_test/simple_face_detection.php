<?php
/**
 * 簡化版人臉辨識類別
 * 專門用於 album_by_person.php 的人臉辨識功能
 */

ini_set('memory_limit', '512M');
ini_set('max_execution_time', 300);
ini_set('display_errors', 0);

// 檢查必要的擴展
if (!extension_loaded('gd')) {
    throw new Exception('GD 擴展未安裝');
}

if (!extension_loaded('exif')) {
    throw new Exception('EXIF 擴展未安裝');
}

class SimpleFaceDetection {
    private $faceDir;
    private $groupDir;
    private $scale = 0.9;
    private $googleCredentials;
    
    public function __construct() {
        // 建立本地暫存目錄
        $this->faceDir = __DIR__ . '/faces';
        $this->groupDir = __DIR__ . '/group';
        
        if (!is_dir($this->faceDir)) mkdir($this->faceDir, 0777, true);
        if (!is_dir($this->groupDir)) mkdir($this->groupDir, 0777, true);
        
        $this->cleanDirectory($this->faceDir);
        $this->cleanDirectory($this->groupDir);
        
        // 檢查 Google Cloud Vision API 憑證
        $this->googleCredentials = __DIR__ . '/shining-glyph-465006-i1-8f6de1bb78de.json';
        if (!file_exists($this->googleCredentials)) {
            throw new Exception('Google Cloud Vision API 憑證檔案不存在');
        }
        
        putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $this->googleCredentials);
    }
    
    // 清理目錄
    private function cleanDirectory($dir) {
        if (!is_dir($dir)) return;
        
        $files = glob($dir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
    
    // 主要處理方法
    public function processImages($imagePaths) {
        $allFaces = [];
        $faceIndex = 0;
        
        foreach ($imagePaths as $imagePath) {
            try {
                // 處理單張圖片
                $faceMap = $this->processSingleImage($imagePath, $faceIndex);
                $allFaces = array_merge($allFaces, $faceMap);
                
            } catch (Exception $e) {
                error_log("處理圖片失敗: " . $e->getMessage());
            }
        }
        
        // 儲存人臉映射
        file_put_contents(__DIR__ . '/face_map.json', json_encode($allFaces, JSON_PRETTY_PRINT));
        
        return $allFaces;
    }
    
    // 處理單張圖片
    private function processSingleImage($imagePath, &$faceIndex) {
        $faceMap = [];
        
        // 偵測人臉
        $faces = $this->detectFacesInImage($imagePath);
        
        // 處理所有人臉
        $faceMap = $this->extractFacesFromImage($imagePath, $faces, $faceIndex);
        
        return $faceMap;
    }
    
    // 在圖片中偵測人臉
    private function detectFacesInImage($imagePath) {
        try {
            // 使用 Google Cloud Vision API
            $client = new Google\Cloud\Vision\V1\Client\ImageAnnotatorClient();
            
            $imageData = file_get_contents($imagePath);
            $image = (new Google\Cloud\Vision\V1\Image())->setContent($imageData);
            $feature = (new Google\Cloud\Vision\V1\Feature())->setType(Google\Cloud\Vision\V1\Feature\Type::FACE_DETECTION);
            $request = (new Google\Cloud\Vision\V1\AnnotateImageRequest())->setImage($image)->setFeatures([$feature]);
            
            $batchReq = new Google\Cloud\Vision\V1\BatchAnnotateImagesRequest();
            $batchReq->setRequests([$request]);
            
            $response = $client->batchAnnotateImages($batchReq)->getResponses()[0];
            $client->close();
            
            if ($response->hasError()) {
                error_log("Vision API 錯誤: " . $response->getError()->getMessage());
                return [];
            }
            
            return $response->getFaceAnnotations();
            
        } catch (Exception $e) {
            error_log("Vision API 調用失敗: " . $e->getMessage());
            return [];
        }
    }
    
    // 從圖片中提取人臉
    private function extractFacesFromImage($imagePath, $faces, &$faceIndex) {
        $faceMap = [];
        
        if (empty($faces)) {
            return $faceMap;
        }
        
        // 載入圖片
        $src = $this->loadImageWithOrientation($imagePath);
        if (!$src) {
            return $faceMap;
        }
        
        $imgW = imagesx($src);
        $imgH = imagesy($src);
        
        // 處理每個人臉
        foreach ($faces as $face) {
            $vertices = $face->getBoundingPoly()->getVertices();
            if (count($vertices) < 2) continue;
            
            $x1 = $vertices[0]->getX() ?? 0;
            $y1 = $vertices[0]->getY() ?? 0;
            $x2 = $vertices[2]->getX() ?? ($x1 + 1);
            $y2 = $vertices[2]->getY() ?? ($y1 + 1);
            
            // 計算人臉尺寸
            $faceWidth = $x2 - $x1;
            $faceHeight = $y2 - $y1;
            
            // 添加邊框
            $margin = 8;
            $crop_x1 = max(0, $x1 - $margin);
            $crop_y1 = max(0, $y1 - $margin);
            $crop_x2 = min($imgW, $x2 + $margin);
            $crop_y2 = min($imgH, $y2 + $margin);
            
            $w = $crop_x2 - $crop_x1;
            $h = $crop_y2 - $crop_y1;
            
            if ($w <= 0 || $h <= 0) continue;
            
            // 裁切人臉
            $crop = imagecrop($src, [
                'x' => $crop_x1,
                'y' => $crop_y1,
                'width' => $w,
                'height' => $h
            ]);
            
            if ($crop) {
                $fname = "face_{$faceIndex}.jpg";
                $fpath = "{$this->faceDir}/{$fname}";
                
                // 儲存人臉圖片
                imagejpeg($crop, $fpath, 90);
                imagedestroy($crop);
                
                $faceMap[$fname] = [
                    'original_image' => $imagePath,
                    'original_name' => basename($imagePath),
                    'local_path' => $fpath,
                    'face_index' => $faceIndex,
                    'face_size' => "{$faceWidth}x{$faceHeight}",
                    'crop_dimensions' => "{$w}x{$h}"
                ];
                $faceIndex++;
            }
        }
        
        imagedestroy($src);
        return $faceMap;
    }
    
    // 載入圖片並修正方向
    private function loadImageWithOrientation($imagePath) {
        try {
            // 讀取 EXIF 方向信息
            $exif = @exif_read_data($imagePath);
            $orientation = $exif['Orientation'] ?? 1;
            
            // 載入圖片
            $image = $this->loadImageStandard($imagePath);
            if (!$image) {
                return false;
            }
            
            // 根據 EXIF 方向自動修正
            $image = $this->correctImageOrientation($image, $orientation);
            
            return $image;
            
        } catch (Exception $e) {
            error_log("EXIF 處理失敗，使用標準載入: " . $e->getMessage());
            return $this->loadImageStandard($imagePath);
        }
    }
    
    // 標準圖片載入
    private function loadImageStandard($imagePath) {
        $imageInfo = getimagesize($imagePath);
        if (!$imageInfo) {
            return false;
        }
        
        switch ($imageInfo[2]) {
            case IMAGETYPE_JPEG:
                return imagecreatefromjpeg($imagePath);
            case IMAGETYPE_PNG:
                return imagecreatefrompng($imagePath);
            case IMAGETYPE_GIF:
                return imagecreatefromgif($imagePath);
            default:
                return false;
        }
    }
    
    // 修正圖片方向
    private function correctImageOrientation($image, $orientation) {
        $width = imagesx($image);
        $height = imagesy($image);
        
        switch ($orientation) {
            case 2: // 水平翻轉
                $image = $this->flipImage($image, 'horizontal');
                break;
            case 3: // 旋轉 180 度
                $image = imagerotate($image, 180, 0);
                break;
            case 4: // 垂直翻轉
                $image = $this->flipImage($image, 'vertical');
                break;
            case 5: // 水平翻轉 + 逆時針 90 度
                $image = $this->flipImage($image, 'horizontal');
                $image = imagerotate($image, 90, 0);
                break;
            case 6: // 順時針 90 度
                $image = imagerotate($image, -90, 0);
                break;
            case 7: // 水平翻轉 + 順時針 90 度
                $image = $this->flipImage($image, 'horizontal');
                $image = imagerotate($image, -90, 0);
                break;
            case 8: // 逆時針 90 度
                $image = imagerotate($image, 90, 0);
                break;
            default: // 1 或其他：不需要修正
                break;
        }
        
        return $image;
    }
    
    // 圖片翻轉
    private function flipImage($image, $direction) {
        $width = imagesx($image);
        $height = imagesy($image);
        
        $flipped = imagecreatetruecolor($width, $height);
        
        // 保持透明度
        imagealphablending($flipped, false);
        imagesavealpha($flipped, true);
        
        if ($direction === 'horizontal') {
            for ($x = 0; $x < $width; $x++) {
                for ($y = 0; $y < $height; $y++) {
                    $color = imagecolorat($image, $x, $y);
                    imagesetpixel($flipped, $width - 1 - $x, $y, $color);
                }
            }
        } else { // vertical
            for ($x = 0; $x < $width; $x++) {
                for ($y = 0; $y < $height; $y++) {
                    $color = imagecolorat($image, $x, $y);
                    imagesetpixel($flipped, $x, $height - 1 - $y, $color);
                }
            }
        }
        
        return $flipped;
    }
    
    // 執行人臉分群
    public function groupFacesWithPython() {
        try {
            // 尋找可用的 Python 執行檔
            $python = null;
            $pythonPaths = [
                "python3",
                "python",
                "/usr/bin/python3",
                "/usr/local/bin/python3"
            ];
            
            foreach ($pythonPaths as $path) {
                $output = [];
                exec("which $path 2>/dev/null", $output, $returnCode);
                if ($returnCode === 0) {
                    $python = $path;
                    break;
                }
            }
            
            if (!$python) {
                throw new Exception("找不到可用的 Python 執行檔");
            }
            
            // 使用分群腳本
            $scriptPath = __DIR__ . '/group_faces_azure_class_fix.py';
            
            if (!file_exists($scriptPath)) {
                throw new Exception("分群腳本不存在: $scriptPath");
            }
            
            // 執行分群腳本
            $command = "cd " . __DIR__ . " && $python $scriptPath 2>&1";
            exec($command, $output, $returnCode);
            
            if ($returnCode !== 0) {
                throw new Exception("分群腳本執行失敗: " . implode("\n", $output));
            }
            
            return $output;
            
    } catch (Exception $e) {
            throw new Exception("人臉分群失敗: " . $e->getMessage());
        }
    }
    
    // 獲取處理報告
    public function getProcessingReport() {
        $faceFiles = glob($this->faceDir . '/*.jpg');
        $groupFolders = glob($this->groupDir . '/people_*', GLOB_ONLYDIR);
        
        return [
            'faces_detected' => count($faceFiles),
            'groups_created' => count($groupFolders),
            'face_files' => $faceFiles,
            'group_folders' => $groupFolders
        ];
    }
}
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