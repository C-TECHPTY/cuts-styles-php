<?php
// checkout.php
require_once 'config/config.php';
require_once 'classes/Product.php';

// Inicializar sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar autenticación
if(!isset($_SESSION['user_id'])) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Debes iniciar sesión para realizar una compra'];
    header("Location: login.php");
    exit();
}

$productClass = new Product();

// Procesar compra directa (Comprar Ahora)
if(isset($_GET['buy_now'])) {
    // Limpiar carrito actual
    $_SESSION['carrito'] = [];
    // Agregar solo el producto seleccionado
    $producto_id = $_GET['buy_now'];
    $cantidad = $_GET['cantidad'] ?? 1;
    $_SESSION['carrito'][$producto_id] = $cantidad;
}

// Obtener ID del cliente
$query = "SELECT id FROM clientes WHERE user_id = :user_id";
$stmt = $productClass->conn->prepare($query);
$stmt->bindParam(":user_id", $_SESSION['user_id']);
$stmt->execute();
$cliente = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$cliente) {
    // Crear cliente si no existe
    $insert = "INSERT INTO clientes (user_id) VALUES (:user_id)";
    $stmt = $productClass->conn->prepare($insert);
    $stmt->bindParam(":user_id", $_SESSION['user_id']);
    $stmt->execute();
    $cliente_id = $productClass->conn->lastInsertId();
} else {
    $cliente_id = $cliente['id'];
}

// Verificar si hay productos en el carrito
if(empty($_SESSION['carrito'])) {
    $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Tu carrito está vacío'];
    header("Location: productos.php");
    exit();
}

// Obtener productos del carrito
$carrito_items = [];
$total = 0;

$ids = array_keys($_SESSION['carrito']);
if(!empty($ids)) {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $query = "SELECT * FROM productos WHERE id IN ($placeholders) AND estado = 'activo'";
    $stmt = $productClass->conn->prepare($query);
    foreach($ids as $key => $id) {
        $stmt->bindValue($key+1, $id);
    }
    $stmt->execute();
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach($productos as $prod) {
        $cantidad = $_SESSION['carrito'][$prod['id']];
        $precio_final = $productClass->getPrecioFinal($prod);
        $subtotal = $precio_final * $cantidad;
        $total += $subtotal;
        
        $carrito_items[] = [
            'id' => $prod['id'],
            'nombre' => $prod['nombre'],
            'precio' => $precio_final,
            'cantidad' => $cantidad,
            'subtotal' => $subtotal
        ];
    }
}

// Procesar pedido
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirmar_pedido'])) {
    $direccion = $_POST['direccion'];
    $metodo_pago = $_POST['metodo_pago'];
    
    try {
        // Iniciar transacción
        $productClass->conn->beginTransaction();
        
        // Crear pedido
        $query = "INSERT INTO pedidos (cliente_id, total, direccion_entrega, metodo_pago, estado) 
                  VALUES (:cliente_id, :total, :direccion, :metodo, 'pendiente')";
        $stmt = $productClass->conn->prepare($query);
        $stmt->bindParam(":cliente_id", $cliente_id);
        $stmt->bindParam(":total", $total);
        $stmt->bindParam(":direccion", $direccion);
        $stmt->bindParam(":metodo", $metodo_pago);
        $stmt->execute();
        
        $pedido_id = $productClass->conn->lastInsertId();
        
        // Guardar detalles del pedido
        foreach($carrito_items as $item) {
            $query = "INSERT INTO pedido_detalles (pedido_id, producto_id, cantidad, precio_unitario) 
                      VALUES (:pedido_id, :producto_id, :cantidad, :precio)";
            $stmt = $productClass->conn->prepare($query);
            $stmt->bindParam(":pedido_id", $pedido_id);
            $stmt->bindParam(":producto_id", $item['id']);
            $stmt->bindParam(":cantidad", $item['cantidad']);
            $stmt->bindParam(":precio", $item['precio']);
            $stmt->execute();
            
            // Actualizar stock
            $productClass->updateStock($item['id'], $item['cantidad']);
        }
        
        // Confirmar transacción
        $productClass->conn->commit();
        
        // Vaciar carrito
        $_SESSION['carrito'] = [];
        
        $_SESSION['flash'] = ['type' => 'success', 'message' => '✅ Pedido realizado exitosamente. Gracias por tu compra!'];
        header("Location: cliente.php");
        exit();
        
    } catch(PDOException $e) {
        // Revertir transacción en caso de error
        $productClass->conn->rollBack();
        $_SESSION['flash'] = ['type' => 'danger', 'message' => '❌ Error al procesar el pedido: ' . $e->getMessage()];
        header("Location: checkout.php");
        exit();
    }
}

