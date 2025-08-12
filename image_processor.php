<?php
header('Content-Type: application/json');

// PHP 圖片處理方案（替代 ImageMagick 命令列）
class PhpImageProcessor {
    
    public function __construct() {
        // 檢查必要的擴展
        if (!extension_loaded('gd') && !extension_loaded('imagick')) {
            throw new Exception('需要 GD 或 Imagick 擴展');
        }
    }
    
    // 圖片切割
    public function cropImage($sourceUrl, $x, $y, $width, $height) {
        try {
            // 下載圖片
            $imageContent = $this->downloadImage($sourceUrl);
            $tempFile = $this->createTempFile($imageContent);
            
            // 使用 GD 或 Imagick 處理
            if (extension_loaded('imagick')) {
                $result = $this->cropWithImagick($tempFile, $x, $y, $width, $height);
            } else {
                $result = $this->cropWithGD($tempFile, $x, $y, $width, $height);
            }
            
            $this->cleanupTempFile($tempFile);
            return $result;
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    // 圖片縮放
    public function resizeImage($sourceUrl, $newWidth, $newHeight) {
        try {
            $imageContent = $this->downloadImage($sourceUrl);
            $tempFile = $this->createTempFile($imageContent);
            
            if (extension_loaded('imagick')) {
                $result = $this->resizeWithImagick($tempFile, $newWidth, $newHeight);
            } else {
                $result = $this->resizeWithGD($tempFile, $newWidth, $newHeight);
            }
            
            $this->cleanupTempFile($tempFile);
            return $result;
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    // 使用 Imagick 切割
    private function cropWithImagick($tempFile, $x, $y, $width, $height) {
        $imagick = new Imagick($tempFile);
        $imagick->cropImage($width, $height, $x, $y);
        
        $outputFile = tempnam(sys_get_temp_dir(), 'crop_');
        $imagick->writeImage($outputFile);
        
        $result = [
            'success' => true,
            'output_file' => $outputFile,
            'width' => $width,
            'height' => $height
        ];
        
        $imagick->destroy();
        return $result;
    }
    
    // 使用 GD 切割
    private function cropWithGD($tempFile, $x, $y, $width, $height) {
        $imageInfo = getimagesize($tempFile);
        $imageType = $imageInfo[2];
        
        switch ($imageType) {
            case IMAGETYPE_JPEG:
                $source = imagecreatefromjpeg($tempFile);
                break;
            case IMAGETYPE_PNG:
                $source = imagecreatefrompng($tempFile);
                break;
            case IMAGETYPE_GIF:
                $source = imagecreatefromgif($tempFile);
                break;
            default:
                throw new Exception('不支援的圖片格式');
        }
        
        $cropped = imagecreatetruecolor($width, $height);
        imagecopy($cropped, $source, 0, 0, $x, $y, $width, $height);
        
        $outputFile = tempnam(sys_get_temp_dir(), 'crop_');
        imagejpeg($cropped, $outputFile, 90);
        
        imagedestroy($source);
        imagedestroy($cropped);
        
        return [
            'success' => true,
            'output_file' => $outputFile,
            'width' => $width,
            'height' => $height
        ];
    }
    
    // 使用 Imagick 縮放
    private function resizeWithImagick($tempFile, $newWidth, $newHeight) {
        $imagick = new Imagick($tempFile);
        $imagick->resizeImage($newWidth, $newHeight, Imagick::FILTER_LANCZOS, 1);
        
        $outputFile = tempnam(sys_get_temp_dir(), 'resize_');
        $imagick->writeImage($outputFile);
        
        $result = [
            'success' => true,
            'output_file' => $outputFile,
            'width' => $newWidth,
            'height' => $newHeight
        ];
        
        $imagick->destroy();
        return $result;
    }
    
    // 使用 GD 縮放
    private function resizeWithGD($tempFile, $newWidth, $newHeight) {
        $imageInfo = getimagesize($tempFile);
        $imageType = $imageInfo[2];
        
        switch ($imageType) {
            case IMAGETYPE_JPEG:
                $source = imagecreatefromjpeg($tempFile);
                break;
            case IMAGETYPE_PNG:
                $source = imagecreatefrompng($tempFile);
                break;
            case IMAGETYPE_GIF:
                $source = imagecreatefromgif($tempFile);
                break;
            default:
                throw new Exception('不支援的圖片格式');
        }
        
        $resized = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled($resized, $source, 0, 0, 0, 0, $newWidth, $newHeight, imagesx($source), imagesy($source));
        
        $outputFile = tempnam(sys_get_temp_dir(), 'resize_');
        imagejpeg($resized, $outputFile, 90);
        
        imagedestroy($source);
        imagedestroy($resized);
        
        return [
            'success' => true,
            'output_file' => $outputFile,
            'width' => $newWidth,
            'height' => $newHeight
        ];
    }
    
    private function downloadImage($url) {
        $context = stream_context_create([
            'http' => [
                'timeout' => 30,
                'user_agent' => 'Mozilla/5.0 (compatible; AI-Album-System/1.0)'
            ]
        ]);
        
        $content = file_get_contents($url, false, $context);
        if ($content === false) {
            throw new Exception('無法下載圖片');
        }
        
        return $content;
    }
    
    private function createTempFile($content) {
        $tempFile = tempnam(sys_get_temp_dir(), 'img_');
        if (file_put_contents($tempFile, $content) === false) {
            throw new Exception('無法寫入臨時檔案');
        }
        
        return $tempFile;
    }
    
    private function cleanupTempFile($tempFile) {
        if (file_exists($tempFile)) {
            unlink($tempFile);
        }
    }
}

// 測試
if (isset($_POST['action'])) {
    try {
        $processor = new PhpImageProcessor();
        
        switch ($_POST['action']) {
            case 'crop':
                $result = $processor->cropImage(
                    $_POST['sourceUrl'],
                    intval($_POST['x']),
                    intval($_POST['y']),
                    intval($_POST['width']),
                    intval($_POST['height'])
                );
                break;
                
            case 'resize':
                $result = $processor->resizeImage(
                    $_POST['sourceUrl'],
                    intval($_POST['width']),
                    intval($_POST['height'])
                );
                break;
                
            default:
                $result = ['success' => false, 'error' => '未知操作'];
        }
        
        echo json_encode($result);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => '請提供 action 參數',
        'usage' => [
            'crop' => 'POST action=crop&sourceUrl=URL&x=0&y=0&width=100&height=100',
            'resize' => 'POST action=resize&sourceUrl=URL&width=800&height=600'
        ]
    ]);
}
?>
