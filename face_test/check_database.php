<?php
// 檢查資料庫中的特徵向量儲存情況
require_once '../DB_open.php';

if ($link instanceof mysqli) {
    echo "<h2>📊 資料庫檢查結果</h2>";
    
    // 檢查總人臉數
    $sql = "SELECT COUNT(*) as total_faces FROM faces";
    $result = mysqli_query($link, $sql);
    $row = mysqli_fetch_assoc($result);
    $total_faces = $row['total_faces'];
    
    // 檢查有特徵向量的人臉數
    $sql = "SELECT COUNT(*) as faces_with_features FROM faces WHERE feature_vector IS NOT NULL AND feature_vector != ''";
    $result = mysqli_query($link, $sql);
    $row = mysqli_fetch_assoc($result);
    $faces_with_features = $row['faces_with_features'];
    
    // 檢查沒有特徵向量的人臉數
    $faces_without_features = $total_faces - $faces_with_features;
    
    echo "<div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
    echo "<h3>📈 統計資料</h3>";
    echo "<p><strong>總人臉數：</strong> {$total_faces}</p>";
    echo "<p><strong>有特徵向量：</strong> {$faces_with_features}</p>";
    echo "<p><strong>沒有特徵向量：</strong> {$faces_without_features}</p>";
    echo "</div>";
    
    // 顯示前 5 筆記錄的詳細資訊
    $sql = "SELECT face_filename, LENGTH(feature_vector) as vector_length, 
                   LEFT(feature_vector, 100) as vector_start,
                   created_at, updated_at
            FROM faces 
            ORDER BY created_at DESC 
            LIMIT 5";
    $result = mysqli_query($link, $sql);
    
    if (mysqli_num_rows($result) > 0) {
        echo "<h3>📋 最近 5 筆記錄</h3>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background: #e9ecef;'>";
        echo "<th>人臉檔案</th><th>特徵向量長度</th><th>特徵向量開頭</th><th>建立時間</th><th>更新時間</th>";
        echo "</tr>";
        
        while ($row = mysqli_fetch_assoc($result)) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['face_filename']) . "</td>";
            echo "<td>" . ($row['vector_length'] ?: 'NULL') . "</td>";
            echo "<td>" . htmlspecialchars($row['vector_start'] ?: 'NULL') . "</td>";
            echo "<td>" . $row['created_at'] . "</td>";
            echo "<td>" . $row['updated_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // 檢查是否有錯誤的 JSON 格式
    $sql = "SELECT face_filename FROM faces WHERE feature_vector IS NOT NULL AND feature_vector != '' AND feature_vector NOT LIKE '[%'";
    $result = mysqli_query($link, $sql);
    $invalid_json_count = mysqli_num_rows($result);
    
    if ($invalid_json_count > 0) {
        echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
        echo "<h3>⚠️ 警告</h3>";
        echo "<p>發現 {$invalid_json_count} 筆記錄的特徵向量格式可能不正確（不是以 [ 開頭）</p>";
        echo "</div>";
    }
    
} else {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px;'>";
    echo "<h3>❌ 資料庫連接失敗</h3>";
    echo "<p>無法連接到資料庫</p>";
    echo "</div>";
}
?>
