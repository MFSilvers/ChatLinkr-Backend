<?php

class Database {
    private $conn;
    
    public function __construct() {
        // Get database configuration from environment variables (Railway)
        $host = $_ENV['DB_HOST'] ?? getenv('DB_HOST');
        $port = $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?? 5432;
        $dbname = $_ENV['DB_NAME'] ?? getenv('DB_NAME');
        $user = $_ENV['DB_USER'] ?? getenv('DB_USER');
        $password = $_ENV['DB_PASS'] ?? getenv('DB_PASS');
        $sslmode = $_ENV['DB_SSLMODE'] ?? getenv('DB_SSLMODE') ?? 'require';
        
        // Validate required parameters
        if (!$host || !$dbname || !$user) {
            throw new Exception("Database configuration incomplete. Please set DB_HOST, DB_NAME, and DB_USER environment variables");
        }
        
        try {
            // Build DSN with configurable SSL mode and force IPv4
            $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=$sslmode";
            $this->conn = new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false,
                PDO::ATTR_TIMEOUT => 30,
                PDO::ATTR_STRINGIFY_FETCHES => false
            ]);
        } catch (PDOException $e) {
            throw new Exception("Connection failed: " . $e->getMessage());
        }
    }
    
    public function getConnection() {
        return $this->conn;
    }
}
