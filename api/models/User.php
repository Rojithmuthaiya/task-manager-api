<?php
require_once 'config/database.php';

class User {
    private $conn;
    private $table = 'users';

    public $id;
    public $name;
    public $email;
    public $password;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }


    public function register() {
        if ($this->emailExists()) {
            throw new Exception("Email already exists");
        }

        $query = "INSERT INTO " . $this->table . " 
                 SET name=:name, email=:email, password=:password";

        $stmt = $this->conn->prepare($query);


        $this->name = htmlspecialchars(strip_tags($this->name));
        $this->email = htmlspecialchars(strip_tags($this->email));


        $stmt->bindParam(":name", $this->name);
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":password", $this->password);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }


    public function emailExists() {
        $query = "SELECT id, name, password 
                 FROM " . $this->table . " 
                 WHERE email = :email 
                 LIMIT 0,1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":email", $this->email);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->id = $row['id'];
            $this->name = $row['name'];
            $this->password = $row['password'];
            return true;
        }
        return false;
    }
}
?>