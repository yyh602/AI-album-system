<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '/tmp/php_errors.log');

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

// 資料庫連線測試
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
    echo json_encode([
        'status' => 'error',
        'message' => '資料庫連線失敗: ' . $db_error->getMessage()
    ]);
    exit();
}

// 安全的分數轉換函數
function safeFractionToFloat($fraction) {
    if (is_numeric($fraction)) {
        return (float)$fraction;
    }
    
    if (is_string($fraction)) {
        if (strpos($fraction, '/') !== false) {
            $parts = explode('/', $fraction);
            if (count($parts) == 2 && is_numeric($parts[0]) && is_numeric($parts[1]) && $parts[1] != 0) {
                return (float)$parts[0] / (float)$parts[1];
            }
        }
        return (float)$fraction;
    }
    
    return 0.0;
}

// GPS 座標轉換函數
function convertGPSToDecimal($gpsArray, $hemisphere) {
    if (empty($gpsArray) || count($gpsArray) < 3) {
        return null;
    }
    
    try {
        $degrees = safeFractionToFloat($gpsArray[0]);
        $minutes = safeFractionToFloat($gpsArray[1]);
        $seconds = safeFractionToFloat($gpsArray[2]);
        
        $decimal = $degrees + ($minutes / 60.0) + ($seconds / 3600.0);
        
        // 根據半球調整正負號
        if (strtoupper($hemisphere) == 'S' || strtoupper($hemisphere) == 'W') {
            $decimal = -$decimal;
        }
        
        return $decimal;
        
    } catch (Exception $e) {
        error_log("GPS 轉換錯誤: " . $e->getMessage());
        return null;
    }
}

// 最終修復的 SAS Token 生成函數（使用 Azure Storage REST API 的正確格式）
function generateSasToken($accountName, $accountKey, $containerName, $blobName) {
    $startTime = gmdate('Y-m-d\TH:i:s\Z');
    $endTime = gmdate('Y-m-d\TH:i:s\Z', strtotime('+1 hour'));
    $permissions = 'w';
    $resource = 'b';
    $version = '2020-04-08';
    
    // 正確的 canonicalized resource 格式
    $canonicalizedResource = "/blob/{$accountName}/{$containerName}/{$blobName}";
    
    // 根據 Azure Storage REST API 文檔的正確 string to sign 格式
    $stringToSign = "{$permissions}\n{$startTime}\n{$endTime}\n{$canonicalizedResource}\n\n\n{$version}\n{$resource}";
    
    error_log("String to sign: " . str_replace("\n", "\\n", $stringToSign));
    
    $signature = base64_encode(hash_hmac('sha256', $stringToSign, base64_decode($accountKey), true));
    $sasToken = "sv={$version}&st={$startTime}&se={$endTime}&sp={$permissions}&sr={$resource}&sig=" . urlencode($signature);
    
    return $sasToken;
}

