<?php
header('Content-Type: application/json');

// 詳細的 EXIF 抓取函數
function extractExifFromBlob($blobUrl) {
    try {
        error_log("開始處理 EXIF - Blob URL: $blobUrl");
        
        // 下載圖片到臨時檔案
        $tempFile = tempnam(sys_get_temp_dir(), 'exif_');
        error_log("臨時檔案路徑: $tempFile");
        
        $imageContent = file_get_contents($blobUrl);
        
        if ($imageContent === false) {
            error_log("無法下載圖片內容");
            throw new Exception('無法下載圖片');
        }
        
        error_log("圖片下載成功，大小: " . strlen($imageContent) . " bytes");
        
        file_put_contents($tempFile, $imageContent);
        error_log("圖片已寫入臨時檔案");
        
        // 檢查檔案是否存在
        if (!file_exists($tempFile)) {
            error_log("臨時檔案不存在");
            throw new Exception('臨時檔案寫入失敗');
        }
        
        error_log("臨時檔案大小: " . filesize($tempFile) . " bytes");
        
        // 使用 PHP EXIF 擴展
        if (extension_loaded('exif')) {
            error_log("PHP EXIF 擴展已載入，開始讀取 EXIF 資料");
            
            $exifData = exif_read_data($tempFile, 'ANY_TAG', true);
            error_log("exif_read_data 結果: " . ($exifData ? '成功' : '失敗'));
            
            // 清理臨時檔案
            unlink($tempFile);
            error_log("臨時檔案已清理");
            
            if ($exifData === false) {
                error_log("無法讀取 EXIF 資料");
                return [
                    'success' => false,
                    'message' => '無法讀取 EXIF 資料',
                    'data' => null,
                    'debug' => [
                        'temp_file' => $tempFile,
                        'file_exists' => file_exists($tempFile),
                        'file_size' => filesize($tempFile)
                    ]
                ];
            }
            
            error_log("EXIF 資料結構: " . print_r($exifData, true));
            
            // 提取 EXIF 資料
            $datetime = null;
            if (isset($exifData['EXIF']['DateTimeOriginal'])) {
                $datetime = convertExifDate($exifData['EXIF']['DateTimeOriginal']);
                error_log("找到 DateTimeOriginal: " . $exifData['EXIF']['DateTimeOriginal'] . " -> " . $datetime);
            } elseif (isset($exifData['EXIF']['CreateDate'])) {
                $datetime = convertExifDate($exifData['EXIF']['CreateDate']);
                error_log("找到 CreateDate: " . $exifData['EXIF']['CreateDate'] . " -> " . $datetime);
            } else {
                error_log("未找到日期時間資訊");
            }
            
            $latitude = null;
            $longitude = null;
            if (isset($exifData['GPS']['GPSLatitude']) && isset($exifData['GPS']['GPSLongitude'])) {
                error_log("找到 GPS 資料: " . print_r($exifData['GPS'], true));
                $latitude = convertGPSToDecimal($exifData['GPS']['GPSLatitude'], $exifData['GPS']['GPSLatitudeRef'] ?? 'N');
                $longitude = convertGPSToDecimal($exifData['GPS']['GPSLongitude'], $exifData['GPS']['GPSLongitudeRef'] ?? 'E');
                error_log("GPS 轉換結果: 緯度=$latitude, 經度=$longitude");
            } else {
                error_log("未找到 GPS 資料");
            }
            
            return [
                'success' => true,
                'message' => 'EXIF 資料抓取成功',
                'data' => [
                    'datetime' => $datetime,
                    'latitude' => $latitude,
                    'longitude' => $longitude
                ],
                'raw_exif' => $exifData,
                'debug' => [
                    'method' => 'php_exif',
                    'temp_file' => $tempFile,
                    'file_size' => strlen($imageContent)
                ]
            ];
        } else {
            error_log("PHP EXIF 擴展未載入");
            unlink($tempFile);
            return [
                'success' => false,
                'message' => 'PHP EXIF 擴展未載入',
                'data' => null
            ];
        }
        
    } catch (Exception $e) {
        error_log("EXIF 抓取錯誤: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'EXIF 抓取錯誤: ' . $e->getMessage(),
            'data' => null
        ];
    }
}

function convertExifDate($exifDate) {
    // EXIF 日期格式: YYYY:MM:DD HH:MM:SS
    $date = DateTime::createFromFormat('Y:m:d H:i:s', $exifDate);
    if ($date) {
        return $date->format('Y-m-d H:i:s');
    }
    return null;
}

function convertGPSToDecimal($gpsArray, $ref) {
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

// 測試 EXIF 抓取
if (isset($_POST['blobUrl'])) {
    $blobUrl = $_POST['blobUrl'];
    error_log("收到 EXIF 測試請求: $blobUrl");
    $result = extractExifFromBlob($blobUrl);
    echo json_encode($result);
} else {
    echo json_encode([
        'success' => false,
        'message' => '請提供 blobUrl 參數',
        'usage' => 'POST blobUrl=你的圖片URL'
    ]);
}
?>
