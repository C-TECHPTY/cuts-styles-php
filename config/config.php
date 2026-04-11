<?php
// config/config.php

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    $sessionPath = session_save_path();
    if (!$sessionPath || !is_dir($sessionPath) || !is_writable($sessionPath)) {
        $fallbackSessionPath = dirname(__DIR__) . '/storage/sessions';
        if (!is_dir($fallbackSessionPath)) {
            mkdir($fallbackSessionPath, 0755, true);
        }
        session_save_path($fallbackSessionPath);
    }
    session_start();
}

if (!defined('BASE_URL')) {
    define('BASE_URL', getenv('APP_URL') ?: 'http://localhost/cuts-styles-php/');
}
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__) . '/');
}
if (!defined('UPLOAD_PATH')) {
    define('UPLOAD_PATH', BASE_PATH . 'assets/uploads/');
}
if (!defined('MAX_FILE_SIZE')) {
    define('MAX_FILE_SIZE', 5 * 1024 * 1024);
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

date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'America/Panama');

if (getenv('APP_ENV') === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

function redirect($url) {
    header('Location: ' . BASE_URL . ltrim($url, '/'));
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
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function verify_csrf($token = null) {
    $token = $token ?? ($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
    if (!isset($_SESSION['csrf_token']) || !is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        throw new Exception('Token CSRF invalido');
    }
    return true;
}

function sanitizar($input) {
    if (is_array($input)) {
        return array_map('sanitizar', $input);
    }
    return htmlspecialchars(trim((string) $input), ENT_QUOTES, 'UTF-8');
}

function validarEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function sanitizarEmail($email) {
    return filter_var((string) $email, FILTER_SANITIZE_EMAIL);
}

function logError($message, $file = null, $line = null) {
    $logDir = BASE_PATH . 'logs/';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $entry = '[' . date('Y-m-d H:i:s') . '] ' . $message;
    if ($file) {
        $entry .= ' en ' . $file . ($line ? ':' . $line : '');
    }

    error_log($entry . PHP_EOL, 3, $logDir . 'error.log');
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function hasRole($rol) {
    return isset($_SESSION['user_rol']) && $_SESSION['user_rol'] === $rol;
}

function requireLogin() {
    if (!isLoggedIn()) {
        setFlash('danger', 'Debes iniciar sesion para acceder a esta pagina');
        redirect('login.php');
    }
}

function requireRole($rol) {
    requireLogin();
    if (!hasRole($rol)) {
        setFlash('danger', 'No tienes permiso para acceder a esta pagina');
        redirect('index.php');
    }
}

// Compatibilidad con la version previa del proyecto.
function generarCSRFToken() {
    return csrf_token();
}

function verificarCSRFToken($token = null) {
    return verify_csrf($token);
}
