<?php
// config/config.php

// Cargar autoload de Composer
require_once __DIR__ . '/../vendor/autoload.php';

// Cargar variables de entorno
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

// Iniciar sesión SOLO UNA VEZ
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Definir constantes
if (!defined('BASE_URL')) {
    define('BASE_URL', getenv('APP_URL') ?: 'http://localhost/cuts-styles-php/');
}
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__) . '/');
}
if (!defined('UPLOAD_PATH')) {
    define('UPLOAD_PATH', BASE_PATH . 'assets/uploads/');
}
if (!defined('PUNTOS_POR_SERVICIO')) {
    define('PUNTOS_POR_SERVICIO', 10);
}

// Zona horaria
date_default_timezone_set('America/Panama');

// Configurar errores según entorno
if (getenv('APP_ENV') === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// ============================================
// FUNCIONES GLOBALES
// ============================================

function redirect($url) {
    header("Location: " . BASE_URL . $url);
    exit();
}

function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function verify_csrf($token = null) {
    $token = $token ?? ($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        throw new Exception('Token CSRF inválido');
    }
    return true;
}