# Azure App Service 持久化部署指南

## 📁 檔案放置位置

### 1. 根目錄檔案（放在 `/home/site/wwwroot/`）

```
/home/site/wwwroot/
├── .deployment          ← 放在根目錄
├── startup.sh           ← 放在根目錄
├── web.config           ← 放在根目錄
└── face_test/           ← 您的專案目錄
    ├── azure_face_dashboard.php
    ├── azure_face_detection.php
    ├── group_faces_azure.py
    ├── ultimate_persistence_test.php
    └── ... (其他檔案)
```

### 2. 重要說明

**✅ 必須放在根目錄的檔案：**
- `.deployment` - Azure 部署配置
- `startup.sh` - 啟動腳本
- `web.config` - IIS 配置

**✅ 放在專案目錄的檔案：**
- `face_test/` 目錄下的所有 PHP 和 Python 檔案

### 3. 部署步驟

1. **上傳根目錄檔案：**
   ```
   /home/site/wwwroot/.deployment
   /home/site/wwwroot/startup.sh
   /home/site/wwwroot/web.config
   ```

2. **上傳專案檔案：**
   ```
   /home/site/wwwroot/face_test/
   ```

3. **設定權限：**
   ```bash
   chmod +x /home/site/wwwroot/startup.sh
   ```

4. **重啟 App Service**

### 4. 測試 URL

- **持久化測試：** `https://your-app.azurewebsites.net/face_test/ultimate_persistence_test.php`
- **人臉偵測：** `https://your-app.azurewebsites.net/face_test/azure_face_dashboard.php`

### 5. 檔案說明

| 檔案 | 位置 | 用途 |
|------|------|------|
| `.deployment` | 根目錄 | 告訴 Azure 執行 startup.sh |
| `startup.sh` | 根目錄 | 每次重啟時自動執行 |
| `web.config` | 根目錄 | IIS 配置 |
| `ultimate_persistence_test.php` | face_test/ | 測試持久化 |
| `azure_face_dashboard.php` | face_test/ | 人臉偵測介面 |
| `group_faces_azure.py` | face_test/ | Python 人臉分群 |

### 6. 重要提醒

- **根目錄檔案** 必須放在 `/home/site/wwwroot/`
- **專案檔案** 放在 `/home/site/wwwroot/face_test/`
- 重啟後等待 2-3 分鐘讓啟動腳本執行
- 使用 `ultimate_persistence_test.php` 驗證持久化
