<?php
// cliente.php
require_once 'config/config.php';
require_once 'classes/User.php';
require_once 'classes/Service.php';
require_once 'classes/Rewards.php';

// Verificar autenticación
if(!isset($_SESSION['user_id']) || $_SESSION['user_rol'] != 'cliente') {
    redirect('login.php');
}

$user = new User();
$user->id = $_SESSION['user_id'];
$profile = $user->getProfile();

$service = new Service();
$rewards = new Rewards();

// Obtener datos del cliente
$cliente_query = "SELECT id FROM clientes WHERE user_id = :user_id";
$stmt = $user->conn->prepare($cliente_query);
$stmt->bindParam(":user_id", $_SESSION['user_id']);
$stmt->execute();
$cliente = $stmt->fetch(PDO::FETCH_ASSOC);
$cliente_id = $cliente['id'] ?? null;

// Procesar solicitud de servicio
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['solicitar_servicio']) && $cliente_id) {
    try {
        verificarCSRFToken($_POST['csrf_token'] ?? null);
    } catch (Exception $e) {
        setFlash('danger', 'Sesion invalida. Intenta nuevamente.');
        redirect('cliente.php');
    }
    $tipo = $_POST['servicio_tipo'];
    $notas = $_POST['servicio_notas'];
    $horarios = $_POST['horario'] ?? [];
    
    $resultado = $service->solicitarServicio($cliente_id, $tipo, $notas, $horarios);
    if($resultado) {
        setFlash('success', '✅ Servicio solicitado exitosamente. Un barbero te contactará pronto.');
    } else {
        setFlash('danger', '❌ Error al solicitar el servicio');
    }
    redirect('cliente.php');
}

