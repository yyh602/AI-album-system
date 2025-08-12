<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/tmp/php_errors.log');

try {
    require_once("DB_open.php");
    require_once("DB_helper.php");
    
    $result = [
        'database_info' => [],
        'photos_table_schema' => [],
        'test_coordinates' => []
    ];
    
    // 檢查資料庫資訊
    $result['database_info']['database_name'] = $link->query("SELECT DATABASE()")->fetch_array()[0];
    $result['database_info']['mysql_version'] = $link->server_info;
    
    // 檢查 photos 表格結構
    $schema_query = "DESCRIBE photos";
    $schema_result = $link->query($schema_query);
    
    while ($row = $schema_result->fetch_assoc()) {
        $result['photos_table_schema'][] = $row;
    }
    
    // 檢查 latitude 和 longitude 欄位的詳細資訊
    $column_query = "SELECT 
        COLUMN_NAME,
        DATA_TYPE,
        IS_NULLABLE,
        COLUMN_DEFAULT,
        NUMERIC_PRECISION,
        NUMERIC_SCALE,
        COLUMN_TYPE
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'photos' 
    AND COLUMN_NAME IN ('latitude', 'longitude')";
    
    $column_result = $link->query($column_query);
    while ($row = $column_result->fetch_assoc()) {
        $result['coordinate_columns'][] = $row;
    }
    
    // 測試一些座標值
    $test_coords = [
        ['lat' => 25.0330, 'lng' => 121.5654], // 台北
        ['lat' => 24.1477, 'lng' => 120.6736], // 台中
        ['lat' => 22.9997, 'lng' => 120.2270], // 台南
        ['lat' => 25.5261, 'lng' => 121.7464], // 你之前錯誤的座標
        ['lat' => 24.1333, 'lng' => 120.6500], // 正確的台中座標
    ];
    
    foreach ($test_coords as $coord) {
        $test_query = "SELECT ? as test_lat, ? as test_lng";
        $stmt = $link->prepare($test_query);
        $stmt->bind_param("dd", $coord['lat'], $coord['lng']);
        $stmt->execute();
        $test_result = $stmt->get_result()->fetch_assoc();
        
        $result['test_coordinates'][] = [
            'input' => $coord,
            'database_result' => $test_result,
            'status' => 'success'
        ];
    }
    
    // 檢查是否有現有的照片資料
    $count_query = "SELECT COUNT(*) as total_photos FROM photos";
    $count_result = $link->query($count_query);
    $result['existing_data']['total_photos'] = $count_result->fetch_assoc()['total_photos'];
    
    // 檢查最近的座標資料
    $recent_query = "SELECT latitude, longitude, filename, created_at 
                     FROM photos 
                     WHERE latitude IS NOT NULL 
                     ORDER BY created_at DESC 
                     LIMIT 5";
    $recent_result = $link->query($recent_query);
    
    while ($row = $recent_result->fetch_assoc()) {
        $result['recent_coordinates'][] = $row;
    }
    
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} finally {
    if (isset($link)) {
        require_once("DB_close.php");
    }
}
?>
