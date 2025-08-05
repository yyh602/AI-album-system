<?php

// 從環境變數獲取資料庫配置
$host = $_ENV['DB_HOST'] ?? '127.0.0.1';
$dbname = $_ENV['DB_NAME'] ?? 'myproject';
$db_user = $_ENV['DB_USER'] ?? 'root';
$db_pass = $_ENV['DB_PASS'] ?? 'MariaDB1688.';
$db_port = $_ENV['DB_PORT'] ?? '3306';
$db_type = $_ENV['DB_TYPE'] ?? 'postgresql'; // 強制使用 PostgreSQL

// 根據資料庫類型選擇連接方式
if ($db_type === 'postgresql' || $db_type === 'pgsql') {
    // PostgreSQL 連接
    $dsn = "pgsql:host=$host;port=$db_port;dbname=$dbname;user=$db_user;password=$db_pass";
    try {
        $link = new PDO($dsn);
        $link->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // 為了保持與現有 mysqli 程式碼相容，我們創建一個包裝類
        if (!class_exists('PDOWrapper')) {
            class PDOWrapper {
                private $pdo;
                
                public function __construct($pdo) {
                    $this->pdo = $pdo;
                }
                
                public function prepare($sql) {
                    return $this->pdo->prepare($sql);
                }
                
                public function query($sql) {
                    return $this->pdo->query($sql);
                }
                
                public function connect_error() {
                    return false; // PDO 會拋出異常，所以沒有 connect_error
                }
                
                public function set_charset($charset) {
                    // PostgreSQL 不需要設定字符集
                    return true;
                }
                
                public function lastInsertId() {
                    return $this->pdo->lastInsertId();
                }
            }
        }
        $link = new PDOWrapper($link);
    } catch (PDOException $e) {
        die("❌ PostgreSQL 資料庫連線失敗：" . $e->getMessage());
    }
} else {
    // MySQL/MariaDB 連接（保持原有邏輯）
    $link = new mysqli($host, $db_user, $db_pass, $dbname, $db_port);
    
    // 檢查連線是否成功
    if ($link->connect_error) {
        error_log("❌ MySQL 資料庫連線失敗：" . $link->connect_error);
        // 不要 die()，讓應用程式繼續運行
        $link = null;
    }
    
    // 設定字符集
    $link->set_charset("utf8");
}

// 紀錄目前使用的資料庫
if ($db_type === 'postgresql' || $db_type === 'pgsql') {
    $currentDb = $link->query("SELECT current_database()");
    $row = $currentDb->fetch();
    error_log('【目前連線資料庫】：' . $row[0]);
} else {
    $currentDb = $link->query("SELECT DATABASE()");
    $row = $currentDb->fetch_row();
    error_log('【目前連線資料庫】：' . $row[0]);
}
?>
