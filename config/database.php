<?php
/**
 * Database Configuration and Connection
 * Project Management System - Nairobi Innovators Hub
 */

class Database {
    private $host = "localhost";
    private $db_name = "hub_pm_system";
    private $username = "root";
    private $password = "";
    private $conn;

    /**
     * Get database connection
     * @return PDO|null
     */
    public function getConnection() {
        $this->conn = null;
        
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch(PDOException $exception) {
            error_log("Database Connection Error: " . $exception->getMessage());
            die("Database connection failed. Please check your configuration.");
        }
        
        return $this->conn;
    }
}
?>
