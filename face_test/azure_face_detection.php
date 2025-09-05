<?php
// 移除輸出緩衝控制，讓調用者管理
// ob_start(); // 移除這行，避免與調用者的輸出緩衝衝突

ini_set('memory_limit', '512M');
ini_set('max_execution_time', 300);
ini_set('display_errors', 0); // 關閉錯誤顯示，避免影響 JSON 輸出

// BCMath 替代方案
if (!extension_loaded('bcmath')) {
    // 簡單的 BCMath 函數替代
    if (!function_exists('bccomp')) {
        function bccomp($left_operand, $right_operand, $scale = 0) {
            $left = (float)$left_operand;
            $right = (float)$right_operand;
            
            if ($left < $right) return -1;
            if ($left > $right) return 1;
            return 0;
        }
    }
    
    if (!function_exists('bcadd')) {
        function bcadd($left_operand, $right_operand, $scale = 0) {
            return (string)((float)$left_operand + (float)$right_operand);
        }
    }
    
    if (!function_exists('bcmul')) {
        function bcmul($left_operand, $right_operand, $scale = 0) {
            return (string)((float)$left_operand * (float)$right_operand);
        }
    }
    
    if (!function_exists('bcdiv')) {
        function bcdiv($left_operand, $right_operand, $scale = 0) {
            if ((float)$right_operand == 0) {
                return false;
            }
            return (string)((float)$left_operand / (float)$right_operand);
        }
    }
    
    if (!function_exists('bcsub')) {
        function bcsub($left_operand, $right_operand, $scale = 0) {
            return (string)((float)$left_operand - (float)$right_operand);
        }
    }
}

require 'vendor/autoload.php';

putenv('GOOGLE_APPLICATION_CREDENTIALS=' . __DIR__ . '/shining-glyph-465006-i1-8f6de1bb78de.json');

use Google\Cloud\Vision\V1\Client\ImageAnnotatorClient;
use Google\Cloud\Vision\V1\Feature;
use Google\Cloud\Vision\V1\Image;
use Google\Cloud\Vision\V1\AnnotateImageRequest;
use Google\Cloud\Vision\V1\BatchAnnotateImagesRequest;

// 移除 Azure Storage SDK 引用，因為 SDK 沒有安裝
// use MicrosoftAzure\Storage\Blob\BlobRestProxy;
// use MicrosoftAzure\Storage\Common\ServicesBuilder;

class AzureFaceDetection {
    private $connectionString;
    private $containerName;
    private $accountName;
    private $accountKey;
    private $faceDir;
    private $groupDir;
    private $scale = 0.9;
    
