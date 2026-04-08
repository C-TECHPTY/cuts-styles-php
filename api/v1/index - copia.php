<?php
// api/v1/index.php - VERSIÓN DEFINITIVA
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use App\Controllers\Api\AuthController;

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

// Extraer la ruta (eliminar la parte del index.php)
$uri = str_replace('/cuts-styles-php/api/v1/index.php', '', $uri);
$uri = trim($uri, '/');
$parts = explode('/', $uri);
$endpoint = $parts[0] ?? '';

if ($endpoint === 'login' && $method === 'POST') {
    $controller = new AuthController();
    $controller->login();
} else {
    echo json_encode([
        'status' => 'error', 
        'message' => 'Endpoint no encontrado: ' . $endpoint,
        'method' => $method,
        'uri' => $uri
    ]);
}
?>