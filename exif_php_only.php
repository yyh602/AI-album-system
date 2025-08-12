<?php
header('Content-Type: application/json');

// 純 PHP EXIF 抓取方案（不依賴命令列工具）
class PhpExifExtractor {
    
    public function extractExifFromBlob($blobUrl) {
        try {
            error_log("開始 PHP EXIF 抓取: $blobUrl");
            
            // 下載圖片
            $imageContent = $this->downloadImage($blobUrl);
            if (!$imageContent) {
                throw new Exception('無法下載圖片');
            }
            
            // 寫入臨時檔案
            $tempFile = $this->createTempFile($imageContent);
            
            // 使用 PHP EXIF 擴展
            $exifData = $this->extractExifData($tempFile);
            
            // 清理臨時檔案
            $this->cleanupTempFile($tempFile);
            
            return $exifData;
            
        } catch (Exception $e) {
            error_log("PHP EXIF 抓取錯誤: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    private function downloadImage($url) {
        $context = stream_context_create([
            'http' => [
                'timeout' => 30,
                'user_agent' => 'Mozilla/5.0 (compatible; AI-Album-System/1.0)'
            ]
        ]);
        
        $content = file_get_contents($url, false, $context);
        if ($content === false) {
            throw new Exception('無法下載圖片內容');
        }
        
        error_log("圖片下載成功，大小: " . strlen($content) . " bytes");
        return $content;
    }
    
    private function createTempFile($content) {
        $tempFile = tempnam(sys_get_temp_dir(), 'exif_');
        if (file_put_contents($tempFile, $content) === false) {
            throw new Exception('無法寫入臨時檔案');
        }
        
        error_log("臨時檔案建立成功: $tempFile");
        return $tempFile;
    }
    
    private function extractExifData($tempFile) {
        if (!extension_loaded('exif')) {
            throw new Exception('PHP EXIF 擴展未載入');
        }
        
        $exifData = exif_read_data($tempFile, 'ANY_TAG', true);
        if ($exifData === false) {
            throw new Exception('無法讀取 EXIF 資料');
        }
        
        error_log("EXIF 資料讀取成功");
        
        return $this->parseExifData($exifData);
    }
    
    private function parseExifData($exifData) {
        $result = [
            'success' => true,
            'datetime' => null,
            'latitude' => null,
            'longitude' => null,
            'camera_make' => null,
            'camera_model' => null,
            'image_width' => null,
            'image_height' => null,
            'raw_exif' => $exifData
        ];
        
        // 日期時間
        if (isset($exifData['EXIF']['DateTimeOriginal'])) {
            $result['datetime'] = $this->convertExifDate($exifData['EXIF']['DateTimeOriginal']);
        } elseif (isset($exifData['EXIF']['CreateDate'])) {
            $result['datetime'] = $this->convertExifDate($exifData['EXIF']['CreateDate']);
        }
        
        // GPS 座標
        if (isset($exifData['GPS']['GPSLatitude']) && isset($exifData['GPS']['GPSLongitude'])) {
            $result['latitude'] = $this->convertGPSToDecimal($exifData['GPS']['GPSLatitude'], $exifData['GPS']['GPSLatitudeRef'] ?? 'N');
            $result['longitude'] = $this->convertGPSToDecimal($exifData['GPS']['GPSLongitude'], $exifData['GPS']['GPSLongitudeRef'] ?? 'E');
        }
        
        // 相機資訊
        if (isset($exifData['IFD0']['Make'])) {
            $result['camera_make'] = $exifData['IFD0']['Make'];
        }
        if (isset($exifData['IFD0']['Model'])) {
            $result['camera_model'] = $exifData['IFD0']['Model'];
        }
        
        // 圖片尺寸
        if (isset($exifData['COMPUTED']['Width'])) {
            $result['image_width'] = $exifData['COMPUTED']['Width'];
        }
        if (isset($exifData['COMPUTED']['Height'])) {
            $result['image_height'] = $exifData['COMPUTED']['Height'];
        }
        
        return $result;
    }
    
    private function convertExifDate($exifDate) {
        $date = DateTime::createFromFormat('Y:m:d H:i:s', $exifDate);
        if ($date) {
            return $date->format('Y-m-d H:i:s');
        }
        return null;
    }
    
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
    
    private function cleanupTempFile($tempFile) {
        if (file_exists($tempFile)) {
            unlink($tempFile);
            error_log("臨時檔案已清理: $tempFile");
        }
    }
}

// 測試
if (isset($_POST['blobUrl'])) {
    $extractor = new PhpExifExtractor();
    $result = $extractor->extractExifFromBlob($_POST['blobUrl']);
    echo json_encode($result);
} else {
    echo json_encode([
        'success' => false,
        'message' => '請提供 blobUrl 參數',
        'usage' => 'POST blobUrl=你的圖片URL'
    ]);
}
?>
