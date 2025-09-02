<?php
/**
 * 緊急安裝腳本
 * 修復 pip 並安裝所有必要套件
 */

echo "<h1>🚨 緊急安裝 Python 套件</h1>";

echo "<h2>🔧 修復 pip</h2>";

// 檢查 pip 狀態
$pip_check = "which pip3 2>&1";
$pip_result = shell_exec($pip_check);
echo "<pre>Pip3 位置檢查：\n$pip_result</pre>";

// 安裝 pip
echo "<h3>📦 安裝 pip3</h3>";
$install_pip_cmd = "curl https://bootstrap.pypa.io/get-pip.py -o get-pip.py && python3 get-pip.py --user 2>&1";
$pip_install_result = shell_exec($install_pip_cmd);
echo "<pre>Pip 安裝結果：\n$pip_install_result</pre>";

// 檢查 pip 是否安裝成功
$pip_test = "/home/site/wwwroot/.local/bin/pip --version 2>&1";
$pip_test_result = shell_exec($pip_test);
echo "<pre>Pip 測試結果：\n$pip_test_result</pre>";

echo "<h2>📦 安裝系統套件</h2>";

// 安裝系統套件
$system_install = "apt update -y && apt install -y python3-numpy python3-sklearn python3-opencv python3-pip 2>&1";
$system_result = shell_exec($system_install);
echo "<pre>系統套件安裝結果：\n$system_result</pre>";

echo "<h2>📦 安裝 Python 套件</h2>";

// 使用系統 pip 安裝套件
$python_install = "python3 -m pip install numpy opencv-python scikit-learn insightface onnxruntime 2>&1";
$python_result = shell_exec($python_install);
echo "<pre>Python 套件安裝結果：\n$python_result</pre>";

echo "<h2>🧪 測試套件安裝</h2>";

// 測試套件
$test_cmd = "python3 -c \"import numpy; print('numpy 版本:', numpy.__version__); import cv2; print('opencv 版本:', cv2.__version__); import sklearn; print('sklearn 可用'); import insightface; print('insightface 可用')\" 2>&1";
$test_result = shell_exec($test_cmd);
echo "<pre>套件測試結果：\n$test_result</pre>";

echo "<h2>🔧 修復 group_faces_azure.py</h2>";

// 修復 Python 腳本
$python_script_path = '/home/site/wwwroot/face_test/group_faces_azure.py';
if (file_exists($python_script_path)) {
    $script_content = file_get_contents($python_script_path);
    
    // 移除所有路徑設定
    $script_content = preg_replace('/import sys\s+sys\.path\.insert\(0,.*?\)\s+/s', '', $script_content);
    
    // 重新寫入腳本
    file_put_contents($python_script_path, $script_content);
    echo "<p>✅ 已修復 group_faces_azure.py</p>";
} else {
    echo "<p>❌ group_faces_azure.py 不存在</p>";
}

echo "<h2>🧪 測試人臉分群腳本</h2>";

// 測試人臉分群腳本
$test_face_cmd = "cd /home/site/wwwroot/face_test && python3 group_faces_azure.py 2>&1";
$face_test_output = shell_exec($test_face_cmd);

if (strpos($face_test_output, 'ModuleNotFoundError') === false) {
    echo "<div style='color: green; background: #d4edda; padding: 10px; border-radius: 5px;'>";
    echo "<h3>🎉 安裝成功！人臉分群腳本現在可以正常執行</h3>";
    echo "</div>";
    echo "<pre>執行結果：\n$face_test_output</pre>";
} else {
    echo "<div style='color: red; background: #f8d7da; padding: 10px; border-radius: 5px;'>";
    echo "<h3>❌ 安裝失敗，嘗試替代方案</h3>";
    echo "</div>";
    echo "<pre>錯誤訊息：\n$face_test_output</pre>";
    
    // 替代方案：使用 conda 或 miniconda
    echo "<h2>🚀 替代安裝方案</h2>";
    
    // 嘗試使用 get-pip.py
    $alt_pip_cmd = "wget https://bootstrap.pypa.io/get-pip.py && python3 get-pip.py --force-reinstall 2>&1";
    $alt_pip_result = shell_exec($alt_pip_cmd);
    echo "<pre>替代 pip 安裝：\n$alt_pip_result</pre>";
    
    // 再次嘗試安裝套件
    $alt_install_cmd = "python3 -m pip install --user numpy opencv-python scikit-learn insightface onnxruntime 2>&1";
    $alt_install_result = shell_exec($alt_install_cmd);
    echo "<pre>替代套件安裝：\n$alt_install_result</pre>";
    
    // 最終測試
    $final_test_cmd = "cd /home/site/wwwroot/face_test && python3 group_faces_azure.py 2>&1";
    $final_result = shell_exec($final_test_cmd);
    echo "<pre>最終測試結果：\n$final_result</pre>";
}

echo "<h2>📋 環境檢查</h2>";

// 檢查 Python 環境
$env_checks = [
    'Python 版本' => 'python3 --version',
    'Pip 版本' => 'python3 -m pip --version',
    'Python 路徑' => 'which python3',
    '已安裝套件' => 'python3 -m pip list'
];

foreach ($env_checks as $name => $cmd) {
    $result = shell_exec($cmd . " 2>&1");
    echo "<h3>$name</h3>";
    echo "<pre>$result</pre>";
}

echo "<h2>📝 下一步</h2>";
echo "<p>如果安裝成功，您可以：</p>";
echo "<ul>";
echo "<li><a href='azure_face_dashboard.php'>前往人臉分群儀表板</a></li>";
echo "<li><a href='test_auto_install.php'>重新測試安裝狀態</a></li>";
echo "</ul>";
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
pre { background: #f8f9fa; padding: 10px; border-radius: 5px; overflow-x: auto; }
h1, h2, h3 { color: #333; }
p { margin: 10px 0; }
</style>