    public function __construct() {
        // 嘗試獲取 Azure Storage 連接字串，但不強制要求
        $this->connectionString = getenv('AZURE_STORAGE_CONNECTION_STRING');
        $this->containerName = getenv('AZURE_STORAGE_CONTAINER_NAME') ?: 'photos';
        
        // 如果沒有連接字串，設定為 null，使用簡化模式
        if (!$this->connectionString) {
            $this->connectionString = null;
            $this->accountName = null;
            $this->accountKey = null;
        } else {
            // 解析連接字串
            $parts = explode(';', $this->connectionString);
            foreach ($parts as $part) {
                if (strpos($part, 'AccountName=') === 0) {
                    $this->accountName = substr($part, 12);
                } elseif (strpos($part, 'AccountKey=') === 0) {
                    $this->accountKey = substr($part, 11);
                }
            }
            
            if (!$this->accountName || !$this->accountKey) {
                throw new Exception('Invalid connection string');
            }
        }
        
        // 建立本地暫存目錄
        $this->faceDir = __DIR__ . '/faces';
        $this->groupDir = __DIR__ . '/group';
        
        if (!is_dir($this->faceDir)) mkdir($this->faceDir, 0777, true);
        if (!is_dir($this->groupDir)) mkdir($this->groupDir, 0777, true);
        
        // 不清理 faces 目錄，保留現有的人臉檔案
        // $this->cleanDirectory($this->faceDir);
        $this->cleanDirectory($this->groupDir);
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
    
    // 獲取下一個可用的 face 編號，避免覆蓋現有檔案
    private function getNextFaceIndex() {
        if (!is_dir($this->faceDir)) {
            return 0;
        }
        
        $existing_faces = [];
        $files = glob($this->faceDir . '/face_*.jpg');
        
        foreach ($files as $file) {
            $filename = basename($file);
            if (preg_match('/face_(\d+)\.jpg/', $filename, $matches)) {
                $existing_faces[] = (int)$matches[1];
            }
        }
        
        if (empty($existing_faces)) {
            return 0;
        }
        
        return max($existing_faces) + 1;
    }
    
    // 主要處理方法
    public function processImages($imageUrls) {
        $allFaces = [];
        $faceIndex = $this->getNextFaceIndex();
        
        error_log("📁 下一個 face 編號將從 {$faceIndex} 開始");
        
        foreach ($imageUrls as $imageUrl) {
            try {
                // 下載圖片
                $imagePath = $this->downloadImage($imageUrl);
                if (!$imagePath) continue;
                
                // 處理單張圖片
                $faceMap = $this->processSingleImage($imagePath, $imageUrl, $faceIndex);
                $allFaces = array_merge($allFaces, $faceMap);
                
                // 清理臨時檔案
                unlink($imagePath);
                
            } catch (Exception $e) {
                error_log("處理圖片失敗: " . $e->getMessage());
            }
        }
        
        // 儲存人臉映射
        file_put_contents(__DIR__ . '/face_map.json', json_encode($allFaces, JSON_PRETTY_PRINT));
        
        return $allFaces;
    }
    
    // 處理單張圖片
    private function processSingleImage($imagePath, $originalUrl, &$faceIndex) {
        $faceMap = [];
        
        // 偵測人臉
        $faces = $this->detectFacesInImage($imagePath);
        
        // 處理所有人臉，不限制數量
        $faceMap = $this->extractFacesFromImage($imagePath, $originalUrl, $faces, $faceIndex);
        
        return $faceMap;
    }
    
    // 在圖片中偵測人臉
    private function detectFacesInImage($imagePath) {
        $client = new ImageAnnotatorClient();
        
        $imageData = file_get_contents($imagePath);
        $image = (new Image())->setContent($imageData);
        $feature = (new Feature())->setType(Feature\Type::FACE_DETECTION);
        $request = (new AnnotateImageRequest())->setImage($image)->setFeatures([$feature]);
        
        $batchReq = new BatchAnnotateImagesRequest();
        $batchReq->setRequests([$request]);
        
        $response = $client->batchAnnotateImages($batchReq)->getResponses()[0];
        $client->close();
        
        if ($response->hasError()) {
            return [];
        }
        
        return $response->getFaceAnnotations();
    }
    
    // 從圖片中提取人臉
    private function extractFacesFromImage($imagePath, $originalUrl, $faces, &$faceIndex) {
        $faceMap = [];
        
        // 載入圖片並自動修正方向
        $src = $this->loadImageWithOrientation($imagePath);
        if (!$src) {
            return $faceMap;
        }
        
        $imgW = imagesx($src);
        $imgH = imagesy($src);
        
        // 收集所有人臉框
        $allBoxes = [];
        foreach ($faces as $face) {
            $vertices = $face->getBoundingPoly()->getVertices();
            if (count($vertices) < 2) continue;
            
            $x1 = $vertices[0]->getX() ?? 0;
            $y1 = $vertices[0]->getY() ?? 0;
            $x2 = $vertices[2]->getX() ?? ($x1 + 1);
            $y2 = $vertices[2]->getY() ?? ($y1 + 1);
            
            // 步驟 1: 計算原始人臉尺寸
            $faceWidth = $x2 - $x1;
            $faceHeight = $y2 - $y1;
            $faceSize = max($faceWidth, $faceHeight); // 使用較大的邊作為人臉尺寸
            
            // 步驟 2: 使用固定的簡單邊框
            $margin = 8; // 固定 8px 邊框，簡單有效
            
            // 確保邊框大小為整數
            $margin = intval($margin);
            
            // 步驟 3: 計算裁切區域
            $crop_x1 = $x1 - $margin;
            $crop_y1 = $y1 - $margin;
            $crop_x2 = $x2 + $margin;
            $crop_y2 = $y2 + $margin;
            
            // 步驟 4: 確保裁切區域不超出圖片邊界
            $final_x1 = max(0, $crop_x1);
            $final_y1 = max(0, $crop_y1);
            $final_x2 = min($imgW, $crop_x2);
            $final_y2 = min($imgH, $crop_y2);
            
            // 計算最終的裁切尺寸
            $w = $final_x2 - $final_x1;
            $h = $final_y2 - $final_y1;
            
            // 品質檢查：確保裁切區域有合理的尺寸
            if ($w <= 20 || $h <= 20) {
                error_log("裁切尺寸太小，跳過: {$w}x{$h}, 原始臉大小: {$faceSize}px, 邊框: {$margin}px");
                continue;
            }
            
            // 記錄成功的人臉資訊
            error_log("成功計算裁切區域: 臉大小 {$faceSize}px, 邊框 {$margin}px, 裁切尺寸 {$w}x{$h}");
            
            $allBoxes[] = [
                'x' => $final_x1, 
                'y' => $final_y1, 
                'width' => $w, 
                'height' => $h,
                'original_face_size' => $faceSize,
                'margin_used' => $margin,
                'crop_area' => "({$final_x1},{$final_y1}) to ({$final_x2},{$final_y2})",
                'method' => 'simple_8px_margin'
            ];
        }
        
        // 去除重複人臉 - 調整閾值以適應大人臉
        $finalBoxes = $this->removeDuplicateFaces($allBoxes, 0.4); // 降低重複檢測閾值，適應大人臉
        
        // 裁切並儲存人臉
        foreach ($finalBoxes as $box) {
            $crop = imagecrop($src, $box);
            if ($crop) {
                $fname = "face_{$faceIndex}.jpg";
                $fpath = "{$this->faceDir}/{$fname}";
                
                // 直接儲存，不進行圖片增強（提升速度）
                imagejpeg($crop, $fpath, 90);
                imagedestroy($crop);
                
                try {
                    $azureUrl = $this->uploadFaceToAzure($fpath, $fname);
                    $faceMap[$fname] = [
                        'original_image' => $originalUrl,
                        'original_name' => basename($originalUrl),
                        'azure_url' => $azureUrl,
                        'local_path' => $fpath,
                        'face_index' => $faceIndex,
                        'face_size' => $box['original_face_size'] ?? 0,
                        'margin_used' => $box['margin_used'] ?? 0,
                        'crop_dimensions' => "{$box['width']}x{$box['height']}"
                    ];
                    $faceIndex++;
                } catch (Exception $e) {
                    error_log("上傳人臉失敗: " . $e->getMessage());
                }
            }
        }
        
        imagedestroy($src);
        return $faceMap;
    }
    
    // 下載圖片
    private function downloadImage($url) {
        $tempPath = tempnam(sys_get_temp_dir(), 'image_');
        $content = file_get_contents($url);
        
        if ($content === false) {
            return false;
        }
        
        file_put_contents($tempPath, $content);
        return $tempPath;
    }
    
    // 上傳人臉到 Azure Storage
    public function uploadFaceToAzure($localPath, $fileName) {
        if (!$this->connectionString) {
            // 如果沒有 Azure Storage 連接字串，則回傳本地路徑
            return $localPath;
        }

        try {
            // 使用 Azure Storage REST API 上傳檔案到 face/ 資料夾
            $blobName = "face/" . $fileName;
            $url = "https://{$this->accountName}.blob.core.windows.net/{$this->containerName}/{$blobName}";
            
            // 讀取檔案內容
            $fileContent = file_get_contents($localPath);
            if ($fileContent === false) {
                throw new Exception("無法讀取檔案: $localPath");
            }
            
            // 準備 HTTP 請求
            $contentLength = strlen($fileContent);
            $date = gmdate('D, d M Y H:i:s T');
            
            // 生成簽名
            $stringToSign = "PUT\n\n\n{$contentLength}\n\nimage/jpeg\n\n\n\n\n\n\nx-ms-blob-type:BlockBlob\nx-ms-date:{$date}\nx-ms-version:2020-04-08\n/{$this->accountName}/{$this->containerName}/{$blobName}";
            $signature = base64_encode(hash_hmac('sha256', $stringToSign, base64_decode($this->accountKey), true));
            
            // 設定 HTTP 標頭
            $headers = [
                "Authorization: SharedKey {$this->accountName}:{$signature}",
                "x-ms-date: {$date}",
                "x-ms-version: 2020-04-08",
                "x-ms-blob-type: BlockBlob",
                "Content-Type: image/jpeg",
                "Content-Length: {$contentLength}"
            ];
            
            // 發送 HTTP 請求
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fileContent);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 201) {
                // 上傳成功，回傳 Azure Blob URL
                return $url;
            } else {
                error_log("Azure Storage 上傳失敗，HTTP 代碼: $httpCode, 回應: $response");
                // 上傳失敗，回傳本地路徑
                return $localPath;
            }
            
        } catch (Exception $e) {
            error_log("Azure Storage 上傳錯誤: " . $e->getMessage());
            // 發生錯誤，回傳本地路徑
            return $localPath;
        }
    }
    
    // 去除重複人臉
    private function removeDuplicateFaces($boxes, $threshold = 0.5) {
        $uniqueBoxes = [];
        
        foreach ($boxes as $box) {
            $isDuplicate = false;
            
            foreach ($uniqueBoxes as $existingBox) {
                $iou = $this->calculateIOU($box, $existingBox);
                if ($iou > $threshold) {
                    $isDuplicate = true;
                    break;
                }
            }
            
            if (!$isDuplicate) {
                $uniqueBoxes[] = $box;
            }
        }
        
        return $uniqueBoxes;
    }
    
    // 圖片品質增強
    private function enhanceImage($image) {
        // 調整對比度和亮度
        $width = imagesx($image);
        $height = imagesy($image);
        
        // 創建增強後的圖片
        $enhanced = imagecreatetruecolor($width, $height);
        
        // 設定對比度和亮度參數
        $contrast = 1.2;  // 對比度增強 20%
        $brightness = 10; // 亮度增加 10
        
        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $rgb = imagecolorat($image, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                
                // 應用對比度和亮度調整
                $r = min(255, max(0, ($r - 128) * $contrast + 128 + $brightness));
                $g = min(255, max(0, ($g - 128) * $contrast + 128 + $brightness));
                $b = min(255, max(0, ($b - 128) * $contrast + 128 + $brightness));
                
                $color = imagecolorallocate($enhanced, $r, $g, $b);
                imagesetpixel($enhanced, $x, $y, $color);
            }
        }
        
        return $enhanced;
    }
    
    // 計算 IOU (Intersection over Union)
    private function calculateIOU($boxA, $boxB) {
        $xA = max($boxA['x'], $boxB['x']);
        $yA = max($boxA['y'], $boxB['y']);
        $xB = min($boxA['x'] + $boxA['width'], $boxB['x'] + $boxB['width']);
        $yB = min($boxA['y'] + $boxA['height'], $boxB['y'] + $boxB['height']);
        
        $interArea = max(0, $xB - $xA) * max(0, $yB - $yA);
        $boxAArea = $boxA['width'] * $boxA['height'];
        $boxBArea = $boxB['width'] * $boxB['height'];
        
        return $interArea / ($boxAArea + $boxBArea - $interArea);
    }
    
    // 獲取裁切統計資訊
    public function getCropStatistics() {
        $stats = [
            'total_faces_processed' => 0,
            'faces_by_size_category' => [
                'small' => 0,      // < 80px
                'medium' => 0,     // 80-250px
                'large' => 0       // > 250px
            ],
            'average_margin_used' => 0,
            'margin_distribution' => [
                '5' => 0,
                '15-30' => 0,
                '50' => 0
            ]
        ];
        
        // 掃描 faces 目錄統計資訊
        if (is_dir($this->faceDir)) {
            $faceFiles = glob($this->faceDir . '/*.jpg');
            $stats['total_faces_processed'] = count($faceFiles);
            
            // 這裡可以添加更詳細的統計邏輯
            // 例如分析每個裁切後的人臉圖片
        }
        
        return $stats;
    }
    
    // 優化裁切參數的建議方法
    public function suggestOptimalCropParameters($faceSize) {
        $suggestions = [];
        
        if ($faceSize < 80) {
            $suggestions = [
                'margin' => 5,
                'reason' => '小臉使用最小邊框，減少背景干擾',
                'quality_tip' => '適合合照中的人臉提取'
            ];
        } elseif ($faceSize < 250) {
            // 計算建議的邊框大小
            $minSize = 80;
            $maxSize = 250;
            $minMargin = 5;
            $maxMargin = 30;
            $ratio = ($faceSize - $minSize) / ($maxSize - $minSize);
            $suggestedMargin = intval($minMargin + ($ratio * ($maxMargin - $minMargin)));
            
            $suggestions = [
                'margin' => $suggestedMargin,
                'reason' => '中等臉使用動態邊框，平衡細節和背景',
                'quality_tip' => '邊框大小根據臉的大小線性調整'
            ];
        } else {
            $suggestions = [
                'margin' => 50,
                'reason' => '大臉使用大邊框，包含更多臉部特徵',
                'quality_tip' => '適合自拍或特寫照片'
            ];
        }
        
        return $suggestions;
    }
    
    // 性能監控和品質評估
    public function getPerformanceMetrics() {
        $metrics = [
            'processing_time' => 0,
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'face_quality_scores' => [],
            'crop_efficiency' => 0,
            'error_rate' => 0
        ];
        
        // 計算處理時間（如果可用）
        if (isset($this->startTime)) {
            $metrics['processing_time'] = microtime(true) - $this->startTime;
        }
        
        // 分析裁切品質
        if (is_dir($this->faceDir)) {
            $faceFiles = glob($this->faceDir . '/*.jpg');
            $totalFaces = count($faceFiles);
            
            if ($totalFaces > 0) {
                $metrics['crop_efficiency'] = $totalFaces;
                
                // 分析每個裁切後的人臉品質
                foreach ($faceFiles as $faceFile) {
                    $qualityScore = $this->assessFaceQuality($faceFile);
                    $metrics['face_quality_scores'][] = $qualityScore;
                }
            }
        }
        
        return $metrics;
    }
    
    // 評估人臉品質
    private function assessFaceQuality($faceImagePath) {
        $quality = [
            'file_path' => basename($faceImagePath),
            'file_size' => filesize($faceImagePath),
            'dimensions' => getimagesize($faceImagePath),
            'quality_score' => 0,
            'issues' => []
        ];
        
        // 檢查檔案大小
        if ($quality['file_size'] < 1000) {
            $quality['issues'][] = '檔案過小，可能品質不佳';
            $quality['quality_score'] -= 20;
        }
        
        // 檢查圖片尺寸
        if ($quality['dimensions']) {
            $width = $quality['dimensions'][0];
            $height = $quality['dimensions'][1];
            
            if ($width < 50 || $height < 50) {
                $quality['issues'][] = '圖片尺寸過小';
                $quality['quality_score'] -= 30;
            } elseif ($width > 800 || $height > 800) {
                $quality['issues'][] = '圖片尺寸過大';
                $quality['quality_score'] -= 10;
            }
            
            // 檢查長寬比
            $aspectRatio = $width / $height;
            if ($aspectRatio < 0.5 || $aspectRatio > 2.0) {
                $quality['issues'][] = '長寬比異常';
                $quality['quality_score'] -= 15;
            }
        }
        
        // 基礎分數
        $quality['quality_score'] = max(0, 100 + $quality['quality_score']);
        
        return $quality;
    }
    
    // 開始性能監控
    public function startPerformanceMonitoring() {
        $this->startTime = microtime(true);
        $this->initialMemory = memory_get_usage(true);
    }
    
    // 生成品質報告
    public function generateQualityReport() {
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'performance_metrics' => $this->getPerformanceMetrics(),
            'crop_statistics' => $this->getCropStatistics(),
            'quality_assessment' => [],
            'recommendations' => []
        ];
        
        // 分析裁切品質
        if (is_dir($this->faceDir)) {
            $faceFiles = glob($this->faceDir . '/*.jpg');
            foreach ($faceFiles as $faceFile) {
                $quality = $this->assessFaceQuality($faceFile);
                $report['quality_assessment'][] = $quality;
            }
        }
        
        // 生成改進建議
        $report['recommendations'] = $this->generateImprovementRecommendations($report);
        
        return $report;
    }
    
    // 生成改進建議
    private function generateImprovementRecommendations($report) {
        $recommendations = [];
        
        // 分析品質分數
        $qualityScores = array_column($report['quality_assessment'], 'quality_score');
        $avgQuality = array_sum($qualityScores) / count($qualityScores);
        
        if ($avgQuality < 70) {
            $recommendations[] = '整體品質偏低，建議調整裁切參數';
        }
        
        // 分析記憶體使用
        $memoryUsage = $report['performance_metrics']['memory_usage'];
        if ($memoryUsage > 100 * 1024 * 1024) { // 100MB
            $recommendations[] = '記憶體使用較高，建議優化圖片處理流程';
        }
        
        // 分析處理時間
        $processingTime = $report['performance_metrics']['processing_time'];
        if ($processingTime > 30) { // 30秒
            $recommendations[] = '處理時間較長，建議檢查圖片大小和數量';
        }
        
        return $recommendations;
    }
    
    // 使用修正版腳本執行人臉分群
    public function groupFacesWithFixedScript() {
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
            
            // 使用修正版腳本
            $scriptPath = __DIR__ . '/group_faces_azure_class_fix.py';
            
            if (!file_exists($scriptPath)) {
                throw new Exception("修正版腳本不存在: $scriptPath");
            }
            
            // 設定環境變數
            $envVars = "PYTHONPATH=/usr/local/lib/python3.9/dist-packages:/usr/lib/python3/dist-packages";
            
            // 執行修正版腳本
            $command = "cd " . __DIR__ . " && $envVars $python $scriptPath 2>&1";
            exec($command, $output, $returnCode);
            
            if ($returnCode !== 0) {
                throw new Exception("修正版腳本執行失敗: " . implode("\n", $output));
            }
            
            // 上傳分群結果到 Azure Storage
            $this->uploadGroupResultsToAzure();
            
            return $output;
            
        } catch (Exception $e) {
            throw new Exception("修正版人臉分群失敗: " . $e->getMessage());
        }
    }
    
    // 上傳分群結果到 Azure Storage
    private function uploadGroupResultsToAzure() {
        if (!$this->connectionString) {
            return; // 如果沒有 Azure Storage 連接字串，跳過上傳
        }
        
        try {
            $groupDir = $this->groupDir;
            if (!is_dir($groupDir)) {
                error_log("本地 group 目錄不存在: $groupDir");
                return;
            }
            
            $groupFolders = glob($groupDir . '/people_*', GLOB_ONLYDIR);
            if (empty($groupFolders)) {
                error_log("沒有找到分群資料夾");
                return;
            }
            
            foreach ($groupFolders as $groupFolder) {
                $groupName = basename($groupFolder);
                $faceFiles = glob($groupFolder . '/*.jpg');
                
                foreach ($faceFiles as $faceFile) {
                    $fileName = basename($faceFile);
                    $blobName = "group/{$groupName}/{$fileName}";
                    
                    try {
                        $this->uploadFileToAzure($faceFile, $blobName);
                        error_log("已上傳分群圖片: {$blobName}");
                    } catch (Exception $e) {
                        error_log("上傳分群圖片失敗 {$blobName}: " . $e->getMessage());
                    }
                }
            }
            
            error_log("分群結果上傳完成");
            
        } catch (Exception $e) {
            error_log("上傳分群結果失敗: " . $e->getMessage());
        }
    }
    
    // 通用檔案上傳方法
    private function uploadFileToAzure($localPath, $blobName) {
        if (!$this->connectionString) {
            return $localPath;
        }
        
        try {
            $url = "https://{$this->accountName}.blob.core.windows.net/{$this->containerName}/{$blobName}";
            
            $fileContent = file_get_contents($localPath);
            if ($fileContent === false) {
                throw new Exception("無法讀取檔案: $localPath");
            }
            
            $contentLength = strlen($fileContent);
            $date = gmdate('D, d M Y H:i:s T');
            
            $stringToSign = "PUT\n\n\n{$contentLength}\n\nimage/jpeg\n\n\n\n\n\n\nx-ms-blob-type:BlockBlob\nx-ms-date:{$date}\nx-ms-version:2020-04-08\n/{$this->accountName}/{$this->containerName}/{$blobName}";
            $signature = base64_encode(hash_hmac('sha256', $stringToSign, base64_decode($this->accountKey), true));
            
            $headers = [
                "Authorization: SharedKey {$this->accountName}:{$signature}",
                "x-ms-date: {$date}",
                "x-ms-version: 2020-04-08",
                "x-ms-blob-type: BlockBlob",
                "Content-Type: image/jpeg",
                "Content-Length: {$contentLength}"
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fileContent);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 201) {
                return $url;
            } else {
                error_log("Azure Storage 上傳失敗，HTTP 代碼: $httpCode, 回應: $response");
                return $localPath;
            }
            
        } catch (Exception $e) {
            error_log("Azure Storage 上傳錯誤: " . $e->getMessage());
            return $localPath;
        }
    }
    
    // 載入圖片並自動修正方向
    private function loadImageWithOrientation($imagePath) {
        // 檢查是否支援 EXIF 擴展
        if (!extension_loaded('exif')) {
            // 如果不支援 EXIF，使用標準載入方式
            return $this->loadImageStandard($imagePath);
        }
        
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
}
?> 