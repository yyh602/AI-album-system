<?php
/**
 * 人臉特徵向量管理器
 * 負責儲存、讀取和比對人臉特徵向量
 */

class FaceFeatureManager {
    private $db_link;
    private $python_script_path;
    
    public function __construct($db_link) {
        $this->db_link = $db_link;
        $this->python_script_path = __DIR__ . '/group_faces_azure_class_fix.py';
    }
    
    /**
     * 從 Python 腳本提取特徵向量並儲存到資料庫
     */
    public function extractAndSaveFeatures($face_filename) {
        try {
            // 檢查 Python 腳本是否存在
            if (!file_exists($this->python_script_path)) {
                throw new Exception("Python 腳本不存在: {$this->python_script_path}");
            }
            
            // 檢查人臉圖片是否存在
            $face_path = __DIR__ . '/faces/' . $face_filename;
            if (!file_exists($face_path)) {
                throw new Exception("人臉圖片不存在: {$face_path}");
            }
            
            // 執行 Python 腳本提取特徵向量
            $command = "cd " . __DIR__ . " && python3 " . escapeshellarg($this->python_script_path) . " " . escapeshellarg($face_path) . " 2>&1";
            $output = shell_exec($command);
            
            if ($output === null) {
                throw new Exception("Python 腳本執行失敗");
            }
            
            // 解析輸出，提取特徵向量
            $feature_vector = $this->parseFeatureVectorFromOutput($output, $face_filename);
            
            if ($feature_vector === null) {
                throw new Exception("無法從輸出中解析特徵向量");
            }
            
            // 儲存特徵向量到資料庫
            $this->saveFeatureVectorToDatabase($face_filename, $feature_vector);
            
            return [
                'success' => true,
                'face_filename' => $face_filename,
                'feature_dimension' => count($feature_vector),
                'message' => '特徵向量提取並儲存成功'
            ];
            
        } catch (Exception $e) {
            error_log("特徵向量提取失敗: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 從 Python 腳本輸出中解析特徵向量
     */
    private function parseFeatureVectorFromOutput($output, $face_filename) {
        // 尋找包含特徵向量的行
        $lines = explode("\n", $output);
        $feature_vector = null;
        
        foreach ($lines as $line) {
            // 尋找特徵向量的行（通常包含 [ 和 ] 的數字陣列）
            if (strpos($line, '[') !== false && strpos($line, ']') !== false && 
                preg_match('/\[([\d\-\.,\s]+)\]/', $line, $matches)) {
                
                // 提取數字陣列
                $numbers_str = $matches[1];
                $numbers = explode(' ', preg_replace('/\s+/', ' ', trim($numbers_str)));
                
                // 過濾空字串並轉換為浮點數
                $feature_vector = [];
                foreach ($numbers as $num) {
                    $num = trim($num);
                    if ($num !== '' && is_numeric($num)) {
                        $feature_vector[] = (float)$num;
                    }
                }
                
                // 檢查維度是否合理（通常是 512 維）
                if (count($feature_vector) >= 100 && count($feature_vector) <= 1000) {
                    break;
                }
            }
        }
        
        return $feature_vector;
    }
    
    /**
     * 儲存特徵向量到資料庫
     */
    private function saveFeatureVectorToDatabase($face_filename, $feature_vector) {
        try {
            // 檢查 faces 表是否存在 feature_vector 欄位
            $this->ensureFeatureVectorColumnExists();
            
            // 將特徵向量轉換為 JSON 字串
            $feature_json = json_encode($feature_vector);
            
            // 更新資料庫中的特徵向量
            $sql = "UPDATE faces SET feature_vector = ? WHERE face_filename = ?";
            $stmt = mysqli_prepare($this->db_link, $sql);
            
            if (!$stmt) {
                throw new Exception("SQL 準備失敗: " . mysqli_error($this->db_link));
            }
            
            mysqli_stmt_bind_param($stmt, "ss", $feature_json, $face_filename);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("SQL 執行失敗: " . mysqli_stmt_error($stmt));
            }
            
            mysqli_stmt_close($stmt);
            
            error_log("特徵向量已儲存到資料庫: {$face_filename}");
            
        } catch (Exception $e) {
            throw new Exception("儲存特徵向量到資料庫失敗: " . $e->getMessage());
        }
    }
    
    /**
     * 確保 faces 表存在 feature_vector 欄位
     */
    private function ensureFeatureVectorColumnExists() {
        try {
            // 檢查欄位是否存在
            $sql = "SHOW COLUMNS FROM faces LIKE 'feature_vector'";
            $result = mysqli_query($this->db_link, $sql);
            
            if (!$result) {
                throw new Exception("檢查欄位失敗: " . mysqli_error($this->db_link));
            }
            
            if (mysqli_num_rows($result) == 0) {
                // 欄位不存在，新增它
                $sql = "ALTER TABLE faces ADD COLUMN feature_vector TEXT";
                if (!mysqli_query($this->db_link, $sql)) {
                    throw new Exception("新增 feature_vector 欄位失敗: " . mysqli_error($this->db_link));
                }
                error_log("已新增 feature_vector 欄位到 faces 表");
            }
            
        } catch (Exception $e) {
            throw new Exception("確保 feature_vector 欄位存在失敗: " . $e->getMessage());
        }
    }
    
    /**
     * 從資料庫讀取特徵向量
     */
    public function getFeatureVector($face_filename) {
        try {
            $sql = "SELECT feature_vector FROM faces WHERE face_filename = ?";
            $stmt = mysqli_prepare($this->db_link, $sql);
            
            if (!$stmt) {
                throw new Exception("SQL 準備失敗: " . mysqli_error($this->db_link));
            }
            
            mysqli_stmt_bind_param($stmt, "s", $face_filename);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if ($row = mysqli_fetch_assoc($result)) {
                $feature_json = $row['feature_vector'];
                if ($feature_json) {
                    return json_decode($feature_json, true);
                }
            }
            
            return null;
            
        } catch (Exception $e) {
            error_log("讀取特徵向量失敗: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * 計算兩個特徵向量的相似度（餘弦相似度）
     */
    public function calculateSimilarity($vector1, $vector2) {
        try {
            if (count($vector1) !== count($vector2)) {
                throw new Exception("特徵向量維度不匹配");
            }
            
            $dot_product = 0;
            $norm1 = 0;
            $norm2 = 0;
            
            for ($i = 0; $i < count($vector1); $i++) {
                $dot_product += $vector1[$i] * $vector2[$i];
                $norm1 += $vector1[$i] * $vector1[$i];
                $norm2 += $vector2[$i] * $vector2[$i];
            }
            
            $norm1 = sqrt($norm1);
            $norm2 = sqrt($norm2);
            
            if ($norm1 == 0 || $norm2 == 0) {
                return 0;
            }
            
            return $dot_product / ($norm1 * $norm2);
            
        } catch (Exception $e) {
            error_log("計算相似度失敗: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * 尋找與指定人臉最相似的人臉
     */
    public function findSimilarFaces($target_face_filename, $similarity_threshold = 0.7, $limit = 10) {
        try {
            $target_vector = $this->getFeatureVector($target_face_filename);
            if (!$target_vector) {
                throw new Exception("找不到目標人臉的特徵向量");
            }
            
            // 獲取所有有特徵向量的人臉
            $sql = "SELECT face_filename, feature_vector FROM faces WHERE feature_vector IS NOT NULL AND face_filename != ?";
            $stmt = mysqli_prepare($this->db_link, $sql);
            
            if (!$stmt) {
                throw new Exception("SQL 準備失敗: " . mysqli_error($this->db_link));
            }
            
            mysqli_stmt_bind_param($stmt, "ss", $target_face_filename);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            $similar_faces = [];
            
            while ($row = mysqli_fetch_assoc($result)) {
                $compare_vector = json_decode($row['feature_vector'], true);
                if ($compare_vector) {
                    $similarity = $this->calculateSimilarity($target_vector, $compare_vector);
                    
                    if ($similarity >= $similarity_threshold) {
                        $similar_faces[] = [
                            'face_filename' => $row['face_filename'],
                            'similarity' => $similarity
                        ];
                    }
                }
            }
            
            // 按相似度排序
            usort($similar_faces, function($a, $b) {
                return $b['similarity'] <=> $a['similarity'];
            });
            
            // 限制結果數量
            return array_slice($similar_faces, 0, $limit);
            
        } catch (Exception $e) {
            error_log("尋找相似人臉失敗: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * 批量提取並儲存所有未處理人臉的特徵向量
     */
    public function batchExtractAndSaveFeatures() {
        try {
            // 獲取所有沒有特徵向量的人臉
            $sql = "SELECT face_filename FROM faces WHERE feature_vector IS NULL OR feature_vector = ''";
            $query_result = mysqli_query($this->db_link, $sql);
            
            if (!$query_result) {
                throw new Exception("查詢失敗: " . mysqli_error($this->db_link));
            }
            
            $total_faces = mysqli_num_rows($query_result);
            $processed = 0;
            $successful = 0;
            $failed = 0;
            
            error_log("開始批量處理 {$total_faces} 個人臉的特徵向量");
            
            while ($row = mysqli_fetch_assoc($query_result)) {
                $face_filename = $row['face_filename'];
                $processed++;
                
                try {
                    $extract_result = $this->extractAndSaveFeatures($face_filename);
                    if ($extract_result['success']) {
                        $successful++;
                    } else {
                        $failed++;
                        error_log("處理失敗 {$face_filename}: " . $extract_result['error']);
                    }
                } catch (Exception $e) {
                    $failed++;
                    error_log("處理異常 {$face_filename}: " . $e->getMessage());
                }
                
                // 每處理 10 個暫停一下，避免伺服器過載
                if ($processed % 10 == 0) {
                    usleep(500000); // 0.5 秒
                }
            }
            
            return [
                'total' => $total_faces,
                'processed' => $processed,
                'successful' => $successful,
                'failed' => $failed
            ];
            
        } catch (Exception $e) {
            throw new Exception("批量處理失敗: " . $e->getMessage());
        }
    }
}
?>
