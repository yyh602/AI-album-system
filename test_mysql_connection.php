<?php
header('Content-Type: application/json');

// 測試 Azure MySQL 連線
$host = 'album.mysql.database.azure.com';
$username = 's1411131020';
$password = '{your-password}'; // 請替換為實際密碼
$database = 'album';
$port = 3306;

try {
    // 建立連線
    $link = new mysqli();
    
    // 設定 SSL
    $link->ssl_set(null, null, null, null, null);
    $link->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
    
    // 連線
    $result = $link->real_connect($host, $username, $password, $database, $port);
    
    if (!$result) {
        throw new Exception("連線失敗：" . $link->connect_error);
    }
    
    // 設定字符集
    $link->set_charset("utf8");
    
    // 測試查詢
    $query = "SHOW TABLES";
    $result = $link->query($query);
    
    $tables = [];
    if ($result) {
        while ($row = $result->fetch_row()) {
            $tables[] = $row[0];
        }
    }
    
    // 測試使用者查詢
    $userQuery = "SELECT * FROM user LIMIT 5";
    $userResult = $link->query($userQuery);
    $users = [];
    
    if ($userResult) {
        while ($row = $userResult->fetch_assoc()) {
            $users[] = $row;
        }
    }
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Azure MySQL 連線成功',
        'server_info' => $link->server_info,
        'client_info' => $link->client_info,
        'host_info' => $link->host_info,
        'protocol_version' => $link->protocol_version,
        'tables' => $tables,
        'users' => $users
    ], JSON_PRETTY_PRINT);
    
    $link->close();
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'host' => $host,
        'username' => $username,
        'database' => $database
    ], JSON_PRETTY_PRINT);
}
?>
