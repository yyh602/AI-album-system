<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION["username"])) {
    echo json_encode(['status' => 'error', 'message' => '未登入']);
    exit();
}

require_once("DB_open.php");

$username = $_SESSION["username"];
$user_id = null;

if ($link instanceof mysqli) {
    // 獲取使用者 ID
    $sql_user = "SELECT id FROM user WHERE username = ?";
    $stmt_user = mysqli_prepare($link, $sql_user);
    mysqli_stmt_bind_param($stmt_user, "s", $username);
    mysqli_stmt_execute($stmt_user);
    mysqli_stmt_bind_result($stmt_user, $fetched_user_id);
    if (mysqli_stmt_fetch($stmt_user)) {
        $user_id = $fetched_user_id;
    }
    mysqli_stmt_close($stmt_user);
}

if (!$user_id) {
    echo json_encode(['status' => 'error', 'message' => '找不到使用者']);
    require_once("DB_close.php");
    exit();
}

try {
    // 依時間分類 (YYYY-MM)
    if (isset($_GET['group_photos_by_month'])) {
        $sql = "SELECT DATE_FORMAT(datetime, '%Y/%m') as month, path, COUNT(*) as photo_count
                FROM photos
                WHERE user_id = ? AND album_id IS NULL
                GROUP BY month
                ORDER BY month DESC";
        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $photos_by_month = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $photos_by_month[] = $row;
        }

        mysqli_stmt_close($stmt);
        echo json_encode(['status' => 'success', 'photos_by_month' => $photos_by_month]);
    }
    // 獲取特定月份的所有照片
    else if (isset($_GET['month'])) {
        $month = $_GET['month'];
        $sql = "SELECT id, path, datetime
                FROM photos
                WHERE user_id = ? AND DATE_FORMAT(datetime, '%Y-%m') = ?
                ORDER BY datetime DESC";
        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, "is", $user_id, $month);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $photos = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $photos[] = $row;
        }

        mysqli_stmt_close($stmt);
        echo json_encode(['status' => 'success', 'photos' => $photos]);
    }
    // 獲取依地點分類的照片（每個地點一張封面）
    else if (isset($_GET['group_photos_by_location'])) {
        $sql = "SELECT location, path, COUNT(*) as count
                FROM photos
                WHERE user_id = ? AND location IS NOT NULL AND location != ''
                GROUP BY location
                ORDER BY count DESC";
        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $photos_by_location = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $photos_by_location[$row['location']] = ['path' => $row['path'], 'count' => $row['count']];
        }

        mysqli_stmt_close($stmt);
        echo json_encode(['status' => 'success', 'photos_by_location' => $photos_by_location]);
    }
    // 獲取特定地點的所有照片
    else if (isset($_GET['location'])) {
        $location = urldecode($_GET['location']);
        $sql = "SELECT id, path, datetime
                FROM photos
                WHERE user_id = ? AND location = ?
                ORDER BY datetime DESC";
        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, "is", $user_id, $location);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $photos = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $photos[] = $row;
        }

        mysqli_stmt_close($stmt);
        echo json_encode(['status' => 'success', 'photos' => $photos]);
    }
    // 獲取所有相簿
    else if (isset($_GET['all_albums'])) {
        $sql = "SELECT id, name, cover_photo FROM albums WHERE user_id = ? ORDER BY created_at DESC";
        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $albums = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $albums[] = $row;
        }

        mysqli_stmt_close($stmt);
        echo json_encode(['status' => 'success', 'albums' => $albums]);
    }
    else {
        echo json_encode(['status' => 'error', 'message' => '無效的請求']);
    }

} catch (Exception $e) {
    error_log("資料庫操作失敗: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => '伺服器錯誤']);
} finally {
    require_once("DB_close.php");
}
?>