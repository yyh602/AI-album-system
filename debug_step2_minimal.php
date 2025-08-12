<?php
header('Content-Type: application/json');

$result = [
    'step' => 'minimal',
    'exif_function_exists' => function_exists('exif_read_data'),
    'exif_loaded' => extension_loaded('exif'),
    'test_url' => 'https://albumstorage1411131020.blob.core.windows.net/photos/689a3b5fc2483.HEIC'
];

if (function_exists('exif_read_data')) {
    $exifData = exif_read_data($result['test_url'], 'ANY_TAG', true);
    $result['exif_result'] = ($exifData !== false) ? 'success' : 'failed';
    $result['exif_data'] = $exifData;
} else {
    $result['exif_result'] = 'function_not_exists';
}

echo json_encode($result);
?>
