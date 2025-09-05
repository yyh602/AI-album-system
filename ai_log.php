<?php
session_start();
if (!isset($_SESSION["username"])) {
    header("Location: login.php");
    exit();
}
require_once("DB_open.php"); // 確保你的資料庫連接檔案存在且正確
require_once("DB_helper.php");

$username = $_SESSION["username"];
$name = $username;

// MySQL 查詢用戶名稱
if ($link instanceof mysqli && $link !== null) {
    $sql = "SELECT name FROM user WHERE username = ?";
    $stmt = mysqli_prepare($link, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $result_name);
        if (mysqli_stmt_fetch($stmt)) {
            $name = $result_name;
        }
        mysqli_stmt_close($stmt);
    }
}
// 不要在這裡關閉資料庫連接，因為後面的 AJAX 處理還需要用到

// ✅ 你的 Gemini API 金鑰
$api_key = 'AIzaSyBZZhisvYRS6RJe6v8kpKzLcNS8lbzjOlU'; 
// 修改模型名稱為 gemini-1.5-pro
$gemini_api_url = "https://generativelanguage.googleapis.com/v1/models/gemini-2.5-flash:generateContent?key=" . $api_key;

// 從 POST 取得使用者輸入
$user_input = $_POST['message'] ?? null;
$response_text = null;

if ($user_input) {
    error_log('AI_LOG 輸入: ' . $user_input); // 新增：記錄送出的 prompt
    // 準備請求的 body
    $post_data = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $user_input]
                ]
            ]
        ]
    ];
    $json_data = json_encode($post_data);

    // 設定 cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $gemini_api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);

    $response = curl_exec($ch);
    error_log('Gemini 回應: ' . $response); // 新增：記錄 Gemini API 的原始回應
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        $response_text = 'cURL 錯誤: ' . curl_error($ch);
    } else {
        $result = json_decode($response, true);
        if ($http_code === 200 && isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            $response_text = $result['candidates'][0]['content']['parts'][0]['text'];
        } else {
            $error_message = $result['error']['message'] ?? '未知 API 錯誤';
            
            // 針對特定錯誤提供友善提示
            if ($http_code === 503) {
                $response_text = "AI 服務暫時忙碌，請稍後再試。\n\n錯誤詳情：{$error_message}";
            } else if ($http_code === 429) {
                $response_text = "AI 服務請求過於頻繁，請稍後再試。\n\n錯誤詳情：{$error_message}";
            } else {
                $response_text = "API 錯誤 (HTTP {$http_code}): " . htmlspecialchars($error_message);
            }
        }
    }

    curl_close($ch);
}

$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// 處理儲存日誌請求
if ($is_ajax && isset($_POST['is_save_diary'])) {
    header('Content-Type: application/json');
    
    $album_id = $_POST['album_id'] ?? '';
    $album_name = $_POST['album_name'] ?? '';
    $content = $_POST['content'] ?? '';
    $cover_photo = $_POST['cover_photo'] ?? '';
    $cover_photo_album = $_POST['cover_photo_album'] ?? '';
    $cover_photo_path = $_POST['cover_photo_path'] ?? '';
    
    if (!$username || $album_id === '' || !$content) {
        echo json_encode(['status' => 'error', 'message' => '缺少必要欄位']);
        exit;
    }
    
    require_once("DB_open.php");
    require_once("DB_helper.php");
    
    if ($link instanceof mysqli) {
        // 添加調試資訊
        error_log("開始處理儲存日誌請求 - 用戶: $username, 相簿ID: $album_id, 相簿名: $album_name, 內容長度: " . strlen($content));
        
        // 檢查 travel_diary 表是否有 cover_photo 和 cover_photo_path 欄位
        $result = mysqli_query($link, "SHOW COLUMNS FROM travel_diary LIKE 'cover_photo'");
        if (!$result) {
            error_log("檢查 cover_photo 欄位失敗: " . mysqli_error($link));
            echo json_encode(['status' => 'error', 'message' => '資料庫欄位檢查失敗']);
            exit;
        }
        $has_cover_fields = mysqli_num_rows($result) > 0;
        
        // 檢查是否有 cover_photo_path 欄位，如果沒有則添加
        $result_path = mysqli_query($link, "SHOW COLUMNS FROM travel_diary LIKE 'cover_photo_path'");
        if (!$result_path) {
            error_log("檢查 cover_photo_path 欄位失敗: " . mysqli_error($link));
            echo json_encode(['status' => 'error', 'message' => '資料庫欄位檢查失敗']);
            exit;
        }
        if (mysqli_num_rows($result_path) == 0) {
            $alter_result = mysqli_query($link, "ALTER TABLE travel_diary ADD COLUMN cover_photo_path VARCHAR(500) NULL COMMENT '封面照片路徑' AFTER content");
            if (!$alter_result) {
                error_log("添加 cover_photo_path 欄位失敗: " . mysqli_error($link));
            }
        }
        
        // 檢查是否有 cover_photo 欄位，如果沒有則添加
        if (!$has_cover_fields) {
            $alter_result1 = mysqli_query($link, "ALTER TABLE travel_diary ADD COLUMN cover_photo VARCHAR(500) NULL COMMENT '封面照片' AFTER content");
            if (!$alter_result1) {
                error_log("添加 cover_photo 欄位失敗: " . mysqli_error($link));
            }
            $alter_result2 = mysqli_query($link, "ALTER TABLE travel_diary ADD COLUMN cover_photo_album VARCHAR(500) NULL COMMENT '封面照片相簿' AFTER cover_photo");
            if (!$alter_result2) {
                error_log("添加 cover_photo_album 欄位失敗: " . mysqli_error($link));
            }
        }
        
        // 使用完整插入，包含所有欄位
        $stmt = mysqli_prepare($link, "INSERT INTO travel_diary (username, album_id, album_name, content, cover_photo, cover_photo_album, cover_photo_path, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        if (!$stmt) {
            error_log("準備 SQL 語句失敗: " . mysqli_error($link));
            echo json_encode(['status' => 'error', 'message' => 'SQL 準備失敗']);
            exit;
        }
        mysqli_stmt_bind_param($stmt, "sisssss", $username, $album_id, $album_name, $content, $cover_photo, $cover_photo_album, $cover_photo_path);
        
        if (mysqli_stmt_execute($stmt)) {
            $new_id = mysqli_insert_id($link);
            echo json_encode(['status' => 'success', 'id' => $new_id]);
        } else {
            $error = mysqli_stmt_error($stmt);
            echo json_encode(['status' => 'error', 'message' => $error]);
        }
        mysqli_stmt_close($stmt);
    } else {
        echo json_encode(['status' => 'error', 'message' => '資料庫連接類型不支援']);
    }
    
    // 不要在這裡關閉資料庫連接，因為後面的歷史日誌讀取還需要用到
    exit;
}

