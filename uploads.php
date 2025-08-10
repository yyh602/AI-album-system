<?php
session_start();
require_once("DB_open.php");
if ($link instanceof mysqli) {
    mysqli_set_charset($link, 'utf8mb4');
}

// 修正 1：username 取得方式
$username = $_POST['username'] ?? ($_SESSION['username'] ?? 'guest');

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

    // 在 Render 環境中，我們跳過檔案寫入，只處理資料庫操作
    // 使用當前時間作為檔案時間
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
    
    // 設定預設的 GPS 座標（null）
    $lat = null;
    $lon = null;

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

    error_log("✅ 成功處理上傳：{$originalName} -> {$uploadRelPath} (僅資料庫操作)");
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
