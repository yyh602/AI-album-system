# Azure Storage 人臉辨識系統整合說明

## 概述

本系統將原本使用本地檔案系統的人臉辨識功能，整合為使用 Azure Storage Account 進行圖片存放，同時保持所有原有的人臉辨識功能不變。

## 系統架構

### 原有功能
- ✅ Google Vision API 人臉偵測
- ✅ OpenCV + InsightFace 人臉特徵提取
- ✅ 人臉分群演算法
- ✅ 本地暫存處理

### 新增功能
- ✅ Azure Storage Account 圖片存放
- ✅ 從 Azure 下載圖片進行處理
- ✅ 將處理結果上傳到 Azure
- ✅ 整合資料庫相簿系統

## 檔案結構

```
face_test/
├── azure_face_detection.php      # 主要的 Azure 人臉偵測類別
├── azure_face_dashboard.php      # Azure 版本的儀表板介面
├── azure_group_faces.py          # 支援 Azure 的 Python 分群腳本
├── group_faces.py                # 原有的 Python 分群腳本
├── test_vision.php               # 原有的本地版本
├── face_dashboard.php            # 原有的本地版本儀表板
├── faces/                        # 本地暫存人臉圖片
├── group/                        # 本地暫存分群結果
├── face_map.json                 # 人臉對應關係
├── group_results.json            # 分群結果
└── group_details.json            # 詳細分群資訊
```

## 環境設定

### 1. Azure Storage Account 設定

確保以下環境變數已設定：

```bash
AZURE_STORAGE_CONNECTION_STRING=DefaultEndpointsProtocol=https;AccountName=your_account;AccountKey=your_key;EndpointSuffix=core.windows.net
AZURE_STORAGE_CONTAINER_NAME=photos
```

### 2. Google Cloud Vision API 設定

確保 Google Cloud 憑證檔案存在：
```
shining-glyph-465006-i1-8f6de1bb78de.json
```

### 3. Python 環境

確保以下 Python 套件已安裝：
```bash
pip install opencv-python insightface scikit-learn requests numpy
```

## 使用方法

### 方法一：使用新的 Azure 儀表板

1. 訪問 `azure_face_dashboard.php`
2. 選擇要分析的相簿
3. 選擇要分析的照片
4. 點擊「開始人臉偵測」

### 方法二：直接呼叫 API

```php
require_once 'azure_face_detection.php';

$detector = new AzureFaceDetection();
$imageUrls = [
    'https://your-storage.blob.core.windows.net/photos/image1.jpg',
    'https://your-storage.blob.core.windows.net/photos/image2.jpg'
];

// 執行人臉偵測
$faceMap = $detector->detectFaces($imageUrls);

// 執行人臉分群
$groupOutput = $detector->groupFaces();

// 上傳分群結果
$groupResults = $detector->uploadGroupsToAzure();
```

## 工作流程

### 1. 圖片來源
- 從 Azure Storage 下載原始圖片
- 支援 Azure Blob URL 格式

### 2. 人臉偵測
- 使用 Google Vision API 偵測人臉位置
- 裁切人臉區域
- 將人臉圖片上傳到 Azure Storage (`/faces/` 目錄)

### 3. 人臉分群
- 使用 InsightFace 提取人臉特徵
- 計算相似度矩陣
- 進行圖論分群
- 將分群結果上傳到 Azure Storage (`/groups/` 目錄)

### 4. 結果儲存
- `face_map.json`: 人臉對應關係
- `group_results.json`: 分群結果
- `group_details.json`: 詳細分群資訊

## Azure Storage 目錄結構

```
photos/
├── original_images/              # 原始圖片
│   ├── image1.jpg
│   └── image2.jpg
├── faces/                        # 偵測到的人臉
│   ├── face_0.jpg
│   ├── face_1.jpg
│   └── ...
└── groups/                       # 分群結果
    ├── people_1/
    │   ├── face_0.jpg
    │   └── face_2.jpg
    └── people_2/
        └── face_1.jpg
```

## 資料格式

### face_map.json
```json
{
  "face_0.jpg": {
    "original_image": "https://storage.blob.core.windows.net/photos/image1.jpg",
    "azure_url": "https://storage.blob.core.windows.net/photos/faces/face_0.jpg",
    "local_path": "faces/face_0.jpg"
  }
}
```

### group_results.json
```json
{
  "people_1": [
    {
      "face_name": "face_0.jpg",
      "azure_url": "https://storage.blob.core.windows.net/photos/groups/people_1/face_0.jpg",
      "local_path": "group/people_1/face_0.jpg"
    }
  ]
}
```

## 錯誤處理

### 常見錯誤及解決方案

1. **Azure Storage 連線失敗**
   - 檢查環境變數設定
   - 確認 Azure Storage Account 權限

2. **圖片下載失敗**
   - 檢查 Azure Blob URL 是否正確
   - 確認網路連線

3. **Python 腳本執行失敗**
   - 檢查 Python 路徑設定
   - 確認必要套件已安裝

4. **記憶體不足**
   - 調整 `memory_limit` 設定
   - 減少同時處理的圖片數量

## 效能優化

### 1. 批次處理
- 一次處理多張圖片
- 使用 Vision API 批次請求

### 2. 暫存管理
- 自動清理本地暫存檔案
- 使用 Azure CDN 加速圖片存取

### 3. 並行處理
- 可考慮使用多執行緒處理
- 非同步上傳到 Azure

## 安全性考量

### 1. 認證
- 使用 Azure SharedKey 認證
- 避免在程式碼中硬編碼金鑰

### 2. 權限控制
- 限制 Azure Storage 存取權限
- 使用 SAS Token 進行臨時存取

### 3. 資料保護
- 加密敏感資料
- 定期清理暫存檔案

## 遷移指南

### 從本地版本遷移到 Azure 版本

1. **備份現有資料**
   ```bash
   cp -r faces/ faces_backup/
   cp -r group/ group_backup/
   cp face_map.json face_map_backup.json
   ```

2. **設定 Azure Storage**
   - 建立 Azure Storage Account
   - 設定環境變數

3. **更新程式碼**
   - 使用新的 Azure 版本檔案
   - 更新相關的引用

4. **測試功能**
   - 測試圖片上傳
   - 測試人臉偵測
   - 測試分群功能

## 維護建議

### 1. 定期清理
- 清理本地暫存檔案
- 清理過期的 Azure Blob

### 2. 監控
- 監控 Azure Storage 使用量
- 監控 API 呼叫次數

### 3. 備份
- 定期備份重要資料
- 備份設定檔案

## 技術支援

如有問題，請檢查：
1. 錯誤日誌
2. Azure Storage 連線狀態
3. Python 環境設定
4. 網路連線狀態
