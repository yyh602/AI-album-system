<?php
// 檢查 session 狀態
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION["username"])) {
    header("Location: login.php");
    exit();
}

require_once 'exif_processor.php';
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EXIF 測試 - AI 智慧相簿</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .test-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .result-box {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 15px;
            margin-top: 10px;
        }
        .success { color: #198754; }
        .error { color: #dc3545; }
        .info { color: #0dcaf0; }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h2><i class="fas fa-camera"></i> EXIF 測試工具</h2>
        <p class="text-muted">測試 JPG 和 HEIC 檔案的 EXIF 抓取（時間和經緯度）</p>

        <!-- 系統環境檢查 -->
        <div class="test-section">
            <h4><i class="fas fa-cog"></i> 系統環境檢查</h4>
            <div class="result-box">
                <p><strong>PHP 版本：</strong> <?php echo PHP_VERSION; ?></p>
                <p><strong>EXIF 擴展：</strong> 
                    <?php if (extension_loaded('exif')): ?>
                        <span class="success">✓ 已載入</span>
                    <?php else: ?>
                        <span class="error">✗ 未載入</span>
                    <?php endif; ?>
                </p>
                <p><strong>Imagick 擴展：</strong> 
                    <?php if (extension_loaded('imagick')): ?>
                        <span class="success">✓ 已載入</span>
                    <?php else: ?>
                        <span class="error">✗ 未載入</span>
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <!-- 檔案上傳測試 -->
        <div class="test-section">
            <h4><i class="fas fa-upload"></i> 檔案上傳測試</h4>
            <form action="save_album_blob.php" method="post" enctype="multipart/form-data" id="uploadForm">
                <div class="mb-3">
                    <label for="albumName" class="form-label">相簿名稱</label>
                    <input type="text" class="form-control" id="albumName" name="albumName" value="EXIF 測試相簿" required>
                </div>
                <div class="mb-3">
                    <label for="photos" class="form-label">選擇圖片檔案（JPG 或 HEIC）</label>
                    <input type="file" class="form-control" id="photos" name="photos[]" multiple accept=".jpg,.jpeg,.heic,.heif" required>
                    <div class="form-text">選擇包含 EXIF 資料的圖片檔案進行測試</div>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-upload"></i> 上傳並測試 EXIF
                </button>
            </form>
            <div id="uploadResult" class="result-box" style="display: none;"></div>
        </div>

        <!-- 手動測試 -->
        <div class="test-section">
            <h4><i class="fas fa-code"></i> 手動測試</h4>
            <form id="manualTestForm">
                <div class="mb-3">
                    <label for="blobUrl" class="form-label">圖片 URL</label>
                    <input type="url" class="form-control" id="blobUrl" name="blobUrl" placeholder="https://example.com/image.jpg" required>
                </div>
                <div class="mb-3">
                    <label for="fileName" class="form-label">檔案名稱</label>
                    <input type="text" class="form-control" id="fileName" name="fileName" placeholder="test.jpg" required>
                </div>
                <button type="submit" class="btn btn-secondary">
                    <i class="fas fa-play"></i> 測試 EXIF 抓取
                </button>
            </form>
            <div id="manualResult" class="result-box" style="display: none;"></div>
        </div>

        <!-- 返回按鈕 -->
        <div class="text-center mt-4">
            <a href="album.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> 返回相簿
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 檔案上傳測試
        document.getElementById('uploadForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const resultDiv = document.getElementById('uploadResult');
            
            resultDiv.style.display = 'block';
            resultDiv.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> 處理中...</div>';
            
            try {
                const response = await fetch('save_album_blob.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.status === 'success') {
                    resultDiv.innerHTML = `
                        <div class="success">
                            <h5>✓ 上傳成功</h5>
                            <p><strong>相簿 ID：</strong> ${result.album_id}</p>
                            <p><strong>上傳檔案數：</strong> ${result.uploaded_files ? result.uploaded_files.length : 0}</p>
                            <p><strong>訊息：</strong> ${result.message || '檔案已成功上傳並處理 EXIF 資料'}</p>
                        </div>
                    `;
                } else {
                    resultDiv.innerHTML = `
                        <div class="error">
                            <h5>✗ 上傳失敗</h5>
                            <p><strong>錯誤：</strong> ${result.message || '未知錯誤'}</p>
                        </div>
                    `;
                }
            } catch (error) {
                resultDiv.innerHTML = `
                    <div class="error">
                        <h5>✗ 網路錯誤</h5>
                        <p><strong>錯誤：</strong> ${error.message}</p>
                    </div>
                `;
            }
        });

        // 手動測試
        document.getElementById('manualTestForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const resultDiv = document.getElementById('manualResult');
            
            resultDiv.style.display = 'block';
            resultDiv.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> 測試中...</div>';
            
            try {
                const response = await fetch('exif_processor.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    resultDiv.innerHTML = `
                        <div class="success">
                            <h5>✓ EXIF 抓取成功</h5>
                            <p><strong>拍攝時間：</strong> ${result.datetime || '無時間資訊'}</p>
                            <p><strong>緯度：</strong> ${result.latitude || '無緯度資訊'}</p>
                            <p><strong>經度：</strong> ${result.longitude || '無經度資訊'}</p>
                            <p><strong>原始格式：</strong> ${result.original_format || 'JPG'}</p>
                            ${result.converted_format ? `<p><strong>轉換格式：</strong> ${result.converted_format}</p>` : ''}
                        </div>
                    `;
                } else {
                    resultDiv.innerHTML = `
                        <div class="error">
                            <h5>✗ EXIF 抓取失敗</h5>
                            <p><strong>錯誤：</strong> ${result.error || result.message || '未知錯誤'}</p>
                        </div>
                    `;
                }
            } catch (error) {
                resultDiv.innerHTML = `
                    <div class="error">
                        <h5>✗ 網路錯誤</h5>
                        <p><strong>錯誤：</strong> ${error.message}</p>
                    </div>
                `;
            }
        });
    </script>
</body>
</html>
