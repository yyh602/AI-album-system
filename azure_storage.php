<?php
require_once 'vendor/autoload.php';

use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use MicrosoftAzure\Storage\Common\Exceptions\ServiceException;

class AzureStorage {
    private $blobClient;
    private $containerName;
    
    public function __construct() {
        $connectionString = getenv('AZURE_STORAGE_CONNECTION_STRING');
        $this->containerName = getenv('AZURE_STORAGE_CONTAINER_NAME') ?: 'photos';
        
        if (!$connectionString) {
            throw new Exception('Azure Storage connection string not found');
        }
        
        $this->blobClient = BlobRestProxy::createBlobService($connectionString);
    }
    
    public function uploadFile($filePath, $blobName) {
        try {
            $content = fopen($filePath, "r");
            $this->blobClient->createBlockBlob($this->containerName, $blobName, $content);
            fclose($content);
            
            // 返回 Blob URL
            return $this->getBlobUrl($blobName);
        } catch (ServiceException $e) {
            throw new Exception('Upload failed: ' . $e->getMessage());
        }
    }
    
    public function uploadFromTemp($tempPath, $originalName) {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $blobName = uniqid() . '.' . $extension;
        
        return $this->uploadFile($tempPath, $blobName);
    }
    
    public function getBlobUrl($blobName) {
        $accountName = $this->getAccountName();
        return "https://{$accountName}.blob.core.windows.net/{$this->containerName}/{$blobName}";
    }
    
    private function getAccountName() {
        $connectionString = getenv('AZURE_STORAGE_CONNECTION_STRING');
        if (preg_match('/AccountName=([^;]+)/', $connectionString, $matches)) {
            return $matches[1];
        }
        throw new Exception('Cannot extract account name from connection string');
    }
    
    public function deleteBlob($blobName) {
        try {
            $this->blobClient->deleteBlob($this->containerName, $blobName);
            return true;
        } catch (ServiceException $e) {
            return false;
        }
    }
}
?>
