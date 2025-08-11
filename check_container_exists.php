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
    
    // 生成 Container 級別的 SAS Token 來檢查 Container
    $startTime = gmdate('Y-m-d\TH:i:s\Z');
    $endTime = gmdate('Y-m-d\TH:i:s\Z', strtotime('+1 hour'));
    $permissions = 'r'; // Read permission
    $resource = 'c'; // Container resource
    $version = '2020-04-08';
    $canonicalizedResource = "/blob/{$accountName}/{$containerName}";
    $stringToSign = "{$permissions}\n{$startTime}\n{$endTime}\n{$canonicalizedResource}\n\n\n{$version}\n";
    $signature = base64_encode(hash_hmac('sha256', $stringToSign, base64_decode($accountKey), true));
    $sasToken = "sv={$version}&st={$startTime}&se={$endTime}&sp={$permissions}&sr={$resource}&sig=" . urlencode($signature);
    
    // 檢查 Container 的 URL
    $checkContainerUrl = "https://{$accountName}.blob.core.windows.net/{$containerName}?restype=container&{$sasToken}";
    
    // 發送 GET 請求檢查 Container
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $checkContainerUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HEADER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    $error = curl_error($ch);
    curl_close($ch);
    
    // 檢查結果
    $containerExists = $httpCode === 200;
    
    echo json_encode([
        'success' => true,
        'accountName' => $accountName,
        'containerName' => $containerName,
        'checkUrl' => $checkContainerUrl,
        'sasToken' => $sasToken,
        'result' => [
            'httpCode' => $httpCode,
            'headers' => $headers,
            'body' => $body,
            'error' => $error,
            'containerExists' => $containerExists
        ],
        'diagnosis' => [
            'containerExists' => $containerExists,
            'canAccessContainer' => $httpCode === 200,
            'accessDenied' => $httpCode === 403,
            'notFound' => $httpCode === 404,
            'otherError' => $httpCode !== 200 && $httpCode !== 403 && $httpCode !== 404
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
