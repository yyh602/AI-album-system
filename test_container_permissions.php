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
    
    // 測試 1: 檢查 Container 是否存在和權限
    $containerUrl = "https://{$accountName}.blob.core.windows.net/{$containerName}?restype=container";
    
    // 生成 Container 的 SAS Token
    $startTime = gmdate('Y-m-d\TH:i:s\Z');
    $endTime = gmdate('Y-m-d\TH:i:s\Z', strtotime('+1 hour'));
    
    $permissions = 'r'; // 讀取權限來檢查 Container
    $resource = 'c'; // container
    $version = '2020-04-08';
    
    $canonicalizedResource = "/blob/{$accountName}/{$containerName}";
    $stringToSign = "{$permissions}\n{$startTime}\n{$endTime}\n{$canonicalizedResource}\n\n\n{$version}\n";
    
    $signature = base64_encode(hash_hmac('sha256', $stringToSign, base64_decode($accountKey), true));
    $sasToken = "sv={$version}&st={$startTime}&se={$endTime}&sp={$permissions}&sr={$resource}&sig=" . urlencode($signature);
    
    $containerUrlWithSas = $containerUrl . "&{$sasToken}";
    
    // 測試 Container 存取
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $containerUrlWithSas);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // 測試 2: 嘗試使用不同的權限組合
    $testPermissions = ['w', 'rw', 'a', 'c', 'u', 'p'];
    $permissionResults = [];
    
    foreach ($testPermissions as $perm) {
        $testStringToSign = "{$perm}\n{$startTime}\n{$endTime}\n{$canonicalizedResource}\n\n\n{$version}\n";
        $testSignature = base64_encode(hash_hmac('sha256', $testStringToSign, base64_decode($accountKey), true));
        $testSasToken = "sv={$version}&st={$startTime}&se={$endTime}&sp={$perm}&sr={$resource}&sig=" . urlencode($testSignature);
        
        $permissionResults[$perm] = [
            'permission' => $perm,
            'sasToken' => $testSasToken,
            'stringToSign' => $testStringToSign
        ];
    }
    
    // 返回結果
    echo json_encode([
        'success' => true,
        'accountName' => $accountName,
        'containerName' => $containerName,
        'containerUrl' => $containerUrl,
        'containerUrlWithSas' => $containerUrlWithSas,
        'containerTest' => [
            'httpCode' => $httpCode,
            'response' => $response,
            'success' => $httpCode === 200
        ],
        'permissionTests' => $permissionResults,
        'connectionStringParts' => $parts
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