// 最終修復的上傳函數（使用 SharedKey 認證）
function uploadConvertedJpg($jpgTempFile, $originalFileName) {
    try {
        error_log("開始上傳轉換後的 JPG: $jpgTempFile");
        
        // 檢查檔案是否存在
        if (!file_exists($jpgTempFile)) {
            error_log("JPG 檔案不存在: $jpgTempFile");
            return null;
        }
        
        // 生成新的檔案名（將 .heic 替換為 .jpg）
        $newFileName = str_replace(['.heic', '.HEIC', '.heif', '.HEIF'], '.jpg', $originalFileName);
        $newFileName = uniqid() . '_' . $newFileName;
        
        error_log("新檔案名: $newFileName");
        
        // 取得環境變數
        $connectionString = getenv('AZURE_STORAGE_CONNECTION_STRING');
        $containerName = getenv('AZURE_STORAGE_CONTAINER_NAME') ?: 'photos';
        
        if (!$connectionString) {
            error_log("Azure Storage connection string not found");
            return null;
        }
        
        // 解析連接字串
        $accountName = '';
        $accountKey = '';
        $parts = explode(';', $connectionString);
        foreach ($parts as $part) {
            if (strpos($part, 'AccountName=') === 0) {
                $accountName = substr($part, 12);
            } elseif (strpos($part, 'AccountKey=') === 0) {
                $accountKey = substr($part, 11);
            }
        }
        
        if (!$accountName || !$accountKey) {
            error_log("Invalid connection string");
            return null;
        }
        
        error_log("Azure Storage 帳戶: $accountName, 容器: $containerName");
        
        // 使用 SharedKey 認證而不是 SAS Token
        $contentLength = filesize($jpgTempFile);
        $contentType = 'image/jpeg';
        $date = gmdate('D, d M Y H:i:s T');
        
        // 構建 canonicalized headers
        $canonicalizedHeaders = "x-ms-blob-type:BlockBlob\nx-ms-date:{$date}\nx-ms-version:2020-04-08\n";
        
        // 構建 canonicalized resource
        $canonicalizedResource = "/{$accountName}/{$containerName}/{$newFileName}";
        
        // 構建 string to sign
        $stringToSign = "PUT\n\n\n{$contentLength}\n\n{$contentType}\n\n\n\n\n\n\n{$canonicalizedHeaders}{$canonicalizedResource}";
        
        error_log("String to sign: " . str_replace("\n", "\\n", $stringToSign));
        
        // 生成簽名
        $signature = base64_encode(hash_hmac('sha256', $stringToSign, base64_decode($accountKey), true));
        
        // 構建 Authorization header
        $authorization = "SharedKey {$accountName}:{$signature}";
        
        // 構建 URL（不需要 SAS Token）
        $blobUrl = "https://{$accountName}.blob.core.windows.net/{$containerName}/{$newFileName}";
        
        error_log("準備上傳到: $blobUrl");
        error_log("Authorization: $authorization");
        
        // 使用 cURL 上傳檔案
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $blobUrl);
        curl_setopt($ch, CURLOPT_PUT, true);
        curl_setopt($ch, CURLOPT_INFILE, fopen($jpgTempFile, 'r'));
        curl_setopt($ch, CURLOPT_INFILESIZE, $contentLength);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: {$authorization}",
            "Content-Type: {$contentType}",
            "x-ms-blob-type: BlockBlob",
            "x-ms-date: {$date}",
            "x-ms-version: 2020-04-08"
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            error_log("cURL 錯誤: $error");
            return null;
        }
        
        if ($httpCode === 201) {
            error_log("JPG 上傳成功: $blobUrl");
            return $blobUrl;
        } else {
            error_log("JPG 上傳失敗，HTTP 代碼: $httpCode, 回應: $response");
            return null;
        }
        
    } catch (Exception $e) {
        error_log("JPG 上傳錯誤: " . $e->getMessage());
        return null;
    }
}

