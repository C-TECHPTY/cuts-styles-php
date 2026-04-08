<?php
// classes/User.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

class User {
    public $conn;
    private $table_name = "users";
    
    public $id;
    public $email;
    public $password;
    public $nombre;
    public $telefono;
    public $direccion;
    public $rol;
    public $is_active;
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }
    
    public function register() {
        $query = "INSERT INTO " . $this->table_name . "
                  SET email=:email, password_hash=:password, nombre=:nombre, 
                      telefono=:telefono, rol=:rol";
        
        $stmt = $this->conn->prepare($query);
        
        $this->password = password_hash($this->password, PASSWORD_DEFAULT);
        
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":password", $this->password);
        $stmt->bindParam(":nombre", $this->nombre);
        $stmt->bindParam(":telefono", $this->telefono);
        $stmt->bindParam(":rol", $this->rol);
        
        if($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            
            // Crear perfil según rol
            if($this->rol == 'cliente') {
                $query2 = "INSERT INTO clientes (user_id) VALUES (:user_id)";
                $stmt2 = $this->conn->prepare($query2);
                $stmt2->bindParam(":user_id", $this->id);
                $stmt2->execute();
            } elseif($this->rol == 'barbero') {
                $query2 = "INSERT INTO barberos (user_id) VALUES (:user_id)";
                $stmt2 = $this->conn->prepare($query2);
                $stmt2->bindParam(":user_id", $this->id);
                $stmt2->execute();
            }
            
            return true;
        }
        return false;
    }
    
    public function login() {
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE email = :email AND is_active = 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":email", $this->email);
        $stmt->execute();
        
        if($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if(password_verify($this->password, $row['password_hash'])) {
                $this->id = $row['id'];
                $this->nombre = $row['nombre'];
                $this->rol = $row['rol'];
                
                // Actualizar último login
                $update = "UPDATE " . $this->table_name . " 
                           SET last_login = NOW() WHERE id = :id";
                $stmt2 = $this->conn->prepare($update);
                $stmt2->bindParam(":id", $this->id);
                $stmt2->execute();
                
                return true;
            }
        }
        return false;
    }
    
    public function getProfile() {
        $query = "SELECT u.*, 
                  CASE WHEN u.rol = 'cliente' THEN c.puntos ELSE NULL END as puntos,
                  CASE WHEN u.rol = 'barbero' THEN b.verificacion_status ELSE NULL END as verificacion_status
                  FROM " . $this->table_name . " u
                  LEFT JOIN clientes c ON u.id = c.user_id
                  LEFT JOIN barberos b ON u.id = b.user_id
                  WHERE u.id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>