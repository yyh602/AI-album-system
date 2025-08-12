<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/tmp/php_errors.log');

// 改進的 EXIF 抓取函數（支援 HEIC）
function extractExifFromBlob($blobUrl, $fileName) {
    try {
        // 檢查必要的擴展
        if (!extension_loaded('exif')) {
            throw new Exception('PHP EXIF 擴展未載入');
        }
        
        if (!extension_loaded('imagick')) {
            throw new Exception('PHP Imagick 擴展未載入（需要處理 HEIC 檔案）');
        }
        
        error_log("開始抓取 EXIF: $fileName, URL: $blobUrl");
        
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        // 如果是 HEIC 檔案，先轉換為 JPG
        if ($fileExtension === 'heic' || $fileExtension === 'heif') {
            error_log("檢測到 HEIC 檔案，開始轉換");
            
            // 下載 HEIC 檔案
            $context = stream_context_create([
                'http' => [
                    'timeout' => 30,
                    'user_agent' => 'Mozilla/5.0 (compatible; AI-Album-System/1.0)'
                ]
            ]);
            
            $fileContent = file_get_contents($blobUrl, false, $context);
            if ($fileContent === false) {
                error_log("無法下載 HEIC 檔案");
                return [
                    'datetime' => date('Y-m-d H:i:s'),
                    'latitude' => null,
                    'longitude' => null,
                    'original_format' => 'HEIC'
                ];
            }
            
            // 建立臨時 HEIC 檔案
            $heicTempFile = tempnam(sys_get_temp_dir(), 'heic_');
            $heicTempFile .= '.heic';
            file_put_contents($heicTempFile, $fileContent);
            
            // 使用 Imagick 轉換為 JPG
            $imagick = new Imagick($heicTempFile);
            $imagick->setImageFormat('jpg');
            
            // 建立臨時 JPG 檔案
            $jpgTempFile = tempnam(sys_get_temp_dir(), 'jpg_');
            $jpgTempFile .= '.jpg';
            $imagick->writeImage($jpgTempFile);
            $imagick->destroy();
            
            // 從轉換後的 JPG 讀取 EXIF
            $exifData = exif_read_data($jpgTempFile, 'ANY_TAG', true);
            
            // 清理臨時檔案
            unlink($heicTempFile);
            unlink($jpgTempFile);
            
            if ($exifData === false) {
                error_log("從轉換後的 JPG 也無法讀取 EXIF");
                return [
                    'datetime' => date('Y-m-d H:i:s'),
                    'latitude' => null,
                    'longitude' => null,
                    'original_format' => 'HEIC'
                ];
            }
            
            error_log("HEIC 轉換成功，EXIF 讀取成功");
        } else {
            // 對於 JPG 檔案，直接嘗試讀取
            $exifData = exif_read_data($blobUrl, 'ANY_TAG', true);
            
            if ($exifData === false) {
                error_log("無法直接從 URL 讀取 EXIF，嘗試下載檔案");
                
                // 下載檔案到臨時位置再讀取
                $tempFile = tempnam(sys_get_temp_dir(), 'exif_temp_');
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
                    error_log("檔案已下載到臨時位置: $tempFile");
                    
                    $exifData = exif_read_data($tempFile, 'ANY_TAG', true);
                    
                    // 清理臨時檔案
                    unlink($tempFile);
                    
                    if ($exifData === false) {
                        error_log("從臨時檔案也無法讀取 EXIF 資料");
                        return [
                            'datetime' => date('Y-m-d H:i:s'),
                            'latitude' => null,
                            'longitude' => null,
                            'original_format' => strtoupper(pathinfo($fileName, PATHINFO_EXTENSION)) ?: 'JPG'
                        ];
                    }
                } else {
                    error_log("無法下載檔案內容");
                    return [
                        'datetime' => date('Y-m-d H:i:s'),
                        'latitude' => null,
                        'longitude' => null,
                        'original_format' => strtoupper(pathinfo($fileName, PATHINFO_EXTENSION)) ?: 'JPG'
                    ];
                }
            }
        }
        
        error_log("EXIF 資料讀取成功，開始解析");
        
        // 解析 EXIF 資料
        $result = [
            'datetime' => null,
            'latitude' => null,
            'longitude' => null,
            'original_format' => strtoupper(pathinfo($fileName, PATHINFO_EXTENSION)) ?: 'JPG'
        ];
        
        // 日期時間
        if (isset($exifData['EXIF']['DateTimeOriginal'])) {
            $result['datetime'] = convertExifDate($exifData['EXIF']['DateTimeOriginal']);
            error_log("找到 DateTimeOriginal: " . $exifData['EXIF']['DateTimeOriginal']);
        } elseif (isset($exifData['EXIF']['CreateDate'])) {
            $result['datetime'] = convertExifDate($exifData['EXIF']['CreateDate']);
            error_log("找到 CreateDate: " . $exifData['EXIF']['CreateDate']);
        } elseif (isset($exifData['IFD0']['DateTime'])) {
            $result['datetime'] = convertExifDate($exifData['IFD0']['DateTime']);
            error_log("找到 IFD0 DateTime: " . $exifData['IFD0']['DateTime']);
        }
        
                 // GPS 座標
         if (isset($exifData['GPS']['GPSLatitude']) && isset($exifData['GPS']['GPSLongitude'])) {
             error_log("原始 GPS 資料 - 緯度: " . json_encode($exifData['GPS']['GPSLatitude']) . ", 緯度參考: " . ($exifData['GPS']['GPSLatitudeRef'] ?? 'N'));
             error_log("原始 GPS 資料 - 經度: " . json_encode($exifData['GPS']['GPSLongitude']) . ", 經度參考: " . ($exifData['GPS']['GPSLongitudeRef'] ?? 'E'));
             
             $result['latitude'] = convertGPSToDecimal($exifData['GPS']['GPSLatitude'], $exifData['GPS']['GPSLatitudeRef'] ?? 'N');
             $result['longitude'] = convertGPSToDecimal($exifData['GPS']['GPSLongitude'], $exifData['GPS']['GPSLongitudeRef'] ?? 'E');
             
             error_log("轉換後 GPS 座標: 緯度=" . $result['latitude'] . ", 經度=" . $result['longitude']);
             
             // 驗證座標合理性（台灣地區大致範圍）
             if ($result['latitude'] !== null && $result['longitude'] !== null) {
                 if ($result['latitude'] < 21.5 || $result['latitude'] > 25.5 || 
                     $result['longitude'] < 119.5 || $result['longitude'] > 122.5) {
                     error_log("警告：GPS 座標超出台灣地區範圍，可能轉換錯誤");
                 }
             }
         } else {
             error_log("未找到 GPS 座標資料");
         }
        
        // 如果沒有日期時間，使用當前時間
        if (!$result['datetime']) {
            $result['datetime'] = date('Y-m-d H:i:s');
            error_log("使用當前時間作為預設值");
        }
        
        error_log("EXIF 抓取完成: " . json_encode($result));
        return $result;
        
    } catch (Exception $e) {
        error_log("EXIF 抓取錯誤: " . $e->getMessage());
        return [
            'datetime' => date('Y-m-d H:i:s'),
            'latitude' => null,
            'longitude' => null,
            'original_format' => strtoupper(pathinfo($fileName, PATHINFO_EXTENSION)) ?: 'JPG'
        ];
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

// 上傳轉換後的 JPG 到 Azure Storage
function uploadConvertedJpg($tempJpgFile, $originalFileName) {
    try {
        // 使用現有的 Azure Storage 類別
        require_once 'azure_storage.php';
        
        $azureStorage = new AzureStorage();
        
        // 生成新的檔案名（將 .heic 改為 .jpg）
        $fileName = pathinfo($originalFileName, PATHINFO_FILENAME) . '.jpg';
        
        // 上傳檔案
        $convertedUrl = $azureStorage->uploadFromTemp($tempJpgFile, $fileName);
        
        if ($convertedUrl) {
            error_log("JPG 上傳成功: $convertedUrl");
            return $convertedUrl;
        } else {
            error_log("JPG 上傳失敗");
            return null;
        }
        
    } catch (Exception $e) {
        error_log("上傳轉換後的 JPG 錯誤: " . $e->getMessage());
        return null;
    }
}

try {
    // 檢查 session 狀態
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    // 檢查登入狀態
    if (!isset($_SESSION['username'])) {
        echo json_encode([
            'status' => 'error',
            'message' => '請先登入'
        ]);
        exit();
    }
    
    $username = $_SESSION['username'];
    $albumName = trim($_POST['albumName'] ?? '');
    $blobUrls = $_POST['blobUrls'] ?? [];
    $fileNames = $_POST['fileNames'] ?? [];
    
    // 基本驗證
    if ($albumName === '') {
        echo json_encode([
            'status' => 'error',
            'message' => '相簿名稱不可為空'
        ]);
        exit();
    }
    
    if (empty($blobUrls)) {
        echo json_encode([
            'status' => 'error',
            'message' => '沒有上傳的檔案'
        ]);
        exit();
    }
    
    // 資料庫連線
    try {
        require_once("DB_open.php");
        require_once("DB_helper.php");
        
        if (!isset($link) || $link === null) {
            throw new Exception("資料庫連線物件未定義或為 null");
        }
        
        if ($link instanceof mysqli) {
            $test_result = $link->query("SELECT 1 as test");
            if (!$test_result) {
                throw new Exception("MySQL 資料庫查詢失敗: " . mysqli_error($link));
            }
        } else {
            throw new Exception("資料庫連線類型不支援");
        }
        
    } catch (Exception $db_error) {
        error_log("save_album_blob.php 資料庫錯誤: " . $db_error->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => '資料庫連線失敗: ' . $db_error->getMessage()
        ]);
        exit();
    }
    
    // 開始資料庫交易
    mysqli_begin_transaction($link);
    
    try {
        // 1. 建立相簿記錄
        $album_sql = "INSERT INTO albums (name, username, created_at) VALUES (?, ?, NOW())";
        $album_stmt = mysqli_prepare($link, $album_sql);
        
        if (!$album_stmt) {
            throw new Exception("相簿建立失敗: " . mysqli_error($link));
        }
        
        mysqli_stmt_bind_param($album_stmt, "ss", $albumName, $username);
        $album_result = mysqli_stmt_execute($album_stmt);
        
        if (!$album_result) {
            throw new Exception("相簿建立執行失敗: " . mysqli_stmt_error($album_stmt));
        }
        
        $album_id = mysqli_insert_id($link);
        mysqli_stmt_close($album_stmt);
        
        // 2. 儲存 Blob URL 到資料庫
        $uploadedFiles = [];
        
        for ($i = 0; $i < count($blobUrls); $i++) {
            $blobUrl = $blobUrls[$i];
            $fileName = $fileNames[$i] ?? 'unknown.jpg';
            
            // 抓取 EXIF 資料
            error_log("開始處理檔案: $fileName, Blob URL: $blobUrl");
            $exifData = extractExifFromBlob($blobUrl, $fileName);
            $datetime = $exifData['datetime'] ?? date('Y-m-d H:i:s');
            $latitude = $exifData['latitude'] ?? null;
            $longitude = $exifData['longitude'] ?? null;
            
                         // 如果是 HEIC 檔案，需要轉換為 JPG 並上傳到 Azure Storage
             $displayUrl = $blobUrl;
             if ($exifData['original_format'] === 'HEIC') {
                 error_log("HEIC 檔案需要轉換為 JPG 並上傳");
                 
                 // 下載 HEIC 檔案
                 $context = stream_context_create([
                     'http' => [
                         'timeout' => 30,
                         'user_agent' => 'Mozilla/5.0 (compatible; AI-Album-System/1.0)'
                     ]
                 ]);
                 
                 $fileContent = file_get_contents($blobUrl, false, $context);
                 if ($fileContent !== false) {
                     // 建立臨時 HEIC 檔案
                     $heicTempFile = tempnam(sys_get_temp_dir(), 'heic_');
                     $heicTempFile .= '.heic';
                     file_put_contents($heicTempFile, $fileContent);
                     
                     // 使用 Imagick 轉換為 JPG
                     $imagick = new Imagick($heicTempFile);
                     $imagick->setImageFormat('jpg');
                     
                     // 建立臨時 JPG 檔案
                     $jpgTempFile = tempnam(sys_get_temp_dir(), 'jpg_');
                     $jpgTempFile .= '.jpg';
                     $imagick->writeImage($jpgTempFile);
                     $imagick->destroy();
                     
                     // 上傳轉換後的 JPG 到 Azure Storage
                     $convertedJpgUrl = uploadConvertedJpg($jpgTempFile, $fileName);
                     
                     // 清理臨時檔案
                     unlink($heicTempFile);
                     unlink($jpgTempFile);
                     
                     if ($convertedJpgUrl) {
                         $displayUrl = $convertedJpgUrl;
                         error_log("使用轉換後的 JPG URL: $displayUrl");
                     }
                 }
             }
            
            // 記錄 EXIF 抓取結果
            error_log("EXIF 抓取結果 - 檔案: $fileName, 日期: $datetime, 緯度: $latitude, 經度: $longitude");
            error_log("完整 EXIF 資料: " . json_encode($exifData));
            
            // 建立照片記錄（只包含時間和經緯度）
            $photo_sql = "INSERT INTO photos (album_id, filename, path, username, datetime, latitude, longitude, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            $photo_stmt = mysqli_prepare($link, $photo_sql);
            
            if ($photo_stmt) {
                mysqli_stmt_bind_param($photo_stmt, "issssdd", 
                    $album_id, $fileName, $displayUrl, $username, $datetime, $latitude, $longitude
                );
                $photo_result = mysqli_stmt_execute($photo_stmt);
                mysqli_stmt_close($photo_stmt);
                
                if ($photo_result) {
                    $uploadedFiles[] = [
                        'original_name' => $fileName,
                        'blob_url' => $blobUrl,
                        'exif_data' => $exifData
                    ];
                }
            }
        }
        
        // 3. 更新相簿封面（使用第一張照片）
        if (!empty($uploadedFiles)) {
            $firstPhotoBlobUrl = $uploadedFiles[0]['blob_url'];
            $update_cover_sql = "UPDATE albums SET cover_photo = ? WHERE id = ?";
            $update_cover_stmt = mysqli_prepare($link, $update_cover_sql);
            
            if ($update_cover_stmt) {
                mysqli_stmt_bind_param($update_cover_stmt, "si", $firstPhotoBlobUrl, $album_id);
                mysqli_stmt_execute($update_cover_stmt);
                mysqli_stmt_close($update_cover_stmt);
            }
        }
        
        // 4. 提交交易
        mysqli_commit($link);
        
        // 5. 成功回應
        echo json_encode([
            'status' => 'success',
            'message' => '相簿建立成功',
            'data' => [
                'album_id' => $album_id,
                'album_name' => $albumName,
                'username' => $username,
                'uploaded_files' => $uploadedFiles,
                'total_files' => count($uploadedFiles)
            ]
        ]);
        
    } catch (Exception $e) {
        // 6. 回滾交易
        mysqli_rollback($link);
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("save_album_blob.php 致命錯誤: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => '伺服器錯誤: ' . $e->getMessage()
    ]);
} catch (Throwable $t) {
    error_log("save_album_blob.php 嚴重錯誤: " . $t->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => '嚴重錯誤: ' . $t->getMessage()
    ]);
} finally {
    if (isset($link) && $link instanceof mysqli) {
        require_once("DB_close.php");
    }
}
?>
