<?php
header('Content-Type: application/json');
echo json_encode([
    'status' => 'success',
    'message' => 'Simple test works!',
    'timestamp' => date('Y-m-d H:i:s')
]);
?>
