<?php
// barbero.php
require_once 'config/config.php';
require_once 'classes/User.php';
require_once 'classes/Service.php';

// Verificar autenticación
if(!isset($_SESSION['user_id']) || $_SESSION['user_rol'] != 'barbero') {
    redirect('login.php');
}

$user = new User();
$user->id = $_SESSION['user_id'];
$profile = $user->getProfile();

$service = new Service();

// Obtener datos del barbero
$barbero_query = "SELECT id, verificacion_status, is_available, calificacion_promedio, total_servicios 
                  FROM barberos WHERE user_id = :user_id";
$stmt = $user->conn->prepare($barbero_query);
$stmt->bindParam(":user_id", $_SESSION['user_id']);
$stmt->execute();
$barbero = $stmt->fetch(PDO::FETCH_ASSOC);
$barbero_id = $barbero['id'] ?? null;
$verificacion_status = $barbero['verificacion_status'] ?? 'pendiente';

// Cambiar disponibilidad
if(isset($_POST['toggle_disponible'])) {
    try {
        verificarCSRFToken($_POST['csrf_token'] ?? null);
    } catch (Exception $e) {
        setFlash('danger', 'Sesion invalida. Intenta nuevamente.');
        redirect('barbero.php');
    }
    if($barbero_id) {
        $new_status = $_POST['is_available'] == 1 ? 0 : 1;
        $update = "UPDATE barberos SET is_available = :status WHERE id = :id";
        $stmt = $user->conn->prepare($update);
        $stmt->bindParam(":status", $new_status);
        $stmt->bindParam(":id", $barbero_id);
        $stmt->execute();
    }
    redirect('barbero.php');
}

// Aceptar servicio
if(isset($_POST['aceptar_servicio']) && $barbero_id) {
    try {
        verificarCSRFToken($_POST['csrf_token'] ?? null);
    } catch (Exception $e) {
        setFlash('danger', 'Sesion invalida. Intenta nuevamente.');
        redirect('barbero.php');
    }
    $resultado = $service->aceptarServicio(
        $_POST['servicio_id'],
        $barbero_id,
        $_POST['tiempo_estimado'],
        $_POST['notas_barbero'] ?? ''
    );
    setFlash($resultado ? 'success' : 'danger', 
            $resultado ? '✅ Servicio aceptado exitosamente' : '❌ Error al aceptar servicio');
    redirect('barbero.php');
}

// Finalizar servicio
if(isset($_POST['finalizar_servicio']) && $barbero_id) {
    try {
        verificarCSRFToken($_POST['csrf_token'] ?? null);
    } catch (Exception $e) {
        setFlash('danger', 'Sesion invalida. Intenta nuevamente.');
        redirect('barbero.php');
    }
    $resultado = $service->completarServicio(
        $_POST['servicio_id'],
        $_POST['duracion_real'],
        $_POST['notas_finalizacion'] ?? ''
    );
    setFlash($resultado ? 'success' : 'danger',
            $resultado ? '✅ Servicio completado exitosamente' : '❌ Error al completar servicio');
    redirect('barbero.php');
}

// Obtener servicios pendientes
$pendientes = [];
$pendientes_query = "SELECT s.*, c.id as cliente_id, u.nombre as cliente_nombre, u.telefono, u.direccion
    FROM servicios s
    JOIN clientes c ON s.cliente_id = c.id
    JOIN users u ON c.user_id = u.id
    WHERE s.estado = 'pendiente'
    ORDER BY s.fecha_solicitud ASC";
$pendientes = $user->conn->query($pendientes_query)->fetchAll(PDO::FETCH_ASSOC);

