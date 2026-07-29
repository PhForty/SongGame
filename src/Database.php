<?php
require_once 'config.php';

class Database {
    private $conn;

    public function __construct() {
        $this->conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($this->conn->connect_error) {
            die("Connection failed: " . $this->conn->connect_error);
        }
    }

    public function query($sql, $params = []) {
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $this->conn->error);
        }

        if (!empty($params)) {
            $types = str_repeat('s', count($params));
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        return $stmt;
    }

    public function fetchOne($sql, $params = []) {
        $result = $this->query($sql, $params)->get_result();
        return $result->fetch_assoc();
    }

    public function fetchAll($sql, $params = []) {
        $result = $this->query($sql, $params)->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function execute($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->affected_rows;
    }

    public function getLastInsertId() {
        return $this->conn->insert_id;
    }
}
?>
