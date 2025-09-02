<?php
/**
 * Azure 人臉偵測 - EXE 版本
 * 使用打包的 EXE 檔案進行人臉分群
 */

class AzureFaceDetectionExe {
    private $exePath;
    private $groupDir;
    
    public function __construct() {
        $this->exePath = __DIR__ . '/face_grouping.exe';
        $this->groupDir = __DIR__ . '/groups';
    }
    
    // 偵測人臉（使用 Azure Face API）
    public function detectFaces($imageUrls) {
        // 保持原有的 Azure Face API 偵測邏輯
        $faceMap = [];
        
        foreach ($imageUrls as $imageUrl) {
            try {
                // 下載圖片到 faces 目錄
                $localPath = $this->downloadImage($imageUrl);
                if ($localPath) {
                    $faceMap[$imageUrl] = $localPath;
                }
            } catch (Exception $e) {
                error_log("下載圖片失敗: " . $e->getMessage());
            }
        }
        
        return $faceMap;
    }
    
    // 使用 EXE 進行人臉分群
    public function groupFaces() {
        try {
            // 檢查 EXE 檔案是否存在
            if (!file_exists($this->exePath)) {
                throw new Exception("EXE 檔案不存在: " . $this->exePath);
            }
            
            // 檢查 faces 目錄
            $facesDir = __DIR__ . '/faces';
            if (!is_dir($facesDir)) {
                throw new Exception("faces 目錄不存在");
            }
            
            // 執行 EXE 檔案
            $command = '"' . $this->exePath . '" 2>&1';
            exec($command, $output, $returnCode);
            
            if ($returnCode !== 0) {
                throw new Exception("EXE 執行失敗: " . implode("\n", $output));
            }
            
            return $output;
            
        } catch (Exception $e) {
            throw new Exception("EXE 人臉分群失敗: " . $e->getMessage());
        }
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
    
    // 下載圖片到本地
    private function downloadImage($imageUrl) {
        $facesDir = __DIR__ . '/faces';
        if (!is_dir($facesDir)) {
            mkdir($facesDir, 0755, true);
        }
        
        $filename = basename($imageUrl);
        $localPath = $facesDir . '/' . $filename;
        
        // 下載圖片
        $imageContent = file_get_contents($imageUrl);
        if ($imageContent !== false) {
            file_put_contents($localPath, $imageContent);
            return $localPath;
        }
        
        return null;
    }
    
    // 上傳到 Azure Storage（簡化版本）
    private function uploadFaceToAzure($localPath, $azurePath) {
        // 這裡可以實現 Azure Storage 上傳邏輯
        // 目前返回本地路徑作為模擬
        return $localPath;
    }
}

// 使用範例
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    try {
        if ($_POST['action'] === 'detect_faces') {
            $detector = new AzureFaceDetectionExe();
            
            $selectedPhotos = $_POST['selected_photos'] ?? [];
            if (empty($selectedPhotos)) {
                throw new Exception('請選擇至少一張照片');
            }
            
            // 執行人臉偵測
            $faceMap = $detector->detectFaces($selectedPhotos);
            
            // 執行人臉分群（使用 EXE）
            $groupOutput = $detector->groupFaces();
            
            // 上傳分群結果
            $groupResults = $detector->uploadGroupsToAzure();
            
            echo json_encode([
                'status' => 'success',
                'message' => '人臉偵測和分群完成（EXE 版本）',
                'data' => [
                    'faces_detected' => count($faceMap),
                    'groups_created' => count($groupResults),
                    'face_map' => $faceMap,
                    'group_results' => $groupResults,
                    'exe_output' => $groupOutput
                ]
            ], JSON_UNESCAPED_UNICODE);
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
?>



