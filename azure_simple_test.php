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
    
    // 生成簡單的 SAS Token
    $startTime = gmdate('Y-m-d\TH:i:s\Z');
    $endTime = gmdate('Y-m-d\TH:i:s\Z', strtotime('+1 hour'));
    $permissions = 'w';
    $resource = 'b';
    $version = '2020-04-08';
    $blobName = 'test-' . uniqid() . '.txt';
    $canonicalizedResource = "/blob/{$accountName}/{$containerName}/{$blobName}";
    $stringToSign = "{$permissions}\n{$startTime}\n{$endTime}\n{$canonicalizedResource}\n\n\n{$version}\n";
    $signature = base64_encode(hash_hmac('sha256', $stringToSign, base64_decode($accountKey), true));
    $sasToken = "sv={$version}&st={$startTime}&se={$endTime}&sp={$permissions}&sr={$resource}&sig=" . urlencode($signature);
    $uploadUrl = "https://{$accountName}.blob.core.windows.net/{$containerName}/{$blobName}?{$sasToken}";
    
    // 測試 Container 是否存在
    $containerUrl = "https://{$accountName}.blob.core.windows.net/{$containerName}?restype=container";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $containerUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // 10 秒超時
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $containerResponse = curl_exec($ch);
    $containerHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $containerError = curl_error($ch);
    curl_close($ch);
    
    echo json_encode([
        'success' => true,
        'accountName' => $accountName,
        'containerName' => $containerName,
        'blobName' => $blobName,
        'uploadUrl' => $uploadUrl,
        'containerTest' => [
            'url' => $containerUrl,
            'httpCode' => $containerHttpCode,
            'response' => $containerResponse,
            'error' => $containerError,
            'exists' => $containerHttpCode === 200
        ],
        'sasToken' => [
            'permissions' => $permissions,
            'resource' => $resource,
            'version' => $version,
            'stringToSign' => $stringToSign,
            'signature' => $signature,
            'token' => $sasToken
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
