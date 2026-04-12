<?php
// index.php
require_once 'config/config.php';
require_once 'classes/Product.php';
require_once 'classes/User.php';
require_once 'classes/Service.php';

$productClass = new Product();
$userClass = new User();
$serviceClass = new Service();

// Obtener productos destacados
$productos_destacados = $productClass->getDestacados();

// Obtener barberos destacados
$barberos_query = "SELECT b.*, u.nombre, u.telefono 
    FROM barberos b
    JOIN users u ON b.user_id = u.id
    WHERE b.verificacion_status = 'verificado' AND b.is_available = 1
    ORDER BY b.calificacion_promedio DESC, b.total_servicios DESC
    LIMIT 6";
$stmt = $userClass->conn->prepare($barberos_query);
$stmt->execute();
$barberos_destacados = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas del sistema
$stats_query = "SELECT 
    (SELECT COUNT(*) FROM users WHERE rol = 'cliente') as total_clientes,
    (SELECT COUNT(*) FROM barberos WHERE verificacion_status = 'verificado') as total_barberos,
    (SELECT COUNT(*) FROM servicios WHERE estado = 'completado') as total_servicios";
$stats = $userClass->conn->query($stats_query)->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cuts & Styles - Barbería a Domicilio y Productos Profesionales</title>
    <meta name="description" content="La mejor plataforma de barbería a domicilio. Encuentra barberos verificados, solicita servicios profesionales y compra productos de alta calidad.">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include BASE_PATH . 'includes/pwa_head.php'; ?>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --primary: #1a1a2e;
            --secondary: #e94560;
            --accent: #0f3460;
            --light: #f5f5f5;
            --dark: #16213e;
            --success: #4caf50;
            --warning: #ff9800;
            --gray: #8a8a8a;
            --white: #ffffff;
            --shadow: 0 10px 30px rgba(0,0,0,0.1);
            --shadow-hover: 0 20px 40px rgba(0,0,0,0.15);
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: var(--white);
            color: var(--primary);
            overflow-x: hidden;
        }
        
        /* Navbar */
        .navbar {
            background: rgba(26, 26, 46, 0.95);
            backdrop-filter: blur(10px);
            color: white;
            padding: 1rem 2rem;
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
            transition: all 0.3s ease;
        }
        
        .navbar.scrolled {
            padding: 0.8rem 2rem;
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
        }
        
        .navbar-container {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 1.8rem;
            font-weight: 800;
            letter-spacing: -1px;
        }
        
        .logo i {
            color: var(--secondary);
            margin-right: 10px;
        }
        
        .logo span {
            background: linear-gradient(135deg, var(--secondary), #ff6b6b);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .nav-links {
            display: flex;
            gap: 2rem;
            align-items: center;
        }
        
        .nav-links a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
            position: relative;
        }
        
        .nav-links a:hover {
            color: var(--secondary);
        }
        
        .nav-links a::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--secondary);
            transition: width 0.3s;
        }
        
        .nav-links a:hover::after {
            width: 100%;
        }
        
        /* Botones */
        .btn {
            padding: 12px 28px;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 1rem;
            text-decoration: none;
        }
        
        .btn-primary {
            background: var(--secondary);
            color: white;
        }
        
        .btn-primary:hover {
            background: #c7354f;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(233, 69, 96, 0.3);
        }
        
        .btn-outline {
            background: transparent;
            border: 2px solid var(--secondary);
            color: var(--secondary);
        }
        
        .btn-outline:hover {
            background: var(--secondary);
            color: white;
            transform: translateY(-2px);
        }
        
        .btn-large {
            padding: 16px 40px;
            font-size: 1.1rem;
        }
        
        /* Hero Section */
        .hero {
            min-height: 100vh;
            background: linear-gradient(135deg, var(--primary) 0%, var(--dark) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        
        .hero::before {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="%23e94560" fill-opacity="0.1" d="M0,96L48,112C96,128,192,160,288,160C384,160,480,128,576,122.7C672,117,768,139,864,154.7C960,171,1056,181,1152,165.3C1248,149,1344,107,1392,85.3L1440,64L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>') no-repeat bottom;
            background-size: cover;
            opacity: 0.3;
        }
        
        .hero-content {
            text-align: center;
            color: white;
            z-index: 1;
            padding: 2rem;
            max-width: 800px;
        }
        
        .hero-content h1 {
            font-size: 4rem;
            font-weight: 800;
            margin-bottom: 1rem;
            animation: fadeInUp 0.8s ease;
        }
        
        .hero-content p {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            opacity: 0.9;
            animation: fadeInUp 0.8s ease 0.2s backwards;
        }
        
        .hero-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            animation: fadeInUp 0.8s ease 0.4s backwards;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Secciones generales */
        .section {
            padding: 5rem 2rem;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .section-title {
            text-align: center;
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--primary);
        }
        
        .section-subtitle {
            text-align: center;
            color: var(--gray);
            margin-bottom: 3rem;
            font-size: 1.1rem;
        }
        
        /* Servicios Grid */
        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }
        
        .service-card {
            background: var(--white);
            border-radius: 20px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s;
            box-shadow: var(--shadow);
            cursor: pointer;
        }
        
        .service-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow-hover);
        }
        
        .service-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--secondary), #ff6b6b);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
        }
        
        .service-icon i {
            font-size: 2.5rem;
            color: white;
        }
        
        .service-card h3 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }
        
        .service-price {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--secondary);
            margin: 1rem 0;
        }
        
        /* Barberos Grid */
        .barberos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }
        
        .barbero-card {
            background: var(--white);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: all 0.3s;
        }
        
        .barbero-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow-hover);
        }
        
        .barbero-avatar {
            background: linear-gradient(135deg, var(--primary), var(--dark));
            padding: 2rem;
            text-align: center;
        }
        
        .barbero-avatar i {
            font-size: 4rem;
            color: var(--secondary);
        }
        
        .barbero-info {
            padding: 1.5rem;
        }
        
        .barbero-info h3 {
            font-size: 1.3rem;
            margin-bottom: 0.5rem;
        }
        
        .barbero-rating {
            color: #ffc107;
            margin-bottom: 1rem;
        }
        
        .barbero-specialty {
            color: var(--gray);
            margin-bottom: 1rem;
        }
        
        /* Productos Grid */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }
        
        .product-card {
            background: var(--white);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: all 0.3s;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
        }
        
        .product-image {
            background: var(--light);
            padding: 2rem;
            text-align: center;
        }
        
        .product-image i {
            font-size: 4rem;
            color: var(--secondary);
        }
        
        .product-info {
            padding: 1.5rem;
        }
        
        .product-info h3 {
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
        }
        
        .product-price {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--secondary);
            margin: 0.5rem 0;
        }
        
        /* Cómo funciona */
        .how-it-works {
            background: var(--light);
        }
        
        .steps {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            text-align: center;
        }
        
        .step {
            position: relative;
        }
        
        .step-number {
            width: 60px;
            height: 60px;
            background: var(--secondary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.5rem;
            font-weight: bold;
        }
        
        /* Estadísticas */
        .stats {
            background: linear-gradient(135deg, var(--primary), var(--dark));
            color: white;
            padding: 4rem 2rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
            text-align: center;
        }
        
        .stat-item h3 {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
        }
        
        /* Testimonios */
        .testimonials-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }
        
        .testimonial-card {
            background: var(--white);
            padding: 2rem;
            border-radius: 20px;
            box-shadow: var(--shadow);
        }
        
        .testimonial-text {
            font-style: italic;
            margin-bottom: 1rem;
            color: var(--gray);
        }
        
        .testimonial-author {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .testimonial-avatar {
            width: 50px;
            height: 50px;
            background: var(--secondary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }
        
        /* CTA */
        .cta {
            background: linear-gradient(135deg, var(--secondary), #ff6b6b);
            color: white;
            text-align: center;
            padding: 4rem 2rem;
        }
        
        .cta h2 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        
        .cta .btn {
            background: white;
            color: var(--secondary);
            margin-top: 1rem;
        }
        
        .cta .btn:hover {
            background: var(--primary);
            color: white;
        }
        
        /* Footer */
        footer {
            background: var(--primary);
            color: white;
            padding: 3rem 2rem 1rem;
        }
        
        .footer-content {
            max-width: 1400px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
        }
        
        .footer-section h3 {
            margin-bottom: 1rem;
        }
        
        .footer-section a {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            display: block;
            margin-bottom: 0.5rem;
            transition: color 0.3s;
        }
        
        .footer-section a:hover {
            color: var(--secondary);
        }
        
        .social-links {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .social-links a {
            width: 40px;
            height: 40px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }
        
        .social-links a:hover {
            background: var(--secondary);
            transform: translateY(-3px);
        }
        
        .copyright {
            text-align: center;
            padding-top: 2rem;
            margin-top: 2rem;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .navbar-container {
                flex-direction: column;
                gap: 1rem;
            }
            
            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
                gap: 1rem;
            }
            
            .hero-content h1 {
                font-size: 2.5rem;
            }
            
            .hero-buttons {
                flex-direction: column;
            }
            
            .section-title {
                font-size: 2rem;
            }
            
            .services-grid, .barberos-grid, .products-grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* Animaciones */
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }
        
        .floating {
            animation: float 3s ease-in-out infinite;
        }
    </style>
</head>
<body>
    <nav class="navbar" id="navbar">
        <div class="navbar-container">
            <div class="logo">
                <i class="fas fa-cut"></i>
                <span>Cuts</span> & Styles
            </div>
            <div class="nav-links">
                <a href="#inicio">Inicio</a>
                <a href="#servicios">Servicios</a>
                <a href="#barberos">Barberos</a>
                <a href="#productos">Productos</a>
                <a href="#como-funciona">Cómo Funciona</a>
                <a href="productos.php" class="btn btn-outline" style="padding: 8px 20px;">
                    <i class="fas fa-store"></i> Tienda
                </a>
                <a href="login.php" class="btn btn-primary" style="padding: 8px 20px;">
                    <i class="fas fa-user"></i> Iniciar Sesión
                </a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="inicio" class="hero">
        <div class="hero-content">
            <h1>Barbería Profesional<br>Donde Tú Estés</h1>
            <p>Los mejores barberos verificados llegan a tu domicilio. Calidad, comodidad y estilo en un solo lugar.</p>
            <div class="hero-buttons">
                <a href="register.php" class="btn btn-primary btn-large">
                    <i class="fas fa-cut"></i> Solicita tu Barbero
                </a>
                <a href="#como-funciona" class="btn btn-outline btn-large">
                    <i class="fas fa-play"></i> Cómo Funciona
                </a>
            </div>
        </div>
    </section>

    <!-- Servicios -->
    <section id="servicios" class="section">
        <div class="container">
            <h2 class="section-title">Nuestros Servicios</h2>
            <p class="section-subtitle">Servicios profesionales adaptados a tus necesidades</p>
            <div class="services-grid">
                <div class="service-card">
                    <div class="service-icon"><i class="fas fa-cut"></i></div>
                    <h3>Corte de Cabello</h3>
                    <p>Estilos modernos y clásicos para todos los gustos</p>
                    <div class="service-price">Desde $15</div>
                    <a href="register.php" class="btn btn-primary">Solicitar</a>
                </div>
                <div class="service-card">
                    <div class="service-icon"><i class="fas fa-beard"></i></div>
                    <h3>Arreglo de Barba</h3>
                    <p>Perfilado, recorte y mantenimiento profesional</p>
                    <div class="service-price">Desde $10</div>
                    <a href="register.php" class="btn btn-primary">Solicitar</a>
                </div>
                <div class="service-card">
                    <div class="service-icon"><i class="fas fa-cut"></i><i class="fas fa-beard"></i></div>
                    <h3>Paquete Completo</h3>
                    <p>Corte + Barba + Afeitado con navaja</p>
                    <div class="service-price">Desde $25</div>
                    <a href="register.php" class="btn btn-primary">Solicitar</a>
                </div>
                <div class="service-card">
                    <div class="service-icon"><i class="fas fa-tint"></i></div>
                    <h3>Tinte y Tratamientos</h3>
                    <p>Coloración y tratamientos capilares</p>
                    <div class="service-price">Desde $30</div>
                    <a href="register.php" class="btn btn-primary">Solicitar</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Barberos Destacados -->
    <section id="barberos" class="section" style="background: var(--light);">
        <div class="container">
            <h2 class="section-title">Barberos Destacados</h2>
            <p class="section-subtitle">Profesionales verificados con alta calificación</p>
            <div class="barberos-grid">
                <?php foreach($barberos_destacados as $barbero): ?>
                <div class="barbero-card">
                    <div class="barbero-avatar">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <div class="barbero-info">
                        <h3><?php echo htmlspecialchars($barbero['nombre']); ?></h3>
                        <div class="barbero-rating">
                            <?php 
                            $rating = round($barbero['calificacion_promedio'], 1);
                            for($i = 1; $i <= 5; $i++): 
                                if($i <= $rating): ?>
                                    <i class="fas fa-star"></i>
                                <?php elseif($i - 0.5 <= $rating): ?>
                                    <i class="fas fa-star-half-alt"></i>
                                <?php else: ?>
                                    <i class="far fa-star"></i>
                                <?php endif; ?>
                            <?php endfor; ?>
                            <span style="color: var(--gray);">(<?php echo $barbero['total_servicios']; ?> servicios)</span>
                        </div>
                        <div class="barbero-specialty">
                            <i class="fas fa-cut"></i> <?php echo $barbero['especialidad'] ?? 'Barbero Profesional'; ?>
                        </div>
                        <div class="barbero-specialty">
                            <i class="fas fa-briefcase"></i> <?php echo $barbero['experiencia']; ?> años de experiencia
                        </div>
                        <a href="register.php" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">
                            <i class="fas fa-calendar-check"></i> Agendar
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Productos Destacados -->
    <section id="productos" class="section">
        <div class="container">
            <h2 class="section-title">Productos para Barberos</h2>
            <p class="section-subtitle">Equipamiento profesional de alta calidad</p>
            <div class="products-grid">
                <?php foreach($productos_destacados as $producto): ?>
                <div class="product-card">
                    <div class="product-image">
                        <i class="fas fa-cut"></i>
                    </div>
                    <div class="product-info">
                        <h3><?php echo htmlspecialchars($producto['nombre']); ?></h3>
                        <p><?php echo substr(htmlspecialchars($producto['descripcion']), 0, 80); ?>...</p>
                        <div class="product-price">$<?php echo number_format($producto['precio'], 2); ?></div>
                        <a href="productos.php" class="btn btn-primary" style="width: 100%;">
                            <i class="fas fa-shopping-cart"></i> Ver Producto
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div style="text-align: center; margin-top: 3rem;">
                <a href="productos.php" class="btn btn-outline">
                    <i class="fas fa-store"></i> Ver Todos los Productos
                </a>
            </div>
        </div>
    </section>

    <!-- Cómo Funciona -->
    <section id="como-funciona" class="how-it-works section">
        <div class="container">
            <h2 class="section-title">¿Cómo Funciona?</h2>
            <p class="section-subtitle">Simple, rápido y seguro</p>
            <div class="steps">
                <div class="step">
                    <div class="step-number">1</div>
                    <h3>Regístrate Gratis</h3>
                    <p>Crea tu cuenta en menos de 2 minutos</p>
                </div>
                <div class="step">
                    <div class="step-number">2</div>
                    <h3>Solicita tu Servicio</h3>
                    <p>Elige el tipo de servicio y tu ubicación</p>
                </div>
                <div class="step">
                    <div class="step-number">3</div>
                    <h3>Encuentra un Barbero</h3>
                    <p>Barberos verificados cerca de ti</p>
                </div>
                <div class="step">
                    <div class="step-number">4</div>
                    <h3>Recibe en tu Domicilio</h3>
                    <p>El barbero llega a la hora acordada</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Estadísticas -->
    <section class="stats">
        <div class="container">
            <div class="stats-grid">
                <div class="stat-item">
                    <h3><?php echo number_format($stats['total_clientes'] ?? 0); ?></h3>
                    <p>Clientes Satisfechos</p>
                </div>
                <div class="stat-item">
                    <h3><?php echo number_format($stats['total_barberos'] ?? 0); ?></h3>
                    <p>Barberos Verificados</p>
                </div>
                <div class="stat-item">
                    <h3><?php echo number_format($stats['total_servicios'] ?? 0); ?></h3>
                    <p>Servicios Completados</p>
                </div>
                <div class="stat-item">
                    <h3>4.8</h3>
                    <p>Calificación Promedio</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonios -->
    <section class="section">
        <div class="container">
            <h2 class="section-title">Lo Que Dicen Nuestros Clientes</h2>
            <p class="section-subtitle">Miles de clientes confían en nosotros</p>
            <div class="testimonials-grid">
                <div class="testimonial-card">
                    <p class="testimonial-text">"Excelente servicio, el barbero llegó a tiempo y muy profesional. Mi corte quedó perfecto. Definitivamente volveré a usar la plataforma."</p>
                    <div class="testimonial-author">
                        <div class="testimonial-avatar"><i class="fas fa-user"></i></div>
                        <div>
                            <strong>Carlos Rodríguez</strong>
                            <div class="barbero-rating"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div>
                        </div>
                    </div>
                </div>
                <div class="testimonial-card">
                    <p class="testimonial-text">"La mejor plataforma de barbería a domicilio. Barberos verificados, precios justos y atención de primera. 100% recomendado."</p>
                    <div class="testimonial-author">
                        <div class="testimonial-avatar"><i class="fas fa-user"></i></div>
                        <div>
                            <strong>María González</strong>
                            <div class="barbero-rating"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div>
                        </div>
                    </div>
                </div>
                <div class="testimonial-card">
                    <p class="testimonial-text">"Increíble la calidad de los productos que venden. Compré unas tijeras profesionales y son excelentes. El envío fue rápido."</p>
                    <div class="testimonial-author">
                        <div class="testimonial-avatar"><i class="fas fa-user"></i></div>
                        <div>
                            <strong>Javier Méndez</strong>
                            <div class="barbero-rating"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="cta">
        <div class="container">
            <h2>¿Listo para lucir un look espectacular?</h2>
            <p>Únete a miles de clientes satisfechos y solicita tu barbero hoy mismo</p>
            <a href="register.php" class="btn btn-large">
                <i class="fas fa-user-plus"></i> Crear Cuenta Gratis
            </a>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="footer-content">
            <div class="footer-section">
                <h3><i class="fas fa-cut"></i> Cuts & Styles</h3>
                <p>La plataforma líder en barbería a domicilio. Conectamos clientes con barberos profesionales verificados.</p>
                <div class="social-links">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-whatsapp"></i></a>
                    <a href="#"><i class="fab fa-tiktok"></i></a>
                </div>
            </div>
            <div class="footer-section">
                <h3>Enlaces Rápidos</h3>
                <a href="#inicio">Inicio</a>
                <a href="#servicios">Servicios</a>
                <a href="#barberos">Barberos</a>
                <a href="#productos">Productos</a>
                <a href="#como-funciona">Cómo Funciona</a>
            </div>
            <div class="footer-section">
                <h3>Para Barberos</h3>
                <a href="register.php">Regístrate como Barbero</a>
                <a href="#">Requisitos de Verificación</a>
                <a href="#">Centro de Ayuda</a>
                <a href="#">Gana con Nosotros</a>
            </div>
            <div class="footer-section">
                <h3>Contacto</h3>
                <p><i class="fas fa-envelope"></i> info@cutsstyles.com</p>
                <p><i class="fas fa-phone"></i> +57 300 123 4567</p>
                <p><i class="fas fa-clock"></i> Lun-Dom: 8am - 10pm</p>
            </div>
        </div>
        <div class="copyright">
            <p>&copy; 2024 Cuts & Styles - Todos los derechos reservados</p>
        </div>
    </footer>

    <script>
        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.getElementById('navbar');
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });

        // Smooth scroll para enlaces
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    </script>
    <?php include BASE_PATH . 'includes/pwa_register.php'; ?>
</body>
</html>
