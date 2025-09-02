<?php
/**
 * Azure App Service 相容安裝腳本
 * 不使用 sudo，只使用用戶權限安裝套件
 */

echo "<h1>🔧 Azure App Service 相容安裝</h1>";

echo "<h2>📦 檢查系統套件</h2>";

// 檢查系統是否已經有套件
$check_system = "python3 -c \"import numpy; print('numpy 已安裝:', numpy.__version__)\" 2>&1";
$system_result = shell_exec($check_system);
echo "<pre>系統套件檢查：\n$system_result</pre>";

echo "<h2>🔧 修復 pip（不使用 sudo）</h2>";

// 創建用戶目錄
$create_user_dir = "mkdir -p /home/site/wwwroot/.local/bin && mkdir -p /home/site/wwwroot/.local/lib/python3.9/site-packages 2>&1";
$dir_result = shell_exec($create_user_dir);
echo "<pre>創建用戶目錄：\n$dir_result</pre>";

// 下載並安裝 pip 到用戶目錄
$install_pip = "cd /tmp && curl https://bootstrap.pypa.io/get-pip.py -o get-pip.py && python3 get-pip.py --user --force-reinstall 2>&1";
$pip_result = shell_exec($install_pip);
echo "<pre>Pip 安裝結果：\n$pip_result</pre>";

// 設定環境變數
putenv("PATH=/home/site/wwwroot/.local/bin:" . getenv("PATH"));
putenv("PYTHONPATH=/home/site/wwwroot/.local/lib/python3.9/site-packages");
putenv("PYTHONUSERBASE=/home/site/wwwroot/.local");

echo "<h2>📦 安裝 Python 套件到用戶目錄</h2>";

// 安裝套件到用戶目錄
$install_packages = "/home/site/wwwroot/.local/bin/pip install --user numpy opencv-python scikit-learn insightface onnxruntime 2>&1";
$packages_result = shell_exec($install_packages);
echo "<pre>套件安裝結果：\n$packages_result</pre>";

echo "<h2>🧪 測試套件安裝</h2>";

// 測試套件
$test_cmd = "PYTHONPATH=/home/site/wwwroot/.local/lib/python3.9/site-packages python3 -c \"import numpy; print('numpy 版本:', numpy.__version__); import cv2; print('opencv 版本:', cv2.__version__); import sklearn; print('sklearn 可用'); import insightface; print('insightface 可用')\" 2>&1";
$test_result = shell_exec($test_cmd);
echo "<pre>套件測試結果：\n$test_result</pre>";

echo "<h2>🔧 修復 group_faces_azure.py</h2>";

// 修復 Python 腳本，加入正確的路徑設定
$python_script_path = '/home/site/wwwroot/face_test/group_faces_azure.py';
if (file_exists($python_script_path)) {
    $script_content = file_get_contents($python_script_path);
    
    // 移除舊的路徑設定
    $script_content = preg_replace('/import sys\s+sys\.path\.insert\(0,.*?\)\s+/s', '', $script_content);
    
    // 加入正確的路徑設定
    $path_fix = "import sys\nsys.path.insert(0, '/home/site/wwwroot/.local/lib/python3.9/site-packages')\n\n";
    
    if (strpos($script_content, 'sys.path.insert') === false) {
        $new_content = $path_fix . $script_content;
        file_put_contents($python_script_path, $new_content);
        echo "<p>✅ 已修復 group_faces_azure.py，加入用戶目錄路徑設定</p>";
    } else {
        echo "<p>✅ group_faces_azure.py 已有路徑設定</p>";
    }
} else {
    echo "<p>❌ group_faces_azure.py 不存在</p>";
}

echo "<h2>🧪 測試人臉分群腳本</h2>";

// 測試人臉分群腳本
$test_face_cmd = "cd /home/site/wwwroot/face_test && PYTHONPATH=/home/site/wwwroot/.local/lib/python3.9/site-packages python3 group_faces_azure.py 2>&1";
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
    
    // 替代方案：檢查是否有系統套件
    echo "<h2>🚀 檢查系統套件</h2>";
    
    $sys_check = "python3 -c \"import sys; print('Python 路徑:'); [print(p) for p in sys.path]\" 2>&1";
    $sys_result = shell_exec($sys_check);
    echo "<pre>Python 路徑：\n$sys_result</pre>";
    
    // 嘗試使用系統套件
    $sys_test = "python3 -c \"try: import numpy; print('numpy 可用'); except: print('numpy 不可用')\" 2>&1";
    $sys_test_result = shell_exec($sys_test);
    echo "<pre>系統 numpy 測試：\n$sys_test_result</pre>";
}

echo "<h2>📋 環境檢查</h2>";

// 檢查環境
$env_checks = [
    'Python 版本' => 'python3 --version',
    'Pip 版本' => '/home/site/wwwroot/.local/bin/pip --version',
    '用戶目錄' => 'ls -la /home/site/wwwroot/.local/lib/python3.9/site-packages/',
    '環境變數' => 'echo "PYTHONPATH: $PYTHONPATH" && echo "PYTHONUSERBASE: $PYTHONUSERBASE"'
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

echo "<h2>💡 如果還是失敗，建議：</h2>";
echo "<ul>";
echo "<li>升級到 Azure App Service 付費方案（有更多權限）</li>";
echo "<li>使用 Azure Container Instances</li>";
echo "<li>使用 Azure Functions</li>";
echo "<li>使用 Azure Virtual Machines</li>";
echo "</ul>";
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
pre { background: #f8f9fa; padding: 10px; border-radius: 5px; overflow-x: auto; }
h1, h2, h3 { color: #333; }
p { margin: 10px 0; }
</style>
