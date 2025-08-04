<?php
class DBWrapper {
    private $connection;
    private $type;
    
    public function __construct() {
        $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
        $dbname = $_ENV['DB_NAME'] ?? 'myproject';
        $db_user = $_ENV['DB_USER'] ?? 'root';
        $db_pass = $_ENV['DB_PASS'] ?? 'MariaDB1688.';
        $db_port = $_ENV['DB_PORT'] ?? '3306';
        
        if ($db_port == '5432') {
            // PostgreSQL
            $this->type = 'postgresql';
            $dsn = "pgsql:host=$host;port=$db_port;dbname=$dbname;user=$db_user;password=$db_pass";
            $this->connection = new PDO($dsn);
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } else {
            // MySQL
            $this->type = 'mysql';
            $this->connection = new mysqli($host, $db_user, $db_pass, $dbname, $db_port);
            if ($this->connection->connect_error) {
                throw new Exception("MySQL 連線失敗：" . $this->connection->connect_error);
            }
            $this->connection->set_charset("utf8");
        }
    }
    
    public function query($sql) {
        if ($this->type == 'postgresql') {
            return $this->connection->query($sql);
        } else {
            return $this->connection->query($sql);
        }
    }
    
    public function prepare($sql) {
        if ($this->type == 'postgresql') {
            return $this->connection->prepare($sql);
        } else {
            return $this->connection->prepare($sql);
        }
    }
    
    public function close() {
        if ($this->type == 'postgresql') {
            $this->connection = null;
        } else {
            $this->connection->close();
        }
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function getType() {
        return $this->type;
    }
}
?> 