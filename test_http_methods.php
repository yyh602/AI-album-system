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
    $uploadUrl = "https://{$accountName}.blob.core.windows.net/{$containerName}/{$blobName}?{$sasToken}";
    
    // 測試不同的 HTTP 方法
    $methods = [
        'PUT' => 'Standard PUT method',
        'POST' => 'POST method (alternative)',
        'GET' => 'GET method (for testing)',
        'HEAD' => 'HEAD method (for testing)'
    ];
    
    $results = [];
    
    foreach ($methods as $method => $description) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $uploadUrl);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        
        if ($method === 'PUT' || $method === 'POST') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $testContent);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'x-ms-blob-type: BlockBlob',
                'Content-Type: text/plain',
                'Content-Length: ' . strlen($testContent)
            ]);
        }
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HEADER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);
        curl_close($ch);
        
        $results[$method] = [
            'description' => $description,
            'httpCode' => $httpCode,
            'headers' => $headers,
            'body' => $body,
            'success' => $httpCode === 201 || $httpCode === 200,
            'uploadUrl' => $uploadUrl
        ];
    }
    
    // 測試 Container 資訊
    $containerUrl = "https://{$accountName}.blob.core.windows.net/{$containerName}?restype=container";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $containerUrl);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HEADER, true);
    
    $containerResponse = curl_exec($ch);
    $containerHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $containerHeaderSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $containerHeaders = substr($containerResponse, 0, $containerHeaderSize);
    $containerBody = substr($containerResponse, $containerHeaderSize);
    curl_close($ch);
    
    // 返回結果
    echo json_encode([
        'success' => true,
        'accountName' => $accountName,
        'containerName' => $containerName,
        'blobName' => $blobName,
        'testContent' => $testContent,
        'sasToken' => $sasToken,
        'uploadUrl' => $uploadUrl,
        'methods' => $results,
        'containerInfo' => [
            'url' => $containerUrl,
            'httpCode' => $containerHttpCode,
            'headers' => $containerHeaders,
            'body' => $containerBody,
            'exists' => $containerHttpCode === 200
        ],
        'summary' => [
            'total_methods' => count($methods),
            'successful_methods' => count(array_filter($results, function($r) { return $r['success']; })),
            'failed_methods' => count(array_filter($results, function($r) { return !$r['success']; })),
            'container_exists' => $containerHttpCode === 200
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
