<?php
class AzureStorage {
    private $accountName;
    private $accountKey;
    private $containerName;
    
    public function __construct() {
        $connectionString = getenv('AZURE_STORAGE_CONNECTION_STRING');
        $this->containerName = getenv('AZURE_STORAGE_CONTAINER_NAME') ?: 'photos';
        
        if (!$connectionString) {
            throw new Exception('Azure Storage connection string not found');
        }
        
        // 解析連接字串
        $this->parseConnectionString($connectionString);
    }
    
    private function parseConnectionString($connectionString) {
        $parts = explode(';', $connectionString);
        foreach ($parts as $part) {
            if (strpos($part, 'AccountName=') === 0) {
                $this->accountName = substr($part, 12);
            } elseif (strpos($part, 'AccountKey=') === 0) {
                $this->accountKey = substr($part, 11);
            }
        }
        
        if (!$this->accountName || !$this->accountKey) {
            throw new Exception('Invalid connection string');
        }
    }
    
    public function uploadFromTemp($tempPath, $originalName) {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $blobName = uniqid() . '.' . $extension;
        
        $url = $this->getBlobUrl($blobName);
        $headers = $this->getAuthHeaders('PUT', $blobName);
        
        $fileContent = file_get_contents($tempPath);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_PUT, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fileContent);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 201) {
            return $url;
        } else {
            throw new Exception('Upload failed with HTTP code: ' . $httpCode);
        }
    }
    
    private function getBlobUrl($blobName) {
        return "https://{$this->accountName}.blob.core.windows.net/{$this->containerName}/{$blobName}";
    }
    
    private function getAuthHeaders($method, $blobName) {
        $date = gmdate('D, d M Y H:i:s T');
        $contentLength = 0;
        $contentType = 'application/octet-stream';
        
        $canonicalizedHeaders = "x-ms-blob-type:BlockBlob\nx-ms-date:{$date}\nx-ms-version:2020-04-08\n";
        $canonicalizedResource = "/{$this->accountName}/{$this->containerName}/{$blobName}";
        
        $stringToSign = "{$method}\n\n\n{$contentLength}\n\n{$contentType}\n\n\n\n\n\n\n\n{$canonicalizedHeaders}{$canonicalizedResource}";
        
        $signature = base64_encode(hash_hmac('sha256', $stringToSign, base64_decode($this->accountKey), true));
        
        return [
            "Authorization: SharedKey {$this->accountName}:{$signature}",
            "x-ms-blob-type: BlockBlob",
            "x-ms-date: {$date}",
            "x-ms-version: 2020-04-08",
            "Content-Type: {$contentType}",
            "Content-Length: {$contentLength}"
        ];
    }
    
    public function deleteBlob($blobName) {
        $url = $this->getBlobUrl($blobName);
        $headers = $this->getAuthHeaders('DELETE', $blobName);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $httpCode === 202;
    }
}
?>
