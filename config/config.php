<?php
// config/config.php

// Iniciar sesión SOLO si no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Definir constantes SOLO si no están definidas
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
date_default_timezone_set('America/Bogota');

// Función de redirección
function redirect($url) {
    header("Location: " . BASE_URL . $url);
    exit();
}

// Función para mostrar mensajes flash
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
?>