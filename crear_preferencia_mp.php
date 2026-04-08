<?php
// crear_preferencia_mp.php
require_once 'config/config.php';
require_once 'config/mercadopago.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$pedido_id = $data['pedido_id'] ?? 0;
$total = $data['total'] ?? 0;
$descripcion = $data['descripcion'] ?? 'Compra en Cuts & Styles';

if(!$pedido_id || !$total) {
    echo json_encode(['error' => 'Datos incompletos']);
    exit;
}

// Obtener datos del usuario
$database = new Database();
$conn = $database->getConnection();

$query = "SELECT u.nombre, u.email, u.telefono 
          FROM pedidos p
          JOIN clientes c ON p.cliente_id = c.id
          JOIN users u ON c.user_id = u.id
          WHERE p.id = :id";
$stmt = $conn->prepare($query);
$stmt->bindParam(":id", $pedido_id);
$stmt->execute();
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

// En producción, aquí se haría la integración real con la API de Mercado Pago
// Usar curl para crear la preferencia

// Simulación de respuesta
echo json_encode([
    'id' => 'PREF-' . $pedido_id . '-' . time(),
    'init_point' => BASE_URL . 'confirmar_pago.php?pedido_id=' . $pedido_id
]);
?>