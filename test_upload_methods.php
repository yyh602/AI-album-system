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
    $extension = 'txt';
    $blobName = 'test-' . uniqid() . '.' . $extension;
    $testContent = 'Hello Azure Storage! ' . date('Y-m-d H:i:s');
    
    // 生成 SAS Token - 使用三種方法
    $startTime = gmdate('Y-m-d\TH:i:s\Z');
    $endTime = gmdate('Y-m-d\TH:i:s\Z', strtotime('+1 hour'));
    
    $permissions = 'w';
    $resource = 'b';
    $version = '2020-04-08';
    
    // 方法 1: 標準格式 (我們目前使用的)
    $canonicalizedResource1 = "/blob/{$accountName}/{$containerName}/{$blobName}";
    $stringToSign1 = "{$permissions}\n{$startTime}\n{$endTime}\n{$canonicalizedResource1}\n\n\n{$version}\n";
    $signature1 = base64_encode(hash_hmac('sha256', $stringToSign1, base64_decode($accountKey), true));
    $sasToken1 = "sv={$version}&st={$startTime}&se={$endTime}&sp={$permissions}&sr={$resource}&sig=" . urlencode($signature1);
    $uploadUrl1 = "https://{$accountName}.blob.core.windows.net/{$containerName}/{$blobName}?{$sasToken1}";
    
    // 方法 2: 不同的 canonicalized resource 格式
    $canonicalizedResource2 = "/{$accountName}/{$containerName}/{$blobName}";
    $stringToSign2 = "{$permissions}\n{$startTime}\n{$endTime}\n{$canonicalizedResource2}\n\n\n{$version}\n";
    $signature2 = base64_encode(hash_hmac('sha256', $stringToSign2, base64_decode($accountKey), true));
    $sasToken2 = "sv={$version}&st={$startTime}&se={$endTime}&sp={$permissions}&sr={$resource}&sig=" . urlencode($signature2);
    $uploadUrl2 = "https://{$accountName}.blob.core.windows.net/{$containerName}/{$blobName}?{$sasToken2}";
    
    // 方法 3: 不同的換行符
    $stringToSign3 = "{$permissions}\r\n{$startTime}\r\n{$endTime}\r\n{$canonicalizedResource1}\r\n\r\n\r\n{$version}\r\n";
    $signature3 = base64_encode(hash_hmac('sha256', $stringToSign3, base64_decode($accountKey), true));
    $sasToken3 = "sv={$version}&st={$startTime}&se={$endTime}&sp={$permissions}&sr={$resource}&sig=" . urlencode($signature3);
    $uploadUrl3 = "https://{$accountName}.blob.core.windows.net/{$containerName}/{$blobName}?{$sasToken3}";
    
    // 測試上傳 - 使用 cURL
    $results = [];
    
    // 測試方法 1
    $ch1 = curl_init();
    curl_setopt($ch1, CURLOPT_URL, $uploadUrl1);
    curl_setopt($ch1, CURLOPT_PUT, true);
    curl_setopt($ch1, CURLOPT_POSTFIELDS, $testContent);
    curl_setopt($ch1, CURLOPT_HTTPHEADER, [
        'x-ms-blob-type: BlockBlob',
        'Content-Type: text/plain',
        'Content-Length: ' . strlen($testContent)
    ]);
    curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch1, CURLOPT_SSL_VERIFYPEER, false);
    
    $response1 = curl_exec($ch1);
    $httpCode1 = curl_getinfo($ch1, CURLINFO_HTTP_CODE);
    curl_close($ch1);
    
    $results['method1'] = [
        'httpCode' => $httpCode1,
        'response' => $response1,
        'success' => $httpCode1 === 201,
        'uploadUrl' => $uploadUrl1
    ];
    
    // 測試方法 2
    $ch2 = curl_init();
    curl_setopt($ch2, CURLOPT_URL, $uploadUrl2);
    curl_setopt($ch2, CURLOPT_PUT, true);
    curl_setopt($ch2, CURLOPT_POSTFIELDS, $testContent);
    curl_setopt($ch2, CURLOPT_HTTPHEADER, [
        'x-ms-blob-type: BlockBlob',
        'Content-Type: text/plain',
        'Content-Length: ' . strlen($testContent)
    ]);
    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
    
    $response2 = curl_exec($ch2);
    $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
    curl_close($ch2);
    
    $results['method2'] = [
        'httpCode' => $httpCode2,
        'response' => $response2,
        'success' => $httpCode2 === 201,
        'uploadUrl' => $uploadUrl2
    ];
    
    // 測試方法 3
    $ch3 = curl_init();
    curl_setopt($ch3, CURLOPT_URL, $uploadUrl3);
    curl_setopt($ch3, CURLOPT_PUT, true);
    curl_setopt($ch3, CURLOPT_POSTFIELDS, $testContent);
    curl_setopt($ch3, CURLOPT_HTTPHEADER, [
        'x-ms-blob-type: BlockBlob',
        'Content-Type: text/plain',
        'Content-Length: ' . strlen($testContent)
    ]);
    curl_setopt($ch3, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch3, CURLOPT_SSL_VERIFYPEER, false);
    
    $response3 = curl_exec($ch3);
    $httpCode3 = curl_getinfo($ch3, CURLINFO_HTTP_CODE);
    curl_close($ch3);
    
    $results['method3'] = [
        'httpCode' => $httpCode3,
        'response' => $response3,
        'success' => $httpCode3 === 201,
        'uploadUrl' => $uploadUrl3
    ];
    
    // 返回結果
    echo json_encode([
        'success' => true,
        'testContent' => $testContent,
        'blobName' => $blobName,
        'results' => $results,
        'summary' => [
            'method1_success' => $httpCode1 === 201,
            'method2_success' => $httpCode2 === 201,
            'method3_success' => $httpCode3 === 201
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
