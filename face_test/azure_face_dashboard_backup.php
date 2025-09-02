<?php
/**
 * Azure 人臉儀表板 - OpenCV 備用方案
 * 使用系統套件進行人臉分群，不需要安裝額外套件
 */

session_start();
require_once 'config.php';

// 檢查登入狀態
if (!isset($_SESSION['user_id'])) {
    header('Location: auth_test.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$album_id = isset($_GET['album_id']) ? (int)$_GET['album_id'] : 0;

// 獲取相簿資訊
$stmt = $pdo->prepare("SELECT * FROM albums WHERE id = ? AND user_id = ?");
$stmt->execute([$album_id, $user_id]);
$album = $stmt->fetch();

if (!$album) {
    die("相簿不存在或無權限訪問");
}

// 獲取相簿中的圖片
$stmt = $pdo->prepare("SELECT * FROM images WHERE album_id = ? ORDER BY upload_date DESC");
$stmt->execute([$album_id]);
$images = $stmt->fetchAll();

// 處理人臉偵測請求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $response = ['success' => false, 'message' => ''];
    
    if ($_POST['action'] === 'detect_faces') {
        $selected_images = isset($_POST['selected_images']) ? $_POST['selected_images'] : [];
        
        if (empty($selected_images)) {
            $response['message'] = '請選擇要處理的圖片';
        } else {
            try {
                // 使用 OpenCV 備用方案進行人臉分群
                $result = executeOpenCVFaceDetection($selected_images, $album_id);
                $response = $result;
            } catch (Exception $e) {
                $response['message'] = '處理失敗: ' . $e->getMessage();
            }
        }
        
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
}

/**
 * 執行 OpenCV 人臉偵測（備用方案）
 */
function executeOpenCVFaceDetection($image_ids, $album_id) {
    global $pdo;
    
    try {
        // 獲取圖片路徑
        $placeholders = str_repeat('?,', count($image_ids) - 1) . '?';
        $stmt = $pdo->prepare("SELECT id, filename, file_path FROM images WHERE id IN ($placeholders)");
        $stmt->execute($image_ids);
        $images = $stmt->fetchAll();
        
        if (empty($images)) {
            return ['success' => false, 'message' => '未找到選定的圖片'];
        }
        
        // 創建臨時的 OpenCV 腳本
        $script_content = createOpenCVScript($images);
        $script_path = '/tmp/opencv_face_detection_' . time() . '.py';
        
        // 寫入腳本檔案
        file_put_contents($script_path, $script_content);
        
        // 執行腳本
        $command = "cd /home/site/wwwroot && python3 $script_path 2>&1";
        $output = shell_exec($command);
        
        // 清理臨時檔案
        unlink($script_path);
        
        // 解析結果
        $result = parseOpenCVOutput($output);
        
        if ($result['success']) {
            // 儲存結果到資料庫
            saveFaceGroupsToDatabase($result['groups'], $album_id);
        }
        
        return $result;
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'OpenCV 處理失敗: ' . $e->getMessage()];
    }
}

/**
 * 創建 OpenCV 腳本內容
 */
function createOpenCVScript($images) {
    $image_paths = [];
    foreach ($images as $image) {
        $image_paths[] = $image['file_path'];
    }
    
    $script = <<<PYTHON
#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
OpenCV 人臉分群腳本 - 備用方案
使用系統套件進行人臉偵測和分群
"""

import cv2
import numpy as np
import os
import json
import sys
from sklearn.cluster import DBSCAN
from sklearn.metrics.pairwise import cosine_similarity

def detect_faces_opencv(image_path):
    """使用 OpenCV 偵測人臉"""
    try:
        # 讀取圖片
        img = cv2.imread(image_path)
        if img is None:
            return []
        
        # 轉換為灰階
        gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
        
        # 載入人臉偵測器
        face_cascade = cv2.CascadeClassifier(cv2.data.haarcascades + 'haarcascade_frontalface_default.xml')
        
        # 偵測人臉
        faces = face_cascade.detectMultiScale(
            gray,
            scaleFactor=1.1,
            minNeighbors=5,
            minSize=(30, 30)
        )
        
        face_features = []
        for (x, y, w, h) in faces:
            # 提取人臉區域
            face_roi = gray[y:y+h, x:x+w]
            
            # 調整大小為標準尺寸
            face_roi = cv2.resize(face_roi, (64, 64))
            
            # 提取特徵（使用像素值）
            features = face_roi.flatten()[:100]  # 取前100個像素值
            
            face_features.append({
                'bbox': (int(x), int(y), int(w), int(h)),
                'features': features.tolist(),
                'confidence': 0.8  # OpenCV 預設信心度
            })
        
        return face_features
        
    except Exception as e:
        print(f"偵測人臉時發生錯誤: {e}", file=sys.stderr)
        return []

def group_faces_opencv(all_faces):
    """使用 OpenCV 特徵分群人臉"""
    try:
        if not all_faces:
            return []
        
        # 提取特徵
        features = []
        face_info = []
        
        for img_path, faces in all_faces.items():
            for face in faces:
                features.append(face['features'])
                face_info.append({
                    'image_path': img_path,
                    'bbox': face['bbox'],
                    'confidence': face['confidence']
                })
        
        if len(features) < 2:
            return [{'faces': face_info, 'group_id': 0}]
        
        # 轉換為 numpy 陣列
        feature_matrix = np.array(features)
        
        # 使用 DBSCAN 分群
        clustering = DBSCAN(eps=0.3, min_samples=2)
        labels = clustering.fit_predict(feature_matrix)
        
        # 組織分群結果
        groups = {}
        for i, label in enumerate(labels):
            if label not in groups:
                groups[label] = []
            groups[label].append(face_info[i])
        
        # 轉換為列表格式
        result = []
        for group_id, faces in groups.items():
            result.append({
                'group_id': int(group_id),
                'faces': faces
            })
        
        return result
        
    except Exception as e:
        print(f"分群人臉時發生錯誤: {e}", file=sys.stderr)
        return []

def main():
    """主程式"""
    try:
        # 獲取圖片路徑
        image_paths = {json.loads(arg) for arg in sys.argv[1:]}
        
        print(f"開始處理 {len(image_paths)} 張圖片...", file=sys.stderr)
        
        all_faces = {}
        total_faces = 0
        
        for image_path in image_paths:
            if os.path.exists(image_path):
                faces = detect_faces_opencv(image_path)
                all_faces[image_path] = faces
                total_faces += len(faces)
                print(f"圖片 {os.path.basename(image_path)}: 偵測到 {len(faces)} 個人臉", file=sys.stderr)
        
        print(f"總共偵測到 {total_faces} 個人臉", file=sys.stderr)
        
        if total_faces == 0:
            result = {
                'success': True,
                'message': '未偵測到人臉',
                'groups': [],
                'total_faces': 0
            }
        else:
            # 分群人臉
            groups = group_faces_opencv(all_faces)
            
            result = {
                'success': True,
                'message': f'成功處理 {len(groups)} 個群組',
                'groups': groups,
                'total_faces': total_faces
            }
        
        # 輸出 JSON 結果
        print(json.dumps(result, ensure_ascii=False))
        
    except Exception as e:
        error_result = {
            'success': False,
            'message': f'處理失敗: {str(e)}',
            'groups': [],
            'total_faces': 0
        }
        print(json.dumps(error_result, ensure_ascii=False))

if __name__ == "__main__":
    main()
PYTHON;
    
    return $script;
}

/**
 * 解析 OpenCV 腳本輸出
 */
function parseOpenCVOutput($output) {
    try {
        // 尋找 JSON 結果
        $lines = explode("\n", $output);
        $json_line = '';
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (strpos($line, '{') === 0 && strpos($line, '}') !== false) {
                $json_line = $line;
                break;
            }
        }
        
        if (empty($json_line)) {
            return ['success' => false, 'message' => '無法解析腳本輸出: ' . $output];
        }
        
        $result = json_decode($json_line, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['success' => false, 'message' => 'JSON 解析失敗: ' . json_last_error_msg()];
        }
        
        return $result;
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => '解析輸出失敗: ' . $e->getMessage()];
    }
}

/**
 * 儲存人臉群組到資料庫
 */
function saveFaceGroupsToDatabase($groups, $album_id) {
    global $pdo;
    
    try {
        // 清除舊的人臉群組
        $stmt = $pdo->prepare("DELETE FROM face_groups WHERE album_id = ?");
        $stmt->execute([$album_id]);
        
        foreach ($groups as $group) {
            $group_id = $group['group_id'];
            
            foreach ($group['faces'] as $face) {
                // 找到對應的圖片
                $stmt = $pdo->prepare("SELECT id FROM images WHERE file_path = ?");
                $stmt->execute([$face['image_path']]);
                $image = $stmt->fetch();
                
                if ($image) {
                    // 儲存人臉資訊
                    $stmt = $pdo->prepare("INSERT INTO face_groups (album_id, image_id, group_id, bbox_x, bbox_y, bbox_width, bbox_height, confidence) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $album_id,
                        $image['id'],
                        $group_id,
                        $face['bbox'][0],
                        $face['bbox'][1],
                        $face['bbox'][2],
                        $face['bbox'][3],
                        $face['confidence']
                    ]);
                }
            }
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("儲存人臉群組失敗: " . $e->getMessage());
        return false;
    }
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI 相簿系統 - Azure 人臉辨識儀表板 (OpenCV 備用方案)</title>
    <style>
        body {
            font-family: 'Microsoft JhengHei', Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 2em;
        }
        .content {
            padding: 30px;
        }
        .album-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        .test-files {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .test-file {
            background: #e9ecef;
            padding: 10px 15px;
            border-radius: 5px;
            font-size: 0.9em;
        }
        .image-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .image-item {
            border: 2px solid #ddd;
            border-radius: 10px;
            overflow: hidden;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .image-item:hover {
            border-color: #667eea;
            transform: translateY(-2px);
        }
        .image-item.selected {
            border-color: #28a745;
            box-shadow: 0 0 15px rgba(40, 167, 69, 0.3);
        }
        .image-item img {
            width: 100%;
            height: 150px;
            object-fit: cover;
        }
        .image-info {
            padding: 10px;
            background: white;
        }
        .image-name {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .image-date {
            font-size: 0.8em;
            color: #666;
        }
        .controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 25px;
            cursor: pointer;
            font-size: 1em;
            transition: all 0.3s ease;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }
        .checkbox-container {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .checkbox-container input[type="checkbox"] {
            transform: scale(1.2);
        }
        .result {
            margin-top: 20px;
            padding: 20px;
            border-radius: 10px;
        }
        .result.success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .result.error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .loading {
            text-align: center;
            padding: 20px;
        }
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 10px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🤖 AI 相簿系統 - Azure 人臉辨識儀表板</h1>
            <p>OpenCV 備用方案 - 使用系統套件進行人臉分群</p>
        </div>
        
        <div class="content">
            <div class="album-info">
                <h2>📁 相簿：<?= htmlspecialchars($album['name']) ?></h2>
                <p>📊 圖片數量：<?= count($images) ?> 張</p>
                <p>📅 建立時間：<?= $album['created_at'] ?></p>
            </div>
            
            <div class="test-files">
                <div class="test-file">924KB TEST</div>
                <div class="test-file">504KB TEST</div>
                <div class="test-file">150KB TEST</div>
                <div class="test-file">8KB TEST</div>
            </div>
            
            <div class="controls">
                <div class="checkbox-container">
                    <input type="checkbox" id="selectAll">
                    <label for="selectAll">全選</label>
                </div>
                <button class="btn" id="detectBtn" onclick="detectFaces()">開始人臉偵測</button>
            </div>
            
            <div class="image-grid">
                <?php foreach ($images as $image): ?>
                <div class="image-item" data-id="<?= $image['id'] ?>">
                    <img src="<?= htmlspecialchars($image['file_path']) ?>" alt="<?= htmlspecialchars($image['filename']) ?>">
                    <div class="image-info">
                        <div class="image-name"><?= htmlspecialchars($image['filename']) ?></div>
                        <div class="image-date"><?= $image['upload_date'] ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div id="result"></div>
        </div>
    </div>

    <script>
        // 圖片選擇功能
        document.querySelectorAll('.image-item').forEach(item => {
            item.addEventListener('click', function() {
                this.classList.toggle('selected');
                updateSelectAll();
            });
        });
        
        // 全選功能
        document.getElementById('selectAll').addEventListener('change', function() {
            const selected = this.checked;
            document.querySelectorAll('.image-item').forEach(item => {
                if (selected) {
                    item.classList.add('selected');
                } else {
                    item.classList.remove('selected');
                }
            });
        });
        
        function updateSelectAll() {
            const total = document.querySelectorAll('.image-item').length;
            const selected = document.querySelectorAll('.image-item.selected').length;
            document.getElementById('selectAll').checked = total === selected;
        }
        
        // 人臉偵測功能
        function detectFaces() {
            const selectedImages = Array.from(document.querySelectorAll('.image-item.selected'))
                .map(item => item.dataset.id);
            
            if (selectedImages.length === 0) {
                showResult('請選擇要處理的圖片', 'error');
                return;
            }
            
            // 顯示載入狀態
            showLoading();
            
            // 發送請求
            fetch('azure_face_dashboard_backup.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'detect_faces',
                    selected_images: selectedImages
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showResult(`✅ ${data.message}<br>總共處理 ${data.total_faces} 個人臉，分成 ${data.groups.length} 個群組`, 'success');
                } else {
                    showResult(`❌ ${data.message}`, 'error');
                }
            })
            .catch(error => {
                showResult(`❌ 請求失敗: ${error.message}`, 'error');
            });
        }
        
        function showLoading() {
            const resultDiv = document.getElementById('result');
            resultDiv.innerHTML = `
                <div class="loading">
                    <div class="spinner"></div>
                    <p>正在使用 OpenCV 進行人臉偵測...</p>
                </div>
            `;
        }
        
        function showResult(message, type) {
            const resultDiv = document.getElementById('result');
            resultDiv.innerHTML = `<div class="result ${type}">${message}</div>`;
        }
    </script>
</body>
</html>
