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
    // PostgreSQL 連接 (Neon 專用) - 使用原生 pgsql 函數
    // 如果主機名稱不完整，添加完整的域名
    if (strpos($host, '.neon.tech') === false) {
        $host = $host . '.neon.tech';
    }
    
    // 從主機名稱提取 endpoint ID - 移除 -pooler 後綴
    $hostParts = explode('.', $host);
    $endpointId = str_replace('-pooler', '', $hostParts[0]);
    
    try {
        // 使用原生 pgsql 函數連線（已驗證可行）
        $connection_string = "host=$host port=$db_port dbname=$dbname user=$db_user password=$db_pass sslmode=require options='endpoint=$endpointId'";
        $pg_connection = pg_connect($connection_string);
        
        if (!$pg_connection) {
            throw new Exception("PostgreSQL 連線失敗");
        }
        // 為了保持與現有 mysqli 程式碼相容，我們創建一個包裝類
        if (!class_exists('PgSQLWrapper')) {
            class PgSQLWrapper {
                private $pg_connection;
                
                public function __construct($pg_connection) {
                    $this->pg_connection = $pg_connection;
                }
                
                public function query($sql) {
                    $result = pg_query($this->pg_connection, $sql);
                    if (!$result) {
                        return false;
                    }
                    
                    // 創建結果包裝類
                    return new PgSQLResultWrapper($result);
                }
                
                public function prepare($sql) {
                    // 簡化的 prepare 實作
                    return new PgSQLPreparedWrapper($this->pg_connection, $sql);
                }
                
                public function connect_error() {
                    return pg_last_error($this->pg_connection) ?: false;
                }
                
                public function set_charset($charset) {
                    // PostgreSQL 使用 UTF-8 為預設編碼
                    return true;
                }
                
                public function lastInsertId() {
                    $result = pg_query($this->pg_connection, "SELECT lastval()");
                    if ($result) {
                        $row = pg_fetch_row($result);
                        return $row[0];
                    }
                    return false;
                }
                
                public function close() {
                    return pg_close($this->pg_connection);
                }
            }
            
            class PgSQLResultWrapper {
                private $result;
                
                public function __construct($result) {
                    $this->result = $result;
                }
                
                public function fetch() {
                    return pg_fetch_row($this->result);
                }
                
                public function fetch_row() {
                    return pg_fetch_row($this->result);
                }
                
                public function fetch_assoc() {
                    return pg_fetch_assoc($this->result);
                }
                
                public function fetch_array() {
                    return pg_fetch_array($this->result);
                }
                
                public function num_rows() {
                    return pg_num_rows($this->result);
                }
            }
            
            class PgSQLPreparedWrapper {
                private $pg_connection;
                private $sql;
                
                public function __construct($pg_connection, $sql) {
                    $this->pg_connection = $pg_connection;
                    $this->sql = $sql;
                }
                
                public function execute($params = []) {
                    $sql = $this->sql;
                    // 簡單的參數替換（實際專案中建議使用 pg_prepare）
                    foreach ($params as $param) {
                        $sql = preg_replace('/\?/', "'" . pg_escape_string($this->pg_connection, $param) . "'", $sql, 1);
                    }
                    return pg_query($this->pg_connection, $sql);
                }
            }
        }
        $link = new PgSQLWrapper($pg_connection);
    } catch (Exception $e) {
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
    if ($currentDb) {
        $row = $currentDb->fetch();
        error_log('【目前連線資料庫】：' . $row[0]);
    }
} else {
    $currentDb = $link->query("SELECT DATABASE()");
    if ($currentDb) {
        $row = $currentDb->fetch_row();
        error_log('【目前連線資料庫】：' . $row[0]);
    }
}
