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
    
    // 測試 Storage Account 基本連接
    $accountUrl = "https://{$accountName}.blob.core.windows.net/?restype=account&comp=properties";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $accountUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'x-ms-version: 2020-04-08',
        'x-ms-date: ' . gmdate('D, d M Y H:i:s T')
    ]);
    
    $accountResponse = curl_exec($ch);
    $accountHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $accountError = curl_error($ch);
    curl_close($ch);
    
    // 測試 Container 列表
    $listContainersUrl = "https://{$accountName}.blob.core.windows.net/?restype=container&comp=list";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $listContainersUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'x-ms-version: 2020-04-08',
        'x-ms-date: ' . gmdate('D, d M Y H:i:s T')
    ]);
    
    $listResponse = curl_exec($ch);
    $listHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $listError = curl_error($ch);
    curl_close($ch);
    
    // 檢查 Container 是否存在於列表中
    $containerExists = false;
    if ($listHttpCode === 200) {
        $xml = simplexml_load_string($listResponse);
        if ($xml && isset($xml->Containers)) {
            foreach ($xml->Containers->Container as $container) {
                if ((string)$container->Name === $containerName) {
                    $containerExists = true;
                    break;
                }
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'accountName' => $accountName,
        'containerName' => $containerName,
        'accountTest' => [
            'url' => $accountUrl,
            'httpCode' => $accountHttpCode,
            'response' => $accountResponse,
            'error' => $accountError,
            'accessible' => $accountHttpCode === 200
        ],
        'listContainersTest' => [
            'url' => $listContainersUrl,
            'httpCode' => $listHttpCode,
            'response' => $listResponse,
            'error' => $listError,
            'accessible' => $listHttpCode === 200,
            'containerExists' => $containerExists
        ],
        'diagnosis' => [
            'accountAccessible' => $accountHttpCode === 200,
            'canListContainers' => $listHttpCode === 200,
            'containerExists' => $containerExists,
            'needsContainerCreation' => !$containerExists
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
