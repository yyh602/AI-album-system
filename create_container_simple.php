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
    
    // 生成 Container 級別的 SAS Token
    $startTime = gmdate('Y-m-d\TH:i:s\Z');
    $endTime = gmdate('Y-m-d\TH:i:s\Z', strtotime('+1 hour'));
    $permissions = 'c'; // Create permission for container
    $resource = 'c'; // Container resource
    $version = '2020-04-08';
    $canonicalizedResource = "/blob/{$accountName}/{$containerName}";
    $stringToSign = "{$permissions}\n{$startTime}\n{$endTime}\n{$canonicalizedResource}\n\n\n{$version}\n";
    $signature = base64_encode(hash_hmac('sha256', $stringToSign, base64_decode($accountKey), true));
    $sasToken = "sv={$version}&st={$startTime}&se={$endTime}&sp={$permissions}&sr={$resource}&sig=" . urlencode($signature);
    
    // 建立 Container 的 URL
    $createContainerUrl = "https://{$accountName}.blob.core.windows.net/{$containerName}?restype=container&{$sasToken}";
    
    // 發送 PUT 請求建立 Container
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $createContainerUrl);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'x-ms-version: 2020-04-08',
        'x-ms-blob-public-access: blob' // 設定為 Blob 存取層級
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    // 檢查結果
    $success = $httpCode === 201 || $httpCode === 409; // 201 = Created, 409 = Already exists
    
    echo json_encode([
        'success' => $success,
        'accountName' => $accountName,
        'containerName' => $containerName,
        'createUrl' => $createContainerUrl,
        'result' => [
            'httpCode' => $httpCode,
            'response' => $response,
            'error' => $error,
            'created' => $httpCode === 201,
            'alreadyExists' => $httpCode === 409
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
