<?php
session_start();
require_once("DB_open.php");
require_once("DB_helper.php");

// 檢查是否有登入
if (!isset($_SESSION["username"])) {
    die(json_encode(["status" => "error", "message" => "請先登入"]));
}

// 檢查是否有提供照片 ID
if (!isset($_POST["photo_id"])) {
    die(json_encode(["status" => "error", "message" => "未提供照片 ID"]));
}

$photo_id = $_POST["photo_id"];
$username = $_SESSION["username"];

if ($link instanceof PgSQLWrapper || $link instanceof PDO) {
    // 先取得檔案名稱
    $sql = "SELECT filename FROM uploads WHERE id = ? AND username = ?";
    $stmt = $link->prepare($sql);
    $stmt->execute([$photo_id, $username]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($row) {
        $filename = $row['filename'];
        
        // 刪除資料庫記錄
        $sql = "DELETE FROM uploads WHERE id = ? AND username = ?";
        $stmt = $link->prepare($sql);
        
        if ($stmt->execute([$photo_id, $username])) {
            // 刪除實體檔案
            $file_path = "uploads/" . $filename;
            if (file_exists($file_path)) {
                if (unlink($file_path)) {
                    echo json_encode(["status" => "success", "message" => "照片已成功刪除"]);
                } else {
                    echo json_encode(["status" => "warning", "message" => "資料庫記錄已刪除，但檔案刪除失敗"]);
                }
            } else {
                echo json_encode(["status" => "warning", "message" => "資料庫記錄已刪除，檔案不存在"]);
            }
        } else {
            echo json_encode(["status" => "error", "message" => "刪除失敗"]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "找不到照片或權限不足"]);
    }
} else {
    // 先取得檔案名稱
    $sql = "SELECT filename FROM uploads WHERE id = ? AND username = ?";
    $stmt = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($stmt, "is", $photo_id, $username);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $filename);

    if (mysqli_stmt_fetch($stmt)) {
        mysqli_stmt_close($stmt);
        
        // 刪除資料庫記錄
        $sql = "DELETE FROM uploads WHERE id = ? AND username = ?";
        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, "is", $photo_id, $username);
        
        if (mysqli_stmt_execute($stmt)) {
            // 刪除實體檔案
            $file_path = "uploads/" . $filename;
            if (file_exists($file_path)) {
                if (unlink($file_path)) {
                    echo json_encode(["status" => "success", "message" => "照片已成功刪除"]);
                } else {
                    echo json_encode(["status" => "warning", "message" => "資料庫記錄已刪除，但檔案刪除失敗"]);
                }
            } else {
                echo json_encode(["status" => "warning", "message" => "資料庫記錄已刪除，檔案不存在"]);
            }
        } else {
            echo json_encode(["status" => "error", "message" => "刪除失敗"]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "找不到照片或權限不足"]);
    }

    mysqli_stmt_close($stmt);
}

require_once("DB_close.php");
?> 