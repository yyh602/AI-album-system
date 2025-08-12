<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/tmp/php_errors.log');

// 複製 convertGPSToDecimal 函數
function convertGPSToDecimal($gpsArray, $ref) {
    if (!is_array($gpsArray) || count($gpsArray) !== 3) {
        return null;
    }
    
    // 處理分數格式 (例如: "24/1", "8/1", "5014/100")
    $degrees = is_string($gpsArray[0]) ? eval("return " . $gpsArray[0] . ";") : floatval($gpsArray[0]);
    $minutes = is_string($gpsArray[1]) ? eval("return " . $gpsArray[1] . ";") : floatval($gpsArray[1]);
    $seconds = is_string($gpsArray[2]) ? eval("return " . $gpsArray[2] . ";") : floatval($gpsArray[2]);
    
    $decimal = $degrees + ($minutes / 60) + ($seconds / 3600);
    
    if ($ref === 'S' || $ref === 'W') {
        $decimal *= -1;
    }
    
    return $decimal;
}

$result = [
    'test_cases' => []
];

// 測試案例 1: 你之前提供的 EXIF 資料
$test_case_1 = [
    'name' => '你的原始 EXIF 資料',
    'input' => [
        'latitude' => ["24/1", "8/1", "5014/100"],
        'latitudeRef' => 'N',
        'longitude' => ["120/1", "39/1", "3947/100"],
        'longitudeRef' => 'E'
    ]
];

$lat1 = convertGPSToDecimal($test_case_1['input']['latitude'], $test_case_1['input']['latitudeRef']);
$lng1 = convertGPSToDecimal($test_case_1['input']['longitude'], $test_case_1['input']['longitudeRef']);

$result['test_cases'][] = [
    'name' => $test_case_1['name'],
    'input' => $test_case_1['input'],
    'result' => [
        'latitude' => $lat1,
        'longitude' => $lng1,
        'latitude_calculation' => "24 + (8/60) + (50.14/3600) = " . (24 + (8/60) + (50.14/3600)),
        'longitude_calculation' => "120 + (39/60) + (39.47/3600) = " . (120 + (39/60) + (39.47/3600))
    ]
];

// 測試案例 2: 手動計算正確的台中座標
$test_case_2 = [
    'name' => '手動計算台中座標',
    'input' => [
        'latitude' => [24, 8, 0],
        'latitudeRef' => 'N',
        'longitude' => [120, 39, 0],
        'longitudeRef' => 'E'
    ]
];

$lat2 = convertGPSToDecimal($test_case_2['input']['latitude'], $test_case_2['input']['latitudeRef']);
$lng2 = convertGPSToDecimal($test_case_2['input']['longitude'], $test_case_2['input']['longitudeRef']);

$result['test_cases'][] = [
    'name' => $test_case_2['name'],
    'input' => $test_case_2['input'],
    'result' => [
        'latitude' => $lat2,
        'longitude' => $lng2
    ]
];

// 測試案例 3: 檢查資料庫範圍限制
$result['database_range_check'] = [
    'latitude_range' => [
        'min' => -90,
        'max' => 90,
        'test_values' => [
            'valid' => [25.0330, 24.1477, 22.9997, 24.1333],
            'invalid' => [91, -91, 999, -999]
        ]
    ],
    'longitude_range' => [
        'min' => -180,
        'max' => 180,
        'test_values' => [
            'valid' => [121.5654, 120.6736, 120.2270, 120.6500],
            'invalid' => [181, -181, 999, -999]
        ]
    ]
];

// 測試案例 4: 檢查 eval 函數的安全性
$result['eval_safety_check'] = [
    'warning' => '使用 eval() 函數可能有安全風險',
    'suggested_improvement' => '使用更安全的數學計算方法'
];

// 測試案例 5: 模擬實際的 EXIF 資料格式
$test_case_5 = [
    'name' => '模擬實際 EXIF 格式',
    'input' => [
        'latitude' => ["24", "8", "50.1399999999994606"],
        'latitudeRef' => 'N',
        'longitude' => ["120", "39", "39.46999999720319"],
        'longitudeRef' => 'E'
    ]
];

$lat5 = convertGPSToDecimal($test_case_5['input']['latitude'], $test_case_5['input']['latitudeRef']);
$lng5 = convertGPSToDecimal($test_case_5['input']['longitude'], $test_case_5['input']['longitudeRef']);

$result['test_cases'][] = [
    'name' => $test_case_5['name'],
    'input' => $test_case_5['input'],
    'result' => [
        'latitude' => $lat5,
        'longitude' => $lng5
    ]
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
