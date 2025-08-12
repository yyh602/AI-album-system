<?php
header('Content-Type: application/json');

// Composer 套件支援檢查
class ComposerChecker {
    
    public function checkComposerSupport() {
        $result = [
            'composer_available' => false,
            'composer_version' => null,
            'php_version' => PHP_VERSION,
            'extensions' => [],
            'recommended_packages' => [],
            'face_detection_packages' => [],
            'vision_api_packages' => []
        ];
        
        // 檢查 Composer 是否可用
        $composerPath = $this->findComposer();
        if ($composerPath) {
            $result['composer_available'] = true;
            $result['composer_version'] = $this->getComposerVersion($composerPath);
        }
        
        // 檢查 PHP 擴展
        $result['extensions'] = [
            'curl' => extension_loaded('curl'),
            'json' => extension_loaded('json'),
            'openssl' => extension_loaded('openssl'),
            'zip' => extension_loaded('zip'),
            'gd' => extension_loaded('gd'),
            'imagick' => extension_loaded('imagick'),
            'exif' => extension_loaded('exif'),
            'mbstring' => extension_loaded('mbstring')
        ];
        
        // 推薦的人臉偵測套件
        $result['face_detection_packages'] = [
            'google/cloud-vision' => [
                'description' => 'Google Cloud Vision API',
                'composer_command' => 'composer require google/cloud-vision',
                'azure_compatible' => true,
                'features' => ['face_detection', 'object_detection', 'text_recognition']
            ],
            'microsoft/azure-cognitiveservices-vision-face' => [
                'description' => 'Azure Cognitive Services Face API',
                'composer_command' => 'composer require microsoft/azure-cognitiveservices-vision-face',
                'azure_compatible' => true,
                'features' => ['face_detection', 'face_recognition', 'emotion_detection']
            ],
            'aws/aws-sdk-php' => [
                'description' => 'AWS SDK for PHP (包含 Rekognition)',
                'composer_command' => 'composer require aws/aws-sdk-php',
                'azure_compatible' => true,
                'features' => ['face_detection', 'face_recognition', 'object_detection']
            ]
        ];
        
        // 推薦的圖片處理套件
        $result['vision_api_packages'] = [
            'intervention/image' => [
                'description' => '圖片處理庫',
                'composer_command' => 'composer require intervention/image',
                'azure_compatible' => true,
                'features' => ['resize', 'crop', 'rotate', 'filter']
            ],
            'spatie/image-optimizer' => [
                'description' => '圖片優化',
                'composer_command' => 'composer require spatie/image-optimizer',
                'azure_compatible' => false, // 需要命令列工具
                'features' => ['optimization', 'compression']
            ]
        ];
        
        // 推薦的通用套件
        $result['recommended_packages'] = [
            'guzzlehttp/guzzle' => [
                'description' => 'HTTP 客戶端',
                'composer_command' => 'composer require guzzlehttp/guzzle',
                'azure_compatible' => true,
                'features' => ['http_requests', 'api_calls']
            ],
            'monolog/monolog' => [
                'description' => '日誌記錄',
                'composer_command' => 'composer require monolog/monolog',
                'azure_compatible' => true,
                'features' => ['logging', 'error_tracking']
            ],
            'ramsey/uuid' => [
                'description' => 'UUID 生成',
                'composer_command' => 'composer require ramsey/uuid',
                'azure_compatible' => true,
                'features' => ['unique_ids', 'file_naming']
            ]
        ];
        
        return $result;
    }
    
    private function findComposer() {
        // 檢查系統 PATH
        $output = shell_exec('which composer 2>&1');
        if ($output && !strpos($output, 'not found')) {
            return trim($output);
        }
        
        // 檢查常見路徑
        $commonPaths = [
            '/usr/local/bin/composer',
            '/usr/bin/composer',
            'composer.phar'
        ];
        
        foreach ($commonPaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        
        return null;
    }
    
    private function getComposerVersion($composerPath) {
        $output = shell_exec("$composerPath --version 2>&1");
        if ($output) {
            preg_match('/Composer version (\d+\.\d+\.\d+)/', $output, $matches);
            return $matches[1] ?? 'unknown';
        }
        return 'unknown';
    }
    
    // 測試安裝套件
    public function testPackageInstallation($packageName) {
        if (!$this->findComposer()) {
            return ['success' => false, 'error' => 'Composer 不可用'];
        }
        
        try {
            // 建立臨時 composer.json
            $tempDir = sys_get_temp_dir() . '/composer_test_' . uniqid();
            mkdir($tempDir, 0755, true);
            
            $composerJson = [
                'name' => 'test/package-installation',
                'require' => [
                    $packageName => '*'
                ],
                'config' => [
                    'platform' => [
                        'php' => PHP_VERSION
                    ]
                ]
            ];
            
            file_put_contents($tempDir . '/composer.json', json_encode($composerJson, JSON_PRETTY_PRINT));
            
            // 執行 composer install
            $command = "cd $tempDir && composer install --no-dev --no-interaction 2>&1";
            $output = shell_exec($command);
            
            // 清理
            $this->removeDirectory($tempDir);
            
            if (strpos($output, 'error') !== false || strpos($output, 'failed') !== false) {
                return ['success' => false, 'error' => $output];
            }
            
            return ['success' => true, 'output' => $output];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    private function removeDirectory($dir) {
        if (is_dir($dir)) {
            $files = array_diff(scandir($dir), ['.', '..']);
            foreach ($files as $file) {
                $path = $dir . '/' . $file;
                if (is_dir($path)) {
                    $this->removeDirectory($path);
                } else {
                    unlink($path);
                }
            }
            rmdir($dir);
        }
    }
}

// 處理請求
$checker = new ComposerChecker();

if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'check':
            $result = $checker->checkComposerSupport();
            break;
            
        case 'test_package':
            if (isset($_GET['package'])) {
                $result = $checker->testPackageInstallation($_GET['package']);
            } else {
                $result = ['success' => false, 'error' => '請提供 package 參數'];
            }
            break;
            
        default:
            $result = ['success' => false, 'error' => '未知操作'];
    }
} else {
    $result = $checker->checkComposerSupport();
}

echo json_encode($result);
?>