// Servicios activos del barbero
$activos = [];
if($barbero_id) {
    $activos_query = "SELECT s.*, c.id as cliente_id, u.nombre as cliente_nombre, u.telefono, u.direccion
        FROM servicios s
        JOIN clientes c ON s.cliente_id = c.id
        JOIN users u ON c.user_id = u.id
        WHERE s.barbero_id = :barbero_id AND s.estado IN ('aceptado', 'en_proceso')
        ORDER BY s.fecha_aceptacion DESC";
    $stmt = $user->conn->prepare($activos_query);
    $stmt->bindParam(":barbero_id", $barbero_id);
    $stmt->execute();
    $activos = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Estadísticas
$stats = ['total_servicios' => 0, 'completados' => 0, 'hoy' => 0];
if($barbero_id) {
    $stats_query = "SELECT 
        COUNT(*) as total_servicios,
        SUM(CASE WHEN estado = 'completado' THEN 1 ELSE 0 END) as completados,
        SUM(CASE WHEN DATE(fecha_fin) = CURDATE() THEN 1 ELSE 0 END) as hoy
        FROM servicios WHERE barbero_id = :barbero_id";
    $stmt = $user->conn->prepare($stats_query);
    $stmt->bindParam(":barbero_id", $barbero_id);
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Historial
$historial = [];
if($barbero_id) {
    $historial_query = "SELECT s.*, u.nombre as cliente_nombre
        FROM servicios s
        JOIN clientes c ON s.cliente_id = c.id
        JOIN users u ON c.user_id = u.id
        WHERE s.barbero_id = :barbero_id AND s.estado = 'completado'
        ORDER BY s.fecha_fin DESC LIMIT 20";
    $stmt = $user->conn->prepare($historial_query);
    $stmt->bindParam(":barbero_id", $barbero_id);
    $stmt->execute();
    $historial = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Barbero - Cuts & Styles</title>
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
        .user-info { display: flex; align-items: center; gap: 15px; }
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
        .btn-danger { background: var(--danger); color: white; }
        .btn-warning { background: var(--warning); color: white; }
        .btn-sm { padding: 8px 16px; font-size: 14px; }
        .stats-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px; margin-bottom: 30px;
        }
        .stat-card {
            background: white; padding: 25px; border-radius: 12px;
            text-align: center; box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
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
        .badge {
            padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;
        }
        .badge-pendiente { background: #FEF5E7; color: var(--warning); }
        .badge-aceptado { background: #E8F4FC; color: #3498DB; }
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
        .status-toggle {
            display: flex; align-items: center; gap: 15px;
            padding: 8px 15px; background: var(--light); border-radius: 8px;
        }
        .switch {
            position: relative; display: inline-block; width: 50px; height: 24px;
        }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider {
            position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0;
            background-color: #ccc; transition: .4s; border-radius: 24px;
        }
        .slider:before {
            position: absolute; content: ""; height: 16px; width: 16px;
            left: 4px; bottom: 4px; background-color: white; transition: .4s; border-radius: 50%;
        }
        input:checked + .slider { background-color: var(--success); }
        input:checked + .slider:before { transform: translateX(26px); }
        
        /* Modal styles */
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
            max-width: 500px;
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
        .modal-footer {
            padding: 20px;
            border-top: 1px solid #ddd;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--primary);
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
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
                    <div class="user-avatar"><i class="fas fa-user-tie"></i></div>
                    <div class="user-details">
                        <h3><?php echo htmlspecialchars($_SESSION['user_email']); ?></h3>
                        <span class="role-badge">Barbero</span>
                    </div>
                </div>
            </div>
            <nav class="nav-menu">
                <a class="nav-item active" data-section="dashboard"><i class="fas fa-home"></i> <span>Dashboard</span></a>
                <a class="nav-item" data-section="servicios-pendientes"><i class="fas fa-clock"></i> <span>Servicios Pendientes</span></a>
                <a class="nav-item" data-section="servicios-activos"><i class="fas fa-cut"></i> <span>Servicios Activos</span></a>
                <a class="nav-item" data-section="historial"><i class="fas fa-history"></i> <span>Historial</span></a>
                <a class="nav-item" data-section="perfil"><i class="fas fa-user-cog"></i> <span>Mi Perfil</span></a>
                <a href="logout.php" class="nav-item"><i class="fas fa-sign-out-alt"></i> <span>Cerrar Sesión</span></a>
            </nav>
        </aside>

        <main class="main-content">
            <header class="header">
                <h2 id="section-title">Dashboard Barbero</h2>
                <div style="display: flex; gap: 10px;">
                    <form method="POST" style="display: inline;">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="is_available" value="<?php echo $barbero['is_available'] ?? 0; ?>">
                        <div class="status-toggle">
                            <span class="status-label"><?php echo ($barbero['is_available'] ?? 0) ? 'Disponible' : 'No disponible'; ?></span>
                            <label class="switch">
                                <input type="checkbox" id="disponible-toggle" onchange="this.form.submit()" <?php echo ($barbero['is_available'] ?? 0) ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                        <button type="submit" name="toggle_disponible" style="display: none;"></button>
                    </form>
                    <button class="btn btn-primary" onclick="location.reload()">
                        <i class="fas fa-sync-alt"></i> Actualizar
                    </button>
                </div>
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
                        <div class="stat-value"><?php echo count($pendientes); ?></div>
                        <div class="stat-label">Pendientes</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-cut"></i></div>
                        <div class="stat-value"><?php echo count($activos); ?></div>
                        <div class="stat-label">En Proceso</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                        <div class="stat-value"><?php echo $stats['hoy'] ?? 0; ?></div>
                        <div class="stat-label">Completados Hoy</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-star"></i></div>
                        <div class="stat-value"><?php echo $barbero['calificacion_promedio'] ?? '0.0'; ?></div>
                        <div class="stat-label">Calificación</div>
                    </div>
                </div>

                <div class="table-container">
                    <div class="table-header">
                        <h3>Servicios Pendientes</h3>
                    </div>
                    <table class="table">
                        <thead><tr><th>Cliente</th><th>Tipo</th><th>Solicitado</th><th>Acciones</th></tr></thead>
                        <tbody>
                            <?php foreach($pendientes as $servicio): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($servicio['cliente_nombre']); ?></td>
                                <td><?php echo $servicio['tipo']; ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($servicio['fecha_solicitud'])); ?></td>
                                <td>
                                    <button class="btn btn-success btn-sm" onclick="mostrarAceptar(<?php echo $servicio['id']; ?>)">
                                        <i class="fas fa-check"></i> Aceptar
                                    </button>
                                    <button class="btn btn-primary btn-sm" onclick="verDetalles(<?php echo $servicio['id']; ?>)">
                                        <i class="fas fa-eye"></i> Ver
                                    </button>
                                 </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($pendientes)): ?>
                            <tr><td colspan="4" style="text-align: center;">No hay servicios pendientes</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Servicios Pendientes Section -->
            <section id="servicios-pendientes-section" class="section-content">
                <div class="table-container">
                    <div class="table-header"><h3>Servicios Pendientes de Aceptar</h3></div>
                    <table class="table">
                        <thead><tr><th>Cliente</th><th>Tipo</th><th>Notas</th><th>Solicitado</th><th>Acciones</th></tr></thead>
                        <tbody>
                            <?php foreach($pendientes as $servicio): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($servicio['cliente_nombre']); ?></td>
                                <td><?php echo $servicio['tipo']; ?></td>
                                <td><?php echo substr($servicio['notas'] ?? '', 0, 50); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($servicio['fecha_solicitud'])); ?></td>
                                <td>
                                    <button class="btn btn-success btn-sm" onclick="mostrarAceptar(<?php echo $servicio['id']; ?>)">
                                        <i class="fas fa-check"></i> Aceptar
                                    </button>
                                    <button class="btn btn-primary btn-sm" onclick="verDetalles(<?php echo $servicio['id']; ?>)">
                                        <i class="fas fa-eye"></i> Ver
                                    </button>
                                 </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Servicios Activos Section -->
            <section id="servicios-activos-section" class="section-content">
                <div class="table-container">
                    <div class="table-header"><h3>Mis Servicios en Proceso</h3></div>
                    <table class="table">
                        <thead><tr><th>Cliente</th><th>Tipo</th><th>Estado</th><th>Aceptado</th><th>Acciones</th></tr></thead>
                        <tbody>
                            <?php foreach($activos as $servicio): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($servicio['cliente_nombre']); ?></td>
                                <td><?php echo $servicio['tipo']; ?></td>
                                <td><span class="badge badge-<?php echo $servicio['estado']; ?>"><?php echo ucfirst($servicio['estado']); ?></span></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($servicio['fecha_aceptacion'])); ?></td>
                                <td>
                                    <button class="btn btn-warning btn-sm" onclick="mostrarFinalizar(<?php echo $servicio['id']; ?>)">
                                        <i class="fas fa-check-double"></i> Finalizar
                                    </button>
                                    <button class="btn btn-primary btn-sm" onclick="verDetalles(<?php echo $servicio['id']; ?>)">
                                        <i class="fas fa-eye"></i> Ver
                                    </button>
                                 </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Historial Section -->
            <section id="historial-section" class="section-content">
                <div class="table-container">
                    <div class="table-header"><h3>Historial de Servicios</h3></div>
                    <table class="table">
                        <thead><tr><th>Fecha</th><th>Cliente</th><th>Tipo</th><th>Duración</th><th>Calificación</th></tr></thead>
                        <tbody>
                            <?php foreach($historial as $servicio): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($servicio['fecha_fin'])); ?></td>
                                <td><?php echo htmlspecialchars($servicio['cliente_nombre']); ?></td>
                                <td><?php echo $servicio['tipo']; ?></td>
                                <td><?php echo $servicio['tiempo_real'] ?? $servicio['tiempo_estimado']; ?> min</td>
                                <td><?php echo $servicio['calificacion'] ? '⭐ ' . $servicio['calificacion'] : 'Sin calificar'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Perfil Section -->
            <section id="perfil-section" class="section-content">
                <div class="table-container">
                    <div class="table-header"><h3>Mi Perfil Profesional</h3></div>
                    <div style="padding: 20px;">
                        <form method="POST" action="actualizar_perfil_barbero.php">
                            <?php echo csrf_field(); ?>
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" value="<?php echo htmlspecialchars($_SESSION['user_email']); ?>" readonly style="width:100%; padding:10px; border:1px solid #ddd; border-radius:5px;">
                            </div>
                            <div class="form-group">
                                <label>Nombre Completo</label>
                                <input type="text" name="nombre" value="<?php echo htmlspecialchars($profile['nombre'] ?? ''); ?>" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:5px;">
                            </div>
                            <div class="form-group">
                                <label>Teléfono</label>
                                <input type="tel" name="telefono" value="<?php echo htmlspecialchars($profile['telefono'] ?? ''); ?>" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:5px;">
                            </div>
                            <div class="form-group">
                                <label>Especialidad</label>
                                <select name="especialidad" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:5px;">
                                    <option value="">Seleccionar especialidad</option>
                                    <option value="Corte Clásico">Corte Clásico</option>
                                    <option value="Corte Moderno">Corte Moderno</option>
                                    <option value="Barba">Barba</option>
                                    <option value="Navaja">Afeitado con Navaja</option>
                                    <option value="Tinte">Tinte</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Años de Experiencia</label>
                                <input type="number" name="experiencia" min="0" max="50" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:5px;">
                            </div>
                            <div class="form-group">
                                <label>Descripción Profesional</label>
                                <textarea name="descripcion" rows="3" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:5px;" placeholder="Cuéntanos sobre tu experiencia y estilo"></textarea>
                            </div>
                            <div class="form-group">
                                <label>Tarifa por Hora (USD)</label>
                                <input type="number" name="tarifa_hora" step="5" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:5px;" placeholder="Ej: 25">
                            </div>
                            <button type="submit" class="btn btn-primary">Guardar Perfil</button>
                        </form>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <!-- Modal Aceptar Servicio -->
    <div id="aceptar-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Aceptar Servicio</h3>
                <button class="btn" onclick="cerrarModal('aceptar-modal')">&times;</button>
            </div>
            <form method="POST">
                <?php echo csrf_field(); ?>
                <div class="modal-body">
                    <input type="hidden" name="servicio_id" id="aceptar-servicio-id">
                    <div class="form-group">
                        <label><i class="fas fa-clock"></i> Tiempo Estimado (minutos)</label>
                        <input type="number" name="tiempo_estimado" min="15" max="180" value="45" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-sticky-note"></i> Notas para el Cliente</label>
                        <textarea name="notas_barbero" rows="3" placeholder="Ej: Por favor traer foto de referencia, llegar 5 minutos antes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" onclick="cerrarModal('aceptar-modal')">Cancelar</button>
                    <button type="submit" name="aceptar_servicio" class="btn btn-success">Aceptar Servicio</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Finalizar Servicio -->
    <div id="finalizar-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Finalizar Servicio</h3>
                <button class="btn" onclick="cerrarModal('finalizar-modal')">&times;</button>
            </div>
            <form method="POST">
                <?php echo csrf_field(); ?>
                <div class="modal-body">
                    <input type="hidden" name="servicio_id" id="finalizar-servicio-id">
                    <div class="form-group">
                        <label><i class="fas fa-hourglass-half"></i> Duración Real (minutos)</label>
                        <input type="number" name="duracion_real" min="1" max="300" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-sticky-note"></i> Notas de Finalización</label>
                        <textarea name="notas_finalizacion" rows="3" placeholder="Detalles del servicio realizado, observaciones..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" onclick="cerrarModal('finalizar-modal')">Cancelar</button>
                    <button type="submit" name="finalizar_servicio" class="btn btn-success">Finalizar Servicio</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Ver Detalles -->
    <div id="detalles-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Detalles del Servicio</h3>
                <button class="btn" onclick="cerrarModal('detalles-modal')">&times;</button>
            </div>
            <div class="modal-body" id="detalles-modal-body">
                <!-- Contenido cargado dinámicamente -->
            </div>
        </div>
    </div>

    <script>
        // Navegación entre secciones
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
        
        // Toggle disponibilidad
        document.getElementById('disponible-toggle')?.addEventListener('change', function() {
            this.closest('form').submit();
        });
        
        // Mostrar modal de aceptar
        function mostrarAceptar(servicioId) {
            document.getElementById('aceptar-servicio-id').value = servicioId;
            document.getElementById('aceptar-modal').style.display = 'flex';
        }
        
        // Mostrar modal de finalizar
        function mostrarFinalizar(servicioId) {
            document.getElementById('finalizar-servicio-id').value = servicioId;
            document.getElementById('finalizar-modal').style.display = 'flex';
        }
        
        // Ver detalles del servicio
        function verDetalles(servicioId) {
            fetch('ver_servicio.php?id=' + servicioId)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('detalles-modal-body').innerHTML = html;
                    document.getElementById('detalles-modal').style.display = 'flex';
                })
                .catch(error => {
                    document.getElementById('detalles-modal-body').innerHTML = '<p>Error al cargar los detalles</p>';
                    document.getElementById('detalles-modal').style.display = 'flex';
                });
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