// 完整的 EXIF 處理函數（包含 HEIC 轉換和上傳）
function processExifWithConversion($blobUrl, $fileName) {
    try {
        error_log("開始處理檔案: $fileName");
        
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        // 檢查是否為 HEIC
        if ($fileExtension === 'heic' || $fileExtension === 'heif') {
            error_log("檢測到 HEIC 檔案: $fileName");
            
            // 檢查 Imagick 擴展
            if (!extension_loaded('imagick')) {
                error_log("Imagick 擴展未載入");
                return [
                    'datetime' => date('Y-m-d H:i:s'),
                    'latitude' => null,
                    'longitude' => null,
                    'original_format' => 'HEIC',
                    'error' => 'Imagick 擴展未載入'
                ];
            }
            
            // 檢查 EXIF 擴展
            if (!extension_loaded('exif')) {
                error_log("EXIF 擴展未載入");
                return [
                    'datetime' => date('Y-m-d H:i:s'),
                    'latitude' => null,
                    'longitude' => null,
                    'original_format' => 'HEIC',
                    'error' => 'EXIF 擴展未載入'
                ];
            }
            
            // 嘗試下載檔案
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
                    'original_format' => 'HEIC',
                    'error' => '無法下載檔案'
                ];
            }
            
            // 建立臨時檔案
            $tempFile = tempnam(sys_get_temp_dir(), 'heic_');
            $tempFile .= '.heic';
            file_put_contents($tempFile, $fileContent);
            
            error_log("HEIC 檔案已下載到: $tempFile");
            
            // 嘗試轉換為 JPG
            try {
                $imagick = new Imagick($tempFile);
                $imagick->setImageFormat('jpg');
                
                $jpgTempFile = tempnam(sys_get_temp_dir(), 'jpg_');
                $jpgTempFile .= '.jpg';
                $imagick->writeImage($jpgTempFile);
                $imagick->destroy();
                
                error_log("HEIC 轉換為 JPG 成功: $jpgTempFile");
                
                // 讀取 EXIF
                $exifData = exif_read_data($jpgTempFile, 'ANY_TAG', true);
                
                // 將轉換後的 JPG 上傳到 Azure Storage
                $jpgBlobUrl = uploadConvertedJpg($jpgTempFile, $fileName);
                
                // 清理臨時檔案
                unlink($tempFile);
                unlink($jpgTempFile);
                
                if ($exifData === false) {
                    error_log("無法讀取 EXIF 資料");
                    return [
                        'datetime' => date('Y-m-d H:i:s'),
                        'latitude' => null,
                        'longitude' => null,
                        'original_format' => 'HEIC',
                        'error' => '無法讀取 EXIF'
                    ];
                }
                
                // 解析 EXIF 資料
                $result = [
                    'datetime' => date('Y-m-d H:i:s'),
                    'latitude' => null,
                    'longitude' => null,
                    'original_format' => 'HEIC'
                ];
                
                // 日期時間
                if (isset($exifData['EXIF']['DateTimeOriginal'])) {
                    $result['datetime'] = str_replace(':', '-', $exifData['EXIF']['DateTimeOriginal']);
                    error_log("找到日期時間: " . $result['datetime']);
                }
                
                // GPS 座標
                if (isset($exifData['GPS']['GPSLatitude']) && isset($exifData['GPS']['GPSLongitude'])) {
                    error_log("找到 GPS 資料");
                    error_log("原始 GPS 資料 - 緯度: " . json_encode($exifData['GPS']['GPSLatitude']) . ", 緯度參考: " . ($exifData['GPS']['GPSLatitudeRef'] ?? 'N'));
                    error_log("原始 GPS 資料 - 經度: " . json_encode($exifData['GPS']['GPSLongitude']) . ", 經度參考: " . ($exifData['GPS']['GPSLongitudeRef'] ?? 'E'));
                    
                    // 轉換 GPS 座標
                    $latitude = convertGPSToDecimal($exifData['GPS']['GPSLatitude'], $exifData['GPS']['GPSLatitudeRef'] ?? 'N');
                    $longitude = convertGPSToDecimal($exifData['GPS']['GPSLongitude'], $exifData['GPS']['GPSLongitudeRef'] ?? 'E');
                    
                    error_log("轉換後 GPS 座標: 緯度=$latitude, 經度=$longitude");
                    
                    $result['latitude'] = $latitude;
                    $result['longitude'] = $longitude;
                }
                
                error_log("EXIF 處理完成: " . json_encode($result));
                
                // 如果有轉換後的 JPG URL，使用它來顯示
                if ($jpgBlobUrl) {
                    $result['display_url'] = $jpgBlobUrl;
                    error_log("使用轉換後的 JPG URL 顯示: $jpgBlobUrl");
                } else {
                    error_log("JPG 上傳失敗，使用原始 HEIC URL");
                    $result['display_url'] = $blobUrl;
                }
                
                return $result;
                
            } catch (Exception $e) {
                error_log("HEIC 轉換失敗: " . $e->getMessage());
                if (file_exists($tempFile)) unlink($tempFile);
                return [
                    'datetime' => date('Y-m-d H:i:s'),
                    'latitude' => null,
                    'longitude' => null,
                    'original_format' => 'HEIC',
                    'error' => 'HEIC 轉換失敗: ' . $e->getMessage()
                ];
            }
            
        } else {
            // JPG 檔案處理
            error_log("處理 JPG 檔案: $fileName");
            
            if (!extension_loaded('exif')) {
                return [
                    'datetime' => date('Y-m-d H:i:s'),
                    'latitude' => null,
                    'longitude' => null,
                    'original_format' => 'JPEG',
                    'error' => 'EXIF 擴展未載入'
                ];
            }
            
            // 直接嘗試讀取 EXIF
            $exifData = exif_read_data($blobUrl, 'ANY_TAG', true);
            
            if ($exifData === false) {
                error_log("無法從 URL 讀取 EXIF");
                return [
                    'datetime' => date('Y-m-d H:i:s'),
                    'latitude' => null,
                    'longitude' => null,
                    'original_format' => 'JPEG',
                    'error' => '無法讀取 EXIF'
                ];
            }
            
            $result = [
                'datetime' => date('Y-m-d H:i:s'),
                'latitude' => null,
                'longitude' => null,
                'original_format' => 'JPEG'
            ];
            
            // 日期時間
            if (isset($exifData['EXIF']['DateTimeOriginal'])) {
                $result['datetime'] = str_replace(':', '-', $exifData['EXIF']['DateTimeOriginal']);
            }
            
            // GPS 座標
            if (isset($exifData['GPS']['GPSLatitude']) && isset($exifData['GPS']['GPSLongitude'])) {
                error_log("找到 GPS 資料");
                error_log("原始 GPS 資料 - 緯度: " . json_encode($exifData['GPS']['GPSLatitude']) . ", 緯度參考: " . ($exifData['GPS']['GPSLatitudeRef'] ?? 'N'));
                error_log("原始 GPS 資料 - 經度: " . json_encode($exifData['GPS']['GPSLongitude']) . ", 經度參考: " . ($exifData['GPS']['GPSLongitudeRef'] ?? 'E'));
                
                // 轉換 GPS 座標
                $latitude = convertGPSToDecimal($exifData['GPS']['GPSLatitude'], $exifData['GPS']['GPSLatitudeRef'] ?? 'N');
                $longitude = convertGPSToDecimal($exifData['GPS']['GPSLongitude'], $exifData['GPS']['GPSLongitudeRef'] ?? 'E');
                
                error_log("轉換後 GPS 座標: 緯度=$latitude, 經度=$longitude");
                
                $result['latitude'] = $latitude;
                $result['longitude'] = $longitude;
            }
            
            return $result;
        }
        
    } catch (Exception $e) {
        error_log("EXIF 處理錯誤: " . $e->getMessage());
        return [
            'datetime' => date('Y-m-d H:i:s'),
            'latitude' => null,
            'longitude' => null,
            'original_format' => strtoupper(pathinfo($fileName, PATHINFO_EXTENSION)) ?: 'UNKNOWN',
            'error' => $e->getMessage()
        ];
    }
}

