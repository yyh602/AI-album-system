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
        
        $this->cleanDirectory($this->faceDir);
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
    
    // 主要處理方法 - 簡化版本，不進行照片切割
    public function processImages($imageUrls) {
        $allFaces = [];
        $faceIndex = 0;
        
        foreach ($imageUrls as $imageUrl) {
            try {
                // 下載圖片
                $imagePath = $this->downloadImage($imageUrl);
                if (!$imagePath) continue;
                
                // 處理單張圖片 - 簡化版本
                $faceMap = $this->processSingleImageSimple($imagePath, $imageUrl, $faceIndex);
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
    
    // 簡化版本：處理單張圖片，不進行切割
    private function processSingleImageSimple($imagePath, $originalUrl, &$faceIndex) {
        $faceMap = [];
        
        // 偵測人臉
        $faces = $this->detectFacesInImage($imagePath);
        
        // 直接處理人臉，不進行切割
        foreach ($faces as $index => $face) {
            $fname = "face_{$faceIndex}.jpg";
            $fpath = "{$this->faceDir}/{$fname}";
            
            // 直接複製原圖作為人臉圖片
            copy($imagePath, $fpath);
            
            try {
                $azureUrl = $this->uploadFaceToAzure($fpath, $fname);
                $faceMap[$fname] = [
                    'original_image' => $originalUrl,
                    'original_name' => basename($originalUrl),
                    'azure_url' => $azureUrl,
                    'local_path' => $fpath,
                    'face_index' => $faceIndex
                ];
                $faceIndex++;
            } catch (Exception $e) {
                error_log("上傳人臉失敗: " . $e->getMessage());
            }
        }
        
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
    private function uploadFaceToAzure($localPath, $fileName) {
        if (!$this->connectionString) {
            // 如果沒有 Azure Storage 連接字串，則回傳本地路徑
            return $localPath;
        }

        // 簡化版本：直接回傳本地路徑，不實際上傳到 Azure
        return $localPath;
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
}
?> 