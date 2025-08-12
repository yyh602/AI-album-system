# Azure App Service PHP 擴展啟用指南

## 問題
- Composer 不可用
- ZIP 擴展未載入

## 解決方案

### 1. 啟用 ZIP 擴展
在 Azure App Service 的 `Application Settings` 中新增：

```
WEBSITE_LOAD_USER_PROFILE = 1
```

### 2. 手動安裝 Composer
在 Azure App Service 的 `Deployment Center` 中：

1. 選擇 `Local Git/FTPS credentials`
2. 使用 SSH 連接到 App Service
3. 執行以下命令：

```bash
# 下載 Composer
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer
chmod +x /usr/local/bin/composer

# 或使用 Homebrew
brew install composer
```

### 3. 使用 Kudu Console
1. 前往 `https://你的網域.scm.azurewebsites.net`
2. 選擇 `Debug Console` > `SSH`
3. 執行 Composer 安裝命令

### 4. 替代方案：手動下載套件
如果 Composer 仍然無法使用，可以：

1. 在本地環境下載套件
2. 上傳 `vendor` 資料夾到 Azure
3. 確保 `autoload.php` 正確載入

## 推薦的套件安裝順序

1. **基礎套件**：
   ```bash
   composer require guzzlehttp/guzzle
   composer require monolog/monolog
   composer require ramsey/uuid
   ```

2. **Vision API 套件**：
   ```bash
   composer require google/cloud-vision
   # 或
   composer require microsoft/azure-cognitiveservices-vision-face
   ```

3. **圖片處理套件**：
   ```bash
   composer require intervention/image
   ```

## 驗證安裝
建立測試檔案 `test_packages.php`：

```php
<?php
require_once 'vendor/autoload.php';

// 測試 Guzzle
$client = new \GuzzleHttp\Client();
echo "Guzzle 載入成功\n";

// 測試 UUID
$uuid = \Ramsey\Uuid\Uuid::uuid4();
echo "UUID 生成成功: " . $uuid . "\n";

// 測試 Monolog
$log = new \Monolog\Logger('test');
echo "Monolog 載入成功\n";
?>
```
