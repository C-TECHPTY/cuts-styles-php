<?php
// carrito.php
require_once 'config/config.php';
require_once 'classes/Product.php';

// Inicializar sesión si no existe
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Inicializar carrito si no existe
if(!isset($_SESSION['carrito'])) {
    $_SESSION['carrito'] = [];
}

// Procesar acciones
if(isset($_GET['add_to_cart'])) {
    $producto_id = $_GET['add_to_cart'];
    $cantidad = $_GET['cantidad'] ?? 1;
    
    if(isset($_SESSION['carrito'][$producto_id])) {
        $_SESSION['carrito'][$producto_id] += $cantidad;
    } else {
        $_SESSION['carrito'][$producto_id] = $cantidad;
    }
    header("Location: carrito.php");
    exit();
}

if(isset($_GET['remove'])) {
    $producto_id = $_GET['remove'];
    unset($_SESSION['carrito'][$producto_id]);
    header("Location: carrito.php");
    exit();
}

if(isset($_GET['update'])) {
    $producto_id = $_GET['update'];
    $cantidad = $_GET['cantidad'];
    if($cantidad > 0) {
        $_SESSION['carrito'][$producto_id] = $cantidad;
    } else {
        unset($_SESSION['carrito'][$producto_id]);
    }
    header("Location: carrito.php");
    exit();
}

if(isset($_GET['empty'])) {
    $_SESSION['carrito'] = [];
    header("Location: carrito.php");
    exit();
}

$productClass = new Product();
$carrito_items = [];
$total = 0;

