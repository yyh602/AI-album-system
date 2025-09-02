<?php
/**
 * 進階人臉分析工具
 * 包含品質評估、性能監控和詳細報告
 */

require_once 'azure_face_detection.php';

// 設定錯誤報告
error_reporting(E_ALL);
ini_set('display_errors', 1);

class AdvancedFaceAnalyzer {
    private $faceDetection;
    private $analysisResults;
    
    public function __construct() {
        $this->faceDetection = new AzureFaceDetection();
        $this->analysisResults = [];
    }
    
    // 執行完整分析
    public function runFullAnalysis($imageUrls = []) {
        echo "<h1>🔍 進階人臉分析報告</h1>\n";
        echo "<p><em>分析時間：" . date('Y-m-d H:i:s') . "</em></p>\n";
        
        // 開始性能監控
        $this->faceDetection->startPerformanceMonitoring();
        
        try {
            // 1. 性能分析
            $this->analyzePerformance();
            
            // 2. 品質評估
            $this->analyzeQuality();
            
            // 3. 裁切優化建議
            $this->analyzeCropOptimization();
            
            // 4. 生成綜合報告
            $this->generateComprehensiveReport();
            
        } catch (Exception $e) {
            echo "<h2>❌ 分析錯誤</h2>\n";
            echo "<p style='color: red;'>" . htmlspecialchars($e->getMessage()) . "</p>\n";
        }
    }
    
    // 性能分析
    private function analyzePerformance() {
        echo "<h2>📊 性能分析</h2>\n";
        
        $metrics = $this->faceDetection->getPerformanceMetrics();
        
        echo "<div style='background: #f5f5f5; padding: 15px; border-radius: 8px; margin: 10px 0;'>\n";
        echo "<h3>系統性能指標</h3>\n";
        echo "<ul>\n";
        echo "<li><strong>記憶體使用:</strong> " . $this->formatBytes($metrics['memory_usage']) . "</li>\n";
        echo "<li><strong>峰值記憶體:</strong> " . $this->formatBytes($metrics['peak_memory']) . "</li>\n";
        echo "<li><strong>處理效率:</strong> " . $metrics['crop_efficiency'] . " 個人臉</li>\n";
        if ($metrics['processing_time'] > 0) {
            echo "<li><strong>處理時間:</strong> " . number_format($metrics['processing_time'], 2) . " 秒</li>\n";
        }
        echo "</ul>\n";
        echo "</div>\n";
        
        $this->analysisResults['performance'] = $metrics;
    }
    
    // 品質評估
    private function analyzeQuality() {
        echo "<h2>🎯 品質評估</h2>\n";
        
        $report = $this->faceDetection->generateQualityReport();
        $qualityAssessment = $report['quality_assessment'];
        
        if (empty($qualityAssessment)) {
            echo "<p>⚠️ 沒有找到人臉圖片進行品質評估</p>\n";
            return;
        }
        
        // 計算統計數據
        $qualityScores = array_column($qualityAssessment, 'quality_score');
        $avgQuality = array_sum($qualityScores) / count($qualityScores);
        $minQuality = min($qualityScores);
        $maxQuality = max($qualityScores);
        
        echo "<div style='background: #e8f5e8; padding: 15px; border-radius: 8px; margin: 10px 0;'>\n";
        echo "<h3>品質統計</h3>\n";
        echo "<ul>\n";
        echo "<li><strong>平均品質分數:</strong> " . number_format($avgQuality, 1) . "/100</li>\n";
        echo "<li><strong>最高品質:</strong> " . $maxQuality . "/100</li>\n";
        echo "<li><strong>最低品質:</strong> " . $minQuality . "/100</li>\n";
        echo "<li><strong>總評估數量:</strong> " . count($qualityAssessment) . "</li>\n";
        echo "</ul>\n";
        echo "</div>\n";
        
        // 顯示品質分佈
        $this->displayQualityDistribution($qualityScores);
        
        // 顯示問題分析
        $this->displayQualityIssues($qualityAssessment);
        
        $this->analysisResults['quality'] = $report;
    }
    
    // 顯示品質分佈
    private function displayQualityDistribution($qualityScores) {
        echo "<h3>📈 品質分佈</h3>\n";
        
        $distribution = [
            '優秀 (90-100)' => 0,
            '良好 (80-89)' => 0,
            '一般 (70-79)' => 0,
            '較差 (60-69)' => 0,
            '差 (0-59)' => 0
        ];
        
        foreach ($qualityScores as $score) {
            if ($score >= 90) $distribution['優秀 (90-100)']++;
            elseif ($score >= 80) $distribution['良好 (80-89)']++;
            elseif ($score >= 70) $distribution['一般 (70-79)']++;
            elseif ($score >= 60) $distribution['較差 (60-69)']++;
            else $distribution['差 (0-59)']++;
        }
        
        echo "<div style='display: flex; flex-wrap: wrap; gap: 10px; margin: 10px 0;'>\n";
        foreach ($distribution as $range => $count) {
            $percentage = count($qualityScores) > 0 ? ($count / count($qualityScores)) * 100 : 0;
            $color = $this->getQualityColor($range);
            
            echo "<div style='background: {$color}; padding: 10px; border-radius: 5px; min-width: 120px; text-align: center;'>\n";
            echo "<div style='font-weight: bold;'>{$range}</div>\n";
            echo "<div style='font-size: 1.2em;'>{$count}</div>\n";
            echo "<div style='font-size: 0.9em; color: #666;'>" . number_format($percentage, 1) . "%</div>\n";
            echo "</div>\n";
        }
        echo "</div>\n";
    }
    
