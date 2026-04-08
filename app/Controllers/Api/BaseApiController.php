<?php
// app/Controllers/Api/BaseApiController.php
namespace App\Controllers\Api;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class BaseApiController
{
    protected function jsonResponse($data, $statusCode = 200, $message = '')
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        
        $response = [
            'status' => $statusCode >= 200 && $statusCode < 300 ? 'success' : 'error',
            'data' => $data,
            'message' => $message
        ];
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    protected function errorResponse($message, $statusCode = 400)
    {
        $this->jsonResponse(null, $statusCode, $message);
    }
    
    protected function successResponse($data = null, $message = 'Operación exitosa')
    {
        $this->jsonResponse($data, 200, $message);
    }
    
    protected function getJsonInput()
    {
        $input = file_get_contents('php://input');
        return json_decode($input, true) ?: [];
    }
    
    protected function generateToken($userId, $email, $rol)
    {
        $payload = [
            'user_id' => $userId,
            'email' => $email,
            'rol' => $rol,
            'iat' => time(),
            'exp' => time() + (7 * 24 * 60 * 60)
        ];
        
        $secret = getenv('JWT_SECRET') ?: 'mi_secreto_jwt_12345';
        return JWT::encode($payload, $secret, 'HS256');
    }
    
    protected function validateToken($token)
    {
        try {
            $secret = getenv('JWT_SECRET') ?: 'mi_secreto_jwt_12345';
            $decoded = JWT::decode($token, new Key($secret, 'HS256'));
            return (array) $decoded;
        } catch (\Exception $e) {
            $this->errorResponse('Token inválido o expirado', 401);
        }
    }
    
    protected function getBearerToken()
    {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? '';
        return str_replace('Bearer ', '', $authHeader);
    }
    
    protected function authenticate()
    {
        $token = $this->getBearerToken();
        if (!$token) {
            $this->errorResponse('Token no proporcionado', 401);
        }
        return $this->validateToken($token);
    }
}
