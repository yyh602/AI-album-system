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

if ($link instanceof PDO) {
    $sql = "SELECT name FROM \"user\" WHERE username = ?";
    $stmt = $link->prepare($sql);
    $stmt->execute([$username]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $name = $row['name'];
    }
} else {
    $sql = "SELECT name FROM user WHERE username = ?";
    $stmt = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $result_name);
    if (mysqli_stmt_fetch($stmt)) {
        $name = $result_name;
    }
    mysqli_stmt_close($stmt);
}
require_once("DB_close.php"); // 確保你的資料庫關閉檔案存在且正確

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
            $response_text = "API 錯誤 (HTTP {$http_code}): " . htmlspecialchars($error_message);
        }
    }

    curl_close($ch);
}

$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($is_ajax && $response_text) {
    // 只回傳 response-box 給 AJAX
    echo '<div class="response-box">' . nl2br(htmlspecialchars($response_text)) . '</div>';
    exit;
}

// 歷史日誌讀取
require("DB_open.php");
require_once("DB_helper.php");

$diaries = [];
if ($link instanceof PDO) {
    $diary_sql = "SELECT d.*, a.cover_photo, a.name as album_name FROM travel_diary d LEFT JOIN albums a ON d.album_id = a.id WHERE d.username = ? ORDER BY d.created_at DESC";
    $diary_stmt = $link->prepare($diary_sql);
    $diary_stmt->execute([$username]);
    while ($row = $diary_stmt->fetch(PDO::FETCH_ASSOC)) {
        $diaries[] = $row;
    }
} else {
    $diary_sql = "SELECT d.*, a.cover_photo, a.name as album_name FROM travel_diary d LEFT JOIN albums a ON d.album_id = a.id WHERE d.username = ? ORDER BY d.created_at DESC";
    $diary_stmt = mysqli_prepare($link, $diary_sql);
    mysqli_stmt_bind_param($diary_stmt, "s", $username);
    mysqli_stmt_execute($diary_stmt);
    $diary_result = mysqli_stmt_get_result($diary_stmt);
    while ($row = mysqli_fetch_assoc($diary_result)) {
        $diaries[] = $row;
    }
    mysqli_stmt_close($diary_stmt);
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
      <img src="img/logo.png" width="32" height="32" class="me-2" alt="Logo">
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
        <img src="img/avatar.png" alt="avatar" class="navbar-avatar">
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
            <label class="form-label">選擇相簿</label>
            <div id="albumCardList" style="display:flex;flex-wrap:wrap;gap:12px;max-height:260px;overflow-y:auto;"></div>
          </div>
          <div class="mb-3" id="photoPreviewWrap" style="display:none;">
            <label class="form-label">相簿照片預覽</label>
            <div id="photoPreview" style="display:flex;flex-wrap:wrap;gap:8px;"></div>
          </div>
          <div class="mb-3">
            <label for="logLength" class="form-label">日誌字數</label>
            <input type="number" class="form-control" id="logLength" min="50" max="2000" value="200">
          </div>
          <div class="mb-3" id="aiLogEditWrap" style="display:none;">
            <label class="form-label">AI 生成日誌（可修改）</label>
            <textarea class="form-control" id="aiLogEdit" rows="6"></textarea>
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
        <img src="<?php echo htmlspecialchars($d['cover_photo'] ?? 'img/default_album_cover.png'); ?>" 
             class="history-item-image" 
             alt="<?php echo htmlspecialchars($d['album_name']); ?>">
        <div class="history-item-overlay">
          <div class="history-item-title"><?php echo htmlspecialchars($d['album_name']); ?></div>
          <div class="history-item-date"><?php echo date('Y/m/d', strtotime($d['created_at'])); ?></div>
        </div>
      </div>
    <?php endforeach; ?>
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
    .album-card-select.selected {
      border: 2px solid #1976d2;
      box-shadow: 0 0 0 2px #1976d2;
    }
    .album-card-select img {
      width: 90px;
      height: 90px;
      object-fit: cover;
      border-radius: 8px;
      margin-bottom: 6px;
      background: #f0f0f0;
    }
    .album-card-select .album-title {
      font-size: 0.95rem;
      color: #333;
      text-align: center;
      font-weight: 500;
      word-break: break-all;
    }
  </style>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
  let selectedAlbumId = null;
  let selectedAlbumName = '';
  let selectedPhotos = [];
  const createLogBtn = document.getElementById('createLogBtn');
  const createLogModal = new bootstrap.Modal(document.getElementById('createLogModal'));
  createLogBtn.onclick = () => {
    loadAlbums();
    document.getElementById('photoPreviewWrap').style.display = 'none';
    document.getElementById('aiLogEditWrap').style.display = 'none';
    document.getElementById('saveDiaryBtn').style.display = 'none';
    document.getElementById('aiLogEdit').value = '';
    createLogModal.show();
  };

  // 載入所有相簿並顯示卡片
  function loadAlbums() {
    fetch('get_album_photos.php?all_albums=1')
      .then(res => res.json())
      .then(data => {
        const list = document.getElementById('albumCardList');
        list.innerHTML = '';
        selectedAlbumId = null;
        selectedAlbumName = '';
        selectedPhotos = [];
        if (data.status === 'success' && data.albums) {
          data.albums.forEach(album => {
            const card = document.createElement('div');
            card.className = 'album-card-select';
            card.innerHTML = `
              <img src="${album.cover_photo || 'img/default_album_cover.png'}" alt="cover">
              <div class="album-title">${album.name}</div>
            `;
            card.onclick = function() {
              document.querySelectorAll('.album-card-select').forEach(c => c.classList.remove('selected'));
              card.classList.add('selected');
              selectedAlbumId = album.id;
              selectedAlbumName = album.name;
              loadPhotosForAlbum(album.id);
            };
            list.appendChild(card);
          });
        } else {
          list.innerHTML = '<div class="text-muted">無相簿可選</div>';
        }
      });
  }

  // 載入相簿所有照片並顯示縮圖
  function loadPhotosForAlbum(albumId) {
    fetch('get_album_photos.php?album_id=' + albumId)
      .then(res => res.json())
      .then(data => {
        const wrap = document.getElementById('photoPreviewWrap');
        const grid = document.getElementById('photoPreview');
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
            grid.appendChild(img);
            selectedPhotos.push(photo);
          });
          wrap.style.display = '';
        } else {
          wrap.style.display = 'none';
        }
      });
  }

  // 送出日誌生成請求
  const submitLogBtn = document.getElementById('submitLogBtn');
  const saveDiaryBtn = document.getElementById('saveDiaryBtn');
  submitLogBtn.onclick = async function() {
    const albumId = selectedAlbumId;
    const logLength = document.getElementById('logLength').value;
    if (!albumId) { alert('請選擇相簿'); return; }
    if (!logLength || logLength < 50) { alert('請輸入合理字數'); return; }
    if (!selectedPhotos.length) { alert('此相簿無照片'); return; }
    // 組合 prompt
    let prompt = `請根據以下相簿的所有照片內容，並依照字數 ${logLength} 字，生成一篇日誌。\n`;
    selectedPhotos.forEach((photo, idx) => {
      prompt += `照片${idx+1}: 路徑: ${photo.path || photo.filename}, `;
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
    } else {
      document.getElementById('aiLogEdit').value = 'AI 回應解析失敗';
    }
  };

  // 儲存日誌
  saveDiaryBtn.onclick = async function() {
    const content = document.getElementById('aiLogEdit').value.trim();
    if (!content) { alert('日誌內容不可為空'); return; }
    const formData = new FormData();
    formData.append('album_id', selectedAlbumId);
    formData.append('album_name', selectedAlbumName);
    formData.append('content', content);
    // AJAX 儲存
    const resp = await fetch('save_diary.php', { method: 'POST', body: formData });
    const result = await resp.json();
    if (result.status === 'success') {
      alert('日誌已儲存');
      location.reload();
    } else {
      alert('儲存失敗：' + (result.message || '未知錯誤'));
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