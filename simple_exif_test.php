<?php
header('Content-Type: application/json');

// 簡單的 EXIF 測試
function testExifExtraction($imageUrl) {
    try {
        error_log("=== 開始 EXIF 測試 ===");
        error_log("圖片 URL: $imageUrl");
        
        // 下載圖片
        $imageContent = file_get_contents($imageUrl);
        if ($imageContent === false) {
            error_log("❌ 無法下載圖片");
            return ['success' => false, 'error' => '無法下載圖片'];
        }
        
        error_log("✅ 圖片下載成功，大小: " . strlen($imageContent) . " bytes");
        
        // 寫入臨時檔案
        $tempFile = tempnam(sys_get_temp_dir(), 'exif_test_');
        file_put_contents($tempFile, $imageContent);
        
        error_log("✅ 臨時檔案寫入成功: $tempFile");
        error_log("臨時檔案大小: " . filesize($tempFile) . " bytes");
        
        // 檢查 PHP EXIF 擴展
        if (!extension_loaded('exif')) {
            error_log("❌ PHP EXIF 擴展未載入");
            unlink($tempFile);
            return ['success' => false, 'error' => 'PHP EXIF 擴展未載入'];
        }
        
        error_log("✅ PHP EXIF 擴展已載入");
        
        // 讀取 EXIF 資料
        $exifData = exif_read_data($tempFile, 'ANY_TAG', true);
        
        // 清理臨時檔案
        unlink($tempFile);
        error_log("✅ 臨時檔案已清理");
        
        if ($exifData === false) {
            error_log("❌ 無法讀取 EXIF 資料");
            return ['success' => false, 'error' => '無法讀取 EXIF 資料'];
        }
        
        error_log("✅ EXIF 資料讀取成功");
        error_log("EXIF 資料結構: " . print_r($exifData, true));
        
        // 提取資訊
        $result = [
            'success' => true,
            'datetime' => null,
            'latitude' => null,
            'longitude' => null,
            'raw_exif' => $exifData
        ];
        
        // 日期時間
        if (isset($exifData['EXIF']['DateTimeOriginal'])) {
            $result['datetime'] = $exifData['EXIF']['DateTimeOriginal'];
            error_log("✅ 找到日期時間: " . $result['datetime']);
        } else {
            error_log("❌ 未找到日期時間");
        }
        
        // GPS 座標
        if (isset($exifData['GPS']['GPSLatitude']) && isset($exifData['GPS']['GPSLongitude'])) {
            $result['latitude'] = $exifData['GPS']['GPSLatitude'];
            $result['longitude'] = $exifData['GPS']['GPSLongitude'];
            error_log("✅ 找到 GPS 座標: " . print_r($result['latitude'], true) . ", " . print_r($result['longitude'], true));
        } else {
            error_log("❌ 未找到 GPS 座標");
        }
        
        error_log("=== EXIF 測試完成 ===");
        return $result;
        
    } catch (Exception $e) {
        error_log("❌ EXIF 測試錯誤: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// 處理請求
if (isset($_POST['imageUrl'])) {
    $imageUrl = $_POST['imageUrl'];
    $result = testExifExtraction($imageUrl);
    echo json_encode($result);
} else {
    echo json_encode([
        'success' => false,
        'message' => '請提供 imageUrl 參數',
        'usage' => 'POST imageUrl=你的圖片URL'
    ]);
}
?>
