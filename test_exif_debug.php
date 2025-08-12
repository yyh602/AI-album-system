<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EXIF 抓取測試</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }
        input[type="text"], input[type="url"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            box-sizing: border-box;
        }
        button {
            background-color: #007bff;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
        }
        button:hover {
            background-color: #0056b3;
        }
        .result {
            margin-top: 30px;
            padding: 20px;
            border-radius: 5px;
            white-space: pre-wrap;
            font-family: monospace;
            font-size: 12px;
            max-height: 500px;
            overflow-y: auto;
        }
        .success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .info {
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }
        .example {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 EXIF 抓取測試工具</h1>
        
        <div class="example">
            <strong>使用說明：</strong><br>
            1. 輸入您上傳到 Azure Storage 的圖片 URL<br>
            2. 輸入檔案名稱（包含副檔名）<br>
            3. 點擊測試按鈕查看 EXIF 抓取結果
        </div>

        <form id="exifTestForm">
            <div class="form-group">
                <label for="blobUrl">圖片 URL (Blob URL):</label>
                <input type="url" id="blobUrl" name="blobUrl" placeholder="https://yourstorage.blob.core.windows.net/container/filename.jpg" required>
            </div>
            
            <div class="form-group">
                <label for="fileName">檔案名稱:</label>
                <input type="text" id="fileName" name="fileName" placeholder="example.HEIC 或 example.jpg" required>
            </div>
            
            <button type="submit">🔍 測試 EXIF 抓取</button>
        </form>

        <div id="result" class="result" style="display: none;"></div>
    </div>

    <script>
        document.getElementById('exifTestForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const blobUrl = document.getElementById('blobUrl').value;
            const fileName = document.getElementById('fileName').value;
            const resultDiv = document.getElementById('result');
            
            if (!blobUrl || !fileName) {
                alert('請填寫所有欄位');
                return;
            }
            
            // 顯示載入中
            resultDiv.style.display = 'block';
            resultDiv.className = 'result info';
            resultDiv.textContent = '正在測試 EXIF 抓取...';
            
            try {
                const formData = new FormData();
                formData.append('blobUrl', blobUrl);
                formData.append('fileName', fileName);
                
                const response = await fetch('debug_step1.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                // 顯示結果
                resultDiv.style.display = 'block';
                
                // 顯示結果
                resultDiv.className = 'result success';
                resultDiv.textContent = '✅ 基本測試成功！\n\n' + 
                    '🔧 詳細資訊:\n' + JSON.stringify(result, null, 2);
                
            } catch (error) {
                resultDiv.className = 'result error';
                resultDiv.textContent = '❌ 請求失敗: ' + error.message;
            }
        });
    </script>
</body>
</html>
