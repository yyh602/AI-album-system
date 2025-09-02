<?php
/**
 * Azure 部署檢查腳本
 * 診斷 404 錯誤的原因
 */

// 設定錯誤報告
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>\n";
echo "<html lang='zh-TW'>\n";
echo "<head>\n";
echo "<meta charset='UTF-8'>\n";
echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>\n";
echo "<title>Azure 部署檢查</title>\n";
echo "<style>\n";
echo "body { font-family: 'Microsoft JhengHei', Arial, sans-serif; margin: 20px; background: #f8f9fa; }\n";
echo ".container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }\n";
echo ".header { text-align: center; margin-bottom: 30px; padding: 20px; background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%); color: white; border-radius: 10px; }\n";
echo ".check-section { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #dc3545; }\n";
echo ".success { border-left-color: #28a745; background: #d4edda; }\n";
echo ".warning { border-left-color: #ffc107; background: #fff3cd; }\n";
echo ".error { border-left-color: #dc3545; background: #f8d7da; }\n";
echo ".info { border-left-color: #17a2b8; background: #d1ecf1; }\n";
echo ".code-block { background: #f8f9fa; padding: 15px; border-radius: 5px; border-left: 4px solid #6c757d; margin: 10px 0; font-family: monospace; }\n";
echo ".btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin: 5px; transition: background 0.3s; }\n";
echo ".btn:hover { background: #0056b3; }\n";
echo ".btn-success { background: #28a745; }\n";
echo ".btn-warning { background: #ffc107; color: #212529; }\n";
echo ".btn-danger { background: #dc3545; }\n";
echo "</style>\n";
echo "</head>\n";
echo "<body>\n";

echo "<div class='container'>\n";
echo "<div class='header'>\n";
echo "<h1>🔍 Azure 部署檢查</h1>\n";
echo "<p>診斷 404 錯誤的原因</p>\n";
echo "</div>\n";

// 檢查 1: 基本環境
echo "<div class='check-section'>\n";
echo "<h2>🌐 基本環境檢查</h2>\n";

echo "<div class='info'>\n";
echo "<h3>伺服器資訊：</h3>\n";
echo "<p><strong>伺服器軟體：</strong> " . ($_SERVER['SERVER_SOFTWARE'] ?? '未知') . "</p>\n";
echo "<p><strong>PHP 版本：</strong> " . phpversion() . "</p>\n";
echo "<p><strong>當前時間：</strong> " . date('Y-m-d H:i:s') . "</p>\n";
echo "<p><strong>時區：</strong> " . date_default_timezone_get() . "</p>\n";
echo "</div>\n";

echo "<div class='info'>\n";
echo "<h3>請求資訊：</h3>\n";
echo "<p><strong>請求 URL：</strong> " . ($_SERVER['REQUEST_URI'] ?? '未知') . "</p>\n";
echo "<p><strong>請求方法：</strong> " . ($_SERVER['REQUEST_METHOD'] ?? '未知') . "</p>\n";
echo "<p><strong>主機：</strong> " . ($_SERVER['HTTP_HOST'] ?? '未知') . "</p>\n";
echo "</div>\n";
echo "</div>\n";

// 檢查 2: 檔案存在性
echo "<div class='check-section'>\n";
echo "<h2>📁 檔案存在性檢查</h2>\n";

$criticalFiles = [
    'azure_face_dashboard.php',
    'azure_face_detection.php',
    'quick_start.php',
    'web.config',
    '.htaccess'
];

foreach ($criticalFiles as $file) {
    if (file_exists($file)) {
        $size = filesize($file);
        $modified = date('Y-m-d H:i:s', filemtime($file));
        echo "<div class='success'>\n";
        echo "<p><strong>✅ {$file}</strong> - 大小: {$size} bytes, 修改時間: {$modified}</p>\n";
        echo "</div>\n";
    } else {
        echo "<div class='error'>\n";
        echo "<p><strong>❌ {$file}</strong> - 檔案不存在！</p>\n";
        echo "</div>\n";
    }
}
echo "</div>\n";

// 檢查 3: 目錄權限
echo "<div class='check-section'>\n";
echo "<h2>🔐 目錄權限檢查</h2>\n";

$directories = [
    'faces',
    'group',
    'uploads',
    'subimages'
];

foreach ($directories as $dir) {
    if (is_dir($dir)) {
        $writable = is_writable($dir) ? '可寫入' : '不可寫入';
        $readable = is_readable($dir) ? '可讀取' : '不可讀取';
        echo "<div class='info'>\n";
        echo "<p><strong>📁 {$dir}</strong> - 讀取: {$readable}, 寫入: {$writable}</p>\n";
        echo "</div>\n";
    } else {
        echo "<div class='warning'>\n";
        echo "<p><strong>⚠️ {$dir}</strong> - 目錄不存在</p>\n";
        echo "</div>\n";
    }
}
echo "</div>\n";

// 檢查 4: PHP 擴展
echo "<div class='check-section'>\n";
echo "<h2>🔧 PHP 擴展檢查</h2>\n";

