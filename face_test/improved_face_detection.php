<?php
ini_set('memory_limit', '2G');
ini_set('max_execution_time', 900);

require 'vendor/autoload.php';

putenv('GOOGLE_APPLICATION_CREDENTIALS=' . __DIR__ . '/shining-glyph-465006-i1-8f6de1bb78de.json');

use Google\Cloud\Vision\V1\Client\ImageAnnotatorClient;
use Google\Cloud\Vision\V1\Feature;
use Google\Cloud\Vision\V1\Image;
use Google\Cloud\Vision\V1\AnnotateImageRequest;
use Google\Cloud\Vision\V1\BatchAnnotateImagesRequest;

class ImprovedFaceDetection {
    private $accountName = 'albumstorage1411131020';
    private $accountKey = 'your_account_key_here';
    private $containerName = 'photos';
    private $faceDir = 'faces';
    
    public function __construct() {
        if (!is_dir($this->faceDir)) {
            mkdir($this->faceDir, 0755, true);
        }
    }
    
    // 改進的人臉偵測策略
    public function detectFacesImproved($imageUrls) {
        $faceMap = [];
        $faceIndex = 0;
        
        foreach ($imageUrls as $imagePath) {
            // 檢查是本地路徑還是 Azure URL
            if (strpos($imagePath, 'http') === 0) {
                $tempFile = $this->downloadFromAzure($imagePath);
            } else {
                $tempFile = __DIR__ . '/../' . $imagePath;
                if (!file_exists($tempFile)) {
                    throw new Exception("圖片檔案不存在: $tempFile");
                }
            }
            
            // 使用改進的處理策略
            $imageFaces = $this->processImageImproved($tempFile, $imagePath, $faceIndex);
            $faceMap = array_merge($faceMap, $imageFaces);
            $faceIndex = count($faceMap);
            
            if (strpos($imagePath, 'http') === 0) {
                unlink($tempFile);
            }
        }
        
        file_put_contents(__DIR__ . "/face_map.json", json_encode($faceMap, JSON_PRETTY_PRINT));
        return $faceMap;
    }
    
    // 改進的圖片處理策略
    private function processImageImproved($imagePath, $originalUrl, &$faceIndex) {
        $faceMap = [];
        $maxFacesPerImage = 8;
        
        // 策略1：先嘗試完整圖片偵測
        $initialFaces = $this->detectFacesInImage($imagePath);
        error_log("完整圖片偵測到 " . count($initialFaces) . " 張人臉");
        
        if (count($initialFaces) <= $maxFacesPerImage) {
            // 人臉數量在限制內，直接處理
            $faceMap = $this->extractFacesFromImage($imagePath, $originalUrl, $initialFaces, $faceIndex);
        } else {
            // 策略2：使用智能切割
            error_log("人臉數量超過 {$maxFacesPerImage} 張，使用智能切割策略");
            $faceMap = $this->smartGridProcessing($imagePath, $originalUrl, $faceIndex);
        }
        
        return $faceMap;
    }
    
