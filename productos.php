<?php
// productos.php
require_once 'config/config.php';
require_once 'classes/Product.php';

$productClass = new Product();
$categoria = $_GET['categoria'] ?? 'todos';
$en_oferta = isset($_GET['ofertas']) && $_GET['ofertas'] == 1;

if($en_oferta) {
    $productos = $productClass->getEnOferta();
} else {
    $productos = $productClass->getAll($categoria);
}

$categorias = $productClass->getCategorias();

// URL base para imágenes
$base_url = BASE_URL;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Productos - Cuts & Styles</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
        }
        .navbar {
            background: #1a1a2e;
            color: white;
            padding: 1rem 2rem;
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
        }
        .navbar-container {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .logo {
            font-size: 1.5rem;
            font-weight: bold;
        }
        .logo i { color: #e94560; margin-right: 10px; }
        .nav-links a {
            color: white;
            text-decoration: none;
            margin-left: 2rem;
            transition: color 0.3s;
        }
        .nav-links a:hover { color: #e94560; }
        .container {
            max-width: 1400px;
            margin: 100px auto 0;
            padding: 2rem;
        }
        .page-header { text-align: center; margin-bottom: 3rem; }
        .page-header h1 { color: #1a1a2e; font-size: 2.5rem; margin-bottom: 1rem; }
        .page-header p { color: #7f8c8d; font-size: 1.1rem; }
        
        /* Categories */
        .categories {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            justify-content: center;
            margin-bottom: 2rem;
        }
        .category-btn {
            padding: 0.8rem 1.5rem;
            background: white;
            border: 2px solid #ecf0f1;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            color: #1a1a2e;
            font-weight: 500;
        }
        .category-btn.active, .category-btn:hover {
            background: #e94560;
            color: white;
            border-color: #e94560;
        }
        
        /* Products Grid */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 2rem;
        }
        .product-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
            position: relative;
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }
        .product-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            background: #e94560;
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            z-index: 1;
        }
        .product-badge.oferta {
            background: #27ae60;
        }
        .product-image {
            height: 250px;
            background: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }
        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s;
        }
        .product-card:hover .product-image img {
            transform: scale(1.05);
        }
        .product-info {
            padding: 1.5rem;
        }
        .product-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #1a1a2e;
        }
        .product-description {
            color: #7f8c8d;
            margin-bottom: 1rem;
            font-size: 0.9rem;
            line-height: 1.4;
        }
        .product-price {
            margin-bottom: 0.5rem;
        }
        .price-current {
            font-size: 1.5rem;
            color: #e94560;
            font-weight: bold;
        }
        .price-old {
            font-size: 1rem;
            color: #95a5a6;
            text-decoration: line-through;
            margin-left: 0.5rem;
        }
        .product-stock {
            color: #27ae60;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }
        .product-stock.agotado {
            color: #e74c3c;
        }
        .btn-add {
            width: 100%;
            padding: 0.8rem;
            background: #1a1a2e;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-weight: 600;
            text-decoration: none;
        }
        .btn-add:hover:not(:disabled) {
            background: #e94560;
        }
        .btn-add:disabled {
            background: #95a5a6;
            cursor: not-allowed;
        }
        .btn-ver {
            width: 100%;
            padding: 0.8rem;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-weight: 600;
            text-decoration: none;
            margin-top: 0.5rem;
        }
        .btn-ver:hover {
            background: #2980b9;
        }
        
        /* Cart Icon */
        .cart-icon {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #e94560;
            color: white;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
            transition: transform 0.3s;
            z-index: 100;
            text-decoration: none;
        }
        .cart-icon:hover { transform: scale(1.1); }
        .cart-count {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #1a1a2e;
            color: white;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem;
            background: white;
            border-radius: 16px;
        }
        .empty-state i {
            font-size: 4rem;
            color: #95a5a6;
            margin-bottom: 1rem;
        }
        
        footer {
            background: #1a1a2e;
            color: white;
            padding: 2rem;
            text-align: center;
            margin-top: 3rem;
        }
        
        @media (max-width: 768px) {
            .navbar-container { flex-direction: column; gap: 1rem; }
            .nav-links { display: flex; gap: 1rem; flex-wrap: wrap; justify-content: center; }
            .nav-links a { margin-left: 0; }
            .products-grid { grid-template-columns: 1fr; }
            .container { padding: 1rem; margin-top: 120px; }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-container">
            <div class="logo"><i class="fas fa-cut"></i> Cuts & Styles</div>
            <div class="nav-links">
                <a href="index.php">Inicio</a>
                <a href="index.php#servicios">Servicios</a>
                <a href="productos.php" class="active">Productos</a>
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
        <div class="page-header">
            <h1><i class="fas fa-store"></i> Tienda para Barberos</h1>
            <p>Equipamiento profesional para los mejores resultados</p>
        </div>

        <div class="categories">
            <a href="?categoria=todos" class="category-btn <?php echo $categoria == 'todos' && !$en_oferta ? 'active' : ''; ?>">Todos</a>
            <?php foreach($categorias as $cat): ?>
            <a href="?categoria=<?php echo urlencode($cat); ?>" class="category-btn <?php echo $categoria == $cat ? 'active' : ''; ?>"><?php echo htmlspecialchars($cat); ?></a>
            <?php endforeach; ?>
            <a href="?ofertas=1" class="category-btn <?php echo $en_oferta ? 'active' : ''; ?>"><i class="fas fa-tag"></i> En Oferta</a>
        </div>

        <?php if(empty($productos)): ?>
        <div class="empty-state">
            <i class="fas fa-box-open"></i>
            <h3>No hay productos disponibles</h3>
            <p>Pronto agregaremos nuevos productos para ti.</p>
            <a href="index.php" class="category-btn" style="display: inline-block; margin-top: 1rem;">Volver al inicio</a>
        </div>
        <?php else: ?>
        <div class="products-grid">
            <?php foreach($productos as $producto): 
                $precio_final = $productClass->getPrecioFinal($producto);
                $imagen = !empty($producto['imagen_principal']) ? $producto['imagen_principal'] : 'default-product.png';
                $ruta_imagen = BASE_URL . 'assets/uploads/productos/' . $imagen;
            ?>
            <div class="product-card">
                <?php if($producto['en_oferta'] && $producto['descuento'] > 0): ?>
                    <div class="product-badge oferta"><i class="fas fa-tag"></i> -<?php echo $producto['descuento']; ?>%</div>
                <?php elseif($producto['destacado']): ?>
                    <div class="product-badge"><i class="fas fa-star"></i> Destacado</div>
                <?php endif; ?>
                
                <a href="ver_producto.php?id=<?php echo $producto['id']; ?>" style="text-decoration: none;">
                    <div class="product-image">
                        <img src="<?php echo $ruta_imagen; ?>" 
                             alt="<?php echo htmlspecialchars($producto['nombre']); ?>"
                             onerror="this.src='<?php echo BASE_URL; ?>assets/img/no-image.png'">
                    </div>
                </a>
                
                <div class="product-info">
                    <a href="ver_producto.php?id=<?php echo $producto['id']; ?>" style="text-decoration: none; color: inherit;">
                        <h3 class="product-title"><?php echo htmlspecialchars($producto['nombre']); ?></h3>
                        <p class="product-description"><?php echo htmlspecialchars(substr($producto['descripcion'], 0, 80)); ?>...</p>
                    </a>
                    
                    <div class="product-price">
                        <span class="price-current">$<?php echo number_format($precio_final, 2); ?></span>
                        <?php if($producto['en_oferta'] && $producto['descuento'] > 0): ?>
                            <span class="price-old">$<?php echo number_format($producto['precio'], 2); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="product-stock <?php echo $producto['stock'] <= 0 ? 'agotado' : ''; ?>">
                        <i class="fas fa-box"></i> 
                        <?php echo $producto['stock'] > 0 ? 'Stock disponible (' . $producto['stock'] . ' uds)' : 'Agotado'; ?>
                    </div>
                    
                    <?php if($producto['stock'] > 0): ?>
                    <form method="GET" action="carrito.php" style="display: inline; width: 100%;">
                        <input type="hidden" name="add_to_cart" value="<?php echo $producto['id']; ?>">
                        <input type="hidden" name="cantidad" value="1">
                        <button type="submit" class="btn-add">
                            <i class="fas fa-shopping-cart"></i> Agregar al Carrito
                        </button>
                    </form>
                    <?php else: ?>
                        <button class="btn-add" disabled>
                            <i class="fas fa-ban"></i> Agotado
                        </button>
                    <?php endif; ?>
                    
                    <a href="ver_producto.php?id=<?php echo $producto['id']; ?>" class="btn-ver">
                        <i class="fas fa-eye"></i> Ver Detalles
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <a href="carrito.php" class="cart-icon">
        <i class="fas fa-shopping-cart"></i>
        <span class="cart-count" id="cart-count">0</span>
    </a>

    <footer>
        <p>&copy; 2024 Cuts & Styles - Equipamiento profesional para barberos</p>
    </footer>

    <script>
        function updateCartCount() {
            fetch('api/get_cart_count.php')
                .then(response => response.json())
                .then(data => {
                    document.getElementById('cart-count').textContent = data.count || 0;
                })
                .catch(() => {
                    // Si hay error, intentar leer de localStorage como respaldo
                    let cart = JSON.parse(localStorage.getItem('cart') || '[]');
                    document.getElementById('cart-count').textContent = cart.length;
                });
        }
        
        updateCartCount();
    </script>
</body>
</html>