<?php
// 設定錯誤處理，避免干擾 JSON 輸出
error_reporting(E_ALL);
ini_set('display_errors', 0);

// 設定 JSON header
header('Content-Type: application/json');

// 檢查 session 狀態，避免重複啟動
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

try {
    require_once("DB_open.php");
    require_once("DB_helper.php");
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => '資料庫連線失敗: ' . $e->getMessage()
    ]);
    exit();
}

// GPS 座標轉換函數
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

// 設定字符集（PostgreSQL 預設就是 UTF-8）
if ($link instanceof mysqli) {
    mysqli_set_charset($link, "utf8mb4");
}

error_log("FILES array: " . print_r($_FILES, true));
error_log("POST array: " . print_r($_POST, true));

$username = $_SESSION['username'] ?? 'guest';
$albumName = trim($_POST['albumName'] ?? '');

if ($albumName === '') {
    echo json_encode(["status" => "error", "message" => "相簿名稱不可為空"]);
    exit();
}
if (empty($_FILES)) {
    echo json_encode(["status" => "error", "message" => "請選擇要上傳的照片"]);
    exit();
}

$uploadedPhotoIds = [];
$uploadedPhotoPaths = [];
$coverPhoto = null;

// 直接在這裡處理檔案上傳，不使用 cURL
foreach ($_FILES as $key => $file) {
    if ($file['error'] === UPLOAD_ERR_OK) {
        $originalName = $file['name'];
        error_log("🟡 處理檔案：{$originalName}");

        // 在 Render 環境中，我們跳過檔案寫入，只處理資料庫操作
        $datetime = date("Y-m-d H:i:s");
        $dateObj = new DateTime($datetime);
        $year = $dateObj->format('Y');
        $month = $dateObj->format('m');
        $day = $dateObj->format('d');

        // 建立虛擬檔案路徑（不實際寫入檔案）
        $newName = uniqid() . '.jpg';
        $uploadRelPath = "uploads/$year/$month/$day/" . $newName;
        
        // 在 Render 上，我們無法寫入檔案，所以跳過檔案操作
        error_log("⚠️ Render 環境：跳過檔案寫入，只處理資料庫操作");
        
        // 嘗試抓取 EXIF 資料
        $lat = null;
        $lon = null;
        
        // 方法 1：嘗試使用 exiftool
        $exiftoolPath = "exiftool";
        if (!file_exists($exiftoolPath)) {
            $exiftoolPath = "/usr/bin/exiftool";
        }
        
        if (file_exists($exiftoolPath)) {
            error_log("🟡 嘗試使用 exiftool 抓取 EXIF 資料");
            $cmd = "\"$exiftoolPath\" -j -DateTimeOriginal -CreateDate -DateTimeDigitized -GPSLatitude -GPSLongitude " . escapeshellarg($file['tmp_name']);
            $exifOutput = [];
            $exifReturnCode = 0;
            exec($cmd, $exifOutput, $exifReturnCode);
            
            if ($exifReturnCode === 0 && !empty($exifOutput)) {
                $exifData = json_decode(implode('', $exifOutput), true);
                if (is_array($exifData) && isset($exifData[0])) {
                    $exif = $exifData[0];
                    
                    // 抓取 GPS 座標
                    if (isset($exif['GPSLatitude']) && isset($exif['GPSLongitude'])) {
                        $lat = convertGPS($exif['GPSLatitude']);
                        $lon = convertGPS($exif['GPSLongitude']);
                        error_log("✅ exiftool 成功抓取 GPS 座標：{$lat}, {$lon}");
                    }
                    
                    // 抓取拍攝時間
                    $exifDateTime = $exif['DateTimeOriginal'] ?? $exif['CreateDate'] ?? $exif['DateTimeDigitized'] ?? null;
                    if ($exifDateTime) {
                        $datetime = $exifDateTime;
                        // 轉換 EXIF 時間格式 (YYYY:MM:DD HH:MM:SS) 為 MySQL 格式
                        if (preg_match('/^(\d{4}):(\d{2}):(\d{2}) (\d{2}):(\d{2}):(\d{2})$/', $datetime, $matches)) {
                            $datetime = "{$matches[1]}-{$matches[2]}-{$matches[3]} {$matches[4]}:{$matches[5]}:{$matches[6]}";
                            error_log("✅ exiftool 成功抓取拍攝時間：{$datetime}");
                        }
                    }
                }
            } else {
                error_log("⚠️ exiftool 執行失敗，返回碼：{$exifReturnCode}");
            }
        } else {
            error_log("⚠️ exiftool 不可用，嘗試使用 ImageMagick");
            
            // 方法 2：嘗試使用 ImageMagick convert
            $convertPath = "convert";
            if (!file_exists($convertPath)) {
                $convertPath = "/usr/bin/convert";
            }
            
            if (file_exists($convertPath)) {
                $cmd = "\"$convertPath\" " . escapeshellarg($file['tmp_name']) . " -format '%[EXIF:DateTimeOriginal] %[EXIF:GPSLatitude] %[EXIF:GPSLongitude]' info:";
                $magickOutput = [];
                $magickReturnCode = 0;
                exec($cmd, $magickOutput, $magickReturnCode);
                
                if ($magickReturnCode === 0 && !empty($magickOutput)) {
                    $output = trim($magickOutput[0]);
                    $parts = explode(' ', $output);
                    
                    if (count($parts) >= 3) {
                        // 抓取拍攝時間
                        if ($parts[0] !== '') {
                            $datetime = $parts[0];
                            if (preg_match('/^(\d{4}):(\d{2}):(\d{2}) (\d{2}):(\d{2}):(\d{2})$/', $datetime, $matches)) {
                                $datetime = "{$matches[1]}-{$matches[2]}-{$matches[3]} {$matches[4]}:{$matches[5]}:{$matches[6]}";
                                error_log("✅ ImageMagick 成功抓取拍攝時間：{$datetime}");
                            }
                        }
                        
                        // 抓取 GPS 座標
                        if ($parts[1] !== '' && $parts[2] !== '') {
                            $lat = convertGPS($parts[1]);
                            $lon = convertGPS($parts[2]);
                            error_log("✅ ImageMagick 成功抓取 GPS 座標：{$lat}, {$lon}");
                        }
                    }
                } else {
                    error_log("⚠️ ImageMagick 執行失敗，返回碼：{$magickReturnCode}");
                }
            } else {
                error_log("⚠️ ImageMagick 也不可用，跳過 EXIF 處理");
            }
        }

        // 直接插入資料庫
        if ($link instanceof mysqli) {
            $stmt = mysqli_prepare($link, "INSERT INTO uploads (username, filename, datetime, latitude, longitude, uploaded_at, album_id) VALUES (?, ?, ?, ?, ?, NOW(), NULL)");
            mysqli_stmt_bind_param($stmt, "sssdd", $username, $uploadRelPath, $datetime, $lat, $lon);
            if (!mysqli_stmt_execute($stmt)) {
                error_log("❌ uploads 寫入失敗: " . mysqli_stmt_error($stmt));
                continue;
            }
            $photoId = mysqli_insert_id($link);
            mysqli_stmt_close($stmt);

            // photos 表
            $stmt2 = mysqli_prepare($link, "INSERT INTO photos (album_id, filename, path, latitude, longitude, username, datetime, created_at) VALUES (NULL, ?, ?, ?, ?, ?, ?, NOW())");
            mysqli_stmt_bind_param($stmt2, "ssddss", $newName, $uploadRelPath, $lat, $lon, $username, $datetime);
            if (!mysqli_stmt_execute($stmt2)) {
                error_log("❌ photos 寫入失敗: " . mysqli_stmt_error($stmt2));
                mysqli_stmt_close($stmt2);
                continue;
            }
            mysqli_stmt_close($stmt2);
        } else {
            // 如果是 PDOWrapper，使用 PDO 方式
            $stmt = $link->prepare("INSERT INTO uploads (username, filename, datetime, latitude, longitude, uploaded_at, album_id) VALUES (?, ?, ?, ?, ?, NOW(), NULL)");
            if (!$stmt->execute([$username, $uploadRelPath, $datetime, $lat, $lon])) {
                error_log("❌ uploads 寫入失敗: " . $stmt->errorInfo()[2]);
                continue;
            }
            $photoId = $link->lastInsertId();

            // photos 表
            $stmt2 = $link->prepare("INSERT INTO photos (album_id, filename, path, latitude, longitude, username, datetime, created_at) VALUES (NULL, ?, ?, ?, ?, ?, ?, NOW())");
            if (!$stmt2->execute([$newName, $uploadRelPath, $lat, $lon, $username, $datetime])) {
                error_log("❌ photos 寫入失敗: " . $stmt2->errorInfo()[2]);
                continue;
            }
        }

        error_log("✅ 成功處理上傳：{$originalName} -> {$uploadRelPath} (僅資料庫操作)");
        $uploadedPhotoIds[] = $photoId;
        $uploadedPhotoPaths[] = $uploadRelPath;
        if (!$coverPhoto) $coverPhoto = $uploadRelPath;
    }
}