    // 顯示品質問題
    private function displayQualityIssues($qualityAssessment) {
        echo "<h3>⚠️ 品質問題分析</h3>\n";
        
        $allIssues = [];
        foreach ($qualityAssessment as $assessment) {
            foreach ($assessment['issues'] as $issue) {
                if (!isset($allIssues[$issue])) {
                    $allIssues[$issue] = 0;
                }
                $allIssues[$issue]++;
            }
        }
        
        if (empty($allIssues)) {
            echo "<p style='color: green;'>✅ 沒有發現品質問題</p>\n";
            return;
        }
        
        echo "<ul>\n";
        foreach ($allIssues as $issue => $count) {
            echo "<li><strong>{$issue}:</strong> 影響 {$count} 個檔案</li>\n";
        }
        echo "</ul>\n";
    }
    
    // 裁切優化建議
    private function analyzeCropOptimization() {
        echo "<h2>🔧 裁切優化建議</h2>\n";
        
        $report = $this->faceDetection->generateQualityReport();
        $recommendations = $report['recommendations'];
        
        if (empty($recommendations)) {
            echo "<p style='color: green;'>✅ 系統運行良好，無需特別優化</p>\n";
            return;
        }
        
        echo "<div style='background: #fff3cd; padding: 15px; border-radius: 8px; margin: 10px 0; border-left: 4px solid #ffc107;'>\n";
        echo "<h3>💡 改進建議</h3>\n";
        echo "<ul>\n";
        foreach ($recommendations as $recommendation) {
            echo "<li>{$recommendation}</li>\n";
        }
        echo "</ul>\n";
        echo "</div>\n";
        
        // 顯示參數建議
        $this->displayParameterSuggestions();
    }
    
    // 顯示參數建議
    private function displayParameterSuggestions() {
        echo "<h3>⚙️ 參數調整建議</h3>\n";
        
        $testSizes = [60, 100, 150, 200, 280];
        
        echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>\n";
        echo "<tr style='background: #f8f9fa;'>\n";
        echo "<th>臉大小 (px)</th><th>建議邊框</th><th>適用場景</th><th>品質提示</th>\n";
        echo "</tr>\n";
        
        foreach ($testSizes as $size) {
            $suggestions = $this->faceDetection->suggestOptimalCropParameters($size);
            
            echo "<tr>\n";
            echo "<td>{$size}</td>\n";
            echo "<td>{$suggestions['margin']}</td>\n";
            echo "<td>{$suggestions['reason']}</td>\n";
            echo "<td>{$suggestions['quality_tip']}</td>\n";
            echo "</tr>\n";
        }
        
        echo "</table>\n";
    }
    
    // 生成綜合報告
    private function generateComprehensiveReport() {
        echo "<h2>📋 綜合分析報告</h2>\n";
        
        $report = $this->faceDetection->generateQualityReport();
        
        echo "<div style='background: #e3f2fd; padding: 15px; border-radius: 8px; margin: 10px 0;'>\n";
        echo "<h3>📊 系統狀態摘要</h3>\n";
        echo "<ul>\n";
        echo "<li><strong>分析時間:</strong> " . $report['timestamp'] . "</li>\n";
        echo "<li><strong>總處理人臉:</strong> " . $report['crop_statistics']['total_faces_processed'] . "</li>\n";
        echo "<li><strong>記憶體使用:</strong> " . $this->formatBytes($report['performance_metrics']['memory_usage']) . "</li>\n";
        echo "<li><strong>品質評估數量:</strong> " . count($report['quality_assessment']) . "</li>\n";
        echo "<li><strong>改進建議數量:</strong> " . count($report['recommendations']) . "</li>\n";
        echo "</ul>\n";
        echo "</div>\n";
        
        // 保存報告
        $this->saveReport($report);
    }
    
    // 保存報告
    private function saveReport($report) {
        $reportFile = __DIR__ . '/face_analysis_report_' . date('Y-m-d_H-i-s') . '.json';
        file_put_contents($reportFile, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        echo "<div style='background: #d4edda; padding: 10px; border-radius: 5px; margin: 10px 0;'>\n";
        echo "<p style='color: #155724; margin: 0;'>💾 分析報告已保存至: " . basename($reportFile) . "</p>\n";
        echo "</div>\n";
    }
    
    // 格式化位元組
    private function formatBytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    // 獲取品質顏色
    private function getQualityColor($range) {
        if (strpos($range, '優秀') !== false) return '#d4edda';
        if (strpos($range, '良好') !== false) return '#cce5ff';
        if (strpos($range, '一般') !== false) return '#fff3cd';
        if (strpos($range, '較差') !== false) return '#f8d7da';
        return '#f5c6cb';
    }
}

// 執行分析
if (isset($_GET['run_analysis']) || true) {
    $analyzer = new AdvancedFaceAnalyzer();
    $analyzer->runFullAnalysis();
}

echo "<hr>\n";
echo "<p><em>分析完成時間：" . date('Y-m-d H:i:s') . "</em></p>\n";
echo "<p><a href='?run_analysis=1'>🔄 重新執行分析</a></p>\n";
?> 