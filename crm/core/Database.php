<?php
namespace Core;

use PDO;
use PDOException;

class Database {
    private static $instance = null;
    private $conn;

    private function __construct() {
        require_once __DIR__ . '/../config/config.php';
        try {
            $this->conn = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
            $this->conn->exec("SET time_zone = '+05:30'");
        } catch (PDOException $e) {
            die("Connection failed: " . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->conn;
    }

    // Helper for simple queries
    public function query($sql, $params = []) {
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll();
    }

    public function fetchOne($sql, $params = []) {
        return $this->query($sql, $params)->fetch();
    }

    public function insert($table, $data) {
        $keys = array_keys($data);
        $fields = implode(", ", $keys);
        $placeholders = ":" . implode(", :", $keys);
        $sql = "INSERT INTO $table ($fields) VALUES ($placeholders)";
        $this->query($sql, $data);
        return $this->conn->lastInsertId();
    }

    public function update($table, $data, $where, $params = []) {
        $fields = [];
        foreach ($data as $key => $value) {
            $fields[] = "$key = :$key";
        }
        $sql = "UPDATE $table SET " . implode(", ", $fields) . " WHERE $where";
        
        // Merge data for named parameters
        // Note: query() in this class uses execute($params)
        // If query() uses positional parameters, we'd need to adjust.
        // But query() passes $params directly to execute().
        // Named parameters work if $params is an associative array.
        
        // However, Requirement.php passes positional parameters for WHERE: "id = ? AND company_id = ?", [$id, $company_id]
        // This is a mix of named and positional parameters which PDO DOES NOT SUPPORT.
        
        // I should stick to one style. Positional is safer for generic update.
        
        $fields_pos = [];
        foreach ($data as $key => $value) {
            $fields_pos[] = "$key = ?";
        }
        $sql_pos = "UPDATE $table SET " . implode(", ", $fields_pos) . " WHERE $where";
        $allParams = array_merge(array_values($data), $params);
        return $this->query($sql_pos, $allParams);
    }
}
?>
