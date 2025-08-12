<?php
header('Content-Type: application/json');

// 簡單的 EXIF 測試
function testExif($blobUrl, $fileName) {
    $result = [
        'success' => false,
        'blob_url' => $blobUrl,
        'file_name' => $fileName,
        'exif_extension' => extension_loaded('exif'),
        'errors' => [],
        'data' => null
    ];
    
    try {
        // 檢查 EXIF 擴展
        if (!extension_loaded('exif')) {
            $result['errors'][] = 'EXIF 擴展未載入';
            return $result;
        }
        
        // 嘗試直接讀取
        $exifData = exif_read_data($blobUrl, 'ANY_TAG', true);
        
        if ($exifData === false) {
            $result['errors'][] = '無法直接從 URL 讀取 EXIF';
            
            // 嘗試下載檔案
            $tempFile = tempnam(sys_get_temp_dir(), 'exif_');
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
                
                $exifData = exif_read_data($tempFile, 'ANY_TAG', true);
                unlink($tempFile);
                
                if ($exifData !== false) {
                    $result['errors'][] = '從臨時檔案成功讀取 EXIF';
                } else {
                    $result['errors'][] = '從臨時檔案也無法讀取 EXIF';
                }
            } else {
                $result['errors'][] = '無法下載檔案內容';
            }
        }
        
        if ($exifData !== false) {
            $result['success'] = true;
            $result['data'] = $exifData;
        }
        
    } catch (Exception $e) {
        $result['errors'][] = 'Exception: ' . $e->getMessage();
    }
    
    return $result;
}

// 處理請求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $blobUrl = $_POST['blobUrl'] ?? '';
    $fileName = $_POST['fileName'] ?? '';
    
    if ($blobUrl && $fileName) {
        $result = testExif($blobUrl, $fileName);
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['error' => '請提供 blobUrl 和 fileName 參數']);
    }
} else {
    echo json_encode([
        'usage' => 'POST blobUrl=圖片URL&fileName=檔案名稱',
        'status' => 'ready'
    ]);
}
?>