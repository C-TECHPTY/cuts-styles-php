<?php
// ver_producto.php
require_once 'config/config.php';
require_once 'classes/Product.php';

$producto_id = $_GET['id'] ?? 0;
$productClass = new Product();
$producto = $productClass->getById($producto_id);

if(!$producto) {
    header("Location: productos.php");
    exit;
}

$precio_final = $productClass->getPrecioFinal($producto);
$imagen_principal = !empty($producto['imagen_principal']) ? $producto['imagen_principal'] : 'default-product.png';
$galeria_imagenes = $producto['imagenes'] ?? [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($producto['nombre']); ?> - Cuts & Styles</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include BASE_PATH . 'includes/pwa_head.php'; ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
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
            padding: 2rem;
        }
        
        /* Product Detail */
        .product-detail {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        /* Galería de imágenes */
        .product-gallery {
            position: relative;
        }
        .main-image {
            width: 100%;
            height: 400px;
            border-radius: 12px;
            overflow: hidden;
            background: #f5f5f5;
            cursor: pointer;
            margin-bottom: 1rem;
        }
        .main-image img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            transition: transform 0.3s;
        }
        .main-image img:hover {
            transform: scale(1.05);
        }
        .thumbnail-list {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .thumbnail {
            width: 80px;
            height: 80px;
            border-radius: 8px;
            overflow: hidden;
            cursor: pointer;
            border: 2px solid transparent;
            transition: all 0.3s;
            background: #f5f5f5;
        }
        .thumbnail.active {
            border-color: #e94560;
            box-shadow: 0 0 0 2px rgba(233,69,96,0.3);
        }
        .thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .thumbnail:hover {
            transform: translateY(-3px);
        }
        
        /* Product Info */
        .product-info {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .product-title {
            font-size: 2rem;
            color: #1a1a2e;
        }
        .product-category {
            color: #7f8c8d;
            font-size: 0.9rem;
        }
        .product-category i { margin-right: 5px; }
        .product-price {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 12px;
        }
        .price-current {
            font-size: 2rem;
            color: #e94560;
            font-weight: bold;
        }
        .price-old {
            font-size: 1.2rem;
            color: #95a5a6;
            text-decoration: line-through;
            margin-left: 0.5rem;
        }
        .product-stock {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
        }
        .stock-disponible {
            background: #d5f4e6;
            color: #27ae60;
        }
        .stock-agotado {
            background: #fdedec;
            color: #e74c3c;
        }
        .product-description {
            color: #555;
            line-height: 1.6;
            margin: 1rem 0;
        }
        .quantity-selector {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin: 1rem 0;
        }
        .quantity-selector label {
            font-weight: 600;
        }
        .quantity-input {
            width: 80px;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 8px;
            text-align: center;
        }
        .btn-add-cart, .btn-buy-now {
            width: 100%;
            padding: 1rem;
            border: none;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
        }
        .btn-add-cart {
            background: #1a1a2e;
            color: white;
        }
        .btn-add-cart:hover {
            background: #e94560;
            transform: translateY(-2px);
        }
        .btn-buy-now {
            background: #27ae60;
            color: white;
            margin-top: 1rem;
        }
        .btn-buy-now:hover {
            background: #219653;
            transform: translateY(-2px);
        }
        
        /* Back button */
        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 1rem;
            color: #1a1a2e;
            text-decoration: none;
            font-weight: 500;
        }
        .back-button:hover {
            color: #e94560;
        }
        
        @media (max-width: 768px) {
            .product-detail { grid-template-columns: 1fr; }
            .main-image { height: 300px; }
            .thumbnail { width: 60px; height: 60px; }
            .container { padding: 1rem; margin-top: 80px; }
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
                <a href="productos.php">Productos</a>
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
        <a href="javascript:history.back()" class="back-button">
            <i class="fas fa-arrow-left"></i> Volver a la tienda
        </a>
        
        <div class="product-detail">
            <!-- Galería de imágenes -->
            <div class="product-gallery">
                <div class="main-image" id="mainImage" data-fancybox="gallery" data-src="<?php echo BASE_URL; ?>assets/uploads/productos/<?php echo $imagen_principal; ?>">
                    <img src="<?php echo BASE_URL; ?>assets/uploads/productos/<?php echo $imagen_principal; ?>" 
                         alt="<?php echo htmlspecialchars($producto['nombre']); ?>"
                         id="mainImageSrc"
                         onerror="this.src='<?php echo BASE_URL; ?>assets/img/no-image.png'">
                </div>
                <div class="thumbnail-list" id="thumbnailList">
                    <?php if(!empty($galeria_imagenes)): ?>
                        <?php foreach($galeria_imagenes as $index => $img): ?>
                        <div class="thumbnail <?php echo $index == 0 ? 'active' : ''; ?>" 
                             onclick="cambiarImagen('<?php echo $img['imagen_url']; ?>', this)">
                            <img src="<?php echo BASE_URL; ?>assets/uploads/productos/<?php echo $img['imagen_url']; ?>" 
                                 alt="Thumbnail <?php echo $index+1; ?>"
                                 onerror="this.src='<?php echo BASE_URL; ?>assets/img/no-image.png'">
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="thumbnail active">
                            <img src="<?php echo BASE_URL; ?>assets/img/no-image.png" alt="Sin imagen">
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Información del producto -->
            <div class="product-info">
                <h1 class="product-title"><?php echo htmlspecialchars($producto['nombre']); ?></h1>
                <div class="product-category">
                    <i class="fas fa-tag"></i> <?php echo htmlspecialchars($producto['categoria']); ?>
                </div>
                
                <div class="product-price">
                    <span class="price-current">$<?php echo number_format($precio_final, 2); ?></span>
                    <?php if($producto['en_oferta'] && $producto['descuento'] > 0): ?>
                        <span class="price-old">$<?php echo number_format($producto['precio'], 2); ?></span>
                        <span style="color:#27ae60; margin-left:10px;">-<?php echo $producto['descuento']; ?>% OFF</span>
                    <?php endif; ?>
                </div>
                
                <div>
                    <span class="product-stock <?php echo $producto['stock'] > 0 ? 'stock-disponible' : 'stock-agotado'; ?>">
                        <i class="fas fa-box"></i> <?php echo $producto['stock'] > 0 ? 'Stock disponible (' . $producto['stock'] . ' unidades)' : 'Producto agotado'; ?>
                    </span>
                </div>
                
                <div class="product-description">
                    <h3>Descripción del producto</h3>
                    <p><?php echo nl2br(htmlspecialchars($producto['descripcion'])); ?></p>
                </div>
                
                <?php if($producto['stock'] > 0): ?>
                <div class="quantity-selector">
                    <label>Cantidad:</label>
                    <input type="number" id="cantidad" class="quantity-input" value="1" min="1" max="<?php echo $producto['stock']; ?>">
                </div>
                
                <!-- Formulario para agregar al carrito -->
                <form method="GET" action="carrito.php">
                    <input type="hidden" name="add_to_cart" value="<?php echo $producto['id']; ?>">
                    <input type="hidden" name="cantidad" id="cantidad_hidden" value="1">
                    <button type="submit" class="btn-add-cart" onclick="document.getElementById('cantidad_hidden').value = document.getElementById('cantidad').value">
                        <i class="fas fa-shopping-cart"></i> Agregar al Carrito
                    </button>
                </form>
                
                <!-- Formulario para comprar ahora -->
                <form method="GET" action="checkout.php">
                    <input type="hidden" name="buy_now" value="<?php echo $producto['id']; ?>">
                    <input type="hidden" name="cantidad" id="cantidad_buy" value="1">
                    <button type="submit" class="btn-buy-now" onclick="document.getElementById('cantidad_buy').value = document.getElementById('cantidad').value">
                        <i class="fas fa-bolt"></i> Comprar Ahora
                    </button>
                </form>
                <?php else: ?>
                    <button class="btn-add-cart" disabled style="background:#95a5a6; cursor:not-allowed;">
                        <i class="fas fa-ban"></i> Producto Agotado
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.umd.js"></script>
    <script>
        // Inicializar Fancybox
        Fancybox.bind('[data-fancybox="gallery"]', {
            infinite: true,
            Thumbs: { autoStart: true },
            Toolbar: { display: { left: ["infobar"], right: ["close"] } }
        });
        
        // Cambiar imagen principal
        function cambiarImagen(imagenUrl, elemento) {
            // Actualizar imagen principal
            document.getElementById('mainImageSrc').src = '<?php echo BASE_URL; ?>assets/uploads/productos/' + imagenUrl;
            document.getElementById('mainImage').setAttribute('data-src', '<?php echo BASE_URL; ?>assets/uploads/productos/' + imagenUrl);
            
            // Actualizar clase active en thumbnails
            document.querySelectorAll('.thumbnail').forEach(thumb => {
                thumb.classList.remove('active');
            });
            elemento.classList.add('active');
        }
        
        // Actualizar cantidad en los formularios
        function actualizarCantidad() {
            let cantidad = document.getElementById('cantidad').value;
            document.getElementById('cantidad_hidden').value = cantidad;
            document.getElementById('cantidad_buy').value = cantidad;
        }
        
        // Escuchar cambios en cantidad
        document.getElementById('cantidad').addEventListener('change', actualizarCantidad);
        document.getElementById('cantidad').addEventListener('input', actualizarCantidad);
        
        // Inicializar
        actualizarCantidad();
    </script>
    <?php include BASE_PATH . 'includes/pwa_register.php'; ?>
</body>
</html>