// 主要處理邏輯
try {
    // 開始資料庫交易
    mysqli_begin_transaction($link);
    
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
    
    // 2. 處理每個檔案
    $uploadedFiles = [];
    
    for ($i = 0; $i < count($blobUrls); $i++) {
        $blobUrl = $blobUrls[$i];
        $fileName = $fileNames[$i] ?? 'unknown.jpg';
        
        error_log("開始處理檔案 $i: $fileName");
        
        // 提取 EXIF 資料（包含 HEIC 轉換和上傳）
        $exifData = processExifWithConversion($blobUrl, $fileName);
        $datetime = $exifData['datetime'] ?? date('Y-m-d H:i:s');
        $latitude = $exifData['latitude'] ?? null;
        $longitude = $exifData['longitude'] ?? null;
        
        error_log("EXIF 結果: " . json_encode($exifData));
        
        // 決定要儲存的 URL（優先使用轉換後的 JPG URL）
        $displayUrl = $exifData['display_url'] ?? $blobUrl;
        
        // 建立照片記錄
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
                    'blob_url' => $displayUrl,
                    'exif_data' => $exifData
                ];
                error_log("檔案 $fileName 儲存成功");
            } else {
                error_log("檔案 $fileName 儲存失敗");
            }
        }
    }
    
    // 3. 更新相簿封面
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
        'message' => '相簿建立成功（最終修復版本 - 使用 SharedKey 認證）',
        'data' => [
            'album_id' => $album_id,
            'album_name' => $albumName,
            'username' => $username,
            'uploaded_files' => $uploadedFiles,
            'total_files' => count($uploadedFiles)
        ]
    ]);
    
} catch (Exception $e) {
    // 回滾交易
    mysqli_rollback($link);
    error_log("資料庫錯誤: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => '伺服器錯誤: ' . $e->getMessage()
    ]);
} finally {
    if (isset($link) && $link instanceof mysqli) {
        require_once("DB_close.php");
    }
}
?>
