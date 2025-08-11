<?php
header('Content-Type: application/json');

echo json_encode([
    'status' => 'PHP is working',
    'time' => date('Y-m-d H:i:s'),
    'connection_string_exists' => !empty(getenv('AZURE_STORAGE_CONNECTION_STRING')),
    'container_name' => getenv('AZURE_STORAGE_CONTAINER_NAME') ?: 'photos'
]);
?>
