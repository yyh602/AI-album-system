<?php
/**
 * 簡單的入口點
 * 用於測試 Azure 部署
 */

// 設定錯誤報告
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>\n";
echo "<html lang='zh-TW'>\n";
echo "<head>\n";
echo "<meta charset='UTF-8'>\n";
echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>\n";
echo "<title>Azure 部署測試</title>\n";
echo "<style>\n";
echo "body { font-family: 'Microsoft JhengHei', Arial, sans-serif; margin: 20px; background: #f8f9fa; }\n";
echo ".container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }\n";
echo ".header { text-align: center; margin-bottom: 30px; padding: 20px; background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; border-radius: 10px; }\n";
echo ".btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin: 5px; transition: background 0.3s; }\n";
echo ".btn:hover { background: #0056b3; }\n";
echo ".btn-success { background: #28a745; }\n";
echo ".btn-warning { background: #ffc107; color: #212529; }\n";
echo ".status { padding: 15px; border-radius: 8px; margin: 15px 0; }\n";
echo ".success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }\n";
echo ".info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }\n";
echo "</style>\n";
echo "</head>\n";
echo "<body>\n";

echo "<div class='container'>\n";
echo "<div class='header'>\n";
echo "<h1>✅ Azure 部署成功！</h1>\n";
echo "<p>PHP 環境正常運行</p>\n";
echo "</div>\n";

echo "<div class='status success'>\n";
echo "<h3>🎉 恭喜！</h3>\n";
echo "<p>您的 Azure 部署已經成功！PHP 檔案現在可以正常訪問了。</p>\n";
echo "</div>\n";

echo "<div class='status info'>\n";
echo "<h3>🔍 下一步測試：</h3>\n";
echo "<p>現在可以測試其他 PHP 檔案了：</p>\n";
echo "</div>\n";

echo "<div style='text-align: center; margin: 20px 0;'>\n";
echo "<a href='azure_deployment_check.php' class='btn btn-success'>🔍 完整系統檢查</a>\n";
echo "<a href='azure_face_dashboard.php' class='btn btn-warning'>📊 人臉偵測儀表板</a>\n";
echo "<a href='test_simple_margin.php' class='btn'>✂️ 邊框邏輯測試</a>\n";
echo "</div>\n";

echo "<div class='status info'>\n";
echo "<h3>📋 系統資訊：</h3>\n";
echo "<p><strong>PHP 版本：</strong> " . phpversion() . "</p>\n";
echo "<p><strong>伺服器：</strong> " . ($_SERVER['SERVER_SOFTWARE'] ?? '未知') . "</p>\n";
echo "<p><strong>當前時間：</strong> " . date('Y-m-d H:i:s') . "</p>\n";
echo "<p><strong>時區：</strong> " . date_default_timezone_get() . "</p>\n";
echo "</div>\n";

echo "</div>\n";
echo "</body>\n";
echo "</html>\n";
?> 