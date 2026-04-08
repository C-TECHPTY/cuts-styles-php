<?php
// app/Controllers/Api/AuthController.php
namespace App\Controllers\Api;

use App\Models\User;

class AuthController extends BaseApiController
{
    // POST /api/v1/auth/login
    public function login()
    {
        $input = $this->getJsonInput();
        
        // Validar campos requeridos
        if (empty($input['email']) || empty($input['password'])) {
            $this->errorResponse('Email y contraseña son requeridos', 400);
        }
        
        $user = new User();
        $user->email = $input['email'];
        $user->password = $input['password'];
        
        if ($user->login()) {
            $token = $this->generateToken($user->id, $user->email, $user->rol);
            
            $this->successResponse([
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'nombre' => $user->nombre,
                    'rol' => $user->rol
                ]
            ], 'Login exitoso');
        } else {
            $this->errorResponse('Credenciales incorrectas', 401);
        }
    }
    
    // POST /api/v1/auth/register
    public function register()
    {
        $input = $this->getJsonInput();
        
        // Validar campos requeridos
        $required = ['email', 'password', 'nombre', 'rol'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                $this->errorResponse("El campo {$field} es requerido", 400);
            }
        }
        
        if (!in_array($input['rol'], ['cliente', 'barbero'])) {
            $this->errorResponse('Rol inválido', 400);
        }
        
        $user = new User();
        $user->email = $input['email'];
        $user->password = $input['password'];
        $user->nombre = $input['nombre'];
        $user->telefono = $input['telefono'] ?? '';
        $user->rol = $input['rol'];
        
        if ($user->register()) {
            $this->successResponse(null, 'Usuario registrado exitosamente');
        } else {
            $this->errorResponse('Error al registrar usuario. El email puede estar en uso', 400);
        }
    }
    
    // GET /api/v1/auth/me
    public function me()
    {
        $userData = $this->authenticate();
        $this->successResponse(['user' => $userData], 'Usuario autenticado');
    }
    
    // POST /api/v1/auth/logout
    public function logout()
    {
        $this->successResponse(null, 'Sesión cerrada exitosamente');
    }
}
