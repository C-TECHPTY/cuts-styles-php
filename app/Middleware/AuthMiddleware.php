<?php
// app/Middleware/AuthMiddleware.php
namespace App\Middleware;

class AuthMiddleware
{
    public static function requireLogin()
    {
        if (!isset($_SESSION['user_id'])) {
            header("Location: " . BASE_URL . "login.php");
            exit();
        }
    }
    
    public static function requireRole($rol)
    {
        self::requireLogin();
        if ($_SESSION['user_rol'] != $rol) {
            header("Location: " . BASE_URL . "index.php");
            exit();
        }
    }
    
    public static function requireAdmin()
    {
        self::requireRole('admin');
    }
    
    public static function requireBarber()
    {
        self::requireRole('barbero');
    }
    
    public static function requireClient()
    {
        self::requireRole('cliente');
    }
    
    public static function isLoggedIn()
    {
        return isset($_SESSION['user_id']);
    }
    
    public static function currentUser()
    {
        if (self::isLoggedIn()) {
            return [
                'id' => $_SESSION['user_id'],
                'email' => $_SESSION['user_email'],
                'rol' => $_SESSION['user_rol'],
                'nombre' => $_SESSION['user_nombre']
            ];
        }
        return null;
    }
}