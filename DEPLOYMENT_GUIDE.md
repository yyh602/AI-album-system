# AI 相簿系統部署指南

## 在新帳號部署到 Render 的步驟

### 1. 準備工作

1. **Fork 或複製專案**
   - 將專案複製到新的 GitHub 帳號
   - 或直接使用現有的 GitHub 專案

2. **選擇資料庫方案**
   - **選項 A**: 使用 Render 的 PostgreSQL 資料庫
   - **選項 B**: 使用外部資料庫（如 Neon、AWS RDS 等）

### 2. 資料庫設定

#### 選項 A: 使用 Render PostgreSQL

1. 在 Render Dashboard 建立新的 PostgreSQL 資料庫
2. 記錄以下資訊：
   - 主機地址 (Host)
   - 資料庫名稱 (Database Name)
   - 用戶名 (Username)
   - 密碼 (Password)
   - 端口 (通常是 5432)

#### 選項 B: 使用外部資料庫

1. 建立外部資料庫（如 Neon）
2. 記錄連接資訊

### 3. 環境變數設定

在 Render Dashboard 的 Web Service 設定中，添加以下環境變數：

```
DB_HOST=your-database-host
DB_NAME=your-database-name
DB_USER=your-database-user
DB_PASS=your-database-password
DB_PORT=3306  # MySQL 使用 3306，PostgreSQL 使用 5432
DB_TYPE=mysql  # 或 postgresql
```

### 4. 資料庫初始化

#### 如果是 PostgreSQL：
需要將現有的 MySQL 資料庫結構轉換為 PostgreSQL 格式。

#### 如果是 MySQL/MariaDB：
可以直接使用現有的資料庫結構。

### 5. 部署步驟

1. **連接 GitHub**
   - 在 Render Dashboard 點擊 "New Web Service"
   - 選擇您的 GitHub 專案

2. **設定服務**
   - 名稱: `ai-album-system`
   - 環境: `Docker`
   - 分支: `main` 或 `master`
   - 根目錄: `/` (如果專案在根目錄)

3. **環境變數**
   - 添加上述資料庫環境變數
   - 確保所有敏感資訊都設定為環境變數

4. **部署**
   - 點擊 "Create Web Service"
   - 等待部署完成

### 6. 驗證部署

1. 檢查健康檢查路徑 `/simple.php` 是否正常
2. 測試資料庫連接
3. 測試上傳功能
4. 檢查檔案權限

### 7. 常見問題

#### 資料庫連接失敗
- 檢查環境變數是否正確設定
- 確認資料庫防火牆設定
- 檢查網路連接

#### 檔案上傳失敗
- 檢查 `uploads` 目錄權限
- 確認 PHP 上傳設定

#### 圖片處理失敗
- 確認 ImageMagick 已正確安裝
- 檢查 PHP 擴展是否載入

### 8. 安全注意事項

1. **環境變數**
   - 永遠不要將資料庫密碼寫在程式碼中
   - 使用環境變數管理敏感資訊

2. **檔案權限**
   - 確保 `uploads` 目錄有適當的寫入權限
   - 限制可上傳的檔案類型

3. **資料庫安全**
   - 使用強密碼
   - 限制資料庫用戶權限
   - 定期備份資料

### 9. 監控和維護

1. **日誌監控**
   - 定期檢查應用程式日誌
   - 監控錯誤率

2. **效能優化**
   - 監控資料庫查詢效能
   - 優化圖片處理流程

3. **備份策略**
   - 定期備份資料庫
   - 備份上傳的圖片檔案 