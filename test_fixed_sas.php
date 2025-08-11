<?php
header('Content-Type: application/json');

try {
    // 檢查環境變數
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
    
    // 生成修正後的 SAS Token
    $startTime = gmdate('Y-m-d\TH:i:s\Z');
    $endTime = gmdate('Y-m-d\TH:i:s\Z', strtotime('+1 hour'));
    $permissions = 'w';
    $resource = 'b';
    $version = '2020-04-08';
    $blobName = 'test-' . uniqid() . '.txt';
    $canonicalizedResource = "/blob/{$accountName}/{$containerName}/{$blobName}";
    
    // 修正的 string to sign
    $stringToSign = "{$permissions}\n{$startTime}\n{$endTime}\n{$canonicalizedResource}\n\n\n\n{$version}\n{$resource}\n\n\n\n\n\n";
    $signature = base64_encode(hash_hmac('sha256', $stringToSign, base64_decode($accountKey), true));
    $sasToken = "sv={$version}&st={$startTime}&se={$endTime}&sp={$permissions}&sr={$resource}&sig=" . urlencode($signature);
    $uploadUrl = "https://{$accountName}.blob.core.windows.net/{$containerName}/{$blobName}?{$sasToken}";
    
    // 測試上傳
    $testContent = 'Hello Azure Storage! ' . date('Y-m-d H:i:s');
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $uploadUrl);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $testContent);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'x-ms-blob-type: BlockBlob',
        'Content-Type: text/plain',
        'Content-Length: ' . strlen($testContent)
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    echo json_encode([
        'success' => $httpCode === 201,
        'accountName' => $accountName,
        'containerName' => $containerName,
        'blobName' => $blobName,
        'uploadUrl' => $uploadUrl,
        'stringToSign' => $stringToSign,
        'signature' => $signature,
        'sasToken' => $sasToken,
        'result' => [
            'httpCode' => $httpCode,
            'response' => $response,
            'error' => $error,
            'uploaded' => $httpCode === 201
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
