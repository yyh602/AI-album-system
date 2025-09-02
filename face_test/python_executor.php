<?php
/**
 * 跨平台 Python 執行器
 * 自動檢測 Python 路徑並提供統一的執行介面
 */
class PythonExecutor {
    private $pythonPath = null;
    private $isWindows = false;
    
    public function __construct() {
        $this->isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $this->detectPythonPath();
    }
    
    /**
     * 自動檢測 Python 路徑
     */
    private function detectPythonPath() {
        // 方法 1: 使用 where/python 命令
        $output = [];
        $returnCode = 0;
        
        if ($this->isWindows) {
            exec("where python 2>&1", $output, $returnCode);
        } else {
            exec("which python3 2>&1", $output, $returnCode);
            if ($returnCode !== 0) {
                exec("which python 2>&1", $output, $returnCode);
            }
        }
        
        if ($returnCode === 0 && !empty($output)) {
            $this->pythonPath = trim($output[0]);
            return;
        }
        
        // 方法 2: 檢查常見路徑
        $possiblePaths = $this->getPossiblePaths();
        
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                // 測試是否可執行
                $testOutput = [];
                $testReturnCode = 0;
                exec("\"$path\" --version 2>&1", $testOutput, $testReturnCode);
                
                if ($testReturnCode === 0) {
                    $this->pythonPath = $path;
                    return;
                }
            }
        }
    }
    
    /**
     * 獲取可能的 Python 路徑
     */
    private function getPossiblePaths() {
        $paths = [];
        
        if ($this->isWindows) {
            // Windows 路徑
            $user = get_current_user();
            $paths = [
                // 標準安裝路徑
                "C:\\Python313\\python.exe",
                "C:\\Python312\\python.exe",
                "C:\\Python311\\python.exe",
                "C:\\Python310\\python.exe",
                "C:\\Python39\\python.exe",
                "C:\\Python38\\python.exe",
                
                // 使用者特定路徑
                "C:\\Users\\$user\\AppData\\Local\\Microsoft\\WindowsApps\\python.exe",
                "C:\\Users\\$user\\AppData\\Local\\Programs\\Python\\Python313\\python.exe",
                "C:\\Users\\$user\\AppData\\Local\\Programs\\Python\\Python312\\python.exe",
                "C:\\Users\\$user\\AppData\\Local\\Programs\\Python\\Python311\\python.exe",
                
                // Program Files 路徑
                "C:\\Program Files\\Python313\\python.exe",
                "C:\\Program Files\\Python312\\python.exe",
                "C:\\Program Files\\Python311\\python.exe",
                "C:\\Program Files (x86)\\Python313\\python.exe",
                "C:\\Program Files (x86)\\Python312\\python.exe",
                "C:\\Program Files (x86)\\Python311\\python.exe"
            ];
        } else {
            // Linux/Mac 路徑
            $paths = [
                "/usr/bin/python3",
                "/usr/bin/python",
                "/usr/local/bin/python3",
                "/usr/local/bin/python",
                "/opt/homebrew/bin/python3",
                "/opt/homebrew/bin/python"
            ];
        }
        
        return $paths;
    }
    
    /**
     * 執行 Python 腳本
     */
    public function executeScript($scriptPath, $args = []) {
        if (!$this->pythonPath) {
            throw new Exception("Python 路徑未找到");
        }
        
        if (!file_exists($scriptPath)) {
            throw new Exception("Python 腳本不存在: $scriptPath");
        }
        
        $command = "\"" . $this->pythonPath . "\" \"" . $scriptPath . "\"";
        
        if (!empty($args)) {
            $command .= " " . implode(" ", array_map('escapeshellarg', $args));
        }
        
        $output = [];
        $returnCode = 0;
        
        exec($command . " 2>&1", $output, $returnCode);
        
        return [
            'output' => $output,
            'returnCode' => $returnCode,
            'command' => $command
        ];
    }
    
    /**
     * 執行 Python 命令
     */
    public function executeCommand($command) {
        if (!$this->pythonPath) {
            throw new Exception("Python 路徑未找到");
        }
        
        $fullCommand = "\"" . $this->pythonPath . "\" -c \"" . addslashes($command) . "\"";
        
        $output = [];
        $returnCode = 0;
        
        exec($fullCommand . " 2>&1", $output, $returnCode);
        
        return [
            'output' => $output,
            'returnCode' => $returnCode,
            'command' => $fullCommand
        ];
    }
    
    /**
     * 檢查 Python 是否可用
     */
    public function isAvailable() {
        return $this->pythonPath !== null;
    }
    
    /**
     * 獲取 Python 路徑
     */
    public function getPythonPath() {
        return $this->pythonPath;
    }
    
    /**
     * 獲取 Python 版本
     */
    public function getVersion() {
        if (!$this->pythonPath) {
            return null;
        }
        
        $result = $this->executeCommand("import sys; print(sys.version)");
        
        if ($result['returnCode'] === 0 && !empty($result['output'])) {
            return trim($result['output'][0]);
        }
        
        return null;
    }
    
    /**
     * 檢查 Python 套件是否已安裝
     */
    public function isPackageInstalled($packageName) {
        if (!$this->pythonPath) {
            return false;
        }
        
        $result = $this->executeCommand("import $packageName; print('OK')");
        return $result['returnCode'] === 0;
    }
    
    /**
     * 安裝 Python 套件
     */
    public function installPackage($packageName) {
        if (!$this->pythonPath) {
            throw new Exception("Python 路徑未找到");
        }
        
        $command = "\"" . $this->pythonPath . "\" -m pip install " . escapeshellarg($packageName);
        
        $output = [];
        $returnCode = 0;
        
        exec($command . " 2>&1", $output, $returnCode);
        
        return [
            'output' => $output,
            'returnCode' => $returnCode,
            'command' => $command
        ];
    }
}

