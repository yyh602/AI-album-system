<?php
session_start();
header('Content-Type: application/json');

// 檢查登入狀態
if (!isset($_SESSION['username'])) {
    echo json_encode(['error' => '請先登入']);
    exit();
}

// 檢查請求方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => '無效的請求方法']);
    exit();
}

try {
    // 取得環境變數
    $connectionString = getenv('AZURE_STORAGE_CONNECTION_STRING');
    $containerName = getenv('AZURE_STORAGE_CONTAINER_NAME') ?: 'photos';
    
    if (!$connectionString) {
        throw new Exception('Azure Storage connection string not found');
    }
    
    // 解析連接字串
    $accountName = '';
    $accountKey = '';
    $parts = explode(';', $connectionString);
    foreach ($parts as $part) {
        if (strpos($part, 'AccountName=') === 0) {
            $accountName = substr($part, 12);
        } elseif (strpos($part, 'AccountKey=') === 0) {
            $accountKey = substr($part, 11);
        }
    }
    
    if (!$accountName || !$accountKey) {
        throw new Exception('Invalid connection string');
    }
    
    // 生成 Blob 名稱
    $extension = $_POST['extension'] ?? 'jpg';
    $blobName = uniqid() . '.' . $extension;
    
    // 生成 SAS Token
    $startTime = gmdate('Y-m-d\TH:i:s\Z');
    $endTime = gmdate('Y-m-d\TH:i:s\Z', strtotime('+1 hour'));
    
    $permissions = 'w';
    $resource = 'b';
    $version = '2020-04-08';
    
    $canonicalizedResource = "/blob/{$accountName}/{$containerName}/{$blobName}";
    $stringToSign = "{$permissions}\n{$startTime}\n{$endTime}\n{$canonicalizedResource}\n\n\n{$version}\n";
    
    $signature = base64_encode(hash_hmac('sha256', $stringToSign, base64_decode($accountKey), true));
    $sasToken = "sv={$version}&st={$startTime}&se={$endTime}&sp={$permissions}&sr={$resource}&sig=" . urlencode($signature);
    
    // 返回上傳資訊
    echo json_encode([
        'success' => true,
        'uploadUrl' => "https://{$accountName}.blob.core.windows.net/{$containerName}/{$blobName}?{$sasToken}",
        'blobName' => $blobName,
        'blobUrl' => "https://{$accountName}.blob.core.windows.net/{$containerName}/{$blobName}"
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
