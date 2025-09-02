<?php
ini_set('display_errors', 1);
ini_set('memory_limit', '512M');
ini_set('max_execution_time', 300);

echo "<h1>🔄 重新生成分群結果</h1>";

// 載入必要的檔案
require_once 'azure_face_detection.php';

try {
    $detector = new AzureFaceDetection();
    
    echo "<p>正在執行人臉分群...</p>";
    
    // 執行人臉分群
    $groupOutput = $detector->groupFacesWithFixedScript();
    
    echo "<p>✅ 分群完成</p>";
    echo "<p>Python 輸出：</p>";
    echo "<pre>" . implode("\n", $groupOutput) . "</pre>";
    
    // 檢查新的分群結果
    $groupResultsPath = __DIR__ . '/group_results.json';
    if (file_exists($groupResultsPath)) {
        echo "<p>✅ group_results.json 已更新</p>";
        
        $groupData = json_decode(file_get_contents($groupResultsPath), true);
        if ($groupData) {
            echo "<p>群組數量: " . count($groupData) . "</p>";
            
            // 顯示前幾個群組
            $count = 0;
            foreach ($groupData as $groupName => $groupInfo) {
                if ($count >= 3) break;
                echo "<div style='border: 1px solid #ddd; padding: 10px; margin: 10px 0; border-radius: 5px;'>";
                echo "<h4>{$groupName}</h4>";
                if (isset($groupInfo['count'])) {
                    echo "<p>數量: {$groupInfo['count']}</p>";
                }
                if (isset($groupInfo['images']) && is_array($groupInfo['images'])) {
                    echo "<p>圖片: " . implode(', ', $groupInfo['images']) . "</p>";
                }
                echo "</div>";
                $count++;
            }
        } else {
            echo "<p style='color: red;'>❌ JSON 解析失敗</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ group_results.json 不存在</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ 錯誤: " . $e->getMessage() . "</p>";
}

echo "<h2>🔗 測試連結</h2>";
echo "<ul>";
echo "<li><a href='azure_face_dashboard.php'>返回人臉辨識儀表板</a></li>";
echo "<li><a href='test_group_results.php'>測試分群結果</a></li>";
echo "</ul>";
?> 