// 測試腳本
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    echo "<h1>🐍 Python 執行器測試</h1>";
    
    try {
        $executor = new PythonExecutor();
        
        if ($executor->isAvailable()) {
            echo "<p>✅ Python 路徑: " . $executor->getPythonPath() . "</p>";
            
            $version = $executor->getVersion();
            if ($version) {
                echo "<p>✅ Python 版本: $version</p>";
            }
            
            // 測試套件
            $packages = ['cv2', 'numpy', 'sklearn', 'insightface', 'requests'];
            echo "<h2>套件檢查</h2>";
            
            foreach ($packages as $package) {
                if ($executor->isPackageInstalled($package)) {
                    echo "<p>✅ $package: 已安裝</p>";
                } else {
                    echo "<p>❌ $package: 未安裝</p>";
                }
            }
            
            // 測試腳本執行
            echo "<h2>腳本執行測試</h2>";
            $testScript = __DIR__ . '/test_script.py';
            
            // 創建測試腳本
            file_put_contents($testScript, "import sys\nprint('Python version:', sys.version)\nprint('Script executed successfully')");
            
            $result = $executor->executeScript($testScript);
            
            if ($result['returnCode'] === 0) {
                echo "<p>✅ 腳本執行成功</p>";
                echo "<pre>" . implode("\n", $result['output']) . "</pre>";
            } else {
                echo "<p>❌ 腳本執行失敗</p>";
                echo "<pre>" . implode("\n", $result['output']) . "</pre>";
            }
            
            // 清理測試腳本
            if (file_exists($testScript)) {
                unlink($testScript);
            }
            
        } else {
            echo "<p>❌ Python 未找到</p>";
        }
        
    } catch (Exception $e) {
        echo "<p>❌ 錯誤: " . $e->getMessage() . "</p>";
    }
    
    echo "<hr>";
    echo "<h3>使用範例</h3>";
    echo "<pre>";
    echo "// 建立執行器\n";
    echo "\$executor = new PythonExecutor();\n\n";
    echo "// 檢查是否可用\n";
    echo "if (\$executor->isAvailable()) {\n";
    echo "    // 執行腳本\n";
    echo "    \$result = \$executor->executeScript('script.py');\n";
    echo "    // 執行命令\n";
    echo "    \$result = \$executor->executeCommand('print(\"Hello World\")');\n";
    echo "    // 檢查套件\n";
    echo "    if (\$executor->isPackageInstalled('numpy')) {\n";
    echo "        echo 'numpy 已安裝';\n";
    echo "    }\n";
    echo "}\n";
    echo "</pre>";
}
?>



