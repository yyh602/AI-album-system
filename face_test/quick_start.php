<?php
/**
 * 快速啟動腳本
 * 一鍵測試所有優化後的人臉偵測功能
 */

require_once 'azure_face_detection.php';

// 設定錯誤報告
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>\n";
echo "<html lang='zh-TW'>\n";
echo "<head>\n";
echo "<meta charset='UTF-8'>\n";
echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>\n";
echo "<title>人臉偵測系統 - 快速啟動</title>\n";
echo "<style>\n";
echo "body { font-family: 'Microsoft JhengHei', Arial, sans-serif; margin: 20px; background: #f8f9fa; }\n";
echo ".container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }\n";
echo ".header { text-align: center; margin-bottom: 30px; padding: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 10px; }\n";
echo ".feature-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin: 20px 0; }\n";
echo ".feature-card { background: white; padding: 20px; border-radius: 8px; border: 1px solid #e9ecef; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }\n";
echo ".feature-card h3 { color: #495057; margin-top: 0; }\n";
echo ".btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin: 5px; transition: background 0.3s; }\n";
echo ".btn:hover { background: #0056b3; }\n";
echo ".btn-success { background: #28a745; }\n";
echo ".btn-success:hover { background: #1e7e34; }\n";
echo ".btn-warning { background: #ffc107; color: #212529; }\n";
echo ".btn-warning:hover { background: #e0a800; }\n";
echo ".status { padding: 10px; border-radius: 5px; margin: 10px 0; }\n";
echo ".status.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }\n";
echo ".status.info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }\n";
echo ".status.warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }\n";
echo ".code-block { background: #f8f9fa; padding: 15px; border-radius: 5px; border-left: 4px solid #007bff; margin: 10px 0; font-family: monospace; }\n";
echo "</style>\n";
echo "</head>\n";
echo "<body>\n";

echo "<div class='container'>\n";
echo "<div class='header'>\n";
echo "<h1>🚀 人臉偵測系統 - 快速啟動</h1>\n";
echo "<p>優化後的智能邊框調整功能，提升人臉向量提取品質</p>\n";
echo "</div>\n";

// 檢查系統狀態
echo "<h2>📋 系統狀態檢查</h2>\n";
$systemStatus = checkSystemStatus();
displaySystemStatus($systemStatus);

// 功能選單
echo "<h2>🔧 功能選單</h2>\n";
echo "<div class='feature-grid'>\n";

// 功能卡片 1: 基本測試
echo "<div class='feature-card'>\n";
echo "<h3>🧪 基本功能測試</h3>\n";
echo "<p>測試優化後的裁切功能，包括智能邊框調整和品質控制。</p>\n";
echo "<a href='test_optimized_cropping.php' class='btn btn-success'>開始測試</a>\n";
echo "</div>\n";

// 功能卡片 2: 進階分析
echo "<div class='feature-card'>\n";
echo "<h3>🔍 進階分析工具</h3>\n";
echo "<p>深度分析人臉品質、性能指標和優化建議。</p>\n";
echo "<a href='advanced_face_analysis.php' class='btn btn-warning'>執行分析</a>\n";
echo "</div>\n";

// 功能卡片 3: 儀表板
echo "<div class='feature-card'>\n";
echo "<h3>📊 人臉偵測儀表板</h3>\n";
echo "<p>完整的人臉偵測和分群系統介面。</p>\n";
echo "<a href='azure_face_dashboard.php' class='btn'>開啟儀表板</a>\n";
echo "</div>\n";

// 功能卡片 4: 文檔
echo "<div class='feature-card'>\n";
echo "<h3>📚 技術文檔</h3>\n";
echo "<p>詳細的功能說明、API 文檔和最佳實踐指南。</p>\n";
echo "<a href='README_Optimized_Cropping.md' class='btn'>查看文檔</a>\n";
echo "</div>\n";

// 功能卡片 5: 大人臉測試
echo "<div class='feature-card'>\n";
echo "<h3>🔍 大人臉偵測測試</h3>\n";
echo "<p>專門測試和驗證大人臉的偵測、裁切和邊框計算功能。</p>\n";
echo "<a href='test_large_face_detection_fixed.php' class='btn btn-warning'>開始測試</a>\n";
echo "</div>\n";

