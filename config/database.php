<?php
// config/database.php

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    public $conn;

    public function __construct() {
        $this->host = getenv('DB_HOST') ?: 'localhost';
        $this->db_name = getenv('DB_DATABASE') ?: 'cuts_styles_db';
        $this->username = getenv('DB_USERNAME') ?: 'root';
        $this->password = getenv('DB_PASSWORD') ?: '';
    }

    public function getConnection() {
        if ($this->conn instanceof PDO) {
            return $this->conn;
        }

        try {
            $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset=utf8mb4";
            $this->conn = new PDO($dsn, $this->username, $this->password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $this->conn->exec("SET NAMES utf8mb4");
        } catch (PDOException $exception) {
            if (function_exists('logError')) {
                logError('Error de conexion a la base de datos: ' . $exception->getMessage(), __FILE__, __LINE__);
            } else {
                error_log('DB connection error: ' . $exception->getMessage());
            }
            throw new RuntimeException('No se pudo conectar a la base de datos.');
        }

        return $this->conn;
    }
}
