<?php
header('Content-Type: application/json');

// 簡化的 EXIF 處理方案（直接從 URL 讀取）
class ExifProcessor {
    
    public function __construct() {
        // 檢查必要的擴展
        if (!extension_loaded('exif')) {
            throw new Exception('PHP EXIF 擴展未載入');
        }
        
        if (!extension_loaded('imagick')) {
            throw new Exception('PHP Imagick 擴展未載入（需要處理 HEIC 檔案）');
        }
    }
    
    // 主要處理函數
    public function processImage($blobUrl, $fileName) {
        try {
            error_log("開始處理圖片: $fileName, URL: $blobUrl");
            
            // 檢查檔案格式
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            
            // 處理 HEIC 檔案
            if ($fileExtension === 'heic' || $fileExtension === 'heif') {
                $result = $this->processHeicFromUrl($blobUrl, $fileName);
            } else {
                // 處理 JPG/其他格式
                $result = $this->processJpgFromUrl($blobUrl, $fileName);
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log("EXIF 處理錯誤: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'datetime' => date('Y-m-d H:i:s'),
                'latitude' => null,
                'longitude' => null
            ];
        }
    }
    
    // 處理 HEIC 檔案（從 URL）
    private function processHeicFromUrl($blobUrl, $fileName) {
        error_log("處理 HEIC 檔案: $fileName");
        
        try {
            // 直接從 URL 讀取 HEIC 檔案
            $imagick = new Imagick($blobUrl);
            
            // 設定輸出格式為 JPG
            $imagick->setImageFormat('jpg');
            
            // 建立轉換後的 JPG 檔案
            $jpgFileName = $this->generateJpgFileName($fileName);
            $jpgTempFile = tempnam(sys_get_temp_dir(), 'heic_converted_');
            $jpgTempFile .= '.jpg';
            
            // 寫入 JPG 檔案
            $imagick->writeImage($jpgTempFile);
            $imagick->destroy();
            
            error_log("HEIC 轉 JPG 成功: $jpgTempFile");
            
            // 從轉換後的 JPG 檔案提取 EXIF
            $exifData = $this->extractExifFromFile($jpgTempFile);
            
            // 上傳轉換後的 JPG 到 Azure Storage
            $convertedJpgUrl = $this->uploadConvertedJpg($jpgTempFile, $jpgFileName);
            
            // 清理 JPG 臨時檔案
            $this->cleanupTempFile($jpgTempFile);
            
            return array_merge($exifData, [
                'success' => true,
                'original_format' => 'HEIC',
                'converted_format' => 'JPG',
                'converted_jpg_url' => $convertedJpgUrl,
                'original_filename' => $fileName,
                'converted_filename' => $jpgFileName
            ]);
            
        } catch (Exception $e) {
            error_log("HEIC 處理錯誤: " . $e->getMessage());
            throw new Exception('HEIC 檔案處理失敗: ' . $e->getMessage());
        }
    }
    
    // 處理 JPG 檔案（從 URL）
    private function processJpgFromUrl($blobUrl, $fileName) {
        error_log("處理 JPG 檔案: $fileName");
        
        try {
            // 直接從 URL 讀取 EXIF 資料
            $exifData = $this->extractExifFromUrl($blobUrl);
            
            return array_merge($exifData, [
                'success' => true,
                'original_format' => 'JPG',
                'converted_format' => null,
                'converted_jpg_url' => null,
                'original_filename' => $fileName,
                'converted_filename' => null
            ]);
            
        } catch (Exception $e) {
            error_log("JPG 處理錯誤: " . $e->getMessage());
            throw new Exception('JPG 檔案處理失敗: ' . $e->getMessage());
        }
    }
    
    // 從 URL 直接提取 EXIF 資料
    private function extractExifFromUrl($url) {
        error_log("從 URL 提取 EXIF: $url");
        
        // 使用 stream context 設定超時
        $context = stream_context_create([
            'http' => [
                'timeout' => 30,
                'user_agent' => 'Mozilla/5.0 (compatible; AI-Album-System/1.0)'
            ]
        ]);
        
        // 直接從 URL 讀取 EXIF 資料
        $exifData = exif_read_data($url, 'ANY_TAG', true, false, false, false, false, false, $context);
        
        if ($exifData === false) {
            error_log("無法從 URL 讀取 EXIF 資料");
            return $this->getDefaultExifData();
        }
        
        error_log("EXIF 資料讀取成功");
        
        return $this->parseExifData($exifData);
    }
    
    // 從檔案提取 EXIF 資料（用於 HEIC 轉換後的檔案）
    private function extractExifFromFile($filePath) {
        error_log("從檔案提取 EXIF: $filePath");
        
        $exifData = exif_read_data($filePath, 'ANY_TAG', true);
        
        if ($exifData === false) {
            error_log("無法讀取 EXIF 資料");
            return $this->getDefaultExifData();
        }
        
        error_log("EXIF 資料讀取成功");
        
        return $this->parseExifData($exifData);
    }
    
    // 解析 EXIF 資料（只保留時間和經緯度）
    private function parseExifData($exifData) {
        $result = [
            'datetime' => null,
            'latitude' => null,
            'longitude' => null
        ];
        
        // 日期時間
        if (isset($exifData['EXIF']['DateTimeOriginal'])) {
            $result['datetime'] = $this->convertExifDate($exifData['EXIF']['DateTimeOriginal']);
        } elseif (isset($exifData['EXIF']['CreateDate'])) {
            $result['datetime'] = $this->convertExifDate($exifData['EXIF']['CreateDate']);
        } elseif (isset($exifData['IFD0']['DateTime'])) {
            $result['datetime'] = $this->convertExifDate($exifData['IFD0']['DateTime']);
        }
        
        // GPS 座標
        if (isset($exifData['GPS']['GPSLatitude']) && isset($exifData['GPS']['GPSLongitude'])) {
            $result['latitude'] = $this->convertGPSToDecimal($exifData['GPS']['GPSLatitude'], $exifData['GPS']['GPSLatitudeRef'] ?? 'N');
            $result['longitude'] = $this->convertGPSToDecimal($exifData['GPS']['GPSLongitude'], $exifData['GPS']['GPSLongitudeRef'] ?? 'E');
        }
        
        // 如果沒有日期時間，使用當前時間
        if (!$result['datetime']) {
            $result['datetime'] = date('Y-m-d H:i:s');
        }
        
        return $result;
    }
    
    // 生成 JPG 檔案名稱
    private function generateJpgFileName($originalFileName) {
        $nameWithoutExt = pathinfo($originalFileName, PATHINFO_FILENAME);
        return $nameWithoutExt . '_converted.jpg';
    }
    
    // 上傳轉換後的 JPG（需要整合你的 Azure Storage 邏輯）
    private function uploadConvertedJpg($tempFile, $fileName) {
        // 這裡需要整合你的 Azure Storage 上傳邏輯
        // 暫時返回一個假設的 URL
        error_log("需要上傳轉換後的 JPG: $fileName");
        
        // TODO: 整合 Azure Storage 上傳
        // 例如：return $azureStorage->uploadFromTemp($tempFile, $fileName);
        
        return null; // 暫時返回 null
    }
    
    // 清理臨時檔案
    private function cleanupTempFile($tempFile) {
        if (file_exists($tempFile)) {
            unlink($tempFile);
            error_log("臨時檔案已清理: $tempFile");
        }
    }
    
    // 轉換 EXIF 日期格式
    private function convertExifDate($exifDate) {
        $date = DateTime::createFromFormat('Y:m:d H:i:s', $exifDate);
        if ($date) {
            return $date->format('Y-m-d H:i:s');
        }
        return null;
    }
    
    // 轉換 GPS 座標
    private function convertGPSToDecimal($gpsArray, $ref) {
        if (!is_array($gpsArray) || count($gpsArray) !== 3) {
            return null;
        }
        
        $degrees = floatval($gpsArray[0]);
        $minutes = floatval($gpsArray[1]);
        $seconds = floatval($gpsArray[2]);
        
        $decimal = $degrees + ($minutes / 60) + ($seconds / 3600);
        
        if ($ref === 'S' || $ref === 'W') {
            $decimal *= -1;
        }
        
        return $decimal;
    }
    
    // 取得預設 EXIF 資料（只保留時間和經緯度）
    private function getDefaultExifData() {
        return [
            'datetime' => date('Y-m-d H:i:s'),
            'latitude' => null,
            'longitude' => null
        ];
    }
}

// 只有在直接訪問此檔案時才執行測試
if (basename($_SERVER['SCRIPT_NAME']) === 'exif_processor.php') {
    if (isset($_POST['blobUrl']) && isset($_POST['fileName'])) {
        try {
            $processor = new ExifProcessor();
            $result = $processor->processImage($_POST['blobUrl'], $_POST['fileName']);
            echo json_encode($result);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => '請提供 blobUrl 和 fileName 參數',
            'usage' => 'POST blobUrl=圖片URL&fileName=檔案名稱'
        ]);
    }
}
?>
