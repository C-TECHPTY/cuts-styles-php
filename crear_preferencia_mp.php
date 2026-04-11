<?php
// crear_preferencia_mp.php
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'config/mercadopago.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autenticado']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$pedido_id = (int) ($data['pedido_id'] ?? 0);
$total = (float) ($data['total'] ?? 0);

if ($pedido_id <= 0 || $total <= 0) {
    http_response_code(422);
    echo json_encode(['error' => 'Datos incompletos']);
    exit;
}

$database = new Database();
$conn = $database->getConnection();

$query = "SELECT p.id, p.total
          FROM pedidos p
          JOIN clientes c ON p.cliente_id = c.id
          WHERE p.id = :id AND c.user_id = :user_id";
$stmt = $conn->prepare($query);
$stmt->bindParam(':id', $pedido_id);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$pedido = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pedido) {
    http_response_code(404);
    echo json_encode(['error' => 'Pedido no encontrado']);
    exit;
}

$token = $_SESSION['mercadopago_tokens'][$pedido_id] ?? bin2hex(random_bytes(24));
$_SESSION['mercadopago_tokens'][$pedido_id] = $token;

echo json_encode([
    'id' => 'PREF-' . $pedido_id . '-' . time(),
    'init_point' => BASE_URL . 'confirmar_pago.php?pedido_id=' . $pedido_id . '&status=approved&token=' . urlencode($token),
]);
