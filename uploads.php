<?php
session_start();
require_once("DB_open.php");
if ($link instanceof mysqli) {
    mysqli_set_charset($link, 'utf8mb4');
}

// 修正 1：username 取得方式
$username = $_POST['username'] ?? ($_SESSION['username'] ?? 'guest');
// 在 Render 上使用系統的 exiftool，如果沒有就跳過 EXIF 處理
$exiftoolPath = "exiftool";
if (!file_exists($exiftoolPath)) {
    $exiftoolPath = "/usr/bin/exiftool";
}
if (!file_exists($exiftoolPath)) {
    $exiftoolPath = null; // 如果找不到 exiftool，就設為 null
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

foreach ($_FILES as $file) {
    $originalName = $file['name'];
    error_log("🟡 處理檔案：{$originalName}");

    if ($file['error'] !== UPLOAD_ERR_OK) {
        error_log("❌ 上傳錯誤: {$file['error']} ({$originalName})");
        echo json_encode(["status" => "error", "message" => "上傳錯誤: {$file['error']} ({$originalName})"]);
        require_once("DB_close.php");
        exit();
    }

    $tmpPath = $file['tmp_name'];
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    // EXIF 擷取（先取得時間資訊）
    $meta = [];
    if ($exiftoolPath) {
        // 修正 2：exiftool 指令同時抓多個欄位
        $cmdExif = "\"$exiftoolPath\" -j -DateTimeOriginal -CreateDate -DateTimeDigitized -GPSLatitude -GPSLongitude " . escapeshellarg($tmpPath);
        $exifOutput = [];
        $exifReturnCode = 0;
        exec($cmdExif, $exifOutput, $exifReturnCode);
        error_log("ExifTool Command: " . $cmdExif);
        error_log("ExifTool Return Code: " . $exifReturnCode);
        error_log("ExifTool Raw Output: " . implode('', $exifOutput));
        $metaArray = json_decode(implode('', $exifOutput), true);
        error_log("ExifData after json_decode: " . print_r($metaArray, true));
        $meta = is_array($metaArray) && isset($metaArray[0]) ? $metaArray[0] : [];
    } else {
        error_log("⚠️ exiftool 不可用，跳過 EXIF 處理");
    }

    // fallback 順序
    $datetime = $meta['DateTimeOriginal'] ?? $meta['CreateDate'] ?? $meta['DateTimeDigitized'] ?? date("Y-m-d H:i:s");
    if (preg_match('/^\d{4}:\d{2}:\d{2}/', $datetime)) {
        $datetime = preg_replace('/^(\d{4}):(\d{2}):(\d{2})/', '$1-$2-$3', $datetime);
    }
    $dateObj = new DateTime($datetime);
    $year = $dateObj->format('Y');
    $month = $dateObj->format('m');
    $day = $dateObj->format('d');

    // 建立目錄
    $uploadDir = __DIR__ . "/uploads/$year/$month/$day/";
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $newName = uniqid() . '.jpg';
    $uploadRelPath = "uploads/$year/$month/$day/" . $newName;
    $uploadFullPath = $uploadDir . $newName;

    // HEIC 轉 JPG
    if ($ext === 'heic') {
        // 在 Render 上嘗試使用 ImageMagick
        $cmdConvert = "magick convert " . escapeshellarg($tmpPath) . " " . escapeshellarg($uploadFullPath);
        shell_exec($cmdConvert);
        if (!file_exists($uploadFullPath) || filesize($uploadFullPath) < 1000) {
            // 如果 ImageMagick 失敗，嘗試使用 convert 命令
            $cmdConvert = "convert " . escapeshellarg($tmpPath) . " " . escapeshellarg($uploadFullPath);
            shell_exec($cmdConvert);
            if (!file_exists($uploadFullPath) || filesize($uploadFullPath) < 1000) {
                error_log("❌ HEIC 轉檔失敗或檔案太小：$uploadFullPath");
                echo json_encode(["status" => "error", "message" => "HEIC 轉檔失敗或檔案太小"]);
                require_once("DB_close.php");
                exit();
            }
        }
    } else {
        if (!move_uploaded_file($tmpPath, $uploadFullPath)) {
            error_log("❌ JPG 移動失敗：$tmpPath -> $uploadFullPath");
            echo json_encode(["status" => "error", "message" => "JPG 移動失敗"]);
            require_once("DB_close.php");
            exit();
        }
    }

    // 重新擷取 EXIF（因為 HEIC 轉 JPG 後要抓新檔案）
    if ($exiftoolPath) {
        // 修正 3：exiftool 指令同時抓多個欄位
        $cmdExif = "\"$exiftoolPath\" -j -DateTimeOriginal -CreateDate -DateTimeDigitized -GPSLatitude -GPSLongitude " . escapeshellarg($uploadFullPath);
        $exifOutput = [];
        $exifReturnCode = 0;
        exec($cmdExif, $exifOutput, $exifReturnCode);
        error_log("ExifTool Command: " . $cmdExif);
        error_log("ExifTool Return Code: " . $exifReturnCode);
        error_log("ExifTool Raw Output: " . implode('', $exifOutput));
        $metaArray = json_decode(implode('', $exifOutput), true);
        error_log("ExifData after json_decode: " . print_r($metaArray, true));
        $meta = is_array($metaArray) && isset($metaArray[0]) ? $metaArray[0] : [];
    }

    if (!$meta) {
        error_log("❌ 無法擷取 EXIF，原圖名：$originalName");
    }

    // fallback 順序
    $datetime = $meta['DateTimeOriginal'] ?? $meta['CreateDate'] ?? $meta['DateTimeDigitized'] ?? $datetime;
    if (preg_match('/^\d{4}:\d{2}:\d{2}/', $datetime)) {
        $datetime = preg_replace('/^(\d{4}):(\d{2}):(\d{2})/', '$1-$2-$3', $datetime);
    }
    $lat = isset($meta['GPSLatitude']) ? convertGPS($meta['GPSLatitude']) : null;
    $lon = isset($meta['GPSLongitude']) ? convertGPS($meta['GPSLongitude']) : null;

    // uploads 表
    if ($link instanceof mysqli) {
        $stmt = mysqli_prepare($link, "INSERT INTO uploads (username, filename, datetime, latitude, longitude, uploaded_at, album_id) VALUES (?, ?, ?, ?, ?, NOW(), NULL)");
        mysqli_stmt_bind_param($stmt, "sssdd", $username, $uploadRelPath, $datetime, $lat, $lon);
        if (!mysqli_stmt_execute($stmt)) {
            error_log("❌ uploads 寫入失敗: " . mysqli_stmt_error($stmt));
            echo json_encode(["status" => "error", "message" => "uploads 寫入失敗"]);
            mysqli_stmt_close($stmt);
            require_once("DB_close.php");
            exit();
        }
        $photoId = mysqli_insert_id($link);
        mysqli_stmt_close($stmt);

        // photos 表
        $stmt2 = mysqli_prepare($link, "INSERT INTO photos (album_id, filename, path, latitude, longitude, username, datetime, created_at) VALUES (NULL, ?, ?, ?, ?, ?, ?, NOW())");
        mysqli_stmt_bind_param($stmt2, "ssddss", $newName, $uploadRelPath, $lat, $lon, $username, $datetime);
        if (!mysqli_stmt_execute($stmt2)) {
            error_log("❌ photos 寫入失敗: " . mysqli_stmt_error($stmt2));
            echo json_encode(["status" => "error", "message" => "photos 寫入失敗"]);
            mysqli_stmt_close($stmt2);
            require_once("DB_close.php");
            exit();
        }
        mysqli_stmt_close($stmt2);
    } else {
        // 如果是 PDOWrapper，使用 PDO 方式
        $stmt = $link->prepare("INSERT INTO uploads (username, filename, datetime, latitude, longitude, uploaded_at, album_id) VALUES (?, ?, ?, ?, ?, NOW(), NULL)");
        if (!$stmt->execute([$username, $uploadRelPath, $datetime, $lat, $lon])) {
            error_log("❌ uploads 寫入失敗: " . $stmt->errorInfo()[2]);
            echo json_encode(["status" => "error", "message" => "uploads 寫入失敗"]);
            require_once("DB_close.php");
            exit();
        }
        $photoId = $link->lastInsertId();

        // photos 表
        $stmt2 = $link->prepare("INSERT INTO photos (album_id, filename, path, latitude, longitude, username, datetime, created_at) VALUES (NULL, ?, ?, ?, ?, ?, ?, NOW())");
        if (!$stmt2->execute([$newName, $uploadRelPath, $lat, $lon, $username, $datetime])) {
            error_log("❌ photos 寫入失敗: " . $stmt2->errorInfo()[2]);
            echo json_encode(["status" => "error", "message" => "photos 寫入失敗"]);
            require_once("DB_close.php");
            exit();
        }
    }

    error_log("✅ 成功處理上傳：{$originalName} -> {$uploadRelPath}");
    echo json_encode([
        "status" => "success",
        "id" => $photoId,
        "filename" => $uploadRelPath
    ]);
    require_once("DB_close.php");
    exit();
}

echo "✅ 上傳成功";
require_once("DB_close.php");
