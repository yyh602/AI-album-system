<?php
session_start();
require_once("DB_open.php");
require_once("DB_helper.php");
header('Content-Type: application/json');

$username = $_SESSION['username'] ?? '';
$action = $_POST['action'] ?? '';
$diary_id = $_POST['diary_id'] ?? '';

if (!$username || !$action || !$diary_id) {
    echo json_encode(['status' => 'error', 'message' => '缺少必要參數']);
    exit;
}

if ($action === 'edit') {
    $content = $_POST['content'] ?? '';
    if (!$content) {
        echo json_encode(['status' => 'error', 'message' => '日誌內容不可為空']);
        exit;
    }
    
    if ($link instanceof PgSQLWrapper || $link instanceof PDO) {
        // 更新日誌內容
        $update_sql = "UPDATE travel_diary SET content = ? WHERE id = ? AND username = ?";
        $update_stmt = $link->prepare($update_sql);
        
        if ($update_stmt->execute([$content, $diary_id, $username])) {
            echo json_encode(['status' => 'success', 'message' => '日誌已更新']);
        } else {
            $error = $update_stmt->errorInfo();
            echo json_encode(['status' => 'error', 'message' => '更新失敗：' . ($error[2] ?? '未知錯誤')]);
        }
    } else {
        // 更新日誌內容
        $update_sql = "UPDATE travel_diary SET content = ? WHERE id = ? AND username = ?";
        $update_stmt = mysqli_prepare($link, $update_sql);
        mysqli_stmt_bind_param($update_stmt, "sis", $content, $diary_id, $username);
        
        if (mysqli_stmt_execute($update_stmt)) {
            echo json_encode(['status' => 'success', 'message' => '日誌已更新']);
        } else {
            echo json_encode(['status' => 'error', 'message' => '更新失敗：' . mysqli_stmt_error($update_stmt)]);
        }
        mysqli_stmt_close($update_stmt);
    }
    
} elseif ($action === 'delete') {
    if ($link instanceof PgSQLWrapper || $link instanceof PDO) {
        // 刪除日誌
        $delete_sql = "DELETE FROM travel_diary WHERE id = ? AND username = ?";
        $delete_stmt = $link->prepare($delete_sql);
        
        if ($delete_stmt->execute([$diary_id, $username])) {
            if ($delete_stmt->rowCount() > 0) {
                echo json_encode(['status' => 'success', 'message' => '日誌已刪除']);
            } else {
                echo json_encode(['status' => 'error', 'message' => '找不到要刪除的日誌']);
            }
        } else {
            $error = $delete_stmt->errorInfo();
            echo json_encode(['status' => 'error', 'message' => '刪除失敗：' . ($error[2] ?? '未知錯誤')]);
        }
    } else {
        // 刪除日誌
        $delete_sql = "DELETE FROM travel_diary WHERE id = ? AND username = ?";
        $delete_stmt = mysqli_prepare($link, $delete_sql);
        mysqli_stmt_bind_param($delete_stmt, "is", $diary_id, $username);
        
        if (mysqli_stmt_execute($delete_stmt)) {
            if (mysqli_stmt_affected_rows($delete_stmt) > 0) {
                echo json_encode(['status' => 'success', 'message' => '日誌已刪除']);
            } else {
                echo json_encode(['status' => 'error', 'message' => '找不到要刪除的日誌']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => '刪除失敗：' . mysqli_stmt_error($delete_stmt)]);
        }
        mysqli_stmt_close($delete_stmt);
    }
    
} else {
    echo json_encode(['status' => 'error', 'message' => '無效的操作']);
}

require_once("DB_close.php");
?>
