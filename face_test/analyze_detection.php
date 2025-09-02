<?php
ini_set('memory_limit', '2G');
ini_set('max_execution_time', 900);
ini_set('display_errors', 1);

echo "<h1>🔍 偵測方法分析</h1>";

// 檢查當前狀態
function analyzeCurrentDetection() {
    echo "<h2>📊 當前狀態分析</h2>";
    
    // 檢查 face_map.json
    $faceMapPath = __DIR__ . "/face_map.json";
    if (file_exists($faceMapPath)) {
        $faceMap = json_decode(file_get_contents($faceMapPath), true);
        echo "<p>✅ 找到 face_map.json，包含 " . count($faceMap) . " 張人臉</p>";
        
        // 分析人臉品質
        $sizes = [];
        foreach ($faceMap as $faceName => $faceInfo) {
            if (isset($faceInfo['local_path']) && file_exists($faceInfo['local_path'])) {
                $sizes[] = filesize($faceInfo['local_path']);
            }
        }
        
        if (!empty($sizes)) {
            $avgSize = array_sum($sizes) / count($sizes);
            $maxSize = max($sizes);
            $minSize = min($sizes);
            
            echo "<h3>人臉品質分析</h3>";
            echo "<ul>";
            echo "<li>總人臉數量: " . count($faceMap) . "</li>";
            echo "<li>平均檔案大小: " . round($avgSize / 1024, 2) . " KB</li>";
            echo "<li>最大檔案: " . round($maxSize / 1024, 2) . " KB</li>";
            echo "<li>最小檔案: " . round($minSize / 1024, 2) . " KB</li>";
            echo "</ul>";
        }
    } else {
        echo "<p style='color: orange;'>⚠️ 找不到 face_map.json</p>";
    }
    
    // 檢查網格資訊
    $gridFiles = glob(__DIR__ . "/grid_info_*.json");
    if (!empty($gridFiles)) {
        echo "<h3>⚠️ 發現網格處理檔案</h3>";
        echo "<p style='color: orange;'>使用了分割偵測方法，這可能影響分群效果</p>";
        foreach ($gridFiles as $gridFile) {
            $gridInfo = json_decode(file_get_contents($gridFile), true);
            echo "<div style='border: 1px solid #ffc107; margin: 10px 0; padding: 10px; background: #fff3cd;'>";
            echo "<strong>" . basename($gridFile) . "</strong><br>";
            echo "原始圖片: " . basename($gridInfo['original_image']) . "<br>";
            echo "網格大小: {$gridInfo['grid_size']}x{$gridInfo['grid_size']}<br>";
            echo "總網格數: {$gridInfo['total_grids']}<br>";
            echo "處理時間: {$gridInfo['processing_time']}<br>";
            echo "</div>";
        }
    } else {
        echo "<h3>✅ 沒有網格處理檔案</h3>";
        echo "<p style='color: green;'>使用了正常偵測方法</p>";
    }
    
    // 檢查分群結果
    $groupResultsPath = __DIR__ . "/group_results.json";
    if (file_exists($groupResultsPath)) {
        $groupResults = json_decode(file_get_contents($groupResultsPath), true);
        echo "<h3>📈 分群結果分析</h3>";
        
        $groupCount = count($groupResults);
        $groupSizes = [];
        
        foreach ($groupResults as $groupName => $groupInfo) {
            $faceCount = is_array($groupInfo) ? count($groupInfo) : 
                        (isset($groupInfo['images']) ? count($groupInfo['images']) : 0);
            $groupSizes[] = $faceCount;
        }
        
        if (!empty($groupSizes)) {
            $avgSize = array_sum($groupSizes) / count($groupSizes);
            $maxSize = max($groupSizes);
            $minSize = min($groupSizes);
            
            echo "<ul>";
            echo "<li>分群數量: " . $groupCount . "</li>";
            echo "<li>平均每群人臉數: " . round($avgSize, 2) . "</li>";
            echo "<li>最大群組: " . $maxSize . " 張人臉</li>";
            echo "<li>最小群組: " . $minSize . " 張人臉</li>";
            echo "</ul>";
            
            // 顯示分群詳情
            echo "<h3>分群詳情</h3>";
            foreach ($groupResults as $groupName => $groupInfo) {
                $faceCount = is_array($groupInfo) ? count($groupInfo) : 
                            (isset($groupInfo['images']) ? count($groupInfo['images']) : 0);
                echo "<div style='border: 1px solid #ddd; margin: 10px 0; padding: 10px;'>";
                echo "<strong>{$groupName}</strong> ({$faceCount} 張人臉)<br>";
                
                if (is_array($groupInfo) && count($groupInfo) > 0) {
                    echo "<div style='display: flex; flex-wrap: wrap; gap: 5px; margin-top: 10px;'>";
                    foreach (array_slice($groupInfo, 0, 5) as $face) { // 只顯示前5張
                        $imageUrl = is_array($face) ? $face['azure_url'] : $face;
                        echo "<img src='{$imageUrl}' style='width: 60px; height: 60px; object-fit: cover; border: 1px solid #ccc;' onerror='this.style.display=\"none\"'>";
                    }
                    if (count($groupInfo) > 5) {
                        echo "<div style='width: 60px; height: 60px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; border: 1px solid #ccc;'>+{$faceCount - 5}</div>";
                    }
                    echo "</div>";
                }
                echo "</div>";
            }
        }
    } else {
        echo "<p style='color: orange;'>⚠️ 找不到 group_results.json</p>";
    }
}

