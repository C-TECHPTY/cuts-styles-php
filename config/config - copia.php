<?php
// config/config.php

// Iniciar sesión SOLO UNA VEZ
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Definir constantes SOLO si no existen
if (!defined('BASE_URL')) {
    define('BASE_URL', 'http://localhost/cuts-styles-php/');
}
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__) . '/');
}
if (!defined('UPLOAD_PATH')) {
    define('UPLOAD_PATH', BASE_PATH . 'assets/uploads/');
}
if (!defined('MAX_FILE_SIZE')) {
    define('MAX_FILE_SIZE', 5242880); // 5MB
}
if (!defined('PUNTOS_POR_SERVICIO')) {
    define('PUNTOS_POR_SERVICIO', 10);
}
if (!defined('PUNTOS_POR_DOLAR')) {
    define('PUNTOS_POR_DOLAR', 1);
}
if (!defined('TIEMPO_CANCELACION_MINUTOS')) {
    define('TIEMPO_CANCELACION_MINUTOS', 60);
}
if (!defined('RADIO_BUSQUEDA_KM')) {
    define('RADIO_BUSQUEDA_KM', 10);
}

// Zona horaria
date_default_timezone_set('America/Panama');

// ============================================
// FUNCIONES DE SEGURIDAD
// ============================================

// Generar token CSRF
function generarCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verificar token CSRF
function verificarCSRFToken($token) {
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        throw new Exception('Token CSRF inválido. Por favor, recarga la página e intenta nuevamente.');
    }
    return true;
}

// Sanitizar entrada
function sanitizar($input) {
    if (is_array($input)) {
        return array_map('sanitizar', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Validar email
function validarEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Sanitizar email
function sanitizarEmail($email) {
    return filter_var($email, FILTER_SANITIZE_EMAIL);
}

// Redirección segura
function redirect($url) {
    header("Location: " . BASE_URL . $url);
    exit();
}

// Mensajes flash
function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    if(isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// Generar contraseña aleatoria
function generarPassword($length = 10) {
    return bin2hex(random_bytes($length));
}

// Registrar log de errores
function logError($message, $file = null, $line = null) {
    $log = date('Y-m-d H:i:s') . " - " . $message;
    if ($file) $log .= " en $file:$line";
    error_log($log . PHP_EOL, 3, BASE_PATH . 'logs/error.log');
}

// Verificar si el usuario está logueado
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Verificar rol específico
function hasRole($rol) {
    return isset($_SESSION['user_rol']) && $_SESSION['user_rol'] == $rol;
}

// Requerir login
function requireLogin() {
    if (!isLoggedIn()) {
        setFlash('danger', 'Debes iniciar sesión para acceder a esta página');
        redirect('login.php');
    }
}

// Requerir rol específico
function requireRole($rol) {
    requireLogin();
    if (!hasRole($rol)) {
        setFlash('danger', 'No tienes permiso para acceder a esta página');
        redirect('index.php');
    }
}
?>