if ($is_ajax && $response_text) {
    // 只回傳 response-box 給 AJAX
    echo '<div class="response-box">' . nl2br(htmlspecialchars($response_text)) . '</div>';
    exit;
}

// 歷史日誌讀取
require("DB_open.php");
require_once("DB_helper.php");

$diaries = [];
if ($link instanceof PgSQLWrapper || $link instanceof PDO) {
    $diary_sql = "SELECT d.*, a.cover_photo, a.name as album_name FROM travel_diary d LEFT JOIN albums a ON d.album_id = a.id WHERE d.username = ? ORDER BY d.created_at DESC";
    $diary_stmt = $link->prepare($diary_sql);
    $diary_stmt->execute([$username]);
    while ($row = $diary_stmt->fetch('ASSOC')) {
        $diaries[] = $row;
    }
} else {
    if ($link instanceof mysqli) {
        $diary_sql = "SELECT d.*, a.cover_photo, a.name as album_name FROM travel_diary d LEFT JOIN albums a ON d.album_id = a.id WHERE d.username = ? ORDER BY d.created_at DESC";
        $diary_stmt = mysqli_prepare($link, $diary_sql);
        mysqli_stmt_bind_param($diary_stmt, "s", $username);
        mysqli_stmt_execute($diary_stmt);
        $diary_result = mysqli_stmt_get_result($diary_stmt);
        while ($row = mysqli_fetch_assoc($diary_result)) {
            $diaries[] = $row;
        }
        mysqli_stmt_close($diary_stmt);
    } else {
        // 如果是 PDOWrapper，使用 PDO 方式查詢
        $diary_sql = "SELECT d.*, a.cover_photo, a.name as album_name FROM travel_diary d LEFT JOIN albums a ON d.album_id = a.id WHERE d.username = ? ORDER BY d.created_at DESC";
        $diary_stmt = $link->prepare($diary_sql);
        $diary_stmt->execute([$username]);
        while ($row = $diary_stmt->fetch('ASSOC')) {
            $diaries[] = $row;
        }
    }
}
require_once("DB_close.php");
?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="UTF-8">
  <title>AI 智慧相簿管理系統</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <style>
    body {
        background: #f6f8fa;
        font-family: Arial, sans-serif;
        margin: 0;
        padding: 0;
        text-align: center;
    }
    h2, .navbar-title {
        font-weight: bold;
    }
    .custom-navbar, .navbar {
        background-color: #e9d0c3 !important;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .navbar-brand, .nav-link, .navbar-username {
        color: #333 !important;
    }
    .nav-link:hover {
        color: #3498db !important;
    }
    .nav-link.active {
        color: #3498db !important;
        font-weight: bold;
    }
    .navbar-avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        object-fit: cover;
        border: none;
        box-shadow: none;
    }
    .navbar-username {
        color: #fff;
        font-size: 1.1rem;
        font-weight: 500;
        letter-spacing: 1px;
        margin-left: 8px;
    }
    .container {
        max-width: 800px;
        margin: 30px auto;
        padding: 20px;
        font-family: Arial, sans-serif;
    }
    textarea {
        width: 100%;
        height: 100px;
        font-size: 1rem;
        padding: 10px;
        border: 1px solid #ced4da;
        border-radius: 4px;
        box-shadow: inset 0 1px 2px rgba(0,0,0,.075);
    }
    button {
        margin-top: 10px;
        padding: 10px 20px;
        font-size: 1rem;
        background-color: #1976d2;
        color: #fff;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        transition: background-color 0.3s ease;
    }
    button:hover {
        background-color: #155bb5;
    }
    .response-box {
        margin-top: 20px;
        padding: 15px;
        background: #f5f8fc;
        border-radius: 6px;
        white-space: pre-wrap; /* 保留換行和空格 */
        text-align: left; /* 回應文字靠左對齊 */
        line-height: 1.6;
        border: 1px solid #e0e6ea;
    }
    .response-box {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 8px;
        margin: 20px 0;
        border: 1px solid #e0e6ea;
    }
    
    /* 新增：歷史日誌網格樣式 */
    .history-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }
    
    .history-item {
        position: relative;
        cursor: pointer;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        transition: transform 0.2s, box-shadow 0.2s;
        background: white;
    }
    
    .history-item:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    }
    
    .history-item-image {
        width: 100%;
        height: 150px;
        object-fit: cover;
        display: block;
    }
    
    .history-item-overlay {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        background: linear-gradient(transparent, rgba(0,0,0,0.7));
        color: white;
        padding: 15px 10px 10px;
        font-size: 14px;
    }
    
    .history-item-title {
        font-weight: bold;
        margin-bottom: 4px;
        font-size: 16px;
    }
    
    .history-item-date {
        font-size: 12px;
        opacity: 0.9;
    }
    
    /* 詳情模態框樣式 */
    .diary-detail-modal .modal-dialog {
        max-width: 900px;
    }
    
    .diary-photos-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
        gap: 10px;
        margin-top: 15px;
    }
    
    .diary-photo-thumb {
        width: 100%;
        height: 100px;
        object-fit: cover;
        border-radius: 8px;
        cursor: pointer;
        transition: transform 0.2s;
    }
    
    .diary-photo-thumb:hover {
        transform: scale(1.05);
    }
    
    @media (max-width: 576px) {
        .navbar { border-radius: 0; }
        .navbar-username { color: #333; font-size: 1.1rem; }
        .history-grid {
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
        }
    }
  </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark sticky-top">
  <div class="container-fluid px-3">
    <a class="navbar-brand d-flex align-items-center" href="#">
      <img src="img/logo.svg" width="32" height="32" class="me-2" alt="Logo">
      <span style="font-weight:bold;letter-spacing:1px;">AI智慧相簿管理系統</span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavDropdown" aria-controls="navbarNavDropdown" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNavDropdown">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item">
          <a class="nav-link" href="welcome.php">首頁</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="album.php">相簿</a>
        </li>
        <li class="nav-item">
          <a class="nav-link active" href="ai_log.php">AI生成日誌</a>
        </li>
      </ul>
      <div class="d-flex align-items-center ms-auto">
                    <img src="img/avatar.svg" alt="avatar" class="navbar-avatar">
        <span class="navbar-username"><?php echo htmlspecialchars($name); ?></span>
      </div>
    </div>
  </div>
</nav>

<div class="container">
  <h2>AI 智慧日誌</h2>
  <button class="btn btn-primary mb-3" id="createLogBtn">創建日誌</button>

  <!-- Modal -->
  <div class="modal fade" id="createLogModal" tabindex="-1" aria-labelledby="createLogModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title w-100 text-center" id="createLogModalLabel">創建日誌</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="關閉"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-bold text-primary fs-5">選擇方式</label>
                         <div class="d-flex gap-2 mb-3 justify-content-center">
               <button type="button" class="btn btn-outline-primary" id="selectAlbumBtn">選擇相簿</button>
               <button type="button" class="btn btn-outline-success" id="selectPhotosBtn">選擇照片</button>
             </div>
            
            <div id="albumCardList" style="display:none;flex-direction:column;max-height:400px;overflow-y:auto;"></div>
            <div id="allPhotosList" style="display:none;flex-wrap:wrap;gap:12px;max-height:260px;overflow-y:auto;">
              <div class="w-100 mb-2">
                <button type="button" class="btn btn-sm btn-outline-primary me-2" id="selectAllPhotosBtn">全選</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="clearAllPhotosBtn">清除</button>
              </div>
            </div>
          </div>
          <div class="mb-3" id="photoPreviewWrap" style="display:none;">
            <label class="form-label fw-bold text-primary fs-5">選擇的照片預覽 (<span id="photoCount">0</span> 張)</label>
            <div id="photoPreview" style="display:flex;flex-wrap:wrap;gap:8px;max-height:200px;overflow-y:auto;border:1px solid #ddd;padding:10px;border-radius:8px;background:#f8f9fa;"></div>
                          <div class="mt-3" id="coverPhotoSection" style="display:none;">
                <label class="form-label fw-bold text-primary fs-5">選擇封面照片</label>
              <div class="d-flex align-items-center gap-3">
                <div id="coverPhotoPreview" style="width:70px;height:70px;border:2px solid #ddd;border-radius:8px;overflow:hidden;background:#f8f9fa;display:flex;align-items:center;justify-content:center;">
                  <span class="text-muted">未選擇</span>
                </div>
                <div class="flex-grow-1">
                  <button type="button" class="btn btn-outline-primary btn-sm" id="selectCoverBtn">選擇封面照片</button>
                  <button type="button" class="btn btn-outline-secondary btn-sm ms-2" id="clearCoverBtn" style="display:none;">清除封面</button>
                  <div class="form-text mt-1">選擇一張照片作為此日誌的封面圖片</div>
                </div>
              </div>
            </div>
          </div>
          <div class="mb-3">
            <label for="logLength" class="form-label fw-bold text-primary fs-5">日誌字數</label>
            <input type="number" class="form-control" id="logLength" min="50" max="2000" value="200">
          </div>
          <div class="mb-3">
            <label for="promptKeywords" class="form-label fw-bold text-primary fs-5">輸入提示詞</label>
            <input type="text" class="form-control" id="promptKeywords" placeholder="例如: 夏天、戶外教學、家族旅遊..." maxlength="100">
            <div class="form-text">輸入關鍵詞讓 AI 參考，幫助生成更符合您需求的日誌內容</div>
          </div>
          <div class="mb-3">
            <label for="styleSelect" class="form-label fw-bold text-primary fs-5">風格選擇</label>
            <select class="form-select" id="styleSelect">
              <option value="">請選擇風格（可選）</option>
              <option value="簡約">簡約 - 簡潔明瞭，重點突出</option>
              <option value="快樂">快樂 - 充滿活力，正面積極</option>
              <option value="放鬆">放鬆 - 輕鬆自在，舒適愜意</option>
              <option value="溫馨">溫馨 - 溫暖感人，情感豐富</option>
              <option value="文藝">文藝 - 優雅細膩，富有詩意</option>
              <option value="幽默">幽默 - 風趣詼諧，輕鬆有趣</option>
              <option value="懷舊">懷舊 - 回憶滿滿，情感深厚</option>
              <option value="冒險">冒險 - 刺激精彩，充滿挑戰</option>
              <option value="寧靜">寧靜 - 平靜祥和，內心沉澱</option>
              <option value="浪漫">浪漫 - 美好夢幻，情感細膩</option>
            </select>
            <div class="form-text">選擇日誌的寫作風格，讓 AI 生成更符合您期望的內容</div>
          </div>
          <div class="mb-3" id="aiLogEditWrap" style="display:none;">
            <label class="form-label fw-bold text-primary fs-5">AI 生成日誌（可修改）</label>
            <textarea class="form-control" id="aiLogEdit" rows="6"></textarea>
            <div class="mt-2" id="retrySection" style="display:none;">
              <button type="button" class="btn btn-warning btn-sm" id="retryBtn">重試生成</button>
              <small class="text-muted ms-2">如果遇到服務忙碌，請稍後重試</small>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
          <button type="button" class="btn btn-primary" id="submitLogBtn">送出</button>
          <button type="button" class="btn btn-success" id="saveDiaryBtn" style="display:none;">確定</button>
        </div>
      </div>
    </div>
  </div>

  <div id="aiLogResult" class="response-box" style="display:none;"></div>

  <h3 class="mt-5 mb-3">歷史日誌</h3>
  <div id="historyDiaryList" class="history-grid">
    <?php foreach ($diaries as $d): ?>
      <div class="history-item" onclick="showDiaryDetail(<?php echo $d['id']; ?>)">
        <img src="<?php echo !empty($d['cover_photo_path']) ? htmlspecialchars($d['cover_photo_path']) : 'img/default_album_cover.svg'; ?>" 
             class="history-item-image" 
             alt="<?php echo htmlspecialchars($d['album_name']); ?>"
             onerror="this.src='img/default_album_cover.svg'">
        <div class="history-item-overlay">
          <div class="history-item-title"><?php echo htmlspecialchars($d['album_name']); ?></div>
          <div class="history-item-date"><?php echo date('Y/m/d', strtotime($d['created_at'])); ?></div>
        </div>
      </div>
    <?php endforeach; ?>
      </div>

  <!-- 封面照片選擇模態框 -->
  <div class="modal fade" id="coverPhotoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">選擇封面照片</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">從已選擇的照片中挑選一張作為封面</label>
            <div id="coverPhotoGrid" class="d-flex flex-wrap gap-3" style="max-height:400px;overflow-y:auto;"></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
        </div>
      </div>
    </div>
  </div>

  <!-- 日誌詳情模態框 -->
  <div class="modal fade diary-detail-modal" id="diaryDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="diaryDetailTitle">日誌詳情</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-bold">相簿照片</label>
            <div id="diaryPhotos" class="diary-photos-grid"></div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">相簿名稱</label>
            <div id="diaryAlbumName" class="form-control-plaintext"></div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">日誌內容</label>
            <textarea id="diaryContent" class="form-control" rows="8" readonly></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">建立時間</label>
            <div id="diaryCreateTime" class="form-control-plaintext"></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">關閉</button>
          <button type="button" class="btn btn-primary" id="editDiaryBtn">編輯</button>
          <button type="button" class="btn btn-danger" id="deleteDiaryBtn">刪除</button>
          <button type="button" class="btn btn-success" id="saveDiaryEditBtn" style="display:none;">儲存</button>
          <button type="button" class="btn btn-secondary" id="cancelDiaryEditBtn" style="display:none;">取消</button>
        </div>
      </div>
    </div>
  </div>

  <style>
    .album-card-select {
      width: 100%;
      border: 2px solid #eee;
      border-radius: 10px;
      background: #fff;
      cursor: pointer;
      transition: border 0.2s, box-shadow 0.2s;
      margin-bottom: 15px;
      box-shadow: 0 1px 4px rgba(0,0,0,0.06);
    }
    .album-card-select.selected {
      border: 2px solid #1976d2;
      box-shadow: 0 0 0 2px rgba(25, 118, 210, 0.2);
    }
    .album-card-select.expanded {
      border: 2px solid #28a745;
      box-shadow: 0 0 0 2px rgba(40, 167, 69, 0.2);
    }
    .album-header {
      display: flex;
      align-items: center;
      padding: 12px;
      border-bottom: 1px solid #eee;
    }
    .album-header img {
      width: 60px;
      height: 60px;
      object-fit: cover;
      border-radius: 8px;
      margin-right: 12px;
      background: #f0f0f0;
    }
    .album-info {
      flex: 1;
    }
    .album-title {
      font-size: 1rem;
      color: #333;
      font-weight: 500;
      margin-bottom: 4px;
    }
    .album-photo-count {
      font-size: 0.85rem;
      color: #666;
    }
    .album-expand-icon {
      margin-left: auto;
      color: #666;
      transition: transform 0.2s;
    }
    .album-card-select.expanded .album-expand-icon {
      transform: rotate(90deg);
    }
    .album-photos-grid {
      display: none;
      grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
      gap: 8px;
      padding: 12px;
      border-top: 1px solid #f0f0f0;
    }
    .album-card-select.expanded .album-photos-grid {
      display: grid;
    }
    .album-controls {
      display: none;
      padding: 8px 12px;
      background: #f8f9fa;
      border-top: 1px solid #f0f0f0;
      justify-content: space-between;
      align-items: center;
    }
    .album-card-select.expanded .album-controls {
      display: flex;
    }
    
    .photo-card-select {
      width: 110px;
      border: 2px solid #eee;
      border-radius: 10px;
      background: #fff;
      cursor: pointer;
      transition: border 0.2s, box-shadow 0.2s;
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 8px 4px 10px 4px;
      box-shadow: 0 1px 4px rgba(0,0,0,0.06);
    }
    .photo-card-select.selected {
      border: 2px solid #28a745;
      box-shadow: 0 0 0 2px #28a745;
    }
    .photo-card-select img {
      width: 90px;
      height: 90px;
      object-fit: cover;
      border-radius: 8px;
      background: #f0f0f0;
    }
    .photo-card-select .photo-info {
      font-size: 0.75rem;
      color: #666;
      text-align: center;
      word-break: break-all;
      background: #f8f9fa;
      padding: 2px 4px;
      border-radius: 4px;
      margin-top: 2px;
    }
    
    /* 相簿內的照片卡片樣式 */
    .album-photo-card {
      position: relative;
      cursor: pointer;
      border-radius: 8px;
      overflow: hidden;
      border: 2px solid transparent;
      transition: border 0.2s, transform 0.1s;
    }
    .album-photo-card.selected {
      border: 2px solid #28a745;
      transform: scale(0.95);
    }
    .album-photo-card img {
      width: 100%;
      height: 80px;
      object-fit: cover;
      display: block;
    }
    .album-photo-card .photo-overlay {
      position: absolute;
      top: 0;
      right: 0;
      background: rgba(40, 167, 69, 0.9);
      color: white;
      padding: 2px 6px;
      font-size: 12px;
      border-radius: 0 0 0 8px;
      display: none;
    }
    .album-photo-card.selected .photo-overlay {
      display: block;
    }
    
    /* 封面照片選擇樣式 */
    .cover-photo-option {
      position: relative;
      cursor: pointer;
      border: 3px solid transparent;
      border-radius: 8px;
      overflow: hidden;
      transition: border-color 0.2s, transform 0.2s;
      width: 70px;
      height: 70px;
    }
    
    .cover-photo-option:hover {
      transform: scale(1.05);
      border-color: #007bff;
    }
    
    .cover-photo-option.selected {
      border-color: #28a745;
      transform: scale(1.05);
    }
    
    .cover-photo-option img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    
    .cover-photo-option .cover-overlay {
      position: absolute;
      top: 0;
      right: 0;
      background: rgba(40, 167, 69, 0.9);
      color: white;
      padding: 4px 8px;
      font-size: 12px;
      border-radius: 0 0 0 8px;
      display: none;
    }
    
    .cover-photo-option.selected .cover-overlay {
      display: block;
    }
    
    .cover-photo-preview img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
  </style>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
  let selectedAlbumId = null;
  let selectedAlbumName = '';
  let selectedPhotos = [];
  let currentSelectionMode = 'album'; // 'album' 或 'photos'
  let selectedCoverPhoto = null; // 新增：選中的封面照片
  
  const createLogBtn = document.getElementById('createLogBtn');
  const createLogModal = new bootstrap.Modal(document.getElementById('createLogModal'));
  const selectAlbumBtn = document.getElementById('selectAlbumBtn');
  const selectPhotosBtn = document.getElementById('selectPhotosBtn');
  const selectAllPhotosBtn = document.getElementById('selectAllPhotosBtn');
  const clearAllPhotosBtn = document.getElementById('clearAllPhotosBtn');
  const selectCoverBtn = document.getElementById('selectCoverBtn');
  const clearCoverBtn = document.getElementById('clearCoverBtn');
  
  createLogBtn.onclick = () => {
    resetModal();
    createLogModal.show();
  };
  
  // 全選照片按鈕
  selectAllPhotosBtn.onclick = () => {
    document.querySelectorAll('.photo-card-select').forEach(card => {
      card.classList.add('selected');
    });
    updateSelectedPhotos();
  };
  
  // 清除所有選擇按鈕
  clearAllPhotosBtn.onclick = () => {
    document.querySelectorAll('.photo-card-select').forEach(card => {
      card.classList.remove('selected');
    });
    updateSelectedPhotos();
  };
  
  // 選擇封面照片按鈕
  selectCoverBtn.onclick = () => {
    if (selectedPhotos.length === 0) {
      alert('請先選擇照片');
      return;
    }
    showCoverPhotoModal();
  };
  
  // 清除封面照片按鈕
  clearCoverBtn.onclick = () => {
    selectedCoverPhoto = null;
    updateCoverPhotoPreview();
  };
  
  // 選擇相簿按鈕
  selectAlbumBtn.onclick = () => {
    currentSelectionMode = 'album';
    document.getElementById('albumCardList').style.display = 'flex';
    document.getElementById('allPhotosList').style.display = 'none';
    // 清空之前的選擇和產出結果
    clearSelectionAndOutput();
    loadAlbums();
    updateButtonStyles();
  };
  
  // 選擇照片按鈕
  selectPhotosBtn.onclick = () => {
    currentSelectionMode = 'photos';
    document.getElementById('albumCardList').style.display = 'none';
    document.getElementById('allPhotosList').style.display = 'flex';
    // 清空之前的選擇和產出結果
    clearSelectionAndOutput();
    loadAllPhotos();
    updateButtonStyles();
  };
  
  function updateButtonStyles() {
    if (currentSelectionMode === 'album') {
      selectAlbumBtn.classList.remove('btn-outline-primary');
      selectAlbumBtn.classList.add('btn-primary');
      selectPhotosBtn.classList.remove('btn-success');
      selectPhotosBtn.classList.add('btn-outline-success');
    } else {
      selectPhotosBtn.classList.remove('btn-outline-success');
      selectPhotosBtn.classList.add('btn-success');
      selectAlbumBtn.classList.remove('btn-primary');
      selectAlbumBtn.classList.add('btn-outline-primary');
    }
  }
  
  // 顯示封面照片選擇模態框
  function showCoverPhotoModal() {
    const modal = new bootstrap.Modal(document.getElementById('coverPhotoModal'));
    const grid = document.getElementById('coverPhotoGrid');
    grid.innerHTML = '';
    
    selectedPhotos.forEach((photo, index) => {
      const option = document.createElement('div');
      option.className = 'cover-photo-option';
      option.dataset.photoIndex = index;
      
      option.innerHTML = `
        <img src="${photo.path}" alt="photo" onerror="this.src='img/default_album_cover.svg'">
        <div class="cover-overlay">✓</div>
      `;
      
      option.onclick = function() {
        // 移除其他選中的狀態
        document.querySelectorAll('.cover-photo-option').forEach(opt => {
          opt.classList.remove('selected');
        });
        // 選中當前選項
        option.classList.add('selected');
        // 設置選中的封面照片
        selectedCoverPhoto = photo;
        // 更新預覽
        updateCoverPhotoPreview();
        // 關閉模態框
        modal.hide();
      };
      
      grid.appendChild(option);
    });
    
    modal.show();
  }
  
  // 更新封面照片預覽
  function updateCoverPhotoPreview() {
    const preview = document.getElementById('coverPhotoPreview');
    const clearBtn = document.getElementById('clearCoverBtn');
    
    if (selectedCoverPhoto) {
      preview.innerHTML = `<img src="${selectedCoverPhoto.path}" alt="cover" onerror="this.src='img/default_album_cover.svg'" style="width:100%;height:100%;object-fit:cover;">`;
      clearBtn.style.display = 'inline-block';
    } else {
      preview.innerHTML = '<span class="text-muted">未選擇</span>';
      clearBtn.style.display = 'none';
    }
  }
  
  // 清空選擇和產出結果
  function clearSelectionAndOutput() {
    // 清空選擇的相簿和照片
    selectedAlbumId = null;
    selectedAlbumName = '';
    selectedPhotos = [];
    selectedCoverPhoto = null; // 清空封面照片選擇
    
    // 隱藏照片預覽
    document.getElementById('photoPreviewWrap').style.display = 'none';
    document.getElementById('photoPreview').innerHTML = '';
    document.getElementById('photoCount').textContent = '0';
    
    // 隱藏封面照片選擇區域
    document.getElementById('coverPhotoSection').style.display = 'none';
    updateCoverPhotoPreview();
    
    // 隱藏 AI 生成結果
    document.getElementById('aiLogEditWrap').style.display = 'none';
    document.getElementById('aiLogEdit').value = '';
    document.getElementById('saveDiaryBtn').style.display = 'none';
    
    // 重置按鈕文字
    document.getElementById('submitLogBtn').textContent = '送出';
    
    // 清空所有選擇狀態
    document.querySelectorAll('.album-card-select.selected').forEach(c => c.classList.remove('selected'));
    document.querySelectorAll('.album-card-select.expanded').forEach(c => c.classList.remove('expanded'));
    document.querySelectorAll('.photo-card-select.selected').forEach(c => c.classList.remove('selected'));
    document.querySelectorAll('.album-photo-card.selected').forEach(c => c.classList.remove('selected'));
  }
  
  function resetModal() {
    selectedAlbumId = null;
    selectedAlbumName = '';
    selectedPhotos = [];
    selectedCoverPhoto = null; // 清空封面照片選擇
    currentSelectionMode = 'album';
    document.getElementById('albumCardList').style.display = 'none';
    document.getElementById('allPhotosList').style.display = 'none';
    document.getElementById('photoPreviewWrap').style.display = 'none';
    document.getElementById('coverPhotoSection').style.display = 'none';
    document.getElementById('aiLogEditWrap').style.display = 'none';
    document.getElementById('saveDiaryBtn').style.display = 'none';
    document.getElementById('aiLogEdit').value = '';
    document.getElementById('promptKeywords').value = '';
    document.getElementById('styleSelect').value = '';
    // 重置按鈕文字為「送出」
    document.getElementById('submitLogBtn').textContent = '送出';
    // 清空所有選擇狀態
    document.querySelectorAll('.album-card-select.selected').forEach(c => c.classList.remove('selected'));
    document.querySelectorAll('.album-card-select.expanded').forEach(c => c.classList.remove('expanded'));
    document.querySelectorAll('.photo-card-select.selected').forEach(c => c.classList.remove('selected'));
    document.querySelectorAll('.album-photo-card.selected').forEach(c => c.classList.remove('selected'));
    updateCoverPhotoPreview();
    updateButtonStyles();
  }

  // 載入所有相簿並顯示卡片
  function loadAlbums() {
    fetch('get_album_photos.php?all_albums=1')
      .then(res => res.json())
      .then(data => {
        const list = document.getElementById('albumCardList');
        list.innerHTML = '';
        // 不清空 selectedPhotos，保持之前選擇的照片
        if (data.status === 'success' && data.albums) {
          data.albums.forEach(album => {
            const card = document.createElement('div');
            card.className = 'album-card-select';
            card.dataset.albumId = album.id;
            card.innerHTML = `
              <div class="album-header" onclick="toggleAlbum(${album.id})">
                <img src="img/default_album_cover.svg" alt="cover">
                <div class="album-info">
                  <div class="album-title">${album.name}</div>
                  <div class="album-photo-count">載入中...</div>
                </div>
                <i class="fas fa-chevron-right album-expand-icon"></i>
              </div>
              <div class="album-controls">
                <button type="button" class="btn btn-sm btn-success" onclick="selectAllAlbumPhotos(${album.id})">全選此相簿</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearAlbumPhotos(${album.id})">清除此相簿</button>
              </div>
              <div class="album-photos-grid" id="album-photos-${album.id}"></div>
            `;
            list.appendChild(card);
            
            // 載入相簿照片數量
            loadAlbumPhotoCount(album.id);
          });
        } else {
          list.innerHTML = '<div class="text-muted">無相簿可選</div>';
        }
      });
  }
  
           // 載入所有照片並顯示卡片
    function loadAllPhotos() {
      fetch('get_all_photos.php')
        .then(res => res.json())
        .then(data => {
          const list = document.getElementById('allPhotosList');
          list.innerHTML = '';
          // 不清空 selectedPhotos，保持之前選擇的相簿照片
          if (data.status === 'success' && data.photos) {
           // 使用 Set 來去重，以照片路徑作為唯一識別
           const uniquePhotos = new Map();
           data.photos.forEach(photo => {
             const photoPath = photo.path || photo.filename;
             if (!uniquePhotos.has(photoPath)) {
               uniquePhotos.set(photoPath, photo);
             }
           });
           
           // 顯示去重後的照片
           uniquePhotos.forEach(photo => {
             const card = document.createElement('div');
             card.className = 'photo-card-select'; // 不預設選中，讓使用者手動選擇
             card.innerHTML = `
               <img src="${photo.path || photo.filename}" alt="photo" onerror="this.src='img/default_album_cover.svg'">
             `;
             // 將照片的完整資訊存儲在 data 屬性中
             card.dataset.photoInfo = JSON.stringify({
               album_name: photo.album_name || '未知相簿',
               datetime: photo.datetime,
               latitude: photo.latitude,
               longitude: photo.longitude
             });
             card.onclick = function() {
               card.classList.toggle('selected');
               updateSelectedPhotos();
             };
             list.appendChild(card);
           });
           // 更新照片預覽和計數
           updateSelectedPhotos();
           document.getElementById('photoCount').textContent = '0';
         } else {
           list.innerHTML = '<div class="text-muted">無照片可選</div>';
         }
       });
     }

  // 載入相簿照片數量
  function loadAlbumPhotoCount(albumId) {
    fetch('get_album_photos.php?album_id=' + albumId)
      .then(res => res.json())
      .then(data => {
        const countElement = document.querySelector(`[data-album-id="${albumId}"] .album-photo-count`);
        if (countElement && data.photos) {
          countElement.textContent = `${data.photos.length} 張照片`;
        }
      });
  }

  // 切換相簿展開/收合
  function toggleAlbum(albumId) {
    const card = document.querySelector(`[data-album-id="${albumId}"]`);
    const photosGrid = document.getElementById(`album-photos-${albumId}`);
    
    if (card.classList.contains('expanded')) {
      // 收合相簿
      card.classList.remove('expanded');
    } else {
      // 展開相簿
      card.classList.add('expanded');
      
      // 如果還沒載入照片，則載入
      if (photosGrid.children.length === 0) {
        loadAlbumPhotosForGrid(albumId);
      }
    }
  }

  // 載入相簿照片到網格中
  function loadAlbumPhotosForGrid(albumId) {
    fetch('get_album_photos.php?album_id=' + albumId)
      .then(res => res.json())
      .then(data => {
        const photosGrid = document.getElementById(`album-photos-${albumId}`);
        photosGrid.innerHTML = '';
        
        if (data.photos && data.photos.length) {
          data.photos.forEach(photo => {
            const photoCard = document.createElement('div');
            photoCard.className = 'album-photo-card';
            photoCard.dataset.photoPath = photo.path || photo.filename;
            photoCard.dataset.albumId = albumId;
            photoCard.dataset.photoInfo = JSON.stringify({
              album_name: data.album_name || '未知相簿',
              datetime: photo.datetime,
              latitude: photo.latitude,
              longitude: photo.longitude
            });
            
            photoCard.innerHTML = `
              <img src="${photo.path || photo.filename}" alt="photo" onerror="this.src='img/default_album_cover.svg'">
              <div class="photo-overlay">✓</div>
            `;
            
            photoCard.onclick = function(e) {
              e.stopPropagation();
              photoCard.classList.toggle('selected');
              updateSelectedPhotosFromAlbums();
            };
            
            photosGrid.appendChild(photoCard);
          });
        } else {
          photosGrid.innerHTML = '<div class="text-muted p-2">此相簿無照片</div>';
        }
      });
  }

  // 全選某個相簿的所有照片
  function selectAllAlbumPhotos(albumId) {
    const photosGrid = document.getElementById(`album-photos-${albumId}`);
    const photoCards = photosGrid.querySelectorAll('.album-photo-card');
    photoCards.forEach(card => {
      card.classList.add('selected');
    });
    updateSelectedPhotosFromAlbums();
  }

  // 清除某個相簿的所有選擇
  function clearAlbumPhotos(albumId) {
    const photosGrid = document.getElementById(`album-photos-${albumId}`);
    const photoCards = photosGrid.querySelectorAll('.album-photo-card');
    photoCards.forEach(card => {
      card.classList.remove('selected');
    });
    updateSelectedPhotosFromAlbums();
  }

  // 從相簿網格更新選中的照片
  function updateSelectedPhotosFromAlbums() {
    const allSelectedCards = document.querySelectorAll('.album-photo-card.selected');
    const wrap = document.getElementById('photoPreviewWrap');
    const grid = document.getElementById('photoPreview');
    const photoCount = document.getElementById('photoCount');
    const coverSection = document.getElementById('coverPhotoSection');
    
    grid.innerHTML = '';
    selectedPhotos = [];
    
    if (allSelectedCards.length > 0) {
      allSelectedCards.forEach(card => {
        const photoPath = card.dataset.photoPath;
        const photoData = card.dataset.photoInfo ? JSON.parse(card.dataset.photoInfo) : {};
        
        const previewImg = document.createElement('img');
        previewImg.src = photoPath;
        previewImg.style.width = '70px';
        previewImg.style.height = '70px';
        previewImg.style.objectFit = 'cover';
        previewImg.style.borderRadius = '8px';
        previewImg.style.marginRight = '4px';
        previewImg.style.marginBottom = '4px';
        grid.appendChild(previewImg);
        
        selectedPhotos.push({
          path: photoPath,
          album_name: photoData.album_name || '未知相簿',
          datetime: photoData.datetime,
          latitude: photoData.latitude,
          longitude: photoData.longitude
        });
      });
      
      photoCount.textContent = allSelectedCards.length;
      wrap.style.display = 'block';
      coverSection.style.display = 'block'; // 顯示封面選擇區域
    } else {
      photoCount.textContent = '0';
      wrap.style.display = 'none';
      coverSection.style.display = 'none'; // 隱藏封面選擇區域
      selectedCoverPhoto = null; // 清空封面照片選擇
      updateCoverPhotoPreview();
    }
  }

  // 更新選中的照片
  function updateSelectedPhotos() {
    const selectedCards = document.querySelectorAll('.photo-card-select.selected');
    const wrap = document.getElementById('photoPreviewWrap');
    const grid = document.getElementById('photoPreview');
    const photoCount = document.getElementById('photoCount');
    const coverSection = document.getElementById('coverPhotoSection');
    grid.innerHTML = '';
    selectedPhotos = [];
    
    if (selectedCards.length > 0) {
             selectedCards.forEach(card => {
         const img = card.querySelector('img');
         const photoPath = img.src;
         
         const previewImg = document.createElement('img');
         previewImg.src = photoPath;
         previewImg.style.width = '70px';
         previewImg.style.height = '70px';
         previewImg.style.objectFit = 'cover';
         previewImg.style.borderRadius = '8px';
         previewImg.style.marginRight = '4px';
         previewImg.style.marginBottom = '4px';
         grid.appendChild(previewImg);
         
         // 從原始數據中獲取完整的照片資訊
         const photoData = card.dataset.photoInfo ? JSON.parse(card.dataset.photoInfo) : {};
         selectedPhotos.push({
           path: photoPath,
           album_name: photoData.album_name || '未知相簿',
           datetime: photoData.datetime,
           latitude: photoData.latitude,
           longitude: photoData.longitude
         });
       });
      photoCount.textContent = selectedCards.length;
      wrap.style.display = 'block';
      coverSection.style.display = 'block'; // 顯示封面選擇區域
    } else {
      photoCount.textContent = '0';
      wrap.style.display = 'none';
      coverSection.style.display = 'none'; // 隱藏封面選擇區域
      selectedCoverPhoto = null; // 清空封面照片選擇
      updateCoverPhotoPreview();
    }
  }

  // 載入相簿所有照片並顯示縮圖
  function loadPhotosForAlbum(albumId) {
    fetch('get_album_photos.php?album_id=' + albumId)
      .then(res => res.json())
      .then(data => {
        const wrap = document.getElementById('photoPreviewWrap');
        const grid = document.getElementById('photoPreview');
        const photoCount = document.getElementById('photoCount');
        grid.innerHTML = '';
        selectedPhotos = [];
        if (data.photos && data.photos.length) {
          data.photos.forEach(photo => {
            const img = document.createElement('img');
            img.src = photo.path || photo.filename;
            img.style.width = '70px';
            img.style.height = '70px';
            img.style.objectFit = 'cover';
            img.style.borderRadius = '8px';
            img.style.marginRight = '4px';
            img.style.marginBottom = '4px';
            grid.appendChild(img);
            selectedPhotos.push(photo);
          });
          photoCount.textContent = data.photos.length;
          wrap.style.display = 'block';
        } else {
          photoCount.textContent = '0';
          wrap.style.display = 'none';
        }
      });
  }

  // 送出日誌生成請求
  const submitLogBtn = document.getElementById('submitLogBtn');
  const saveDiaryBtn = document.getElementById('saveDiaryBtn');
  const retryBtn = document.getElementById('retryBtn');
  submitLogBtn.onclick = async function() {
    const logLength = document.getElementById('logLength').value;
    const promptKeywords = document.getElementById('promptKeywords').value.trim();
    if (!logLength || logLength < 50) { alert('請輸入合理字數'); return; }
    
    // 檢查是否有選擇相簿或照片
    if (currentSelectionMode === 'album') {
      if (!selectedPhotos.length) {
        alert('請先展開相簿並選擇照片'); 
        return;
      }
    } else {
      if (!selectedPhotos.length) {
        alert('請選擇照片'); 
        return;
      }
    }
    
    // 組合 prompt
    let prompt = '';
    if (currentSelectionMode === 'album') {
      prompt = `請根據以下相簿「${selectedAlbumName}」的所有照片內容，並依照字數 ${logLength} 字，生成一篇日誌。`;
    } else {
      prompt = `請根據以下選擇的照片內容，並依照字數 ${logLength} 字，生成一篇日誌。`;
    }
    
    // 如果有輸入提示詞，加入到 prompt 中
    if (promptKeywords) {
      prompt += `\n\n請參考以下關鍵詞來生成日誌：${promptKeywords}`;
    }
    
    // 如果有選擇風格，加入到 prompt 中
    const selectedStyle = document.getElementById('styleSelect').value;
    if (selectedStyle) {
      prompt += `\n\n請使用「${selectedStyle}」的寫作風格來生成日誌。`;
    }
    
    prompt += '\n';
    
    selectedPhotos.forEach((photo, idx) => {
      prompt += `照片${idx+1}: 路徑: ${photo.path || photo.filename}, `;
      if (photo.album_name) prompt += `相簿: ${photo.album_name}, `;
      if (photo.datetime) prompt += `拍攝時間: ${photo.datetime}, `;
      if (photo.latitude && photo.longitude) prompt += `GPS: (${photo.latitude},${photo.longitude}), `;
      prompt += '\n';
    });
    
    // 顯示 loading
    document.getElementById('aiLogEditWrap').style.display = '';
    document.getElementById('aiLogEdit').value = 'AI 生成中...';
    saveDiaryBtn.style.display = 'none';
    
    // 用 fetch POST 給自己
    const formData = new FormData();
    formData.append('message', prompt);
    const resp = await fetch('ai_log.php', {
      method: 'POST',
      body: formData,
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      }
    });
    const html = await resp.text();
    // 只取 response-box 內容
    const match = html.match(/<div class="response-box"[^>]*>([\s\S]*?)<\/div>/);
    if (match) {
      document.getElementById('aiLogEdit').value = match[1].replace(/<br\s*\/?>(\n)?/g, '\n');
      saveDiaryBtn.style.display = '';
      // 將按鈕文字改為「重新生成」
      submitLogBtn.textContent = '重新生成';
    } else {
      document.getElementById('aiLogEdit').value = 'AI 回應解析失敗';
    }
    
    // 檢查是否為錯誤訊息，如果是則顯示重試按鈕
    const aiLogContent = document.getElementById('aiLogEdit').value;
    if (aiLogContent.includes('錯誤') || aiLogContent.includes('忙碌') || aiLogContent.includes('overloaded')) {
      document.getElementById('retrySection').style.display = 'block';
    } else {
      document.getElementById('retrySection').style.display = 'none';
    }
  };
  
  // 重試按鈕功能
  retryBtn.onclick = function() {
    // 重新執行提交邏輯
    submitLogBtn.click();
  };

  // 儲存日誌
  saveDiaryBtn.onclick = async function() {
    const content = document.getElementById('aiLogEdit').value.trim();
    if (!content) { alert('日誌內容不可為空'); return; }
    
    const formData = new FormData();
    if (currentSelectionMode === 'album') {
      // 檢查是否從多個相簿選擇照片
      const albumNames = [...new Set(selectedPhotos.map(p => p.album_name))];
      if (albumNames.length === 1) {
        formData.append('album_id', 0); // 0 表示自定義選擇
        formData.append('album_name', albumNames[0]);
      } else {
        formData.append('album_id', 0);
        formData.append('album_name', `多相簿選擇 (${albumNames.join(', ')})`);
      }
    } else {
      // 如果是選擇照片模式，使用第一個照片的相簿資訊或自定義名稱
      const firstPhoto = selectedPhotos[0];
      formData.append('album_id', 0); // 0 表示自定義選擇
      formData.append('album_name', '自定義照片選擇');
    }
    formData.append('content', content);
    formData.append('selection_mode', currentSelectionMode);
    formData.append('is_save_diary', '1'); // 添加儲存標識
    
    // 添加封面照片資訊
    if (selectedCoverPhoto) {
      formData.append('cover_photo', selectedCoverPhoto.path);
      formData.append('cover_photo_album', selectedCoverPhoto.album_name || '未知相簿');
      formData.append('cover_photo_path', selectedCoverPhoto.path);
    }
    
    // AJAX 儲存
    const resp = await fetch('ai_log.php', { 
      method: 'POST', 
      body: formData,
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      }
    });
    
    // 檢查回應類型
    const contentType = resp.headers.get('content-type');
    if (contentType && contentType.includes('application/json')) {
      const result = await resp.json();
      if (result.status === 'success') {
        alert('日誌已儲存');
        location.reload();
      } else {
        alert('儲存失敗：' + (result.message || '未知錯誤'));
      }
    } else {
      // 如果不是JSON，可能是錯誤頁面
      const text = await resp.text();
      console.error('伺服器返回非JSON回應:', text);
      alert('儲存失敗：伺服器回應格式錯誤');
    }
  };

  // 顯示日誌詳情
  function showDiaryDetail(diaryId) {
    fetch('get_diary_detail.php?diary_id=' + diaryId)
      .then(res => res.json())
      .then(data => {
        if (data.status === 'success') {
          const modal = document.getElementById('diaryDetailModal');
          const modalTitle = document.getElementById('diaryDetailTitle');
          const albumName = document.getElementById('diaryAlbumName');
          const diaryContent = document.getElementById('diaryContent');
          const diaryPhotos = document.getElementById('diaryPhotos');
          const createTime = document.getElementById('diaryCreateTime');
          modalTitle.textContent = '日誌詳情';
          albumName.textContent = data.album_name;
          diaryContent.value = data.content;
          diaryPhotos.innerHTML = '';
          data.photos.forEach(photo => {
            const img = document.createElement('img');
            img.src = photo.path || photo.filename;
            img.style.width = '100px';
            img.style.height = '100px';
            img.style.objectFit = 'cover';
            img.style.borderRadius = '8px';
            img.style.marginRight = '10px';
            diaryPhotos.appendChild(img);
          });
          createTime.textContent = data.created_at;
          const diaryDetailModal = new bootstrap.Modal(modal);
          diaryDetailModal.show();
        } else {
          alert('載入日誌詳情失敗');
        }
      });
  }
  </script>
</body>
</html>