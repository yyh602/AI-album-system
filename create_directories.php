<?php
/**
 * 創建人臉辨識所需的本地暫存目錄結構
 * 注意：這些是本地暫存目錄，處理完成後會自動上傳到 Azure Storage
 */

$baseDir = __DIR__ . '/face_test';

// 需要創建的本地暫存目錄
$directories = [
    'faces',           // 本地暫存：存放偵測到的人臉 (會上傳到 Azure Storage 的 face/ 資料夾)
    'group',           // 本地暫存：存放分群結果 (會上傳到 Azure Storage 的 group/ 資料夾)
    'models',          // 存放 AI 模型檔案
    'temp',            // 臨時檔案處理
    'logs'             // 日誌檔案
];

echo "=== 創建人臉辨識本地暫存目錄 ===\n";
echo "注意：這些是本地暫存目錄，內容會自動上傳到 Azure Storage\n\n";

foreach ($directories as $dir) {
    $fullPath = $baseDir . '/' . $dir;
    
    if (!is_dir($fullPath)) {
        if (mkdir($fullPath, 0777, true)) {
            echo "✅ 創建本地暫存目錄: $dir\n";
        } else {
            echo "❌ 創建目錄失敗: $dir\n";
        }
    } else {
        echo "ℹ️ 目錄已存在: $dir\n";
    }
    
    // 設置權限
    if (is_dir($fullPath)) {
        chmod($fullPath, 0777);
        echo "   - 權限設置完成\n";
    }
}

// 創建 .htaccess 檔案保護 faces 和 group 目錄
$htaccessContent = "Options -Indexes\nDeny from all";
$htaccessFiles = ['faces/.htaccess', 'group/.htaccess'];

foreach ($htaccessFiles as $htaccessFile) {
    $fullPath = $baseDir . '/' . $htaccessFile;
    if (!file_exists($fullPath)) {
        if (file_put_contents($fullPath, $htaccessContent)) {
            echo "✅ 創建 .htaccess: $htaccessFile\n";
        } else {
            echo "❌ 創建 .htaccess 失敗: $htaccessFile\n";
        }
    }
}

echo "\n=== 目錄結構創建完成 ===\n";
echo "本地暫存目錄：\n";
echo "- face_test/faces/     → 會上傳到 Azure Storage 的 face/ 資料夾\n";
echo "- face_test/group/     → 會上傳到 Azure Storage 的 group/ 資料夾\n";
echo "\n請確保以下檔案存在：\n";
echo "1. Google Cloud Vision API 憑證: face_test/shining-glyph-465006-i1-8f6de1bb78de.json\n";
echo "2. Python 分群腳本: face_test/group_faces_azure_class_fix.py\n";
echo "3. 必要的 Python 套件: opencv-python, numpy, insightface\n";
echo "4. Azure Storage 連接字串環境變數 (可選)\n";
?>
