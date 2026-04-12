<?php
// classes/User.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/MonetizationManager.php';

class User {
    public $conn;
    private $table_name = 'users';

    public $id;
    public $email;
    public $password;
    public $nombre;
    public $telefono;
    public $direccion;
    public $rol;
    public $is_active;
    public $lastError;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function register() {
        $this->email = strtolower(trim((string) $this->email));
        $this->nombre = trim((string) $this->nombre);
        $this->telefono = trim((string) ($this->telefono ?? ''));
        $this->rol = trim((string) $this->rol);
        $this->lastError = null;

        if (!validarEmail($this->email)) {
            $this->lastError = 'Email invalido.';
            return false;
        }

        if (!in_array($this->rol, ['cliente', 'barbero', 'admin'], true)) {
            $this->lastError = 'Rol invalido.';
            return false;
        }

        try {
            $this->conn->beginTransaction();

            $check = $this->conn->prepare("SELECT id FROM {$this->table_name} WHERE email = :email LIMIT 1");
            $check->bindParam(':email', $this->email);
            $check->execute();
            if ($check->fetch()) {
                $this->conn->rollBack();
                $this->lastError = 'Ese email ya esta registrado.';
                return false;
            }

            $query = "INSERT INTO {$this->table_name}
                      SET email = :email, password_hash = :password, nombre = :nombre,
                          telefono = :telefono, rol = :rol";
            $stmt = $this->conn->prepare($query);

            $hashedPassword = password_hash($this->password, PASSWORD_DEFAULT);

            $stmt->bindParam(':email', $this->email);
            $stmt->bindParam(':password', $hashedPassword);
            $stmt->bindParam(':nombre', $this->nombre);
            $stmt->bindParam(':telefono', $this->telefono);
            $stmt->bindParam(':rol', $this->rol);
            $stmt->execute();

            $this->id = (int) $this->conn->lastInsertId();

            if ($this->rol === 'cliente') {
                $profileQuery = "INSERT INTO clientes (user_id) VALUES (:user_id)";
            } elseif ($this->rol === 'barbero') {
                $profileQuery = "INSERT INTO barberos (user_id) VALUES (:user_id)";
            } else {
                $profileQuery = null;
            }

            if ($profileQuery) {
                $profileStmt = $this->conn->prepare($profileQuery);
                $profileStmt->bindParam(':user_id', $this->id);
                $profileStmt->execute();

                if ($this->rol === 'barbero') {
                    $barberId = (int) $this->conn->lastInsertId();
                    if ($barberId > 0) {
                        $monetizationManager = new MonetizationManager($this->conn);
                        $monetizationManager->initializeBarberProfile($barberId);
                    }
                }
            }

            $this->conn->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            $this->lastError = 'Error guardando el usuario: ' . $e->getMessage();
            logError('Error registrando usuario: ' . $e->getMessage(), __FILE__, __LINE__);
            return false;
        }
    }

    public function login() {
        $this->email = strtolower(trim((string) $this->email));
        $this->lastError = null;

        $query = "SELECT * FROM {$this->table_name}
                  WHERE email = :email AND is_active = 1
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $this->email);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || !password_verify($this->password, $row['password_hash'])) {
            $this->lastError = 'Credenciales invalidas.';
            return false;
        }

        $this->id = (int) $row['id'];
        $this->email = $row['email'];
        $this->nombre = $row['nombre'];
        $this->telefono = $row['telefono'] ?? null;
        $this->rol = $row['rol'];
        $this->direccion = $row['direccion'] ?? null;
        $this->is_active = $row['is_active'] ?? 1;

        try {
            $update = "UPDATE {$this->table_name} SET last_login = NOW() WHERE id = :id";
            $stmt2 = $this->conn->prepare($update);
            $stmt2->bindParam(':id', $this->id);
            $stmt2->execute();
        } catch (Throwable $e) {
            logError('No se pudo actualizar last_login: ' . $e->getMessage(), __FILE__, __LINE__);
        }

        return true;
    }

    public function getProfile() {
        $query = "SELECT u.*,
                  CASE WHEN u.rol = 'cliente' THEN c.puntos ELSE NULL END as puntos,
                  CASE WHEN u.rol = 'barbero' THEN b.verificacion_status ELSE NULL END as verificacion_status
                  FROM {$this->table_name} u
                  LEFT JOIN clientes c ON u.id = c.user_id
                  LEFT JOIN barberos b ON u.id = b.user_id
                  WHERE u.id = :id
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
