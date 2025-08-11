<?php
header('Content-Type: application/json');

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
    
    // 測試參數
    $extension = 'jpeg';
    $blobName = 'test-' . uniqid() . '.' . $extension;
    
    // 生成 SAS Token - 使用多種格式測試
    $startTime = gmdate('Y-m-d\TH:i:s\Z');
    $endTime = gmdate('Y-m-d\TH:i:s\Z', strtotime('+1 hour'));
    
    $permissions = 'w';
    $resource = 'b';
    $version = '2020-04-08';
    
    // 方法 1: 標準格式
    $canonicalizedResource1 = "/blob/{$accountName}/{$containerName}/{$blobName}";
    $stringToSign1 = "{$permissions}\n{$startTime}\n{$endTime}\n{$canonicalizedResource1}\n\n\n{$version}\n";
    $signature1 = base64_encode(hash_hmac('sha256', $stringToSign1, base64_decode($accountKey), true));
    
    // 方法 2: 嘗試不同的 canonicalized resource 格式
    $canonicalizedResource2 = "/{$accountName}/{$containerName}/{$blobName}";
    $stringToSign2 = "{$permissions}\n{$startTime}\n{$endTime}\n{$canonicalizedResource2}\n\n\n{$version}\n";
    $signature2 = base64_encode(hash_hmac('sha256', $stringToSign2, base64_decode($accountKey), true));
    
    // 方法 3: 嘗試不同的換行符
    $stringToSign3 = "{$permissions}\r\n{$startTime}\r\n{$endTime}\r\n{$canonicalizedResource1}\r\n\r\n\r\n{$version}\r\n";
    $signature3 = base64_encode(hash_hmac('sha256', $stringToSign3, base64_decode($accountKey), true));
    
    // 生成 SAS Tokens
    $sasToken1 = "sv={$version}&st={$startTime}&se={$endTime}&sp={$permissions}&sr={$resource}&sig=" . urlencode($signature1);
    $sasToken2 = "sv={$version}&st={$startTime}&se={$endTime}&sp={$permissions}&sr={$resource}&sig=" . urlencode($signature2);
    $sasToken3 = "sv={$version}&st={$startTime}&se={$endTime}&sp={$permissions}&sr={$resource}&sig=" . urlencode($signature3);
    
    // 返回詳細資訊
    echo json_encode([
        'success' => true,
        'accountName' => $accountName,
        'containerName' => $containerName,
        'blobName' => $blobName,
        'startTime' => $startTime,
        'endTime' => $endTime,
        'permissions' => $permissions,
        'resource' => $resource,
        'version' => $version,
        'accountKeyLength' => strlen($accountKey),
        'accountKeyFirstChars' => substr($accountKey, 0, 10) . '...',
        'methods' => [
            'method1' => [
                'canonicalizedResource' => $canonicalizedResource1,
                'stringToSign' => $stringToSign1,
                'signature' => $signature1,
                'sasToken' => $sasToken1,
                'uploadUrl' => "https://{$accountName}.blob.core.windows.net/{$containerName}/{$blobName}?{$sasToken1}"
            ],
            'method2' => [
                'canonicalizedResource' => $canonicalizedResource2,
                'stringToSign' => $stringToSign2,
                'signature' => $signature2,
                'sasToken' => $sasToken2,
                'uploadUrl' => "https://{$accountName}.blob.core.windows.net/{$containerName}/{$blobName}?{$sasToken2}"
            ],
            'method3' => [
                'canonicalizedResource' => $canonicalizedResource1,
                'stringToSign' => $stringToSign3,
                'signature' => $signature3,
                'sasToken' => $sasToken3,
                'uploadUrl' => "https://{$accountName}.blob.core.windows.net/{$containerName}/{$blobName}?{$sasToken3}"
            ]
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