// Obtener estadísticas
$stats = ['activos' => 0, 'completados' => 0, 'hoy' => 0];
if($cliente_id) {
    $stats_query = "SELECT 
        COUNT(CASE WHEN estado IN ('pendiente', 'aceptado', 'en_proceso') THEN 1 END) as activos,
        COUNT(CASE WHEN estado = 'completado' THEN 1 END) as completados,
        COUNT(CASE WHEN DATE(fecha_solicitud) = CURDATE() THEN 1 END) as hoy
        FROM servicios WHERE cliente_id = :cliente_id";
    $stmt = $user->conn->prepare($stats_query);
    $stmt->bindParam(":cliente_id", $cliente_id);
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Servicios recientes
$servicios_recientes = [];
if($cliente_id) {
    $recientes_query = "SELECT s.*, u.nombre as barbero_nombre 
        FROM servicios s
        LEFT JOIN barberos b ON s.barbero_id = b.id
        LEFT JOIN users u ON b.user_id = u.id
        WHERE s.cliente_id = :cliente_id 
        ORDER BY s.fecha_solicitud DESC LIMIT 5";
    $stmt = $user->conn->prepare($recientes_query);
    $stmt->bindParam(":cliente_id", $cliente_id);
    $stmt->execute();
    $servicios_recientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Servicios activos
$servicios_activos = [];
if($cliente_id) {
    $activos_query = "SELECT s.*, u.nombre as barbero_nombre 
        FROM servicios s
        LEFT JOIN barberos b ON s.barbero_id = b.id
        LEFT JOIN users u ON b.user_id = u.id
        WHERE s.cliente_id = :cliente_id AND s.estado IN ('pendiente', 'aceptado', 'en_proceso')
        ORDER BY s.fecha_solicitud DESC";
    $stmt = $user->conn->prepare($activos_query);
    $stmt->bindParam(":cliente_id", $cliente_id);
    $stmt->execute();
    $servicios_activos = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Barberos disponibles
$barberos = [];
$barberos_query = "SELECT b.*, u.nombre, u.telefono 
    FROM barberos b
    JOIN users u ON b.user_id = u.id
    WHERE b.is_available = 1 AND b.verificacion_status = 'verificado'
    ORDER BY b.calificacion_promedio DESC LIMIT 10";
$barberos = $user->conn->query($barberos_query)->fetchAll(PDO::FETCH_ASSOC);

// Puntos del cliente
$puntos = 0;
$recompensas = [];
$historial_puntos = [];
if($cliente_id) {
    $puntos = $rewards->getPuntosCliente($cliente_id);
    $recompensas = $rewards->getRecompensasDisponibles();
    $historial_puntos = $rewards->getHistorialPuntos($cliente_id);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Cliente - Cuts & Styles</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --primary: #2C3E50;
            --secondary: #E74C3C;
            --success: #27AE60;
            --warning: #F39C12;
            --light: #ECF0F1;
            --dark: #2C3E50;
            --gray: #95A5A6;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        }
        .dashboard-container { display: flex; min-height: 100vh; }
        .sidebar {
            width: 280px;
            background: var(--primary);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }
        .sidebar-header { padding: 25px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-header h2 { font-size: 24px; margin-bottom: 20px; }
        .sidebar-header h2 i { color: var(--secondary); margin-right: 10px; }
        .user-info { display: flex; align-items: center; gap: 15px; margin-top: 15px; }
        .user-avatar {
            width: 50px; height: 50px; border-radius: 50%;
            background: var(--secondary);
            display: flex; align-items: center; justify-content: center;
            font-size: 24px;
        }
        .role-badge { font-size: 12px; background: var(--secondary); padding: 3px 10px; border-radius: 20px; }
        .nav-menu { padding: 20px 0; }
        .nav-item {
            display: flex; align-items: center; gap: 15px;
            padding: 15px 25px; color: rgba(255,255,255,0.8);
            text-decoration: none; transition: all 0.3s;
            border-left: 3px solid transparent;
            cursor: pointer;
        }
        .nav-item:hover { background: rgba(255,255,255,0.1); color: white; }
        .nav-item.active {
            background: rgba(231,76,60,0.1);
            color: white;
            border-left-color: var(--secondary);
        }
        .nav-item i { width: 20px; }
        .main-content { flex: 1; margin-left: 280px; padding: 25px; }
        .header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 30px; padding-bottom: 20px;
            border-bottom: 2px solid rgba(0,0,0,0.05);
        }
        .header h2 { color: var(--primary); }
        .btn {
            padding: 12px 24px; border: none; border-radius: 8px;
            cursor: pointer; font-weight: 600; transition: all 0.3s;
            display: inline-flex; align-items: center; gap: 8px;
        }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: #1a252f; transform: translateY(-2px); }
        .btn-success { background: var(--success); color: white; }
        .btn-success:hover { background: #219653; transform: translateY(-2px); }
        .btn-sm { padding: 8px 16px; font-size: 14px; }
        .stats-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px; margin-bottom: 30px;
        }
        .stat-card {
            background: white; padding: 25px; border-radius: 12px;
            text-align: center; box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-icon {
            width: 60px; height: 60px; border-radius: 50%;
            background: var(--light); display: flex; align-items: center;
            justify-content: center; margin: 0 auto 15px;
            font-size: 24px; color: var(--secondary);
        }
        .stat-value { font-size: 32px; font-weight: bold; color: var(--primary); }
        .stat-label { color: var(--gray); font-size: 14px; margin-top: 5px; }
        .table-container {
            background: white; border-radius: 12px; overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1); margin-bottom: 30px;
        }
        .table-header {
            padding: 20px; background: var(--light);
            display: flex; justify-content: space-between;
            align-items: center; border-bottom: 1px solid #ddd;
        }
        .table-header h3 { color: var(--primary); }
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { padding: 15px; text-align: left; border-bottom: 1px solid var(--light); }
        .table th { background: #f8f9fa; font-weight: 600; color: var(--primary); }
        .table tr:hover { background: var(--light); }
        .badge {
            padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;
        }
        .badge-pendiente { background: #FEF5E7; color: var(--warning); }
        .badge-aceptado { background: #E8F4FC; color: #3498DB; }
        .badge-en_proceso { background: #E8F4FC; color: #3498DB; }
        .badge-completado { background: #D5F4E6; color: var(--success); }
        .section-content { display: none; animation: fadeIn 0.5s ease; }
        .section-content.active { display: block; }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .alert {
            padding: 15px 20px; border-radius: 8px; margin-bottom: 20px;
            display: flex; align-items: center; gap: 10px;
        }
        .alert-success { background: #D5F4E6; color: var(--success); border: 1px solid #A3E4D7; }
        .alert-danger { background: #FDEDEC; color: var(--danger); border: 1px solid #FADBD8; }
        .form-container { padding: 20px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: var(--primary); }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%; padding: 12px; border: 2px solid var(--light);
            border-radius: 8px; font-size: 16px;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none; border-color: var(--secondary);
        }
        .rewards-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px; padding: 20px;
        }
        .reward-card {
            background: white; border-radius: 12px; padding: 20px;
            text-align: center; border: 2px solid var(--light);
            transition: all 0.3s;
        }
        .reward-card:hover { border-color: var(--secondary); transform: translateY(-3px); }
        .reward-points { font-size: 24px; font-weight: bold; color: var(--warning); margin: 10px 0; }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background: white;
            border-radius: 12px;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }
        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-body { padding: 20px; }
        
        @media (max-width: 768px) {
            .sidebar { width: 70px; }
            .sidebar-header h2 span, .user-details, .nav-item span { display: none; }
            .nav-item { justify-content: center; padding: 15px; }
            .main-content { margin-left: 70px; }
            .stats-grid { grid-template-columns: 1fr; }
            .table-container { overflow-x: auto; }
            .table { min-width: 600px; }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-cut"></i> <span>Cuts & Styles</span></h2>
                <div class="user-info">
                    <div class="user-avatar"><i class="fas fa-user"></i></div>
                    <div class="user-details">
                        <h3><?php echo htmlspecialchars($_SESSION['user_email']); ?></h3>
                        <span class="role-badge">Cliente</span>
                    </div>
                </div>
            </div>
            <nav class="nav-menu">
                <a class="nav-item active" data-section="dashboard"><i class="fas fa-home"></i> <span>Dashboard</span></a>
                <a class="nav-item" data-section="nuevo-servicio"><i class="fas fa-plus-circle"></i> <span>Nuevo Servicio</span></a>
                <a class="nav-item" data-section="servicios-activos"><i class="fas fa-clock"></i> <span>Servicios Activos</span></a>
                <a class="nav-item" data-section="historial"><i class="fas fa-history"></i> <span>Historial</span></a>
                <a class="nav-item" data-section="puntos"><i class="fas fa-star"></i> <span>Mis Puntos</span></a>
                <a class="nav-item" data-section="perfil"><i class="fas fa-user-cog"></i> <span>Mi Perfil</span></a>
                <a href="logout.php" class="nav-item"><i class="fas fa-sign-out-alt"></i> <span>Cerrar Sesión</span></a>
            </nav>
        </aside>

        <main class="main-content">
            <header class="header">
                <h2 id="section-title">Dashboard</h2>
                <button class="btn btn-primary" onclick="location.reload()">
                    <i class="fas fa-sync-alt"></i> Actualizar
                </button>
            </header>

            <div id="alert-container">
                <?php $flash = getFlash(); if($flash): ?>
                <div class="alert alert-<?php echo $flash['type']; ?>">
                    <i class="fas fa-<?php echo $flash['type'] == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo $flash['message']; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Dashboard Section -->
            <section id="dashboard-section" class="section-content active">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-clock"></i></div>
                        <div class="stat-value"><?php echo $stats['activos'] ?? 0; ?></div>
                        <div class="stat-label">Servicios Activos</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                        <div class="stat-value"><?php echo $stats['completados'] ?? 0; ?></div>
                        <div class="stat-label">Completados</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-star"></i></div>
                        <div class="stat-value"><?php echo $puntos; ?></div>
                        <div class="stat-label">Puntos Acumulados</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
                        <div class="stat-value"><?php echo $stats['hoy'] ?? 0; ?></div>
                        <div class="stat-label">Hoy</div>
                    </div>
                </div>

                <div class="table-container">
                    <div class="table-header">
                        <h3>Servicios Recientes</h3>
                    </div>
                    <table class="table">
                        <thead><tr><th>ID</th><th>Tipo</th><th>Barbero</th><th>Fecha</th><th>Estado</th><th>Acciones</th></tr></thead>
                        <tbody>
                            <?php foreach($servicios_recientes as $servicio): ?>
                            <tr>
                                <td>#<?php echo $servicio['id']; ?></td>
                                <td><?php echo $servicio['tipo']; ?></td>
                                <td><?php echo $servicio['barbero_nombre'] ?? 'Pendiente'; ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($servicio['fecha_solicitud'])); ?></td>
                                <td><span class="badge badge-<?php echo $servicio['estado']; ?>"><?php echo ucfirst($servicio['estado']); ?></span></td>
                                <td><button class="btn btn-primary btn-sm" onclick="verDetalles(<?php echo $servicio['id']; ?>)">Ver</button></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($servicios_recientes)): ?>
                            <tr><td colspan="6" style="text-align: center;">No hay servicios recientes</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Nuevo Servicio Section -->
            <section id="nuevo-servicio-section" class="section-content">
                <div class="table-container">
                    <div class="table-header"><h3>Solicitar Nuevo Servicio</h3></div>
                    <div class="form-container">
                        <form method="POST">
                            <?php echo csrf_field(); ?>
                            <div class="form-group">
                                <label><i class="fas fa-cut"></i> Tipo de Servicio</label>
                                <select name="servicio_tipo" required>
                                    <option value="">Seleccionar tipo</option>
                                    <option value="Corte">Corte de Cabello</option>
                                    <option value="Barba">Arreglo de Barba</option>
                                    <option value="Corte+Barba">Corte + Barba</option>
                                    <option value="Tinte">Tinte</option>
                                    <option value="Navaja">Afeitado con Navaja</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-sticky-note"></i> Notas Adicionales</label>
                                <textarea name="servicio_notas" rows="3" placeholder="Ej: Corte degradado, largo en la parte superior..."></textarea>
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-calendar"></i> Preferencia de Horario</label>
                                <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                                    <label><input type="checkbox" name="horario[]" value="mañana"> Mañana (8am - 12pm)</label>
                                    <label><input type="checkbox" name="horario[]" value="tarde"> Tarde (2pm - 6pm)</label>
                                    <label><input type="checkbox" name="horario[]" value="noche"> Noche (7pm - 10pm)</label>
                                </div>
                            </div>
                            <button type="submit" name="solicitar_servicio" class="btn btn-success">
                                <i class="fas fa-paper-plane"></i> Solicitar Servicio
                            </button>
                        </form>
                    </div>
                </div>

                <div class="table-container">
                    <div class="table-header"><h3>Barberos Disponibles</h3></div>
                    <table class="table">
                        <thead><tr><th>Nombre</th><th>Especialidad</th><th>Calificación</th><th>Experiencia</th></tr></thead>
                        <tbody>
                            <?php foreach($barberos as $barbero): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($barbero['nombre']); ?></td>
                                <td><?php echo $barbero['especialidad'] ?? 'General'; ?></td>
                                <td>⭐ <?php echo $barbero['calificacion_promedio']; ?></td>
                                <td><?php echo $barbero['experiencia']; ?> años</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Servicios Activos Section -->
            <section id="servicios-activos-section" class="section-content">
                <div class="table-container">
                    <div class="table-header"><h3>Mis Servicios Activos</h3></div>
                    <table class="table">
                        <thead><tr><th>ID</th><th>Tipo</th><th>Barbero</th><th>Solicitado</th><th>Estado</th><th>Acciones</th></tr></thead>
                        <tbody>
                            <?php foreach($servicios_activos as $servicio): ?>
                            <tr>
                                <td>#<?php echo $servicio['id']; ?></td>
                                <td><?php echo $servicio['tipo']; ?></td>
                                <td><?php echo $servicio['barbero_nombre'] ?? 'Pendiente'; ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($servicio['fecha_solicitud'])); ?></td>
                                <td><span class="badge badge-<?php echo $servicio['estado']; ?>"><?php echo ucfirst($servicio['estado']); ?></span></td>
                                <td><button class="btn btn-primary btn-sm" onclick="verDetalles(<?php echo $servicio['id']; ?>)">Ver</button></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($servicios_activos)): ?>
                            <tr><td colspan="6" style="text-align: center;">No hay servicios activos</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Historial Section -->
            <section id="historial-section" class="section-content">
                <div class="table-container">
                    <div class="table-header"><h3>Historial de Servicios</h3></div>
                    <table class="table">
                        <thead><tr><th>Fecha</th><th>Tipo</th><th>Barbero</th><th>Duración</th><th>Puntos</th><th>Calificación</th></tr></thead>
                        <tbody>
                            <?php
                            $historial_query = "SELECT s.*, u.nombre as barbero_nombre 
                                FROM servicios s
                                LEFT JOIN barberos b ON s.barbero_id = b.id
                                LEFT JOIN users u ON b.user_id = u.id
                                WHERE s.cliente_id = :cliente_id AND s.estado = 'completado'
                                ORDER BY s.fecha_fin DESC LIMIT 20";
                            $stmt = $user->conn->prepare($historial_query);
                            $stmt->bindParam(":cliente_id", $cliente_id);
                            $stmt->execute();
                            $historial = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            ?>
                            <?php foreach($historial as $servicio): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($servicio['fecha_fin'])); ?></td>
                                <td><?php echo $servicio['tipo']; ?></td>
                                <td><?php echo $servicio['barbero_nombre'] ?? 'N/A'; ?></td>
                                <td><?php echo $servicio['tiempo_real'] ?? $servicio['tiempo_estimado']; ?> min</td>
                                <td>+<?php echo PUNTOS_POR_SERVICIO; ?></td>
                                <td><?php echo $servicio['calificacion'] ? '⭐ ' . $servicio['calificacion'] : 'Pendiente'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Puntos Section -->
            <section id="puntos-section" class="section-content">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-star"></i></div>
                        <div class="stat-value"><?php echo $puntos; ?></div>
                        <div class="stat-label">Puntos Totales</div>
                    </div>
                </div>

                <div class="table-container">
                    <div class="table-header"><h3>Recompensas Disponibles</h3></div>
                    <div class="rewards-grid">
                        <?php foreach($recompensas as $recompensa): ?>
                        <div class="reward-card">
                            <i class="fas fa-gift" style="font-size: 40px; color: var(--secondary);"></i>
                            <h3><?php echo $recompensa['nombre']; ?></h3>
                            <p><?php echo $recompensa['descripcion']; ?></p>
                            <div class="reward-points"><?php echo $recompensa['puntos_requeridos']; ?> puntos</div>
                            <button class="btn btn-primary" onclick="canjearRecompensa(<?php echo $recompensa['id']; ?>)">Canjear</button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <!-- Perfil Section -->
            <section id="perfil-section" class="section-content">
                <div class="table-container">
                    <div class="table-header"><h3>Mi Perfil</h3></div>
                    <div class="form-container">
                        <form method="POST" action="actualizar_perfil.php">
                            <?php echo csrf_field(); ?>
                            <div class="form-group">
                                <label><i class="fas fa-envelope"></i> Email</label>
                                <input type="email" value="<?php echo htmlspecialchars($_SESSION['user_email']); ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-user"></i> Nombre Completo</label>
                                <input type="text" name="nombre" value="<?php echo htmlspecialchars($profile['nombre'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-phone"></i> Teléfono</label>
                                <input type="tel" name="telefono" value="<?php echo htmlspecialchars($profile['telefono'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-map-marker-alt"></i> Dirección</label>
                                <textarea name="direccion" rows="2"><?php echo htmlspecialchars($profile['direccion'] ?? ''); ?></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                        </form>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <!-- Modal Detalles -->
    <div id="detalles-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Detalles del Servicio</h3>
                <button class="btn" onclick="cerrarModal('detalles-modal')">&times;</button>
            </div>
            <div class="modal-body" id="detalles-modal-body">
                Cargando...
            </div>
        </div>
    </div>

    <script>
        // Navegación
        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', function(e) {
                const section = this.getAttribute('data-section');
                if(section) {
                    e.preventDefault();
                    document.querySelectorAll('.nav-item').forEach(nav => nav.classList.remove('active'));
                    this.classList.add('active');
                    document.querySelectorAll('.section-content').forEach(content => content.classList.remove('active'));
                    document.getElementById(`${section}-section`).classList.add('active');
                    document.getElementById('section-title').innerHTML = this.querySelector('span').innerHTML;
                }
            });
        });
        
        // Ver detalles
        function verDetalles(servicioId) {
            const modal = document.getElementById('detalles-modal');
            const body = document.getElementById('detalles-modal-body');
            body.innerHTML = '<div style="text-align:center; padding:20px;">Cargando...</div>';
            modal.style.display = 'flex';
            
            fetch('ver_servicio.php?id=' + servicioId)
                .then(response => response.text())
                .then(html => {
                    body.innerHTML = html;
                })
                .catch(error => {
                    body.innerHTML = '<div style="text-align:center; padding:20px; color:red;">Error al cargar los detalles</div>';
                });
        }
        
        // Canjear recompensa
        function canjearRecompensa(recompensaId) {
            if(confirm('¿Deseas canjear esta recompensa?')) {
                window.location.href = 'canjear.php?id=' + recompensaId;
            }
        }
        
        // Cerrar modal
        function cerrarModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Cerrar modal con click fuera
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>
