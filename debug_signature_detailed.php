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
    
    // 方法 1: 我們目前使用的格式
    $canonicalizedResource1 = "/blob/{$accountName}/{$containerName}/{$blobName}";
    $stringToSign1 = "{$permissions}\n{$startTime}\n{$endTime}\n{$canonicalizedResource1}\n\n\n{$version}\n{$resource}";
    $signature1 = base64_encode(hash_hmac('sha256', $stringToSign1, base64_decode($accountKey), true));
    
    // 方法 2: 根據 Azure 錯誤訊息中的格式
    $stringToSign2 = "{$permissions}\n{$startTime}\n{$endTime}\n{$canonicalizedResource1}\n\n\n{$version}\n";
    $signature2 = base64_encode(hash_hmac('sha256', $stringToSign2, base64_decode($accountKey), true));
    
    // 方法 3: 嘗試不同的 canonicalized resource 格式
    $canonicalizedResource3 = "/{$accountName}/{$containerName}/{$blobName}";
    $stringToSign3 = "{$permissions}\n{$startTime}\n{$endTime}\n{$canonicalizedResource3}\n\n\n{$version}\n{$resource}";
    $signature3 = base64_encode(hash_hmac('sha256', $stringToSign3, base64_decode($accountKey), true));
    
    // 方法 4: 嘗試不同的換行符
    $stringToSign4 = "{$permissions}\r\n{$startTime}\r\n{$endTime}\r\n{$canonicalizedResource1}\r\n\r\n\r\n{$version}\r\n{$resource}";
    $signature4 = base64_encode(hash_hmac('sha256', $stringToSign4, base64_decode($accountKey), true));
    
    // 方法 5: 根據 Azure 官方文檔格式
    $stringToSign5 = "{$permissions}\n{$startTime}\n{$endTime}\n{$canonicalizedResource1}\n\n\n{$version}\n";
    $signature5 = base64_encode(hash_hmac('sha256', $stringToSign5, base64_decode($accountKey), true));
    
    // 生成 SAS Tokens
    $sasToken1 = "sv={$version}&st={$startTime}&se={$endTime}&sp={$permissions}&sr={$resource}&sig=" . urlencode($signature1);
    $sasToken2 = "sv={$version}&st={$startTime}&se={$endTime}&sp={$permissions}&sr={$resource}&sig=" . urlencode($signature2);
    $sasToken3 = "sv={$version}&st={$startTime}&se={$endTime}&sp={$permissions}&sr={$resource}&sig=" . urlencode($signature3);
    $sasToken4 = "sv={$version}&st={$startTime}&se={$endTime}&sp={$permissions}&sr={$resource}&sig=" . urlencode($signature4);
    $sasToken5 = "sv={$version}&st={$startTime}&se={$endTime}&sp={$permissions}&sr={$resource}&sig=" . urlencode($signature5);
    
    // 測試上傳 - 使用 cURL 測試每種方法
    $testContent = 'Hello Azure Storage! ' . date('Y-m-d H:i:s');
    $results = [];
    
    $methods = [
        'method1' => ['sasToken' => $sasToken1, 'stringToSign' => $stringToSign1, 'signature' => $signature1],
        'method2' => ['sasToken' => $sasToken2, 'stringToSign' => $stringToSign2, 'signature' => $signature2],
        'method3' => ['sasToken' => $sasToken3, 'stringToSign' => $stringToSign3, 'signature' => $signature3],
        'method4' => ['sasToken' => $sasToken4, 'stringToSign' => $stringToSign4, 'signature' => $signature4],
        'method5' => ['sasToken' => $sasToken5, 'stringToSign' => $stringToSign5, 'signature' => $signature5]
    ];
    
    foreach ($methods as $methodName => $method) {
        $uploadUrl = "https://{$accountName}.blob.core.windows.net/{$containerName}/{$blobName}?{$method['sasToken']}";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $uploadUrl);
        curl_setopt($ch, CURLOPT_PUT, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $testContent);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'x-ms-blob-type: BlockBlob',
            'Content-Type: text/plain',
            'Content-Length: ' . strlen($testContent)
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $results[$methodName] = [
            'httpCode' => $httpCode,
            'response' => $response,
            'success' => $httpCode === 201,
            'uploadUrl' => $uploadUrl,
            'stringToSign' => $method['stringToSign'],
            'signature' => $method['signature']
        ];
    }
    
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
        'results' => $results,
        'summary' => [
            'method1_success' => $results['method1']['success'],
            'method2_success' => $results['method2']['success'],
            'method3_success' => $results['method3']['success'],
            'method4_success' => $results['method4']['success'],
            'method5_success' => $results['method5']['success']
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
