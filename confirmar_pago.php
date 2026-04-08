<?php
// confirmar_pago.php
require_once 'config/config.php';

$pedido_id = $_GET['pedido_id'] ?? $_GET['preference_id'] ?? 0;
$payment_id = $_GET['payment_id'] ?? 0;
$status = $_GET['status'] ?? 'pending';

if($pedido_id) {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Actualizar estado del pedido
    $estado = ($status == 'approved') ? 'pagado' : 'pendiente';
    $query = "UPDATE pedidos SET estado = :estado WHERE id = :id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(":estado", $estado);
    $stmt->bindParam(":id", $pedido_id);
    $stmt->execute();
    
    // Vaciar carrito si el pago fue exitoso
    if($status == 'approved' && isset($_SESSION['carrito'])) {
        unset($_SESSION['carrito']);
    }
    
    // Mensaje según estado
    if($status == 'approved') {
        $_SESSION['flash'] = ['type' => 'success', 'message' => '✅ Pago completado exitosamente. Gracias por tu compra!'];
    } else {
        $_SESSION['flash'] = ['type' => 'warning', 'message' => '⚠️ El pago está pendiente de confirmación.'];
    }
}

redirect('cliente.php');
?>