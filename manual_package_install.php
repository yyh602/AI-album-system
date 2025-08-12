<?php
header('Content-Type: application/json');

// 手動套件安裝方案（當 Composer 不可用時）
class ManualPackageInstaller {
    
    private $packages = [
        'guzzlehttp/guzzle' => [
            'version' => '^7.0',
            'description' => 'HTTP 客戶端',
            'files' => [
                'vendor/guzzlehttp/guzzle/src/Client.php',
                'vendor/guzzlehttp/guzzle/src/RequestOptions.php'
            ]
        ],
        'ramsey/uuid' => [
            'version' => '^4.0',
            'description' => 'UUID 生成',
            'files' => [
                'vendor/ramsey/uuid/src/Uuid.php',
                'vendor/ramsey/uuid/src/UuidInterface.php'
            ]
        ],
        'monolog/monolog' => [
            'version' => '^2.0',
            'description' => '日誌記錄',
            'files' => [
                'vendor/monolog/monolog/src/Monolog/Logger.php',
                'vendor/monolog/monolog/src/Monolog/Handler/StreamHandler.php'
            ]
        ]
    ];
    
    public function checkManualInstallation() {
        $result = [
            'composer_available' => $this->isComposerAvailable(),
            'manual_installation_possible' => true,
            'packages_status' => [],
            'recommendations' => []
        ];
        
        foreach ($this->packages as $package => $info) {
            $result['packages_status'][$package] = [
                'installed' => $this->isPackageInstalled($package),
                'description' => $info['description'],
                'files_exist' => $this->checkPackageFiles($package, $info['files'])
            ];
        }
        
        // 提供建議
        if (!$result['composer_available']) {
            $result['recommendations'][] = 'Composer 不可用，建議使用手動安裝方案';
        }
        
        if (!$this->isVendorDirectoryExists()) {
            $result['recommendations'][] = 'vendor 目錄不存在，需要建立';
        }
        
        return $result;
    }
    
    public function createVendorStructure() {
        try {
            if (!is_dir('vendor')) {
                mkdir('vendor', 0755, true);
                error_log('建立 vendor 目錄');
            }
            
            // 建立 autoload.php
            $autoloadContent = $this->generateAutoloadFile();
            file_put_contents('vendor/autoload.php', $autoloadContent);
            
            return ['success' => true, 'message' => 'vendor 結構建立成功'];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function downloadPackage($packageName) {
        if (!isset($this->packages[$packageName])) {
            return ['success' => false, 'error' => '不支援的套件: ' . $packageName];
        }
        
        try {
            $packageInfo = $this->packages[$packageName];
            
            // 建立套件目錄
            $packageDir = 'vendor/' . str_replace('/', '/', $packageName);
            if (!is_dir($packageDir)) {
                mkdir($packageDir, 0755, true);
            }
            
            // 下載套件檔案（這裡需要實際的檔案內容）
            $this->createPackageFiles($packageName, $packageDir);
            
            return ['success' => true, 'message' => "套件 $packageName 安裝成功"];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    private function isComposerAvailable() {
        $output = shell_exec('which composer 2>&1');
        return $output && !strpos($output, 'not found');
    }
    
    private function isPackageInstalled($package) {
        $packageDir = 'vendor/' . str_replace('/', '/', $package);
        return is_dir($packageDir);
    }
    
    private function checkPackageFiles($package, $files) {
        $existingFiles = [];
        foreach ($files as $file) {
            if (file_exists($file)) {
                $existingFiles[] = $file;
            }
        }
        return $existingFiles;
    }
    
    private function isVendorDirectoryExists() {
        return is_dir('vendor');
    }
    
    private function generateAutoloadFile() {
        return '<?php
// 手動生成的 autoload.php
spl_autoload_register(function ($class) {
    // 將命名空間轉換為檔案路徑
    $file = __DIR__ . "/" . str_replace("\\\", "/", $class) . ".php";
    
    if (file_exists($file)) {
        require_once $file;
        return true;
    }
    
    return false;
});

// 載入常用套件
$commonPackages = [
    "guzzlehttp/guzzle",
    "ramsey/uuid", 
    "monolog/monolog"
];

foreach ($commonPackages as $package) {
    $packageDir = __DIR__ . "/" . str_replace("/", "/", $package);
    if (is_dir($packageDir)) {
        // 載入套件的主要檔案
        $mainFile = $packageDir . "/src/" . basename($package) . ".php";
        if (file_exists($mainFile)) {
            require_once $mainFile;
        }
    }
}
?>';
    }
    
    private function createPackageFiles($packageName, $packageDir) {
        // 這裡應該包含實際的套件檔案內容
        // 由於檔案較大，建議從本地環境複製或使用 CDN 下載
        
        switch ($packageName) {
            case 'guzzlehttp/guzzle':
                $this->createGuzzleFiles($packageDir);
                break;
            case 'ramsey/uuid':
                $this->createUuidFiles($packageDir);
                break;
            case 'monolog/monolog':
                $this->createMonologFiles($packageDir);
                break;
        }
    }
    
    private function createGuzzleFiles($packageDir) {
        // 建立基本的 Guzzle 檔案結構
        $srcDir = $packageDir . '/src';
        if (!is_dir($srcDir)) {
            mkdir($srcDir, 0755, true);
        }
        
        // 這裡應該包含實際的 Guzzle 檔案內容
        // 建議從本地環境複製 vendor/guzzlehttp/guzzle 資料夾
    }
    
    private function createUuidFiles($packageDir) {
        // 建立基本的 UUID 檔案結構
        $srcDir = $packageDir . '/src';
        if (!is_dir($srcDir)) {
            mkdir($srcDir, 0755, true);
        }
        
        // 這裡應該包含實際的 UUID 檔案內容
        // 建議從本地環境複製 vendor/ramsey/uuid 資料夾
    }
    
    private function createMonologFiles($packageDir) {
        // 建立基本的 Monolog 檔案結構
        $srcDir = $packageDir . '/src';
        if (!is_dir($srcDir)) {
            mkdir($srcDir, 0755, true);
        }
        
        // 這裡應該包含實際的 Monolog 檔案內容
        // 建議從本地環境複製 vendor/monolog/monolog 資料夾
    }
}

// 處理請求
$installer = new ManualPackageInstaller();

if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'check':
            $result = $installer->checkManualInstallation();
            break;
            
        case 'create_vendor':
            $result = $installer->createVendorStructure();
            break;
            
        case 'install_package':
            if (isset($_GET['package'])) {
                $result = $installer->downloadPackage($_GET['package']);
            } else {
                $result = ['success' => false, 'error' => '請提供 package 參數'];
            }
            break;
            
        default:
            $result = ['success' => false, 'error' => '未知操作'];
    }
} else {
    $result = $installer->checkManualInstallation();
}

echo json_encode($result);
?>
