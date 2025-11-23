<?php
require_once 'config/database.php';

class Auth {
    private $conn;
    private $table = 'users';

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }


    public function generateToken($userId) {
        $payload = [
            'user_id' => (int)$userId,
            'created_at' => time()
        ];
        return base64_encode(json_encode($payload));
    }


    public function validateToken($token) {

        $logMessage = "=== TOKEN VALIDATION START ===\n";
        $logMessage .= "Token: " . $token . "\n";
        
        if (empty($token)) {
            $logMessage .= "ERROR: Token is empty\n";
            $this->writeLog($logMessage);
            return false;
        }


        $decoded = base64_decode($token);
        if ($decoded === false) {
            $logMessage .= "ERROR: Failed to base64 decode token\n";
            $this->writeLog($logMessage);
            return false;
        }
        
        $logMessage .= "Decoded: " . $decoded . "\n";

        $payload = json_decode($decoded, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $logMessage .= "ERROR: JSON decode failed: " . json_last_error_msg() . "\n";
            $this->writeLog($logMessage);
            return false;
        }

        $logMessage .= "Payload: " . print_r($payload, true) . "\n";

        if (!$payload || !isset($payload['user_id']) || !isset($payload['created_at'])) {
            $logMessage .= "ERROR: Missing required fields in payload\n";
            $this->writeLog($logMessage);
            return false;
        }

        $userId = (int)$payload['user_id'];
        $createdAt = (int)$payload['created_at'];
        $currentTime = time();

        $logMessage .= "User ID: $userId\n";
        $logMessage .= "Created At: $createdAt\n";
        $logMessage .= "Current Time: $currentTime\n";
        $logMessage .= "Time Difference: " . ($currentTime - $createdAt) . "\n";


        if (($currentTime - $createdAt) > 86400) {
            $logMessage .= "ERROR: Token expired\n";
            $this->writeLog($logMessage);
            return false;
        }


        try {
            $query = "SELECT id FROM " . $this->table . " WHERE id = :id LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":id", $userId, PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $logMessage .= "SUCCESS: User exists, token is valid\n";
                $this->writeLog($logMessage);
                return $userId;
            } else {
                $logMessage .= "ERROR: User not found in database\n";
                $this->writeLog($logMessage);
                return false;
            }
        } catch (Exception $e) {
            $logMessage .= "ERROR: Database query failed: " . $e->getMessage() . "\n";
            $this->writeLog($logMessage);
            return false;
        }
    }

    private function writeLog($message) {
        $logFile = __DIR__ . '/../auth_log.txt';
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($logFile, "[$timestamp] " . $message . "\n---\n", FILE_APPEND | LOCK_EX);
    }


    public function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }


    public function verifyPassword($password, $hashedPassword) {
        return password_verify($password, $hashedPassword);
    }
}
?>