<?php
// test_error.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "1. Iniciando prueba...<br>";

require_once 'config/config.php';
echo "2. Config cargado<br>";

require_once 'vendor/autoload.php';
echo "3. Autoload cargado<br>";

use App\Controllers\Api\AuthController;
echo "4. Use statement OK<br>";

echo "5. Todo funciona correctamente";
?>