# AI Album System

一個基於 PHP 和 Python 的智能相簿系統，支援圖片上傳、AI 分析、人臉識別等功能。

## 功能特色

- 📸 圖片上傳和管理
- 🤖 AI 圖片分析 (使用 Google Gemini API)
- 👥 人臉識別和分組
- 📝 日記和相簿功能
- 🖼️ HEIC 格式轉換
- 📊 EXIF 資料提取

## 技術架構

- **後端**: PHP 8.1 + Apache
- **AI 服務**: Python Flask + Google Gemini API
- **資料庫**: MySQL/MariaDB
- **圖片處理**: ImageMagick, ExifTool
- **人臉識別**: Google Cloud Vision API

## 部署到 Render

### 方法一：使用 render.yaml (推薦)

1. 將專案推送到 GitHub
2. 在 Render 中連接 GitHub 倉庫
3. 選擇 "Blueprint" 部署方式
4. Render 會自動使用 `render.yaml` 配置

### 方法二：手動配置

1. 在 Render 中創建新的 Web Service
2. 連接 GitHub 倉庫
3. 設定以下配置：
   - **Environment**: Docker
   - **Build Command**: 留空 (使用 Dockerfile)
   - **Start Command**: 留空 (使用 Dockerfile CMD)
   - **Port**: 80

## 環境變數設定

在 Render 中設定以下環境變數：

```
GEMINI_API_KEY=your_gemini_api_key
GOOGLE_CLOUD_VISION_API_KEY=your_vision_api_key
```

## 資料庫配置

### 本地開發
使用 `docker-compose.yml` 中的 MySQL 服務

### 生產環境
在 Render 中創建 MySQL 資料庫服務，並更新 `DB_open.php` 中的連接設定

## 本地開發

### 使用 Docker Compose

```bash
# 構建並啟動服務
docker-compose up --build

# 訪問應用
http://localhost:8080
```

### 手動 Docker 構建

```bash
# 構建映像
docker build -t ai-album-system .

# 運行容器
docker run -p 8080:80 ai-album-system
```

## 檔案結構

```
AI-album-system/
├── Dockerfile              # Docker 配置
├── docker-compose.yml      # 本地開發配置
├── render.yaml            # Render 部署配置
├── .dockerignore          # Docker 忽略檔案
├── requirements.txt       # Python 依賴
├── face_test/            # 人臉識別模組
│   ├── composer.json     # PHP 依賴
│   └── detect_faces_opencv.py
├── uploads/              # 上傳檔案目錄
├── css/                  # 樣式檔案
└── *.php                 # PHP 應用程式檔案
```

## 常見問題

### 1. PHP 擴展安裝失敗
- 確保使用正確的 PHP 版本 (8.1)
- 檢查系統依賴是否完整安裝

### 2. 圖片上傳失敗
- 檢查 `uploads/` 目錄權限
- 確認 PHP 上傳設定 (`upload_max_filesize`, `post_max_size`)

### 3. AI 功能無法使用
- 確認 API 金鑰設定正確
- 檢查網路連接

## 支援

如有問題，請檢查：
1. Render 部署日誌
2. PHP 錯誤日誌 (`php_errors.log`)
3. 上傳日誌 (`upload_log.txt`)

## 授權

本專案僅供學習和研究使用。 