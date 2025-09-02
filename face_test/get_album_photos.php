<?php
// 設定 JSON 標頭
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 處理 OPTIONS 請求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    // 檢查參數
    if (!isset($_GET['album_id'])) {
        throw new Exception('缺少 album_id 參數');
    }
    
    $albumId = (int)$_GET['album_id'];
    
    // 連接資料庫
    require_once '../DB_open.php';
    
    // 獲取相簿資訊
    $sql = "SELECT * FROM albums WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $albumId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('相簿不存在');
    }
    
    $album = $result->fetch_assoc();
    
    // 獲取相簿中的照片
    $sql = "SELECT * FROM photos WHERE album_id = ? ORDER BY created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $albumId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $photos = [];
    while ($row = $result->fetch_assoc()) {
        // 構建完整的圖片 URL
        $photoUrl = '';
        if (!empty($row['azure_url'])) {
            $photoUrl = $row['azure_url'];
        } elseif (!empty($row['file_path'])) {
            // 如果是本地路徑，轉換為 URL
            $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
            $photoUrl = $baseUrl . dirname($_SERVER['REQUEST_URI']) . '/../' . $row['file_path'];
        }
        
        if ($photoUrl) {
            $photos[] = [
                'id' => $row['id'],
                'filename' => $row['filename'],
                'url' => $photoUrl,
                'azure_url' => $row['azure_url'],
                'file_path' => $row['file_path'],
                'created_at' => $row['created_at']
            ];
        }
    }
    
    echo json_encode([
        'status' => 'success',
        'message' => '獲取照片成功',
        'album' => $album,
        'photos' => $photos,
        'count' => count($photos)
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
