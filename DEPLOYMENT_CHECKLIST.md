# Neon 資料庫部署檢查清單

## ✅ 部署前準備

### 1. 資料庫設定
- [ ] 確認 Neon 資料庫已建立
- [ ] 記錄資料庫連接資訊
- [ ] 在 Neon Console 中執行資料庫結構創建 SQL

### 2. 程式碼修復
- [ ] ✅ DB_open.php - 已添加 PostgreSQL 支援
- [ ] ✅ album.php - 已修復 user 表查詢
- [ ] ✅ login.php - 已修復 user 表查詢
- [ ] ✅ add.php - 已修復 user 表查詢
- [ ] ✅ render.yaml - 已設定 Neon 資料庫環境變數

### 3. 需要手動檢查的檔案
- [ ] get_album_photos.php - 檢查 albums 表查詢
- [ ] get_uploads.php - 檢查 uploads 表查詢
- [ ] delete_album.php - 檢查 albums 表查詢
- [ ] delete_photo.php - 檢查 uploads 表查詢
- [ ] get_diary_detail.php - 檢查 travel_diary 表查詢

## ✅ Render 部署步驟

### 1. 建立 Web Service
- [ ] 在 Render Dashboard 點擊 "New Web Service"
- [ ] 選擇您的 GitHub 專案
- [ ] 設定服務名稱: `ai-album-system`
- [ ] 環境選擇: `Docker`
- [ ] 分支選擇: `main` 或 `master`

### 2. 環境變數設定
- [ ] DB_HOST=ep-sweet-band-a1bt630p-pooler.ap-southeast-1.aws.neon.tech
- [ ] DB_NAME=neondb
- [ ] DB_USER=neondb_owner
- [ ] DB_PASS=npg_V4H7NnEbUFyl
- [ ] DB_PORT=5432
- [ ] DB_TYPE=postgresql

### 3. 部署設定
- [ ] 健康檢查路徑: `/simple.php`
- [ ] 自動部署: 啟用
- [ ] 點擊 "Create Web Service"

## ✅ 部署後驗證

### 1. 基本連接測試
- [ ] 訪問應用程式主頁面
- [ ] 檢查健康檢查路徑 `/simple.php`
- [ ] 測試資料庫連接 `/pgsql_test.php`

### 2. 功能測試
- [ ] 用戶註冊功能
- [ ] 用戶登入功能
- [ ] 照片上傳功能
- [ ] 相簿創建功能
- [ ] AI 功能測試

### 3. 錯誤檢查
- [ ] 檢查 Render 日誌
- [ ] 檢查應用程式錯誤日誌
- [ ] 確認資料庫連接正常

## ✅ 資料庫初始化 SQL

在 Neon Console 的 SQL Editor 中執行：

```sql
-- 創建用戶表
CREATE TABLE "user" (
    id SERIAL PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 創建相簿表
CREATE TABLE album (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    user_id INTEGER REFERENCES "user"(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 創建照片表
CREATE TABLE photo (
    id SERIAL PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    path VARCHAR(500) NOT NULL,
    album_id INTEGER REFERENCES album(id),
    user_id INTEGER REFERENCES "user"(id),
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    datetime TIMESTAMP,
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    location_name VARCHAR(255),
    ai_description TEXT,
    ai_tags TEXT
);

-- 創建相簿照片關聯表
CREATE TABLE album_photo (
    id SERIAL PRIMARY KEY,
    album_id INTEGER REFERENCES album(id),
    photo_id INTEGER REFERENCES photo(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 插入測試用戶（密碼: password）
INSERT INTO "user" (username, password, name, email) 
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '管理員', 'admin@example.com');
```

## ✅ 常見問題解決

### 1. 資料庫連接失敗
- [ ] 檢查環境變數是否正確
- [ ] 確認 Neon 資料庫狀態
- [ ] 檢查網路連接

### 2. 表不存在錯誤
- [ ] 確認已執行資料庫結構創建 SQL
- [ ] 檢查表名是否正確（包括引號）

### 3. 權限錯誤
- [ ] 確認用戶有適當的資料庫權限
- [ ] 檢查 Neon 用戶設定

## ✅ 安全檢查

- [ ] 確認密碼已更改（不要使用預設密碼）
- [ ] 檢查環境變數是否正確設定
- [ ] 確認 SSL 連接已啟用
- [ ] 檢查檔案權限設定

## ✅ 監控設定

- [ ] 在 Neon Console 監控資料庫使用情況
- [ ] 在 Render Dashboard 監控應用程式效能
- [ ] 設定錯誤通知（可選）

---

**部署完成後，請刪除此檢查清單檔案以確保安全！** 