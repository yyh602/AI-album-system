<?php
/**
 * 自動安裝檢查腳本
 * 在每次頁面載入時檢查並安裝必要的 Python 套件
 */

// 只在需要時執行安裝
$install_log = '/home/site/wwwroot/.local/install_complete.txt';
$user_packages_dir = '/home/site/wwwroot/.local/lib/python3.9/site-packages';

// 如果已經安裝過且目錄存在，跳過安裝
if (file_exists($install_log) && is_dir($user_packages_dir)) {
    // 快速檢查關鍵套件是否可用
    $test_cmd = "PYTHONPATH=$user_packages_dir python3 -c \"import numpy, cv2, insightface; print('OK')\" 2>/dev/null";
    $result = shell_exec($test_cmd);
    
    if (trim($result) === 'OK') {
        return; // 套件已安裝且可用，不需要重新安裝
    }
}

// 開始安裝
echo "<div style='background: #e8f5e8; border: 1px solid #4caf50; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
echo "<h3>🔧 正在自動安裝 Python 套件...</h3>";

// 設定環境變數
putenv("PYTHONUSERBASE=/home/site/wwwroot/.local");
putenv("PYTHONPATH=/home/site/wwwroot/.local/lib/python3.9/site-packages");
putenv("PIP_USER=yes");

// 創建用戶目錄
if (!is_dir('/home/site/wwwroot/.local')) {
    mkdir('/home/site/wwwroot/.local', 0755, true);
}

// 安裝系統套件（使用 apt）
$system_cmd = "apt update -y && apt install -y python3-numpy python3-sklearn python3-opencv 2>&1";
shell_exec($system_cmd);

// 安裝 pip
$pip_cmd = "python3 -m ensurepip --user --upgrade 2>&1";
shell_exec($pip_cmd);

// 安裝用戶套件
$user_cmd = "/home/site/wwwroot/.local/bin/pip install --user insightface onnxruntime 2>&1";
shell_exec($user_cmd);

// 創建安裝完成標記
file_put_contents($install_log, date('Y-m-d H:i:s') . " - 安裝完成\n");

echo "<p>✅ Python 套件安裝完成！</p>";
echo "</div>";

// 等待一下讓安裝完成
sleep(2);
?>