// 執行分析
analyzeCurrentDetection();

echo "<h2>🔍 關鍵差異分析</h2>";
echo "<div style='background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107;'>";
echo "<h3>正常偵測 vs 分割偵測的差異：</h3>";
echo "<table style='width: 100%; border-collapse: collapse;'>";
echo "<tr style='background: #f8f9fa;'>";
echo "<th style='border: 1px solid #ddd; padding: 8px;'>項目</th>";
echo "<th style='border: 1px solid #ddd; padding: 8px;'>正常偵測</th>";
echo "<th style='border: 1px solid #ddd; padding: 8px;'>分割偵測</th>";
echo "</tr>";
echo "<tr>";
echo "<td style='border: 1px solid #ddd; padding: 8px;'>人臉完整性</td>";
echo "<td style='border: 1px solid #ddd; padding: 8px; color: green;'>✅ 完整</td>";
echo "<td style='border: 1px solid #ddd; padding: 8px; color: red;'>❌ 可能被分割</td>";
echo "</tr>";
echo "<tr>";
echo "<td style='border: 1px solid #ddd; padding: 8px;'>座標精確度</td>";
echo "<td style='border: 1px solid #ddd; padding: 8px; color: green;'>✅ 精確</td>";
echo "<td style='border: 1px solid #ddd; padding: 8px; color: orange;'>⚠️ 需要調整</td>";
echo "</tr>";
echo "<tr>";
echo "<td style='border: 1px solid #ddd; padding: 8px;'>圖片品質</td>";
echo "<td style='border: 1px solid #ddd; padding: 8px; color: green;'>✅ 原始品質</td>";
echo "<td style='border: 1px solid #ddd; padding: 8px; color: orange;'>⚠️ 可能降低</td>";
echo "</tr>";
echo "<tr>";
echo "<td style='border: 1px solid #ddd; padding: 8px;'>重複消除</td>";
echo "<td style='border: 1px solid #ddd; padding: 8px; color: green;'>✅ 簡單有效</td>";
echo "<td style='border: 1px solid #ddd; padding: 8px; color: red;'>❌ 複雜易錯</td>";
echo "</tr>";
echo "<tr>";
echo "<td style='border: 1px solid #ddd; padding: 8px;'>分群效果</td>";
echo "<td style='border: 1px solid #ddd; padding: 8px; color: green;'>✅ 良好</td>";
echo "<td style='border: 1px solid #ddd; padding: 8px; color: red;'>❌ 不準確</td>";
echo "</tr>";
echo "</table>";
echo "</div>";

echo "<h2>🎯 問題根源</h2>";
echo "<div style='background: #f8d7da; padding: 15px; border-left: 4px solid #dc3545;'>";
echo "<h3>分割偵測的主要問題：</h3>";
echo "<ol>";
echo "<li><strong>人臉被分割</strong>：一個人臉可能被切割到兩個網格中，導致特徵不完整</li>";
echo "<li><strong>座標調整誤差</strong>：切割後的座標需要調整回原圖，容易產生誤差</li>";
echo "<li><strong>圖片品質下降</strong>：網格切割可能導致圖片壓縮或品質損失</li>";
echo "<li><strong>重複消除困難</strong>：同一個人的不同角度可能被誤判為重複</li>";
echo "<li><strong>特徵提取受損</strong>：不完整的人臉影響 insightface 的特徵提取</li>";
echo "</ol>";
echo "</div>";

echo "<h2>💡 解決方案</h2>";
echo "<div style='background: #d1ecf1; padding: 15px; border-left: 4px solid #17a2b8;'>";
echo "<h3>改進方案：</h3>";
echo "<ol>";
echo "<li><strong>避免切割</strong>：使用多次偵測而不是圖片切割</li>";
echo "<li><strong>調整圖片大小</strong>：將大圖片縮小到合適尺寸</li>";
echo "<li><strong>圖片增強</strong>：使用對比度、亮度調整等技術</li>";
echo "<li><strong>更寬鬆的重複消除</strong>：提高 IOU 閾值，避免誤刪</li>";
echo "<li><strong>優化特徵提取</strong>：確保人臉完整性和品質</li>";
echo "</ol>";
echo "</div>";

echo "<h2>🔧 技術細節</h2>";
echo "<div style='background: #e9ecef; padding: 15px; border-left: 4px solid #6c757d;'>";
echo "<h3>兩種偵測方法的技術差異：</h3>";
echo "<h4>1. 正常偵測 (extractFacesFromImage)</h4>";
echo "<ul>";
echo "<li>輸入：Google Vision API FaceAnnotations</li>";
echo "<li>座標處理：直接使用原始座標</li>";
echo "<li>重複消除：removeDuplicateFaces() 使用 bounding box</li>";
echo "<li>IOU 閾值：0.425</li>";
echo "<li>優點：座標精確，人臉完整</li>";
echo "</ul>";

echo "<h4>2. 分割偵測 (extractFacesFromList)</h4>";
echo "<ul>";
echo "<li>輸入：調整後的頂點陣列</li>";
echo "<li>座標處理：需要從網格座標調整回原圖座標</li>";
echo "<li>重複消除：removeDuplicateFacesFromList() 使用頂點陣列</li>";
echo "<li>IOU 閾值：0.5</li>";
echo "<li>缺點：座標誤差，人臉可能被分割</li>";
echo "</ul>";
echo "</div>";
?>

