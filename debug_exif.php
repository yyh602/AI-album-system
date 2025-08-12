<?php
// 確保在輸出任何內容之前設定 header
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0); // 關閉錯誤顯示，避免 HTML 輸出
ini_set('log_errors', 1); // 開啟錯誤日誌

// 測試 EXIF 抓取功能
function testExifExtraction($blobUrl, $fileName) {
    $result = [
        'success' => false,
        'blob_url' => $blobUrl,
        'file_name' => $fileName,
        'exif_extension_loaded' => extension_loaded('exif'),
        'raw_exif_data' => null,
        'parsed_data' => null,
        'errors' => []
    ];
    
    try {
        // 檢查 EXIF 擴展
        if (!extension_loaded('exif')) {
            $result['errors'][] = 'PHP EXIF 擴展未載入';
            return $result;
        }
        
        // 嘗試直接讀取 EXIF
        error_log("嘗試讀取 EXIF: $blobUrl");
        $exifData = exif_read_data($blobUrl, 'ANY_TAG', true);
        
        if ($exifData === false) {
            $result['errors'][] = 'exif_read_data 返回 false';
            
            // 嘗試下載檔案到臨時位置再讀取
            $tempFile = tempnam(sys_get_temp_dir(), 'exif_test_');
            $tempFile .= '.jpg';
            
            $context = stream_context_create([
                'http' => [
                    'timeout' => 30,
                    'user_agent' => 'Mozilla/5.0 (compatible; AI-Album-System/1.0)'
                ]
            ]);
            
            $fileContent = file_get_contents($blobUrl, false, $context);
            if ($fileContent !== false) {
                file_put_contents($tempFile, $fileContent);
                error_log("檔案已下載到: $tempFile");
                
                $exifData = exif_read_data($tempFile, 'ANY_TAG', true);
                if ($exifData !== false) {
                    $result['raw_exif_data'] = $exifData;
                    $result['errors'][] = '從臨時檔案成功讀取 EXIF';
                } else {
                    $result['errors'][] = '從臨時檔案也無法讀取 EXIF';
                }
                
                unlink($tempFile);
            } else {
                $result['errors'][] = '無法下載檔案內容';
            }
        } else {
            $result['raw_exif_data'] = $exifData;
        }
        
        // 解析 EXIF 資料
        if ($result['raw_exif_data']) {
            $parsed = [
                'datetime' => null,
                'latitude' => null,
                'longitude' => null
            ];
            
            // 日期時間
            if (isset($exifData['EXIF']['DateTimeOriginal'])) {
                $parsed['datetime'] = convertExifDate($exifData['EXIF']['DateTimeOriginal']);
            } elseif (isset($exifData['EXIF']['CreateDate'])) {
                $parsed['datetime'] = convertExifDate($exifData['EXIF']['CreateDate']);
            } elseif (isset($exifData['IFD0']['DateTime'])) {
                $parsed['datetime'] = convertExifDate($exifData['IFD0']['DateTime']);
            }
            
            // GPS 座標
            if (isset($exifData['GPS']['GPSLatitude']) && isset($exifData['GPS']['GPSLongitude'])) {
                $parsed['latitude'] = convertGPSToDecimal($exifData['GPS']['GPSLatitude'], $exifData['GPS']['GPSLatitudeRef'] ?? 'N');
                $parsed['longitude'] = convertGPSToDecimal($exifData['GPS']['GPSLongitude'], $exifData['GPS']['GPSLongitudeRef'] ?? 'E');
            }
            
            if (!$parsed['datetime']) {
                $parsed['datetime'] = date('Y-m-d H:i:s');
            }
            
            $result['parsed_data'] = $parsed;
            $result['success'] = true;
        }
        
    } catch (Exception $e) {
        $result['errors'][] = 'Exception: ' . $e->getMessage();
    }
    
    return $result;
}

function convertExifDate($exifDate) {
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

// 處理 POST 請求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $blobUrl = $_POST['blobUrl'] ?? '';
    $fileName = $_POST['fileName'] ?? '';
    
    if ($blobUrl && $fileName) {
        $result = testExifExtraction($blobUrl, $fileName);
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'error' => '請提供 blobUrl 和 fileName 參數'
        ]);
    }
} else {
    echo json_encode([
        'usage' => 'POST blobUrl=圖片URL&fileName=檔案名稱',
        'example' => 'curl -X POST -d "blobUrl=https://example.com/image.jpg&fileName=test.jpg" https://your-domain.com/debug_exif.php'
    ]);
}
?>
