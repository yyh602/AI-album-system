<?php
header('Content-Type: application/json');

// 測試 EXIF 抓取功能
function extractExifFromBlob($blobUrl) {
    try {
        // 下載圖片到臨時檔案
        $tempFile = tempnam(sys_get_temp_dir(), 'exif_');
        $imageContent = file_get_contents($blobUrl);
        
        if ($imageContent === false) {
            throw new Exception('無法下載圖片');
        }
        
        file_put_contents($tempFile, $imageContent);
        
        // 使用 ExifTool 抓取 EXIF 資料
        $exiftoolPath = 'exiftool';
        if (!file_exists($exiftoolPath)) {
            $exiftoolPath = '/usr/bin/exiftool';
        }
        
        if (!file_exists($exiftoolPath)) {
            // 如果 ExifTool 不可用，使用 PHP EXIF 擴展
            if (extension_loaded('exif')) {
                $exifData = exif_read_data($tempFile, 'ANY_TAG', true);
                unlink($tempFile);
                
                if ($exifData === false) {
                    return ['datetime' => null, 'latitude' => null, 'longitude' => null];
                }
                
                // 提取 EXIF 資料
                $datetime = null;
                if (isset($exifData['EXIF']['DateTimeOriginal'])) {
                    $datetime = convertExifDate($exifData['EXIF']['DateTimeOriginal']);
                } elseif (isset($exifData['EXIF']['CreateDate'])) {
                    $datetime = convertExifDate($exifData['EXIF']['CreateDate']);
                }
                
                $latitude = null;
                $longitude = null;
                if (isset($exifData['GPS']['GPSLatitude']) && isset($exifData['GPS']['GPSLongitude'])) {
                    $latitude = convertGPSToDecimal($exifData['GPS']['GPSLatitude'], $exifData['GPS']['GPSLatitudeRef'] ?? 'N');
                    $longitude = convertGPSToDecimal($exifData['GPS']['GPSLongitude'], $exifData['GPS']['GPSLongitudeRef'] ?? 'E');
                }
                
                return [
                    'datetime' => $datetime,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'method' => 'php_exif'
                ];
            } else {
                unlink($tempFile);
                return ['datetime' => null, 'latitude' => null, 'longitude' => null, 'method' => 'none'];
            }
        }
        
        // 使用 ExifTool 命令列工具
        $command = sprintf('%s -j -DateTimeOriginal -GPSLatitude -GPSLongitude "%s"', $exiftoolPath, $tempFile);
        $output = shell_exec($command);
        unlink($tempFile);
        
        if (!$output) {
            return ['datetime' => null, 'latitude' => null, 'longitude' => null, 'method' => 'exiftool_no_output'];
        }
        
        $exifData = json_decode($output, true);
        if (!$exifData || !isset($exifData[0])) {
            return ['datetime' => null, 'latitude' => null, 'longitude' => null, 'method' => 'exiftool_no_data'];
        }
        
        $data = $exifData[0];
        
        // 處理日期時間
        $datetime = null;
        if (isset($data['DateTimeOriginal'])) {
            $datetime = convertExifDate($data['DateTimeOriginal']);
        }
        
        // 處理 GPS 座標
        $latitude = null;
        $longitude = null;
        if (isset($data['GPSLatitude']) && isset($data['GPSLongitude'])) {
            $latitude = convertGPS($data['GPSLatitude']);
            $longitude = convertGPS($data['GPSLongitude']);
        }
        
        return [
            'datetime' => $datetime,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'method' => 'exiftool',
            'raw_data' => $data
        ];
        
    } catch (Exception $e) {
        error_log("EXIF 抓取錯誤: " . $e->getMessage());
        return ['datetime' => null, 'latitude' => null, 'longitude' => null, 'method' => 'error', 'error' => $e->getMessage()];
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

function convertGPS($coordinate) {
    if (preg_match('/(\d+)[^\d]+(\d+)[^\d]+([\d.]+)[^\d]*([NSEW])/', $coordinate, $matches)) {
        $degrees = floatval($matches[1]);
        $minutes = floatval($matches[2]);
        $seconds = floatval($matches[3]);
        $direction = $matches[4];
        $decimal = $degrees + $minutes / 60 + $seconds / 3600;
        if (in_array($direction, ['S', 'W'])) $decimal *= -1;
        return $decimal;
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

// 測試環境
echo json_encode([
    'extensions' => [
        'exif' => extension_loaded('exif'),
        'gd' => extension_loaded('gd'),
        'imagick' => extension_loaded('imagick')
    ],
    'exiftool' => [
        'exists' => file_exists('exiftool'),
        'path' => file_exists('exiftool') ? 'exiftool' : (file_exists('/usr/bin/exiftool') ? '/usr/bin/exiftool' : 'not_found'),
        'version' => shell_exec('exiftool -ver 2>&1') ?: 'not_available'
    ],
    'temp_dir' => sys_get_temp_dir(),
    'test_time' => date('Y-m-d H:i:s')
]);
?>
