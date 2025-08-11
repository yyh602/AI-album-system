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
    
    // 生成 SAS Token - 測試不同的權限組合
    $startTime = gmdate('Y-m-d\TH:i:s\Z');
    $endTime = gmdate('Y-m-d\TH:i:s\Z', strtotime('+1 hour'));
    
    $resource = 'b';
    $version = '2020-04-08';
    $canonicalizedResource = "/blob/{$accountName}/{$containerName}/{$blobName}";
    
    // 測試不同的權限組合
    $permissionTests = [
        'w' => 'Write only',
        'wa' => 'Write + Add',
        'wc' => 'Write + Create',
        'wac' => 'Write + Add + Create',
        'rw' => 'Read + Write',
        'rwa' => 'Read + Write + Add',
        'rwc' => 'Read + Write + Create',
        'rwac' => 'Read + Write + Add + Create'
    ];
    
    $results = [];
    
    foreach ($permissionTests as $permissions => $description) {
        $stringToSign = "{$permissions}\n{$startTime}\n{$endTime}\n{$canonicalizedResource}\n\n\n{$version}\n";
        $signature = base64_encode(hash_hmac('sha256', $stringToSign, base64_decode($accountKey), true));
        $sasToken = "sv={$version}&st={$startTime}&se={$endTime}&sp={$permissions}&sr={$resource}&sig=" . urlencode($signature);
        $uploadUrl = "https://{$accountName}.blob.core.windows.net/{$containerName}/{$blobName}?{$sasToken}";
        
        // 測試上傳
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
        
        $results[$permissions] = [
            'description' => $description,
            'httpCode' => $httpCode,
            'response' => $response,
            'success' => $httpCode === 201,
            'uploadUrl' => $uploadUrl,
            'stringToSign' => $stringToSign,
            'signature' => $signature,
            'sasToken' => $sasToken
        ];
    }
    
    // 返回結果
    echo json_encode([
        'success' => true,
        'accountName' => $accountName,
        'containerName' => $containerName,
        'blobName' => $blobName,
        'testContent' => $testContent,
        'results' => $results,
        'summary' => [
            'total_tests' => count($permissionTests),
            'successful_tests' => count(array_filter($results, function($r) { return $r['success']; })),
            'failed_tests' => count(array_filter($results, function($r) { return !$r['success']; }))
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