$requiredExtensions = [
    'gd' => '圖片處理',
    'curl' => 'HTTP 請求',
    'exif' => 'EXIF 資料',
    'json' => 'JSON 處理',
    'mbstring' => '多字節字串'
];

foreach ($requiredExtensions as $ext => $description) {
    if (extension_loaded($ext)) {
        echo "<div class='success'>\n";
        echo "<p><strong>✅ {$ext}</strong> - {$description}</p>\n";
        echo "</div>\n";
    } else {
        echo "<div class='error'>\n";
        echo "<p><strong>❌ {$ext}</strong> - {$description} (未安裝)</p>\n";
        echo "</div>\n";
    }
}
echo "</div>\n";

// 檢查 5: 路徑問題
echo "<div class='check-section'>\n";
echo "<h2>🛣️ 路徑問題檢查</h2>\n";

$currentPath = __DIR__;
$documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '未知';
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '未知';

echo "<div class='info'>\n";
echo "<h3>路徑資訊：</h3>\n";
echo "<p><strong>當前目錄：</strong> {$currentPath}</p>\n";
echo "<p><strong>文件根目錄：</strong> {$documentRoot}</p>\n";
echo "<p><strong>腳本名稱：</strong> {$scriptName}</p>\n";
echo "</div>\n";

// 檢查相對路徑
$relativePath = str_replace($documentRoot, '', $currentPath);
echo "<div class='info'>\n";
echo "<p><strong>相對路徑：</strong> {$relativePath}</p>\n";
echo "</div>\n";
echo "</div>\n";

// 檢查 6: Azure 特定配置
echo "<div class='check-section'>\n";
echo "<h2>☁️ Azure 特定配置檢查</h2>\n";

// 檢查環境變數
$azureVars = [
    'WEBSITE_SITE_NAME',
    'WEBSITE_INSTANCE_ID',
    'WEBSITE_OWNER_NAME',
    'WEBSITE_RESOURCE_GROUP'
];

echo "<div class='info'>\n";
echo "<h3>Azure 環境變數：</h3>\n";
foreach ($azureVars as $var) {
    $value = getenv($var);
    if ($value) {
        echo "<p><strong>✅ {$var}：</strong> {$value}</p>\n";
    } else {
        echo "<p><strong>❌ {$var}：</strong> 未設定</p>\n";
    }
}
echo "</div>\n";

// 檢查 web.config
if (file_exists('web.config')) {
    $webConfig = file_get_contents('web.config');
    if (strpos($webConfig, 'PHP-FastCGI') !== false) {
        echo "<div class='success'>\n";
        echo "<p><strong>✅ web.config</strong> - 包含 PHP 處理器配置</p>\n";
        echo "</div>\n";
    } else {
        echo "<div class='warning'>\n";
        echo "<p><strong>⚠️ web.config</strong> - 缺少 PHP 處理器配置</p>\n";
        echo "</div>\n";
    }
} else {
    echo "<div class='error'>\n";
    echo "<p><strong>❌ web.config</strong> - 檔案不存在</p>\n";
    echo "</div>\n";
}
echo "</div>\n";

// 解決方案建議
echo "<div class='check-section'>\n";
echo "<h2>💡 解決方案建議</h2>\n";

echo "<div class='info'>\n";
echo "<h3>立即解決方案：</h3>\n";
echo "<ol>\n";
echo "<li><strong>重新部署：</strong> 將修正後的 web.config 重新部署到 Azure</li>\n";
echo "<li><strong>檢查路徑：</strong> 確認 Azure 上的檔案路徑是否正確</li>\n";
echo "<li><strong>重啟服務：</strong> 在 Azure 門戶中重啟 App Service</li>\n";
echo "<li><strong>檢查日誌：</strong> 查看 Azure 的應用程式日誌</li>\n";
echo "</ol>\n";
echo "</div>\n";

echo "<div class='warning'>\n";
echo "<h3>常見問題：</h3>\n";
echo "<ul>\n";
echo "<li><strong>路徑大小寫：</strong> Azure 對路徑大小寫敏感</li>\n";
echo "<li><strong>檔案權限：</strong> 確保檔案有正確的讀取權限</li>\n";
echo "<li><strong>PHP 版本：</strong> 確認 Azure 支援您的 PHP 版本</li>\n";
echo "<li><strong>依賴檔案：</strong> 確保所有必要的依賴檔案都已上傳</li>\n";
echo "</ul>\n";
echo "</div>\n";
echo "</div>\n";

// 操作按鈕
echo "<div style='text-align: center; margin: 20px 0;'>\n";
echo "<a href='quick_start.php' class='btn btn-success'>🏠 返回主頁</a>\n";
echo "<a href='azure_face_dashboard.php' class='btn btn-warning'>📊 測試儀表板</a>\n";
echo "<a href='test_simple_margin.php' class='btn'>✂️ 測試邊框邏輯</a>\n";
echo "</div>\n";

echo "</div>\n";
echo "</body>\n";
echo "</html>\n";
?> 