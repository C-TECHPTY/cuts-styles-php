<?php
// mercadopago_pago.php
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'config/mercadopago.php';

requireLogin();

$pedido_id = (int) ($_GET['pedido_id'] ?? 0);
if (!$pedido_id) {
    redirect('carrito.php');
}

$database = new Database();
$conn = $database->getConnection();

$query = "SELECT p.*, u.nombre, u.email, u.telefono
          FROM pedidos p
          JOIN clientes c ON p.cliente_id = c.id
          JOIN users u ON c.user_id = u.id
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

$paymentToken = bin2hex(random_bytes(24));
$_SESSION['mercadopago_tokens'][$pedido_id] = $paymentToken;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Procesando pago - Cuts & Styles</title>
    <?php include BASE_PATH . 'includes/pwa_head.php'; ?>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .payment-container {
            background: white;
            border-radius: 20px;
            padding: 3rem;
            text-align: center;
            max-width: 560px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .loader {
            width: 50px;
            height: 50px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #e94560;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 1.5rem;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #e94560;
            color: white;
            text-decoration: none;
            border-radius: 8px;
        }
        .btn-secondary {
            background: #1a1a2e;
        }
        .hint {
            margin-top: 1rem;
            color: #666;
            font-size: 0.95rem;
        }
    </style>
</head>
<body>
    <div class="payment-container">
        <div class="loader"></div>
        <h2>Procesando tu pago...</h2>
        <p>Esta integracion esta en modo demo. El pedido <strong>#<?php echo $pedido_id; ?></strong> quedo creado por <strong>$<?php echo number_format((float) $pedido['total'], 2); ?></strong>.</p>
        <p class="hint">Cuando integres Mercado Pago real, este punto debe crear la preferencia en backend y confirmar por webhook.</p>

        <div class="actions">
            <a class="btn" href="<?php echo BASE_URL; ?>confirmar_pago.php?pedido_id=<?php echo $pedido_id; ?>&status=approved&token=<?php echo urlencode($paymentToken); ?>">Simular pago aprobado</a>
            <a class="btn btn-secondary" href="<?php echo BASE_URL; ?>confirmar_pago.php?pedido_id=<?php echo $pedido_id; ?>&status=pending&token=<?php echo urlencode($paymentToken); ?>">Simular pago pendiente</a>
        </div>

        <div class="actions">
            <a href="cliente.php" class="btn btn-secondary">Volver al dashboard</a>
        </div>
    </div>
    <?php include BASE_PATH . 'includes/pwa_register.php'; ?>
</body>
</html>
