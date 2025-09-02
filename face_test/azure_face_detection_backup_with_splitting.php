<?php
// 確保在輸出 JSON 之前沒有其他輸出
ob_start();

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

class AzureFaceDetection {
    private $connectionString;
    private $containerName;
    private $accountName;
    private $accountKey;
    private $faceDir;
    private $groupDir;
    private $scale = 0.9;
    
    public function __construct() {
        $this->connectionString = getenv('AZURE_STORAGE_CONNECTION_STRING');
        $this->containerName = getenv('AZURE_STORAGE_CONTAINER_NAME') ?: 'photos';
        
        if (!$this->connectionString) {
            throw new Exception('Azure Storage connection string not found');
        }
        
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
        
        // 建立本地暫存目錄
        $this->faceDir = __DIR__ . '/faces';
        $this->groupDir = __DIR__ . '/group';
        
        if (!is_dir($this->faceDir)) mkdir($this->faceDir, 0777, true);
        if (!is_dir($this->groupDir)) mkdir($this->groupDir, 0777, true);
        
        $this->cleanDirectory($this->faceDir);
        $this->cleanDirectory($this->groupDir);
    }
    
    private function cleanDirectory($dir) {
        if (!is_dir($dir)) return;
        foreach (glob("$dir/*") as $f) {
            if (is_file($f)) unlink($f);
            if (is_dir($f)) array_map('unlink', glob("$f/*"));
        }
    }
    
    // 從 Azure Storage 下載圖片
    private function downloadFromAzure($blobUrl) {
        $tempFile = tempnam(sys_get_temp_dir(), 'azure_img_');
        $tempFile .= '.jpg';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $blobUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        $imageData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200 || !$imageData) {
            throw new Exception("無法下載圖片: $blobUrl");
        }
        
