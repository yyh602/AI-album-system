<?php
/**
 * PostgreSQL 相容性修復腳本
 * 檢查並修復專案中的 SQL 查詢以支援 PostgreSQL
 */

echo "<h1>PostgreSQL 相容性檢查</h1>";

// 需要修復的檔案和查詢
$files_to_fix = [
    'welcome.php' => [
        'SELECT name FROM user WHERE username = ?' => 'SELECT name FROM "user" WHERE username = ?',
        'SELECT name FROM "user" WHERE username = ?' => 'SELECT name FROM "user" WHERE username = ?' // 已經正確
    ],
    'ai_log.php' => [
        'SELECT name FROM user WHERE username = ?' => 'SELECT name FROM "user" WHERE username = ?',
        'SELECT name FROM "user" WHERE username = ?' => 'SELECT name FROM "user" WHERE username = ?' // 已經正確
    ],
    'db_test.php' => [
        'SELECT COUNT(*) as count FROM user' => 'SELECT COUNT(*) as count FROM "user"'
    ]
];

// 檢查檔案是否存在並顯示修復建議
foreach ($files_to_fix as $filename => $queries) {
    echo "<h2>檢查檔案: $filename</h2>";
    
    if (file_exists($filename)) {
        $content = file_get_contents($filename);
        echo "<p>✅ 檔案存在</p>";
        
        foreach ($queries as $old_query => $new_query) {
            if (strpos($content, $old_query) !== false) {
                echo "<p>⚠️ 發現需要修復的查詢:</p>";
                echo "<pre>舊: $old_query\n新: $new_query</pre>";
            } else {
                echo "<p>✅ 查詢已正確或不存在</p>";
            }
        }
    } else {
        echo "<p>❌ 檔案不存在</p>";
    }
}

echo "<h2>PostgreSQL 相容性建議</h2>";
echo "<ul>";
echo "<li>所有表名都應該用雙引號包圍，特別是 'user' 表</li>";
echo "<li>字串連接使用 || 而不是 CONCAT()</li>";
echo "<li>日期函數語法需要調整</li>";
echo "<li>LIMIT 和 OFFSET 語法保持不變</li>";
echo "</ul>";

echo "<h2>已修復的檔案</h2>";
echo "<ul>";
echo "<li>✅ album.php - 修復了 user 表查詢</li>";
echo "<li>✅ login.php - 修復了 user 表查詢</li>";
echo "<li>✅ add.php - 修復了 user 表查詢</li>";
echo "<li>✅ DB_open.php - 添加了 PostgreSQL 支援</li>";
echo "</ul>";

echo "<h2>需要手動檢查的檔案</h2>";
echo "<ul>";
echo "<li>get_album_photos.php - 檢查 albums 表查詢</li>";
echo "<li>get_uploads.php - 檢查 uploads 表查詢</li>";
echo "<li>delete_album.php - 檢查 albums 表查詢</li>";
echo "<li>delete_photo.php - 檢查 uploads 表查詢</li>";
echo "<li>get_diary_detail.php - 檢查 travel_diary 表查詢</li>";
echo "</ul>";

echo "<p><strong>注意:</strong> 這個腳本只是檢查工具，實際修復需要手動進行。</p>";
?> 