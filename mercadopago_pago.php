<?php
// mercadopago_pago.php
require_once 'config/config.php';
require_once 'config/mercadopago.php';

$pedido_id = $_GET['pedido_id'] ?? 0;

if(!$pedido_id) {
    redirect('carrito.php');
}

// Obtener datos del pedido
$database = new Database();
$conn = $database->getConnection();

$query = "SELECT p.*, u.nombre, u.email, u.telefono 
          FROM pedidos p
          JOIN clientes c ON p.cliente_id = c.id
          JOIN users u ON c.user_id = u.id
          WHERE p.id = :id";
$stmt = $conn->prepare($query);
$stmt->bindParam(":id", $pedido_id);
$stmt->execute();
$pedido = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$pedido) {
    redirect('carrito.php');
}

// Redirigir a Mercado Pago
// En producción, aquí iría la integración real con la API de Mercado Pago

// Simulación de redirección (en producción usar SDK de Mercado Pago)
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Procesando pago - Cuts & Styles</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .payment-container {
            background: white;
            border-radius: 20px;
            padding: 3rem;
            text-align: center;
            max-width: 500px;
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
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #e94560;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            margin-top: 1rem;
        }
    </style>
    <script src="https://sdk.mercadopago.com/js/v2"></script>
</head>
<body>
    <div class="payment-container">
        <div class="loader"></div>
        <h2>Procesando tu pago...</h2>
        <p>Por favor espera mientras te redirigimos a Mercado Pago</p>
        <div id="wallet_container"></div>
        <a href="cliente.php" class="btn">Volver al dashboard</a>
    </div>

    <script>
        // En producción, usar el SDK de Mercado Pago
        const mp = new MercadoPago('<?php echo MP_PUBLIC_KEY; ?>', {
            locale: 'es-MX'
        });
        
        // Crear preferencia de pago (en producción, esto se hace desde el backend)
        fetch('crear_preferencia_mp.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                pedido_id: <?php echo $pedido_id; ?>,
                total: <?php echo $pedido['total']; ?>,
                descripcion: 'Pedido #<?php echo $pedido_id; ?>'
            })
        })
        .then(response => response.json())
        .then(data => {
            if(data.id) {
                mp.bricks().create("wallet", "wallet_container", {
                    initialization: { preferenceId: data.id },
                    customization: { texts: { valueProp: 'smart_option' } }
                });
            } else {
                window.location.href = 'cliente.php';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            window.location.href = 'cliente.php';
        });
    </script>
</body>
</html>