// Obtener perfil del usuario
$query = "SELECT * FROM users WHERE id = :id";
$stmt = $productClass->conn->prepare($query);
$stmt->bindParam(":id", $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finalizar Pedido - Cuts & Styles</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            padding: 2rem;
        }
        .navbar {
            background: #1a1a2e;
            color: white;
            padding: 1rem 2rem;
            position: fixed;
            width: 100%;
            top: 0;
            left: 0;
            z-index: 1000;
        }
        .navbar-container {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .logo { font-size: 1.5rem; font-weight: bold; }
        .logo i { color: #e94560; margin-right: 10px; }
        .nav-links a {
            color: white;
            text-decoration: none;
            margin-left: 2rem;
            transition: color 0.3s;
        }
        .nav-links a:hover { color: #e94560; }
        .container {
            max-width: 1000px;
            margin: 100px auto 0;
        }
        .card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .card-header {
            padding: 1.5rem;
            background: #1a1a2e;
            color: white;
        }
        .card-header h1 {
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .cart-items {
            padding: 1.5rem;
            border-bottom: 1px solid #ecf0f1;
        }
        .cart-item {
            display: flex;
            justify-content: space-between;
            padding: 0.8rem 0;
            border-bottom: 1px solid #f5f7fa;
        }
        .cart-item:last-child {
            border-bottom: none;
        }
        .total {
            padding: 1.5rem;
            text-align: right;
            background: #f8f9fa;
            font-size: 1.3rem;
        }
        .total-amount {
            font-size: 1.8rem;
            font-weight: bold;
            color: #e94560;
        }
        .form-container {
            padding: 1.5rem;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #1a1a2e;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid #ecf0f1;
            border-radius: 8px;
            font-size: 1rem;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: #e94560;
        }
        .payment-methods {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-top: 0.5rem;
        }
        .payment-option {
            text-align: center;
            padding: 1rem;
            border: 2px solid #ecf0f1;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .payment-option.selected {
            border-color: #e94560;
            background: #fef5e7;
        }
        .payment-option i {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        .payment-option i.fa-mercadopago { color: #009ee3; }
        .payment-option i.fa-money-bill-wave { color: #27ae60; }
        .payment-option i.fa-university { color: #3498db; }
        .payment-option span {
            display: block;
            font-size: 0.9rem;
        }
        .btn {
            width: 100%;
            padding: 1rem;
            background: #e94560;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .btn:hover {
            background: #c7354f;
            transform: translateY(-2px);
        }
        .back-link {
            display: inline-block;
            margin-top: 1rem;
            color: #1a1a2e;
            text-decoration: none;
        }
        .back-link:hover {
            color: #e94560;
        }
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-success {
            background: #d5f4e6;
            color: #27ae60;
            border: 1px solid #a3e4d7;
        }
        .alert-danger {
            background: #fdedec;
            color: #e74c3c;
            border: 1px solid #fadbd8;
        }
        .alert-warning {
            background: #fef5e7;
            color: #f39c12;
            border: 1px solid #fdebd0;
        }
        @media (max-width: 768px) {
            .payment-methods { grid-template-columns: 1fr; }
            body { padding: 1rem; }
            .container { margin-top: 80px; }
            .navbar-container { flex-direction: column; gap: 1rem; }
            .nav-links { display: flex; gap: 1rem; flex-wrap: wrap; justify-content: center; }
            .nav-links a { margin-left: 0; }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-container">
            <div class="logo"><i class="fas fa-cut"></i> Cuts & Styles</div>
            <div class="nav-links">
                <a href="index.php">Inicio</a>
                <a href="productos.php">Productos</a>
                <a href="carrito.php">Carrito</a>
                <a href="cliente.php">Mi Cuenta</a>
                <a href="logout.php">Salir</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="card">
            <div class="card-header">
                <h1><i class="fas fa-clipboard-list"></i> Finalizar Pedido</h1>
            </div>
            
            <?php if(isset($_SESSION['flash'])): ?>
                <div class="alert alert-<?php echo $_SESSION['flash']['type']; ?>" style="margin: 1rem;">
                    <i class="fas fa-<?php echo $_SESSION['flash']['type'] == 'success' ? 'check-circle' : ($_SESSION['flash']['type'] == 'danger' ? 'exclamation-circle' : 'info-circle'); ?>"></i>
                    <?php echo $_SESSION['flash']['message']; ?>
                </div>
                <?php unset($_SESSION['flash']); ?>
            <?php endif; ?>
            
            <div class="cart-items">
                <h3 style="margin-bottom: 1rem;">Resumen de tu pedido</h3>
                <?php foreach($carrito_items as $item): ?>
                <div class="cart-item">
                    <span><?php echo htmlspecialchars($item['nombre']); ?> x <?php echo $item['cantidad']; ?></span>
                    <span>$<?php echo number_format($item['subtotal'], 2); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="total">
                <strong>Total a pagar:</strong>
                <span class="total-amount">$<?php echo number_format($total, 2); ?></span>
            </div>
            
            <form method="POST" class="form-container">
                <div class="form-group">
                    <label><i class="fas fa-map-marker-alt"></i> Dirección de Entrega</label>
                    <textarea name="direccion" rows="3" required placeholder="Calle, número, colonia, ciudad, código postal"><?php echo htmlspecialchars($user['direccion'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-credit-card"></i> Método de Pago</label>
                    <div class="payment-methods">
                        <div class="payment-option" data-metodo="mercadopago">
                            <i class="fab fa-mercadopago"></i>
                            <span>Mercado Pago</span>
                        </div>
                        <div class="payment-option" data-metodo="efectivo">
                            <i class="fas fa-money-bill-wave"></i>
                            <span>Efectivo contra entrega</span>
                        </div>
                        <div class="payment-option" data-metodo="transferencia">
                            <i class="fas fa-university"></i>
                            <span>Transferencia bancaria</span>
                        </div>
                    </div>
                    <input type="hidden" name="metodo_pago" id="metodo_pago" value="mercadopago">
                </div>
                
                <button type="submit" name="confirmar_pedido" class="btn" id="btn-confirmar">
                    <i class="fas fa-check-circle"></i> Confirmar Pedido
                </button>
            </form>
            
            <div style="text-align: center; padding-bottom: 1.5rem;">
                <a href="carrito.php" class="back-link">
                    <i class="fas fa-arrow-left"></i> Volver al carrito
                </a>
            </div>
        </div>
    </div>

    <script>
        // Selección de método de pago
        document.querySelectorAll('.payment-option').forEach(option => {
            option.addEventListener('click', function() {
                document.querySelectorAll('.payment-option').forEach(opt => opt.classList.remove('selected'));
                this.classList.add('selected');
                const metodo = this.getAttribute('data-metodo');
                document.getElementById('metodo_pago').value = metodo;
                
                const btn = document.getElementById('btn-confirmar');
                if(metodo === 'mercadopago') {
                    btn.innerHTML = '<i class="fab fa-mercadopago"></i> Pagar con Mercado Pago';
                } else if(metodo === 'efectivo') {
                    btn.innerHTML = '<i class="fas fa-money-bill-wave"></i> Confirmar pedido (Pagar al recibir)';
                } else {
                    btn.innerHTML = '<i class="fas fa-university"></i> Confirmar pedido (Transferencia)';
                }
            });
        });
        
        // Seleccionar primera opción por defecto
        if(document.querySelector('.payment-option')) {
            document.querySelector('.payment-option').classList.add('selected');
        }
    </script>
</body>
</html>