<?php
require_once 'config/database.php';

class Task {
    private $conn;
    private $table = 'tasks';

    public $id;
    public $user_id;
    public $title;
    public $description;
    public $status;
    public $created_at;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }


    public function read($user_id) {
        $query = "SELECT * FROM " . $this->table . " 
                 WHERE user_id = :user_id 
                 ORDER BY created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->execute();

        return $stmt;
    }


    public function readOne() {
        $query = "SELECT * FROM " . $this->table . " 
                 WHERE id = :id AND user_id = :user_id 
                 LIMIT 0,1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":user_id", $this->user_id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $this->title = $row['title'];
            $this->description = $row['description'];
            $this->status = $row['status'];
            $this->created_at = $row['created_at'];
            return true;
        }
        return false;
    }


    public function create() {
        $query = "INSERT INTO " . $this->table . " 
                 SET user_id=:user_id, title=:title, 
                     description=:description, status=:status";

        $stmt = $this->conn->prepare($query);


        $this->title = htmlspecialchars(strip_tags($this->title));
        $this->description = htmlspecialchars(strip_tags($this->description));
        $this->status = htmlspecialchars(strip_tags($this->status));


        $stmt->bindParam(":user_id", $this->user_id);
        $stmt->bindParam(":title", $this->title);
        $stmt->bindParam(":description", $this->description);
        $stmt->bindParam(":status", $this->status);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }

    public function update() {
        $query = "UPDATE " . $this->table . " 
                 SET title=:title, description=:description, status=:status 
                 WHERE id=:id AND user_id=:user_id";

        $stmt = $this->conn->prepare($query);


        $this->title = htmlspecialchars(strip_tags($this->title));
        $this->description = htmlspecialchars(strip_tags($this->description));
        $this->status = htmlspecialchars(strip_tags($this->status));
        $this->id = htmlspecialchars(strip_tags($this->id));


        $stmt->bindParam(":title", $this->title);
        $stmt->bindParam(":description", $this->description);
        $stmt->bindParam(":status", $this->status);
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":user_id", $this->user_id);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }


    public function delete() {
        $query = "DELETE FROM " . $this->table . " 
                 WHERE id = :id AND user_id = :user_id";

        $stmt = $this->conn->prepare($query);


        $this->id = htmlspecialchars(strip_tags($this->id));


        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":user_id", $this->user_id);

        if ($stmt->execute()) {
            if ($stmt->rowCount() > 0) {
                return true;
            } else {
                throw new Exception("Task not found or you don't have permission to delete it");
            }
        }
        return false;
    }
}
?>