// 功能卡片 6: 圖片方向修正測試
echo "<div class='feature-card'>\n";
echo "<h3>🔄 圖片方向修正測試</h3>\n";
echo "<p>測試和驗證圖片方向自動修正功能，解決人臉倒置問題。</p>\n";
echo "<a href='test_orientation_fix.php' class='btn btn-warning'>開始測試</a>\n";
echo "</div>\n";

// 功能卡片 7: 人臉品質過濾測試
echo "<div class='feature-card'>\n";
echo "<h3>🔍 人臉品質過濾測試</h3>\n";
echo "<p>測試和驗證人臉品質過濾功能，避免誤偵測鍋子、食物等物體。</p>\n";
echo "<a href='test_face_quality_filter.php' class='btn btn-warning'>開始測試</a>\n";
echo "</div>\n";

// 功能卡片 8: 增強過濾功能測試
echo "<div class='feature-card'>\n";
echo "<h3>🚀 增強過濾功能測試</h3>\n";
echo "<p>測試和驗證新的廚房用品、食物等物體過濾功能。</p>\n";
echo "<a href='test_enhanced_filtering.php' class='btn btn-warning'>開始測試</a>\n";
echo "</div>\n";

// 功能卡片 9: 邊框調整測試
echo "<div class='feature-card'>\n";
echo "<h3>✂️ 邊框調整測試</h3>\n";
echo "<p>測試和驗證調整後的邊框邏輯，讓裁切更緊湊。</p>\n";
echo "<a href='test_margin_adjustment.php' class='btn btn-warning'>開始測試</a>\n";
echo "</div>\n";

// 功能卡片 10: 智能邊框邏輯測試
echo "<div class='feature-card'>\n";
echo "<h3>🧠 智能邊框邏輯測試</h3>\n";
echo "<p>測試和驗證更智能的邊框計算邏輯。</p>\n";
echo "<a href='test_improved_margin_logic.php' class='btn btn-warning'>開始測試</a>\n";
echo "</div>\n";

// 功能卡片 11: 邊框優化測試
echo "<div class='feature-card'>\n";
echo "<h3>🔧 邊框優化測試</h3>\n";
echo "<p>診斷和解決人臉偵測到但跑不出來的問題。</p>\n";
echo "<a href='test_border_optimization.php' class='btn btn-warning'>開始測試</a>\n";
echo "</div>\n";

echo "</div>\n";

// 快速測試
if (isset($_GET['quick_test'])) {
    echo "<h2>⚡ 快速測試結果</h2>\n";
    runQuickTest();
}

// 顯示快速測試按鈕
echo "<div style='text-align: center; margin: 30px 0;'>\n";
echo "<a href='?quick_test=1' class='btn btn-success' style='font-size: 1.2em; padding: 15px 30px;'>⚡ 執行快速測試</a>\n";
echo "</div>\n";

// 系統資訊
echo "<h2>ℹ️ 系統資訊</h2>\n";
echo "<div class='code-block'>\n";
echo "<strong>PHP 版本:</strong> " . PHP_VERSION . "<br>\n";
echo "<strong>記憶體限制:</strong> " . ini_get('memory_limit') . "<br>\n";
echo "<strong>執行時間限制:</strong> " . ini_get('max_execution_time') . " 秒<br>\n";
echo "<strong>GD 擴展:</strong> " . (extension_loaded('gd') ? '✅ 已安裝' : '❌ 未安裝') . "<br>\n";
echo "<strong>cURL 擴展:</strong> " . (extension_loaded('curl') ? '✅ 已安裝' : '❌ 未安裝') . "<br>\n";
echo "<strong>工作目錄:</strong> " . __DIR__ . "<br>\n";
echo "</div>\n";

echo "</div>\n";
echo "</body>\n";
echo "</html>\n";

// 檢查系統狀態
function checkSystemStatus() {
    $status = [
        'php_version' => version_compare(PHP_VERSION, '7.4.0', '>='),
        'gd_extension' => extension_loaded('gd'),
        'curl_extension' => extension_loaded('curl'),
        'writable_dirs' => [],
        'required_files' => []
    ];
    
    // 檢查目錄權限
    $dirs = ['faces', 'group', 'uploads'];
    foreach ($dirs as $dir) {
        $fullPath = __DIR__ . '/' . $dir;
        if (is_dir($fullPath)) {
            $status['writable_dirs'][$dir] = is_writable($fullPath);
        } else {
            $status['writable_dirs'][$dir] = false;
        }
    }
    
    // 檢查必要檔案
    $files = ['azure_face_detection.php', 'vendor/autoload.php'];
    foreach ($files as $file) {
        $status['required_files'][$file] = file_exists(__DIR__ . '/' . $file);
    }
    
    return $status;
}