    // 智能網格處理 - 改進版本
    private function smartGridProcessing($imagePath, $originalUrl, &$faceIndex) {
        $faceMap = [];
        
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
        
        // 改進的切割策略：根據圖片比例動態調整
        $gridSize = $this->calculateOptimalGridSize($imgW, $imgH);
        $cellW = $imgW / $gridSize;
        $cellH = $imgH / $gridSize;
        
        error_log("使用 {$gridSize}x{$gridSize} 網格，每個網格尺寸: " . round($cellW) . "x" . round($cellH));
        
        $allFaces = [];
        $gridImages = [];
        
        // 對每個網格進行處理
        for ($row = 0; $row < $gridSize; $row++) {
            for ($col = 0; $col < $gridSize; $col++) {
                $x = $col * $cellW;
                $y = $row * $cellH;
                $w = $cellW;
                $h = $cellH;
                
                // 裁切網格
                $crop = imagecrop($src, ['x' => $x, 'y' => $y, 'width' => $w, 'height' => $h]);
                if (!$crop) continue;
                
                // 儲存臨時網格圖片
                $tempGridPath = tempnam(sys_get_temp_dir(), 'grid_');
                imagejpeg($crop, $tempGridPath, 95); // 提高品質
                
                // 上傳網格圖片到 Azure Storage
                $gridName = "grid_{$row}_{$col}_" . basename($originalUrl);
                try {
                    $gridAzureUrl = $this->uploadFaceToAzure($tempGridPath, $gridName);
                    $gridImages[] = [
                        'grid_name' => $gridName,
                        'azure_url' => $gridAzureUrl,
                        'local_path' => $tempGridPath,
                        'position' => ['row' => $row, 'col' => $col],
                        'coordinates' => ['x' => $x, 'y' => $y, 'width' => $w, 'height' => $h]
                    ];
                    error_log("網格圖片已上傳到 Azure: {$gridName}");
                } catch (Exception $e) {
                    error_log("上傳網格圖片失敗: " . $e->getMessage());
                }
                
                // 偵測網格中的人臉
                $gridFaces = $this->detectFacesInImage($tempGridPath);
                error_log("網格 ({$row}, {$col}) 偵測到 " . count($gridFaces) . " 張人臉");
                
                // 改進的座標調整
                foreach ($gridFaces as $face) {
                    $vertices = $face->getBoundingPoly()->getVertices();
                    if (count($vertices) < 2) continue;
                    
                    // 更精確的座標調整
                    $adjustedVertices = [];
                    foreach ($vertices as $vertex) {
                        $adjustedVertices[] = [
                            'x' => max(0, min($imgW - 1, $vertex->getX() + $x)),
                            'y' => max(0, min($imgH - 1, $vertex->getY() + $y))
                        ];
                    }
                    
                    $allFaces[] = [
                        'vertices' => $adjustedVertices,
                        'confidence' => $face->getDetectionConfidence(),
                        'grid_position' => ['row' => $row, 'col' => $col],
                        'grid_name' => $gridName
                    ];
                }
                
                imagedestroy($crop);
            }
        }
        
        imagedestroy($src);
        
        // 改進的重複消除策略
        $uniqueFaces = $this->improvedDuplicateRemoval($allFaces);
        error_log("重複消除後剩餘 " . count($uniqueFaces) . " 張人臉");
        
        // 提取人臉
        $faceMap = $this->extractFacesFromList($imagePath, $originalUrl, $uniqueFaces, $faceIndex);
        
        // 儲存詳細的網格資訊
        $gridInfo = [
            'original_image' => $originalUrl,
            'grid_size' => $gridSize,
            'total_grids' => count($gridImages),
            'total_faces_before_dedup' => count($allFaces),
            'total_faces_after_dedup' => count($uniqueFaces),
            'grids' => $gridImages,
            'processing_time' => date('Y-m-d H:i:s')
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
    
    // 計算最佳網格大小
    private function calculateOptimalGridSize($width, $height) {
        $ratio = $width / $height;
        
        if ($ratio > 1.5) {
            // 寬圖：使用 3x2 網格
            return ['rows' => 2, 'cols' => 3];
        } elseif ($ratio < 0.67) {
            // 高圖：使用 2x3 網格
            return ['rows' => 3, 'cols' => 2];
        } else {
            // 方圖：使用 2x2 網格
            return ['rows' => 2, 'cols' => 2];
        }
    }
    
    // 改進的重複消除策略
    private function improvedDuplicateRemoval($faces) {
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
    
    // 其他必要的方法（從原始檔案複製）
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
    
    private function extractFacesFromImage($imagePath, $originalUrl, $faces, &$faceIndex) {
        // 從原始檔案複製此方法
        // ... 實作內容
    }
    
    private function extractFacesFromList($imagePath, $originalUrl, $faces, &$faceIndex) {
        // 從原始檔案複製此方法
        // ... 實作內容
    }
    
    private function calculateIOUFromVertices($verticesA, $verticesB) {
        // 從原始檔案複製此方法
        // ... 實作內容
    }
    
    private function uploadFaceToAzure($localPath, $faceName) {
        // 從原始檔案複製此方法
        // ... 實作內容
    }
    
    private function downloadFromAzure($url) {
        // 從原始檔案複製此方法
        // ... 實作內容
    }
}

// 測試改進的系統
if (isset($_GET['test'])) {
    $detector = new ImprovedFaceDetection();
    
    // 測試圖片
    $testImages = [
        'https://albumstorage1411131020.blob.core.windows.net/photos/68a5726b96e12.jpeg'
    ];
    
    try {
        $result = $detector->detectFacesImproved($testImages);
        echo json_encode([
            'status' => 'success',
            'message' => '改進的人臉偵測完成',
            'data' => [
                'faces_detected' => count($result),
                'face_map' => $result
            ]
        ], JSON_PRETTY_PRINT);
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ], JSON_PRETTY_PRINT);
    }
}
?>