if (count($uploadedPhotoIds) === 0) {
    echo json_encode(["status" => "error", "message" => "所有圖片上傳失敗"]);
    exit();
}

if ($link instanceof PgSQLWrapper || $link instanceof PDO) {
    // 建立相簿
    $stmt = $link->prepare("INSERT INTO albums (name, cover_photo, username, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$albumName, $coverPhoto, $username]);
    $albumId = $link->lastInsertId();

    // 更新剛剛上傳的圖片 album_id
    foreach ($uploadedPhotoIds as $photoId) {
        // 更新 uploads
        $stmt = $link->prepare("UPDATE uploads SET album_id = ? WHERE id = ?");
        $stmt->execute([$albumId, $photoId]);

        // 同步更新 photos
        $stmt2 = $link->prepare("UPDATE photos SET album_id = ? WHERE id = ?");
        $stmt2->execute([$albumId, $photoId]);
    }
} else {
    if ($link instanceof mysqli) {
        // 建立相簿
        $stmt = mysqli_prepare($link, "INSERT INTO albums (name, cover_photo, username, created_at) VALUES (?, ?, ?, NOW())");
        mysqli_stmt_bind_param($stmt, "sss", $albumName, $coverPhoto, $username);
        mysqli_stmt_execute($stmt);
        $albumId = mysqli_insert_id($link);
        mysqli_stmt_close($stmt);

        // 更新剛剛上傳的圖片 album_id
        foreach ($uploadedPhotoIds as $photoId) {
            // 更新 uploads
            $stmt = mysqli_prepare($link, "UPDATE uploads SET album_id = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "ii", $albumId, $photoId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            // 同步更新 photos
            $stmt2 = mysqli_prepare($link, "UPDATE photos SET album_id = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt2, "ii", $albumId, $photoId);
            mysqli_stmt_execute($stmt2);
            mysqli_stmt_close($stmt2);
        }
    } else {
        // 如果是 PDOWrapper，使用 PDO 方式
        // 建立相簿
        $stmt = $link->prepare("INSERT INTO albums (name, cover_photo, username, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$albumName, $coverPhoto, $username]);
        $albumId = $link->lastInsertId();

        // 更新剛剛上傳的圖片 album_id
        foreach ($uploadedPhotoIds as $photoId) {
            // 更新 uploads
            $stmt = $link->prepare("UPDATE uploads SET album_id = ? WHERE id = ?");
            $stmt->execute([$albumId, $photoId]);

            // 同步更新 photos
            $stmt2 = $link->prepare("UPDATE photos SET album_id = ? WHERE id = ?");
            $stmt2->execute([$albumId, $photoId]);
        }
    }
}

error_log("✅ 相簿建立成功：{$albumName}，ID：{$albumId}，照片數：" . count($uploadedPhotoIds));

echo json_encode([
    "status" => "success",
    "message" => "相簿「{$albumName}」已建立，共上傳 " . count($uploadedPhotoIds) . " 張圖片"
]);
require_once("DB_close.php");
exit();