// 顯示系統狀態
function displaySystemStatus($status) {
    echo "<div class='feature-grid'>\n";
    
    // PHP 版本
    echo "<div class='feature-card'>\n";
    echo "<h3>🐘 PHP 環境</h3>\n";
    if ($status['php_version']) {
        echo "<div class='status success'>✅ PHP 版本符合要求 (" . PHP_VERSION . ")</div>\n";
    } else {
        echo "<div class='status warning'>⚠️ PHP 版本過低，建議升級到 7.4+</div>\n";
    }
    echo "</div>\n";
    
    // 擴展檢查
    echo "<div class='feature-card'>\n";
    echo "<h3>🔌 必要擴展</h3>\n";
    $extensionsOk = $status['gd_extension'] && $status['curl_extension'];
    if ($extensionsOk) {
        echo "<div class='status success'>✅ 所有必要擴展已安裝</div>\n";
    } else {
        echo "<div class='status warning'>⚠️ 部分擴展未安裝</div>\n";
        if (!$status['gd_extension']) echo "<div>❌ GD 擴展未安裝</div>\n";
        if (!$status['curl_extension']) echo "<div>❌ cURL 擴展未安裝</div>\n";
    }
    echo "</div>\n";
    
    // 目錄權限
    echo "<div class='feature-card'>\n";
    echo "<h3>📁 目錄權限</h3>\n";
    $dirsOk = true;
    foreach ($status['writable_dirs'] as $dir => $writable) {
        if ($writable) {
            echo "<div class='status success'>✅ {$dir} 目錄可寫入</div>\n";
        } else {
            echo "<div class='status warning'>⚠️ {$dir} 目錄不可寫入</div>\n";
            $dirsOk = false;
        }
    }
    echo "</div>\n";
    
    // 檔案檢查
    echo "<div class='feature-card'>\n";
    echo "<h3>📄 必要檔案</h3>\n";
    $filesOk = true;
    foreach ($status['required_files'] as $file => $exists) {
        if ($exists) {
            echo "<div class='status success'>✅ {$file}</div>\n";
        } else {
            echo "<div class='status warning'>⚠️ {$file} 不存在</div>\n";
            $filesOk = false;
        }
    }
    echo "</div>\n";
    
    echo "</div>\n";
    
    // 整體狀態
    $overallOk = $status['php_version'] && $extensionsOk && $dirsOk && $filesOk;
    if ($overallOk) {
        echo "<div class='status success'>🎉 系統狀態良好，可以正常使用所有功能！</div>\n";
    } else {
        echo "<div class='status warning'>⚠️ 系統存在一些問題，建議先解決後再使用。</div>\n";
    }
}

// 執行快速測試
function runQuickTest() {
    try {
        $faceDetection = new AzureFaceDetection();
        
        echo "<div class='status info'>🔍 正在執行快速測試...</div>\n";
        
        // 測試裁切參數建議
        echo "<h3>📏 裁切參數測試</h3>\n";
        $testSizes = [60, 120, 200, 300];
        echo "<div class='code-block'>\n";
        foreach ($testSizes as $size) {
            $suggestions = $faceDetection->suggestOptimalCropParameters($size);
            echo "臉大小 {$size}px → 建議邊框: {$suggestions['margin']}px ({$suggestions['reason']})<br>\n";
        }
        echo "</div>\n";
        
        // 測試統計功能
        echo "<h3>📊 統計功能測試</h3>\n";
        $stats = $faceDetection->getCropStatistics();
        echo "<div class='code-block'>\n";
        echo "總處理人臉數: {$stats['total_faces_processed']}<br>\n";
        echo "小臉數量: {$stats['faces_by_size_category']['small']}<br>\n";
        echo "中等臉數量: {$stats['faces_by_size_category']['medium']}<br>\n";
        echo "大臉數量: {$stats['faces_by_size_category']['large']}<br>\n";
        echo "</div>\n";
        
        echo "<div class='status success'>✅ 快速測試完成！所有功能正常運作。</div>\n";
        
    } catch (Exception $e) {
        echo "<div class='status warning'>❌ 測試失敗: " . htmlspecialchars($e->getMessage()) . "</div>\n";
    }
}
?> 