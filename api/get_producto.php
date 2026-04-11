<?php
// api/get_producto.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Product.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autenticado']);
    exit;
}

$id = $_GET['id'] ?? 0;

if($id) {
    $product = new Product();
    $producto = $product->getById($id);
    
    if($producto) {
        echo json_encode($producto);
    } else {
        echo json_encode(['error' => 'Producto no encontrado']);
    }
} else {
    echo json_encode(['error' => 'ID no proporcionado']);
}
?>