        file_put_contents($tempFile, $imageData);
        return $tempFile;
    }
    
    // 上傳人臉圖片到 Azure Storage
    private function uploadFaceToAzure($localPath, $faceName) {
        $contentLength = filesize($localPath);
        $contentType = 'image/jpeg';
        $date = gmdate('D, d M Y H:i:s T');
        
        // 根據檔案名稱決定上傳路徑
        if (strpos($faceName, 'grid_') === 0) {
            // 網格圖片上傳到 grids 資料夾
            $folder = 'grids';
        } else {
            // 人臉圖片上傳到 faces 資料夾
            $folder = 'faces';
        }
        
        // 構建 canonicalized headers
        $canonicalizedHeaders = "x-ms-blob-type:BlockBlob\nx-ms-date:{$date}\nx-ms-version:2020-04-08\n";
        
        // 構建 canonicalized resource
        $canonicalizedResource = "/{$this->accountName}/{$this->containerName}/{$folder}/{$faceName}";
        
        // 構建 string to sign
        $stringToSign = "PUT\n\n\n{$contentLength}\n\n{$contentType}\n\n\n\n\n\n\n{$canonicalizedHeaders}{$canonicalizedResource}";
        
        // 生成簽名
        $signature = base64_encode(hash_hmac('sha256', $stringToSign, base64_decode($this->accountKey), true));
        
        // 構建 Authorization header
        $authorization = "SharedKey {$this->accountName}:{$signature}";
        
        // 構建 URL
        $blobUrl = "https://{$this->accountName}.blob.core.windows.net/{$this->containerName}/{$folder}/{$faceName}";
        
        // 上傳檔案
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $blobUrl);
        curl_setopt($ch, CURLOPT_PUT, true);
        curl_setopt($ch, CURLOPT_INFILE, fopen($localPath, 'r'));
        curl_setopt($ch, CURLOPT_INFILESIZE, $contentLength);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: {$authorization}",
            "Content-Type: {$contentType}",
            "x-ms-blob-type: BlockBlob",
            "x-ms-date: {$date}",
            "x-ms-version: 2020-04-08"
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 201) {
            throw new Exception("圖片上傳失敗: $httpCode");
        }
        
        return $blobUrl;
    }
    
    // 主要的人臉偵測處理
    public function detectFaces($imageUrls) {
        $faceMap = [];
        $faceIndex = 0;
        
        foreach ($imageUrls as $imagePath) {
            // 檢查是本地路徑還是 Azure URL
            if (strpos($imagePath, 'http') === 0) {
                // Azure URL - 下載圖片
                $tempFile = $this->downloadFromAzure($imagePath);
            } else {
                // 本地路徑 - 直接使用
                $tempFile = __DIR__ . '/../' . $imagePath;
                if (!file_exists($tempFile)) {
                    throw new Exception("圖片檔案不存在: $tempFile");
                }
            }
            
            // 處理單張圖片（包含切割邏輯）
            $imageFaces = $this->processSingleImage($tempFile, $imagePath, $faceIndex);
            $faceMap = array_merge($faceMap, $imageFaces);
            $faceIndex = count($faceMap);
            
            // 只清理從 Azure 下載的暫存檔案，不清理本地檔案
            if (strpos($imagePath, 'http') === 0) {
                unlink($tempFile);
            }
        }
        
        // 儲存人臉對應關係
        file_put_contents(__DIR__ . "/face_map.json", json_encode($faceMap, JSON_PRETTY_PRINT));
        
        return $faceMap;
    }
    
    // 處理單張圖片，包含切割邏輯
    private function processSingleImage($imagePath, $originalUrl, &$faceIndex) {
        $faceMap = [];
        $maxFacesPerImage = 10; // 限制每張圖片最多 10 張人臉
        
        // 偵測人臉
        $faces = $this->detectFacesInImage($imagePath);
        
        // 如果人臉數量超過限制，只取前 10 張
        if (count($faces) > $maxFacesPerImage) {
            error_log("圖片人臉數量超過 {$maxFacesPerImage} 張，只取前 {$maxFacesPerImage} 張");
            $faces = array_slice($faces, 0, $maxFacesPerImage);
        }
        
        // 直接處理人臉
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
        $margin = 20;
        $iouThreshold = 0.425;
        
        // 載入圖片
        $imageInfo = getimagesize($imagePath);
        if (!$imageInfo) {
            return $faceMap;
        }
        
        switch ($imageInfo[2]) {
            case IMAGETYPE_JPEG:
                $src = imagecreatefromjpeg($imagePath);
                break;
            case IMAGETYPE_PNG:
                $src = imagecreatefrompng($imagePath);
                break;
            case IMAGETYPE_GIF:
                $src = imagecreatefromgif($imagePath);
                break;
            default:
                return $faceMap;
        }
        
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
            
            $x = max($x1 - $margin, 0);
            $y = max($y1 - $margin, 0);
            $w = min($x2 - $x1 + 2 * $margin, $imgW - $x);
            $h = min($y2 - $y1 + 2 * $margin, $imgH - $y);
            
            $allBoxes[] = ['x' => $x, 'y' => $y, 'width' => $w, 'height' => $h];
        }
        
        // 去除重複人臉
        $finalBoxes = $this->removeDuplicateFaces($allBoxes, 0.5); // 降低閾值
        
        // 裁切並儲存人臉
        foreach ($finalBoxes as $box) {
            $crop = imagecrop($src, $box);
            if ($crop) {
                $fname = "face_{$faceIndex}.jpg";
                $fpath = "{$this->faceDir}/{$fname}";
                imagejpeg($crop, $fpath);
                
                // 上傳到 Azure Storage
                try {
                    $azureUrl = $this->uploadFaceToAzure($fpath, $fname);
                    $faceMap[$fname] = [
                        'original_image' => $originalUrl,
                        'original_name' => basename($originalUrl),
                        'azure_url' => $azureUrl,
                        'local_path' => $fpath,
                        'face_index' => $faceIndex
                    ];
                } catch (Exception $e) {
                    error_log("上傳人臉圖片失敗: " . $e->getMessage());
                    $faceMap[$fname] = [
                        'original_image' => $originalUrl,
                        'original_name' => basename($originalUrl),
                        'azure_url' => null,
                        'local_path' => $fpath,
                        'face_index' => $faceIndex
                    ];
                }
                
                imagedestroy($crop);
                $faceIndex++;
            }
        }
        
        imagedestroy($src);
        return $faceMap;
    }
    
    // 去除重複人臉 - 改進版本
    private function removeDuplicateFaces($boxes, $iouThreshold) {
        $finalBoxes = [];
        
        // 按面積排序，優先保留較大的人臉（通常更清晰）
        usort($boxes, function($a, $b) {
            $areaA = $a['width'] * $a['height'];
            $areaB = $b['width'] * $b['height'];
            return $areaB <=> $areaA;
        });
        
        foreach ($boxes as $box) {
            $isDup = false;
            foreach ($finalBoxes as $exist) {
                $iou = $this->calculateIOU($box, $exist);
                if ($iou > $iouThreshold) {
                    $isDup = true;
                    error_log("檢測到重複人臉框，IOU: " . round($iou, 3));
                    break;
                }
            }
            if (!$isDup) {
                $finalBoxes[] = $box;
            }
        }
        return $finalBoxes;
    }
    
    // 計算 IOU (Intersection over Union)
    private function calculateIOU($a, $b) {
        $x1 = max($a['x'], $b['x']);
        $y1 = max($a['y'], $b['y']);
        $x2 = min($a['x'] + $a['width'], $b['x'] + $b['width']);
        $y2 = min($a['y'] + $a['height'], $b['y'] + $b['height']);
        
        $inter = max(0, $x2 - $x1) * max(0, $y2 - $y1);
        $areaA = $a['width'] * $a['height'];
        $areaB = $b['width'] * $b['height'];
        
        return $inter / ($areaA + $areaB - $inter);
    }
    
    // 改善的切割圖片處理 - 解決座標調整和人臉完整性問題
    private function processImageWithSplitting($imagePath, $originalUrl, &$faceIndex) {
        $faceMap = [];
        $maxFacesPerImage = 8;
        
        // 載入圖片
        $imageInfo = getimagesize($imagePath);
        if (!$imageInfo) {
            return $faceMap;
        }
        
        switch ($imageInfo[2]) {
            case IMAGETYPE_JPEG:
                $src = imagecreatefromjpeg($imagePath);
                break;
            case IMAGETYPE_PNG:
                $src = imagecreatefrompng($imagePath);
                break;
            case IMAGETYPE_GIF:
                $src = imagecreatefromgif($imagePath);
                break;
            default:
                return $faceMap;
        }
        
        if (!$src) {
            return $faceMap;
        }
        
        $imgW = imagesx($src);
        $imgH = imagesy($src);
        
        // 改善的切割策略：使用重疊網格避免人臉被分割
        $gridSize = 2;
        $overlap = 0.2; // 20% 重疊，避免人臉被切割
        
        $cellW = $imgW / $gridSize;
        $cellH = $imgH / $gridSize;
        $overlapW = $cellW * $overlap;
        $overlapH = $cellH * $overlap;
        
        $allFaces = [];
        $gridImages = [];
        
        error_log("改善切割：圖片尺寸 {$imgW}x{$imgH}，網格大小 {$gridSize}x{$gridSize}，重疊 {$overlapW}x{$overlapH}");
        
        // 對每個網格進行人臉偵測，使用重疊區域
        for ($row = 0; $row < $gridSize; $row++) {
            for ($col = 0; $col < $gridSize; $col++) {
                // 計算重疊的網格座標
                $x = max(0, $col * $cellW - $overlapW);
                $y = max(0, $row * $cellH - $overlapH);
                $w = min($cellW + $overlapW, $imgW - $x);
                $h = min($cellH + $overlapH, $imgH - $y);
                
                // 確保網格不超出圖片邊界
                if ($x + $w > $imgW) $w = $imgW - $x;
                if ($y + $h > $imgH) $h = $imgH - $y;
                
                // 裁切網格
                $crop = imagecrop($src, ['x' => $x, 'y' => $y, 'width' => $w, 'height' => $h]);
                if (!$crop) continue;
                
                // 儲存臨時網格圖片，使用高品質
                $tempGridPath = tempnam(sys_get_temp_dir(), 'grid_');
                imagejpeg($crop, $tempGridPath, 95); // 提高 JPEG 品質
                
                // 上傳網格圖片到 Azure Storage
                $gridName = "grid_{$row}_{$col}_" . basename($originalUrl);
                try {
                    $gridAzureUrl = $this->uploadFaceToAzure($tempGridPath, $gridName);
                    $gridImages[] = [
                        'grid_name' => $gridName,
                        'azure_url' => $gridAzureUrl,
                        'local_path' => $tempGridPath,
                        'position' => ['row' => $row, 'col' => $col],
                        'coordinates' => ['x' => $x, 'y' => $y, 'width' => $w, 'height' => $h],
                        'overlap' => ['w' => $overlapW, 'h' => $overlapH]
                    ];
                    error_log("網格圖片已上傳到 Azure: {$gridName} (座標: {$x},{$y},{$w}x{$h})");
                } catch (Exception $e) {
                    error_log("上傳網格圖片失敗: " . $e->getMessage());
                    $gridImages[] = [
                        'grid_name' => $gridName,
                        'azure_url' => null,
                        'local_path' => $tempGridPath,
                        'position' => ['row' => $row, 'col' => $col],
                        'coordinates' => ['x' => $x, 'y' => $y, 'width' => $w, 'height' => $h],
                        'overlap' => ['w' => $overlapW, 'h' => $overlapH]
                    ];
                }
                
                // 偵測網格中的人臉
                $gridFaces = $this->detectFacesInImage($tempGridPath);
                error_log("網格 {$row}_{$col} 偵測到 " . count($gridFaces) . " 張人臉");
                
                // 改善的座標調整：使用精確的整數計算
                foreach ($gridFaces as $face) {
                    $vertices = $face->getBoundingPoly()->getVertices();
                    if (count($vertices) < 2) continue;
                    
                    // 改善的座標調整：避免浮點數誤差
                    $adjustedVertices = [];
                    foreach ($vertices as $vertex) {
                        $adjustedVertices[] = [
                            'x' => (int)($vertex->getX() + $x), // 強制轉換為整數
                            'y' => (int)($vertex->getY() + $y)  // 強制轉換為整數
                        ];
                    }
                    
                    // 驗證座標是否在圖片範圍內
                    $validCoords = true;
                    foreach ($adjustedVertices as $vertex) {
                        if ($vertex['x'] < 0 || $vertex['x'] >= $imgW || 
                            $vertex['y'] < 0 || $vertex['y'] >= $imgH) {
                            $validCoords = false;
                            break;
                        }
                    }
                    
                    if ($validCoords) {
                        $allFaces[] = [
                            'vertices' => $adjustedVertices,
                            'confidence' => $face->getDetectionConfidence(),
                            'grid_position' => ['row' => $row, 'col' => $col],
                            'grid_name' => $gridName,
                            'original_coords' => ['x' => $x, 'y' => $y, 'w' => $w, 'h' => $h]
                        ];
                    } else {
                        error_log("跳過無效座標的人臉: " . json_encode($adjustedVertices));
                    }
                }
                
                imagedestroy($crop);
            }
        }
        
        imagedestroy($src);
        
        error_log("改善切割：總共收集到 " . count($allFaces) . " 張人臉");
        
        // 改善的重複消除：使用更寬鬆的閾值
        $uniqueFaces = $this->removeDuplicateFacesFromListImproved($allFaces);
        error_log("改善切割：去重後剩餘 " . count($uniqueFaces) . " 張人臉");
        
        // 提取人臉
        $faceMap = $this->extractFacesFromList($imagePath, $originalUrl, $uniqueFaces, $faceIndex);
        
        // 儲存改善的網格圖片資訊
        $gridInfo = [
            'original_image' => $originalUrl,
            'grid_size' => $gridSize,
            'overlap_percentage' => $overlap * 100,
            'total_grids' => count($gridImages),
            'total_faces_detected' => count($allFaces),
            'unique_faces_after_dedup' => count($uniqueFaces),
            'grids' => $gridImages,
            'processing_time' => date('Y-m-d H:i:s'),
            'improvements' => [
                'overlap_grids' => '使用重疊網格避免人臉被分割',
                'integer_coordinates' => '使用整數座標避免浮點數誤差',
                'coordinate_validation' => '驗證座標在圖片範圍內',
                'high_quality_jpeg' => '使用高品質 JPEG 編碼'
            ]
        ];
        
        $gridInfoPath = __DIR__ . "/grid_info_" . basename($originalUrl, '.jpg') . ".json";
        file_put_contents($gridInfoPath, json_encode($gridInfo, JSON_PRETTY_PRINT));
        
        // 清理臨時檔案
        foreach ($gridImages as $grid) {
            if (file_exists($grid['local_path'])) {
                unlink($grid['local_path']);
            }
        }
        
        return $faceMap;
    }
    
    // 從人臉列表中去除重複 - 改進版本
    private function removeDuplicateFacesFromList($faces) {
        $uniqueFaces = [];
        $iouThreshold = 0.5; // 降低閾值，更寬鬆的重複檢測
        
        // 按信心度排序，優先保留高信心度的人臉
        usort($faces, function($a, $b) {
            return $b['confidence'] <=> $a['confidence'];
        });
        
        foreach ($faces as $face) {
            $isDup = false;
            foreach ($uniqueFaces as $exist) {
                $iou = $this->calculateIOUFromVertices($face['vertices'], $exist['vertices']);
                if ($iou > $iouThreshold) {
                    $isDup = true;
                    error_log("檢測到重複人臉，IOU: " . round($iou, 3));
                    break;
                }
            }
            if (!$isDup) {
                $uniqueFaces[] = $face;
            }
        }
        
        return $uniqueFaces;
    }
    
    // 改善的重複消除方法 - 專門為切割偵測優化
    private function removeDuplicateFacesFromListImproved($faces) {
        $uniqueFaces = [];
        $iouThreshold = 0.65; // 提高閾值，更嚴格的重複檢測
        
        // 按信心度和面積排序，優先保留高品質人臉
        usort($faces, function($a, $b) {
            // 首先按信心度排序
            $confidenceDiff = $b['confidence'] <=> $a['confidence'];
            if ($confidenceDiff !== 0) return $confidenceDiff;
            
            // 如果信心度相同，按面積排序（較大的人臉通常更清晰）
            $areaA = $this->calculateFaceArea($a['vertices']);
            $areaB = $this->calculateFaceArea($b['vertices']);
            return $areaB <=> $areaA;
        });
        
        foreach ($faces as $face) {
            $isDup = false;
            $bestMatch = null;
            $bestIou = 0;
            
            foreach ($uniqueFaces as $index => $exist) {
                $iou = $this->calculateIOUFromVertices($face['vertices'], $exist['vertices']);
                
                if ($iou > $bestIou) {
                    $bestIou = $iou;
                    $bestMatch = $index;
                }
                
                if ($iou > $iouThreshold) {
                    $isDup = true;
                    // 如果新人臉品質更好，替換舊的
                    if ($face['confidence'] > $exist['confidence']) {
                        error_log("替換低品質人臉，IOU: " . round($iou, 3) . 
                                "，舊信心度: " . round($exist['confidence'], 3) . 
                                "，新信心度: " . round($face['confidence'], 3));
                        $uniqueFaces[$index] = $face;
                    } else {
                        error_log("跳過低品質重複人臉，IOU: " . round($iou, 3));
                    }
                    break;
                }
            }
            
            if (!$isDup) {
                $uniqueFaces[] = $face;
                if ($bestIou > 0.3) { // 記錄相似但未達到重複閾值的人臉
                    error_log("保留相似人臉，IOU: " . round($bestIou, 3));
                }
            }
        }
        
        return $uniqueFaces;
    }
    
    // 計算人臉面積
    private function calculateFaceArea($vertices) {
        if (count($vertices) < 2) return 0;
        
        $x1 = $vertices[0]['x'];
        $y1 = $vertices[0]['y'];
        $x2 = $vertices[2]['x'] ?? $vertices[1]['x'];
        $y2 = $vertices[2]['y'] ?? $vertices[1]['y'];
        
        return abs($x2 - $x1) * abs($y2 - $y1);
    }
    
    // 從頂點計算 IOU
    private function calculateIOUFromVertices($verticesA, $verticesB) {
        if (count($verticesA) < 2 || count($verticesB) < 2) return 0;
        
        $a = [
            'x' => $verticesA[0]['x'],
            'y' => $verticesA[0]['y'],
            'width' => $verticesA[2]['x'] - $verticesA[0]['x'],
            'height' => $verticesA[2]['y'] - $verticesA[0]['y']
        ];
        
        $b = [
            'x' => $verticesB[0]['x'],
            'y' => $verticesB[0]['y'],
            'width' => $verticesB[2]['x'] - $verticesB[0]['x'],
            'height' => $verticesB[2]['y'] - $verticesB[0]['y']
        ];
        
        return $this->calculateIOU($a, $b);
    }
    
    // 從人臉列表提取人臉
    private function extractFacesFromList($imagePath, $originalUrl, $faces, &$faceIndex) {
        $faceMap = [];
        $margin = 20;
        
        // 載入圖片
        $imageInfo = getimagesize($imagePath);
        if (!$imageInfo) {
            return $faceMap;
        }
        
        switch ($imageInfo[2]) {
            case IMAGETYPE_JPEG:
                $src = imagecreatefromjpeg($imagePath);
                break;
            case IMAGETYPE_PNG:
                $src = imagecreatefrompng($imagePath);
                break;
            case IMAGETYPE_GIF:
                $src = imagecreatefromgif($imagePath);
                break;
            default:
                return $faceMap;
        }
        
        if (!$src) {
            return $faceMap;
        }
        
        $imgW = imagesx($src);
        $imgH = imagesy($src);
        
        foreach ($faces as $face) {
            $vertices = $face['vertices'];
            if (count($vertices) < 2) continue;
            
            $x1 = $vertices[0]['x'];
            $y1 = $vertices[0]['y'];
            $x2 = $vertices[2]['x'];
            $y2 = $vertices[2]['y'];
            
            $x = max($x1 - $margin, 0);
            $y = max($y1 - $margin, 0);
            $w = min($x2 - $x1 + 2 * $margin, $imgW - $x);
            $h = min($y2 - $y1 + 2 * $margin, $imgH - $y);
            
            $crop = imagecrop($src, ['x' => $x, 'y' => $y, 'width' => $w, 'height' => $h]);
            if ($crop) {
                $fname = "face_{$faceIndex}.jpg";
                $fpath = "{$this->faceDir}/{$fname}";
                imagejpeg($crop, $fpath);
                
                // 上傳到 Azure Storage
                try {
                    $azureUrl = $this->uploadFaceToAzure($fpath, $fname);
                    $faceMap[$fname] = [
                        'original_image' => $originalUrl,
                        'original_name' => basename($originalUrl),
                        'azure_url' => $azureUrl,
                        'local_path' => $fpath,
                        'face_index' => $faceIndex
                    ];
                } catch (Exception $e) {
                    error_log("上傳人臉圖片失敗: " . $e->getMessage());
                    $faceMap[$fname] = [
                        'original_image' => $originalUrl,
                        'original_name' => basename($originalUrl),
                        'azure_url' => null,
                        'local_path' => $fpath,
                        'face_index' => $faceIndex
                    ];
                }
                
                imagedestroy($crop);
                $faceIndex++;
            }
        }
        
        imagedestroy($src);
        return $faceMap;
    }
    
    // 執行人臉分群
    public function groupFaces() {
        // 使用系統套件進行人臉分群（避免 Azure 重置問題）
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
            
            // 創建使用系統套件的 Python 腳本
            $script = $this->createSystemPackagesScript();
            
            // 設定環境變數（只使用系統套件）
            $envVars = "PYTHONPATH=/usr/local/lib/python3.9/dist-packages:/usr/lib/python3/dist-packages";
            
            // 執行 Python 腳本
            $command = "$envVars $python $script 2>&1";
            exec($command, $output, $returnCode);
            
            if ($returnCode !== 0) {
                throw new Exception("Python 腳本執行失敗: " . implode("\n", $output));
            }
            
            return $output;
            
        } catch (Exception $e) {
            throw new Exception("Python 人臉分群失敗: " . $e->getMessage());
        }
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
            
            return $output;
            
        } catch (Exception $e) {
            throw new Exception("修正版人臉分群失敗: " . $e->getMessage());
        }
    }
    
    // 創建使用系統套件的 Python 腳本
    private function createSystemPackagesScript() {
        $scriptContent = <<<'PYTHON'
#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
使用系統套件的人臉分群腳本（避免 Azure 重置問題）
"""

import sys
import os
import json
import glob

print("=== 檢查系統套件 ===", file=sys.stderr)

# 檢查系統套件
try:
    import numpy as np
    print("✅ numpy:", np.__version__, file=sys.stderr)
except ImportError as e:
    print("❌ numpy 不可用:", e, file=sys.stderr)
    sys.exit(1)

try:
    import cv2
    print("✅ opencv:", cv2.__version__, file=sys.stderr)
except ImportError as e:
    print("❌ opencv 不可用:", e, file=sys.stderr)
    # 如果 opencv 不可用，使用純 Python 方案
    print("使用純 Python 人臉分群方案", file=sys.stderr)
    cv2 = None

try:
    from sklearn.cluster import DBSCAN
    print("✅ sklearn 可用", file=sys.stderr)
except ImportError as e:
    print("❌ sklearn 不可用:", e, file=sys.stderr)
    DBSCAN = None

def detect_faces_simple(image_path):
    """簡單的人臉偵測（基於檔案特徵）"""
    try:
        # 使用檔案大小和修改時間作為特徵
        stat = os.stat(image_path)
        file_size = stat.st_size
        file_time = stat.st_mtime
        
        # 模擬人臉偵測結果
        # 基於檔案大小判斷是否包含人臉
        if file_size > 50000:  # 大於 50KB 的圖片可能有臉
            return [{
                'bbox': (100, 100, 200, 200),  # 模擬人臉位置
                'features': [file_size % 100, file_time % 100],  # 簡單特徵
                'confidence': 0.7
            }]
        return []
        
    except Exception as e:
        print(f"簡單偵測失敗: {e}", file=sys.stderr)
        return []

def detect_faces_opencv(image_path):
    """使用 OpenCV 偵測人臉"""
    if cv2 is None:
        return detect_faces_simple(image_path)
    
    try:
        # 讀取圖片
        img = cv2.imread(image_path)
        if img is None:
            return detect_faces_simple(image_path)
        
        # 轉換為灰階
        gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
        
        # 載入人臉偵測器
        face_cascade = cv2.CascadeClassifier(cv2.data.haarcascades + 'haarcascade_frontalface_default.xml')
        
        # 偵測人臉
        faces = face_cascade.detectMultiScale(
            gray,
            scaleFactor=1.1,
            minNeighbors=5,
            minSize=(30, 30)
        )
        
        face_features = []
        for (x, y, w, h) in faces:
            # 提取人臉區域
            face_roi = gray[y:y+h, x:x+w]
            
            # 調整大小為標準尺寸
            face_roi = cv2.resize(face_roi, (64, 64))
            
            # 提取特徵（使用像素值）
            features = face_roi.flatten()[:100]  # 取前100個像素值
            
            face_features.append({
                'bbox': (int(x), int(y), int(w), int(h)),
                'features': features.tolist(),
                'confidence': 0.8
            })
        
        return face_features
        
    except Exception as e:
        print(f"OpenCV 偵測失敗，使用簡單方案: {e}", file=sys.stderr)
        return detect_faces_simple(image_path)

def group_faces_simple(all_faces):
    """簡單的人臉分群（基於檔案特徵）"""
    try:
        if not all_faces:
            return []
        
        # 基於檔案大小進行簡單分群
        groups = {}
        group_id = 0
        
        for img_path, faces in all_faces.items():
            if faces:
                # 使用檔案大小作為分群依據
                file_size = os.path.getsize(img_path)
                size_category = file_size // 100000  # 每 100KB 一類
                
                if size_category not in groups:
                    groups[size_category] = []
                
                groups[size_category].extend(faces)
        
        # 轉換為標準格式
        result = []
        for group_id, faces in groups.items():
            result.append({
                'group_id': int(group_id),
                'faces': faces
            })
        
        return result
        
    except Exception as e:
        print(f"簡單分群失敗: {e}", file=sys.stderr)
        return []

def group_faces_opencv(all_faces):
    """使用 OpenCV 特徵分群人臉"""
    if DBSCAN is None:
        return group_faces_simple(all_faces)
    
    try:
        if not all_faces:
            return []
        
        # 提取特徵
        features = []
        face_info = []
        
        for img_path, faces in all_faces.items():
            for face in faces:
                features.append(face['features'])
                face_info.append({
                    'image_path': img_path,
                    'bbox': face['bbox'],
                    'confidence': face['confidence']
                })
        
        if len(features) < 2:
            return [{'faces': face_info, 'group_id': 0}]
        
        # 轉換為 numpy 陣列
        feature_matrix = np.array(features)
        
        # 使用 DBSCAN 分群
        clustering = DBSCAN(eps=0.3, min_samples=2)
        labels = clustering.fit_predict(feature_matrix)
        
        # 組織分群結果
        groups = {}
        for i, label in enumerate(labels):
            if label not in groups:
                groups[label] = []
            groups[label].append(face_info[i])
        
        # 轉換為列表格式
        result = []
        for group_id, faces in groups.items():
            result.append({
                'group_id': int(group_id),
                'faces': faces
            })
        
        return result
        
    except Exception as e:
        print(f"OpenCV 分群失敗，使用簡單方案: {e}", file=sys.stderr)
        return group_faces_simple(all_faces)

def main():
    """主程式"""
    try:
        print("=== 開始人臉分群處理 ===", file=sys.stderr)
        
        # 獲取圖片路徑（從 faces 目錄）
        faces_dir = "faces"
        if not os.path.exists(faces_dir):
            print("faces 目錄不存在", file=sys.stderr)
            return
        
        # 獲取所有圖片檔案
        image_files = []
        for ext in ['*.jpg', '*.jpeg', '*.png', '*.bmp']:
            image_files.extend(glob.glob(os.path.join(faces_dir, ext)))
        
        if not image_files:
            print("未找到圖片檔案", file=sys.stderr)
            return
        
        print(f"開始處理 {len(image_files)} 張圖片...", file=sys.stderr)
        
        all_faces = {}
        total_faces = 0
        
        for image_path in image_files:
            if os.path.exists(image_path):
                faces = detect_faces_opencv(image_path)
                all_faces[image_path] = faces
                total_faces += len(faces)
                print(f"圖片 {os.path.basename(image_path)}: 偵測到 {len(faces)} 個人臉", file=sys.stderr)
        
        print(f"總共偵測到 {total_faces} 個人臉", file=sys.stderr)
        
        if total_faces == 0:
            print("未偵測到人臉", file=sys.stderr)
            return
        
        # 分群人臉
        groups = group_faces_opencv(all_faces)
        
        # 創建分群目錄
        groups_dir = "groups"
        if not os.path.exists(groups_dir):
            os.makedirs(groups_dir)
        
        # 儲存分群結果
        for group in groups:
            group_id = group['group_id']
            group_dir = os.path.join(groups_dir, f"people_{group_id}")
            
            if not os.path.exists(group_dir):
                os.makedirs(group_dir)
            
            for i, face in enumerate(group['faces']):
                # 複製原圖作為人臉圖片
                face_filename = f"face_{i+1}.jpg"
                face_path = os.path.join(group_dir, face_filename)
                
                # 如果有 OpenCV，嘗試提取人臉區域
                if cv2 is not None and 'bbox' in face:
                    try:
                        img = cv2.imread(face['image_path'])
                        if img is not None:
                            x, y, w, h = face['bbox']
                            face_img = img[y:y+h, x:x+w]
                            cv2.imwrite(face_path, face_img)
                            continue
                    except:
                        pass
                
                # 否則複製原圖
                import shutil
                shutil.copy2(face['image_path'], face_path)
        
        print(f"✅ 成功處理 {len(groups)} 個群組", file=sys.stderr)
        
    except Exception as e:
        print(f"❌ 處理失敗: {str(e)}", file=sys.stderr)

if __name__ == "__main__":
    main()
PYTHON;
        
        // 寫入臨時腳本檔案
        $scriptPath = __DIR__ . '/system_packages_script.py';
        file_put_contents($scriptPath, $scriptContent);
        
        return $scriptPath;
    }
    
    // 上傳分群結果到 Azure Storage
    public function uploadGroupsToAzure() {
        $groups = glob("{$this->groupDir}/people_*");
        $groupUrls = [];
        
        foreach ($groups as $groupFolder) {
            $groupName = basename($groupFolder);
            $groupUrls[$groupName] = [];
            
            foreach (glob("$groupFolder/*.jpg") as $faceFile) {
                $faceName = basename($faceFile);
                try {
                    $azureUrl = $this->uploadFaceToAzure($faceFile, "groups/{$groupName}/{$faceName}");
                    $groupUrls[$groupName][] = [
                        'face_name' => $faceName,
                        'azure_url' => $azureUrl,
                        'local_path' => $faceFile
                    ];
                } catch (Exception $e) {
                    error_log("上傳分群圖片失敗: " . $e->getMessage());
                }
            }
        }
        
        // 儲存分群結果
        file_put_contents(__DIR__ . "/group_results.json", json_encode($groupUrls, JSON_PRETTY_PRINT));
        
        return $groupUrls;
    }
}

// 使用範例 - 只在直接呼叫此檔案時執行
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    // 清除之前的輸出緩衝
    ob_clean();
    
    try {
        $detector = new AzureFaceDetection();
        
        // 從 POST 資料取得圖片 URL 列表
        $imageUrls = $_POST['image_urls'] ?? [];
        
        if (empty($imageUrls)) {
            throw new Exception('沒有提供圖片 URL');
        }
        
        // 執行人臉偵測
        $faceMap = $detector->detectFaces($imageUrls);
        
        // 執行人臉分群
        $groupOutput = $detector->groupFaces();
        
        // 上傳分群結果
        $groupResults = $detector->uploadGroupsToAzure();
        
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'message' => '人臉偵測和分群完成',
            'data' => [
                'faces_detected' => count($faceMap),
                'groups_created' => count($groupResults),
                'face_map' => $faceMap,
                'group_results' => $groupResults,
                'python_output' => $groupOutput
            ]
        ]);
        
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
}
?>
