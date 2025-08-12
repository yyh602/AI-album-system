<?php
header('Content-Type: application/json');

// 步驟 2：嘗試讀取 EXIF
$blobUrl = $_POST['blobUrl'] ?? '';
$fileName = $_POST['fileName'] ?? '';

$result = [
    'step' => 2,
    'status' => 'testing',
    'blob_url' => $blobUrl,
    'file_name' => $fileName,
    'exif_loaded' => extension_loaded('exif'),
    'direct_read_result' => null,
    'temp_file_result' => null,
    'errors' => []
];

try {
    if (!extension_loaded('exif')) {
        $result['errors'][] = 'EXIF 擴展未載入';
        $result['status'] = 'error';
    } else {
        // 嘗試直接從 URL 讀取
        $exifData = exif_read_data($blobUrl, 'ANY_TAG', true);
        
        if ($exifData === false) {
            $result['direct_read_result'] = 'failed';
            $result['errors'][] = '無法直接從 URL 讀取 EXIF';
            
            // 嘗試下載到臨時檔案
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
                $result['temp_file_result'] = 'downloaded';
                
                $exifData = exif_read_data($tempFile, 'ANY_TAG', true);
                unlink($tempFile);
                
                if ($exifData !== false) {
                    $result['temp_file_result'] = 'success';
                    $result['status'] = 'success';
                    $result['exif_data'] = $exifData;
                } else {
                    $result['temp_file_result'] = 'failed';
                    $result['errors'][] = '從臨時檔案也無法讀取 EXIF';
                }
            } else {
                $result['temp_file_result'] = 'download_failed';
                $result['errors'][] = '無法下載檔案內容';
            }
        } else {
            $result['direct_read_result'] = 'success';
            $result['status'] = 'success';
            $result['exif_data'] = $exifData;
        }
    }
    
} catch (Exception $e) {
    $result['errors'][] = 'Exception: ' . $e->getMessage();
    $result['status'] = 'error';
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
