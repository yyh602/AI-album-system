<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/tmp/php_errors.log');

$result = [
    'extensions' => [
        'exif' => extension_loaded('exif'),
        'imagick' => extension_loaded('imagick'),
        'curl' => extension_loaded('curl')
    ],
    'azure_storage' => [
        'connection_string' => getenv('AZURE_STORAGE_CONNECTION_STRING') ? '已設定' : '未設定',
        'container_name' => getenv('AZURE_STORAGE_CONTAINER_NAME') ?: 'photos'
    ],
    'temp_dir' => sys_get_temp_dir(),
    'test_results' => []
];

// 測試 Imagick 是否支援 HEIC
if (extension_loaded('imagick')) {
    try {
        $imagick = new Imagick();
        $formats = $imagick->queryFormats();
        $result['test_results']['heic_support'] = in_array('HEIC', $formats) ? '支援' : '不支援';
        $result['test_results']['heif_support'] = in_array('HEIF', $formats) ? '支援' : '不支援';
        $result['test_results']['jpeg_support'] = in_array('JPEG', $formats) ? '支援' : '不支援';
        $result['test_results']['available_formats'] = array_filter($formats, function($format) {
            return strpos(strtoupper($format), 'HEI') !== false || strpos(strtoupper($format), 'JPEG') !== false;
        });
    } catch (Exception $e) {
        $result['test_results']['imagick_error'] = $e->getMessage();
    }
}

// 測試 Azure Storage 連線
if (getenv('AZURE_STORAGE_CONNECTION_STRING')) {
    try {
        require_once 'azure_storage.php';
        $azureStorage = new AzureStorage();
        $result['test_results']['azure_storage_connection'] = '成功';
    } catch (Exception $e) {
        $result['test_results']['azure_storage_error'] = $e->getMessage();
    }
} else {
    $result['test_results']['azure_storage_connection'] = '跳過（無環境變數）';
}

// 測試檔案寫入權限
$testFile = tempnam(sys_get_temp_dir(), 'test_');
if (file_put_contents($testFile, 'test') !== false) {
    $result['test_results']['temp_write'] = '成功';
    unlink($testFile);
} else {
    $result['test_results']['temp_write'] = '失敗';
}

// 測試 HEIC 轉換功能
if (extension_loaded('imagick')) {
    try {
        // 創建一個測試的 HEIC 檔案（模擬）
        $testHeicFile = tempnam(sys_get_temp_dir(), 'test_heic_');
        $testHeicFile .= '.heic';
        
        // 創建一個簡單的測試圖片
        $imagick = new Imagick();
        $imagick->newImage(100, 100, 'red');
        $imagick->setImageFormat('heic');
        $imagick->writeImage($testHeicFile);
        $imagick->destroy();
        
        // 測試轉換
        $imagick = new Imagick($testHeicFile);
        $imagick->setImageFormat('jpg');
        
        $testJpgFile = tempnam(sys_get_temp_dir(), 'test_jpg_');
        $testJpgFile .= '.jpg';
        $imagick->writeImage($testJpgFile);
        $imagick->destroy();
        
        // 檢查檔案是否存在
        if (file_exists($testJpgFile)) {
            $result['test_results']['heic_to_jpg_conversion'] = '成功';
            $result['test_results']['converted_file_size'] = filesize($testJpgFile);
        } else {
            $result['test_results']['heic_to_jpg_conversion'] = '失敗';
        }
        
        // 清理測試檔案
        if (file_exists($testHeicFile)) unlink($testHeicFile);
        if (file_exists($testJpgFile)) unlink($testJpgFile);
        
    } catch (Exception $e) {
        $result['test_results']['heic_to_jpg_conversion'] = '錯誤: ' . $e->getMessage();
    }
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
