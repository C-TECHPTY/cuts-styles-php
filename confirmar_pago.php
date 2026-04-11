<?php
// confirmar_pago.php
require_once 'config/config.php';
require_once 'config/database.php';

requireLogin();

$pedido_id = (int) ($_GET['pedido_id'] ?? $_GET['preference_id'] ?? 0);
$status = $_GET['status'] ?? 'pending';
$token = $_GET['token'] ?? '';

if ($pedido_id <= 0) {
    redirect('cliente.php');
}

$database = new Database();
$conn = $database->getConnection();

$query = "SELECT p.id
          FROM pedidos p
          JOIN clientes c ON p.cliente_id = c.id
          WHERE p.id = :id AND c.user_id = :user_id";
$stmt = $conn->prepare($query);
$stmt->bindParam(':id', $pedido_id);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$pedido = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pedido) {
    setFlash('danger', 'Pedido no encontrado.');
    redirect('cliente.php');
}

$sessionToken = $_SESSION['mercadopago_tokens'][$pedido_id] ?? null;
$estado = 'pendiente';

if ($status === 'approved' && $sessionToken && is_string($token) && hash_equals($sessionToken, $token)) {
    $estado = 'pagado';
    unset($_SESSION['mercadopago_tokens'][$pedido_id], $_SESSION['carrito']);
    setFlash('success', 'Pago completado exitosamente. Gracias por tu compra.');
} else {
    setFlash('warning', 'El pago esta pendiente de confirmacion.');
}

$update = "UPDATE pedidos SET estado = :estado WHERE id = :id";
$stmt = $conn->prepare($update);
$stmt->bindParam(':estado', $estado);
$stmt->bindParam(':id', $pedido_id);
$stmt->execute();

redirect('cliente.php');
