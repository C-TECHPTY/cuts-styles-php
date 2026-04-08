<?php
// app/Controllers/Api/AuthController.php - VERSIÓN SIMPLE
namespace App\Controllers\Api;

class AuthController
{
    public function login()
    {
        header('Content-Type: application/json');
        
        // Obtener datos del POST
        $input = json_decode(file_get_contents('php://input'), true);
        $email = $input['email'] ?? '';
        $password = $input['password'] ?? '';
        
        // Conexión directa a la base de datos
        $db = new \Database();
        $conn = $db->getConnection();
        
        $query = "SELECT * FROM users WHERE email = :email";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password_hash'])) {
            echo json_encode([
                'status' => 'success',
                'message' => 'Login exitoso',
                'user' => [
                    'id' => $user['id'],
                    'email' => $user['email'],
                    'nombre' => $user['nombre'],
                    'rol' => $user['rol']
                ]
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Credenciales incorrectas'
            ]);
        }
    }
}
?>