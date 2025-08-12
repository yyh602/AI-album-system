<?php
header('Content-Type: application/json');

$blobUrl = $_POST['blobUrl'] ?? '';
$fileName = $_POST['fileName'] ?? '';

$result = [
    'step' => 2,
    'status' => 'testing',
    'blob_url' => $blobUrl,
    'file_name' => $fileName,
    'exif_loaded' => extension_loaded('exif'),
    'test_result' => null
];

// 簡單測試：嘗試直接讀取
if (extension_loaded('exif')) {
    $exifData = exif_read_data($blobUrl, 'ANY_TAG', true);
    if ($exifData === false) {
        $result['test_result'] = 'direct_read_failed';
        $result['status'] = 'failed';
    } else {
        $result['test_result'] = 'direct_read_success';
        $result['status'] = 'success';
        $result['exif_data'] = $exifData;
    }
} else {
    $result['test_result'] = 'exif_not_loaded';
    $result['status'] = 'error';
}

echo json_encode($result);
?>