if(!empty($_SESSION['carrito'])) {
    $ids = array_keys($_SESSION['carrito']);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $query = "SELECT * FROM productos WHERE id IN ($placeholders) AND estado = 'activo'";
    $stmt = $productClass->conn->prepare($query);
    foreach($ids as $key => $id) {
        $stmt->bindValue($key+1, $id);
    }
    $stmt->execute();
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach($productos as $producto) {
        $cantidad = $_SESSION['carrito'][$producto['id']];
        $precio_final = $productClass->getPrecioFinal($producto);
        $subtotal = $precio_final * $cantidad;
        $total += $subtotal;
        
        $carrito_items[] = [
            'id' => $producto['id'],
            'nombre' => $producto['nombre'],
            'precio' => $precio_final,
            'precio_original' => $producto['precio'],
            'cantidad' => $cantidad,
            'subtotal' => $subtotal,
            'stock' => $producto['stock'],
            'imagen' => $productClass->getImagenPrincipal($producto['id'])
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Carrito - Cuts & Styles</title>
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
            max-width: 1200px;
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
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        .table th, .table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #ecf0f1;
        }
        .table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #1a1a2e;
        }
        .product-img {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            object-fit: cover;
            background: #f5f5f5;
        }
        .quantity-input {
            width: 60px;
            padding: 5px;
            text-align: center;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .btn-update {
            background: #3498db;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
        }
        .btn-remove {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
        }
        .cart-total {
            padding: 1.5rem;
            text-align: right;
            border-top: 2px solid #ecf0f1;
        }
        .total-amount {
            font-size: 1.8rem;
            font-weight: bold;
            color: #e94560;
        }
        .cart-actions {
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            border-top: 1px solid #ecf0f1;
        }
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-primary {
            background: #e94560;
            color: white;
        }
        .btn-primary:hover {
            background: #c7354f;
            transform: translateY(-2px);
        }
        .btn-secondary {
            background: #1a1a2e;
            color: white;
        }
        .btn-secondary:hover {
            background: #2c3e50;
            transform: translateY(-2px);
        }
        .btn-danger {
            background: #e74c3c;
            color: white;
        }
        .btn-danger:hover {
            background: #c0392b;
        }
        .empty-cart {
            text-align: center;
            padding: 4rem;
        }
        .empty-cart i {
            font-size: 5rem;
            color: #95a5a6;
            margin-bottom: 1rem;
        }
        .empty-cart h3 {
            color: #1a1a2e;
            margin-bottom: 1rem;
        }
        @media (max-width: 768px) {
            .table, .table tbody, .table tr, .table td {
                display: block;
            }
            .table thead {
                display: none;
            }
            .table tr {
                border-bottom: 1px solid #ecf0f1;
                padding: 1rem;
            }
            .table td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                border: none;
            }
            .table td:before {
                content: attr(data-label);
                font-weight: bold;
                margin-right: 1rem;
            }
            .cart-actions {
                flex-direction: column;
                gap: 1rem;
            }
            .cart-actions .btn {
                justify-content: center;
            }
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
                <?php if(isset($_SESSION['user_id'])): ?>
                    <a href="<?php echo $_SESSION['user_rol'] == 'admin' ? 'admin/dashboard.php' : ($_SESSION['user_rol'] == 'barbero' ? 'barbero.php' : 'cliente.php'); ?>">Mi Cuenta</a>
                    <a href="logout.php">Salir</a>
                <?php else: ?>
                    <a href="login.php">Iniciar Sesión</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="card">
            <div class="card-header">
                <h1><i class="fas fa-shopping-cart"></i> Mi Carrito de Compras</h1>
            </div>
            
            <?php if(empty($carrito_items)): ?>
                <div class="empty-cart">
                    <i class="fas fa-shopping-basket"></i>
                    <h3>Tu carrito está vacío</h3>
                    <p>Parece que aún no has agregado ningún producto.</p>
                    <a href="productos.php" class="btn btn-primary" style="margin-top: 1rem;">
                        <i class="fas fa-store"></i> Ver Productos
                    </a>
                </div>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Precio</th>
                            <th>Cantidad</th>
                            <th>Subtotal</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($carrito_items as $item): ?>
                        <tr>
                            <td data-label="Producto">
                                <div style="display: flex; align-items: center; gap: 1rem;">
                                    <img src="<?php echo BASE_URL; ?>assets/uploads/productos/<?php echo $item['imagen']; ?>" 
                                         class="product-img" 
                                         onerror="this.src='<?php echo BASE_URL; ?>assets/img/no-image.png'">
                                    <div>
                                        <strong><?php echo htmlspecialchars($item['nombre']); ?></strong>
                                        <?php if($item['precio'] != $item['precio_original']): ?>
                                            <br><small style="color:#27ae60;">Oferta: -<?php echo round((1 - $item['precio']/$item['precio_original'])*100); ?>%</small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Precio">
                                $<?php echo number_format($item['precio'], 2); ?>
                            </td>
                            <td data-label="Cantidad">
                                <form method="GET" style="display: flex; gap: 5px; align-items: center;">
                                    <input type="hidden" name="update" value="<?php echo $item['id']; ?>">
                                    <input type="number" name="cantidad" value="<?php echo $item['cantidad']; ?>" 
                                           min="1" max="<?php echo $item['stock']; ?>" class="quantity-input">
                                    <button type="submit" class="btn-update"><i class="fas fa-sync-alt"></i></button>
                                </form>
                            </td>
                            <td data-label="Subtotal">
                                <strong>$<?php echo number_format($item['subtotal'], 2); ?></strong>
                            </td>
                            <td data-label="Acciones">
                                <a href="?remove=<?php echo $item['id']; ?>" class="btn-remove" onclick="return confirm('¿Eliminar este producto?')">
                                    <i class="fas fa-trash"></i> Eliminar
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="cart-total">
                    <h3>Total del pedido</h3>
                    <div class="total-amount">$<?php echo number_format($total, 2); ?></div>
                </div>
                
                <div class="cart-actions">
                    <a href="?empty=1" class="btn btn-danger" onclick="return confirm('¿Vaciar todo el carrito?')">
                        <i class="fas fa-trash-alt"></i> Vaciar Carrito
                    </a>
                    <a href="productos.php" class="btn btn-secondary">
                        <i class="fas fa-store"></i> Seguir Comprando
                    </a>
                    <a href="checkout.php" class="btn btn-primary">
                        <i class="fas fa-credit-card"></i> Proceder al Pago
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>