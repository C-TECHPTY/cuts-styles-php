<?php
// admin/dashboard.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Service.php';
require_once __DIR__ . '/../classes/Verification.php';
require_once __DIR__ . '/../classes/Product.php';

// Verificar autenticación y rol de admin
if(!isset($_SESSION['user_id']) || $_SESSION['user_rol'] != 'admin') {
    header("Location: " . BASE_URL . "login.php");
    exit();
}

$user = new User();
$service = new Service();
$verification = new Verification();
$productClass = new Product();

// ============================================
// PROCESAR ACCIONES POST
// ============================================
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        verificarCSRFToken($_POST['csrf_token'] ?? null);
    } catch (Exception $e) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Sesion invalida. Intenta nuevamente.'];
        header("Location: " . BASE_URL . "admin/dashboard.php");
        exit();
    }
    
    // Guardar producto (con múltiples imágenes)
    if(isset($_POST['crear_usuario_admin'])) {
        $rol = $_POST['rol'] ?? 'cliente';
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $email = sanitizarEmail($_POST['email'] ?? '');
        $telefono = trim((string) ($_POST['telefono'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $direccion = trim((string) ($_POST['direccion'] ?? ''));
        $especialidad = trim((string) ($_POST['especialidad'] ?? ''));
        $experiencia = (int) ($_POST['experiencia'] ?? 0);
        $descripcion = trim((string) ($_POST['descripcion'] ?? ''));
        $tarifa_hora = $_POST['tarifa_hora'] !== '' ? (float) $_POST['tarifa_hora'] : null;
        $verificacion_status = $_POST['verificacion_status'] ?? 'pendiente';
        $is_available = isset($_POST['is_available']) ? 1 : 0;

        if (!in_array($rol, ['cliente', 'barbero'], true) || $nombre === '' || !validarEmail($email) || strlen($password) < 6) {
            $_SESSION['flash'] = [
                'type' => 'danger',
                'message' => 'Datos invalidos. Verifica nombre, email, rol y una contrasena de al menos 6 caracteres.'
            ];
            header("Location: " . BASE_URL . "admin/dashboard.php#" . ($rol === 'barbero' ? 'barberos' : 'clientes'));
            exit();
        }

        $nuevoUsuario = new User();
        $nuevoUsuario->nombre = $nombre;
        $nuevoUsuario->email = $email;
        $nuevoUsuario->telefono = $telefono;
        $nuevoUsuario->password = $password;
        $nuevoUsuario->rol = $rol;

        if ($nuevoUsuario->register()) {
            $updateUser = $nuevoUsuario->conn->prepare("UPDATE users SET direccion = :direccion WHERE id = :id");
            $updateUser->bindParam(':direccion', $direccion);
            $updateUser->bindParam(':id', $nuevoUsuario->id);
            $updateUser->execute();

            if ($rol === 'barbero') {
                $validStatuses = ['pendiente', 'en_revision', 'verificado', 'rechazado'];
                if (!in_array($verificacion_status, $validStatuses, true)) {
                    $verificacion_status = 'pendiente';
                }

                $updateBarber = $nuevoUsuario->conn->prepare(
                    "UPDATE barberos
                     SET especialidad = :especialidad,
                         experiencia = :experiencia,
                         descripcion = :descripcion,
                         tarifa_hora = :tarifa_hora,
                         verificacion_status = :verificacion_status,
                         is_available = :is_available
                     WHERE user_id = :user_id"
                );
                $updateBarber->bindParam(':especialidad', $especialidad);
                $updateBarber->bindParam(':experiencia', $experiencia);
                $updateBarber->bindParam(':descripcion', $descripcion);
                $updateBarber->bindParam(':tarifa_hora', $tarifa_hora);
                $updateBarber->bindParam(':verificacion_status', $verificacion_status);
                $updateBarber->bindParam(':is_available', $is_available);
                $updateBarber->bindParam(':user_id', $nuevoUsuario->id);
                $updateBarber->execute();
            }

            $_SESSION['flash'] = [
                'type' => 'success',
                'message' => ucfirst($rol) . ' creado correctamente.'
            ];
        } else {
            $_SESSION['flash'] = [
                'type' => 'danger',
                'message' => $nuevoUsuario->lastError ?: 'No se pudo crear el usuario.'
            ];
        }

        header("Location: " . BASE_URL . "admin/dashboard.php#" . ($rol === 'barbero' ? 'barberos' : 'clientes'));
        exit();
    }

    if(isset($_POST['guardar_producto'])) {
        $data = [
            'id' => $_POST['producto_id'] ?? 0,
            'nombre' => $_POST['nombre'],
            'descripcion' => $_POST['descripcion'],
            'precio' => $_POST['precio'],
            'descuento' => $_POST['descuento'] ?? 0,
            'en_oferta' => isset($_POST['en_oferta']) ? 1 : 0,
            'stock' => $_POST['stock'],
            'categoria' => $_POST['categoria'],
            'destacado' => isset($_POST['destacado']) ? 1 : 0,
            'estado' => $_POST['estado']
        ];
        
        $archivos = null;
        if(isset($_FILES['imagenes']) && !empty($_FILES['imagenes']['name'][0])) {
            $archivos = $_FILES;
        }
        
        $resultado = $productClass->guardar($data, $archivos);
        
        $_SESSION['flash'] = [
            'type' => $resultado['success'] ? 'success' : 'danger',
            'message' => $resultado['success'] ? 'Producto guardado correctamente' : 'Error al guardar producto'
        ];
        header("Location: " . BASE_URL . "admin/dashboard.php#productos");
        exit();
    }
    
    // Eliminar producto
    if(isset($_POST['eliminar_producto'])) {
        $id = $_POST['producto_id'];
        $resultado = $productClass->eliminar($id);
        
        $_SESSION['flash'] = [
            'type' => $resultado ? 'success' : 'danger',
            'message' => $resultado ? 'Producto eliminado correctamente' : 'Error al eliminar producto'
        ];
        header("Location: " . BASE_URL . "admin/dashboard.php#productos");
        exit();
    }
    
    // Eliminar imagen de producto
    if(isset($_POST['eliminar_imagen'])) {
        $resultado = $productClass->eliminarImagen($_POST['imagen_id']);
        
        $_SESSION['flash'] = [
            'type' => $resultado ? 'success' : 'danger',
            'message' => $resultado ? 'Imagen eliminada correctamente' : 'Error al eliminar imagen'
        ];
        header("Location: " . BASE_URL . "admin/dashboard.php#productos");
        exit();
    }
    
    // Verificar barbero
    if(isset($_POST['verificar_barbero'])) {
        $barbero_id = $_POST['barbero_id'];
        $aprobado = $_POST['aprobado'] == 'si';
        $comentario = $_POST['comentario'] ?? '';
        
        $resultado = $verification->verificarBarbero($barbero_id, $aprobado, $comentario);
        $_SESSION['flash'] = [
            'type' => $resultado['success'] ? 'success' : 'danger',
            'message' => $resultado['success'] ? 'Barbero verificado exitosamente' : 'Error al verificar barbero'
        ];
        header("Location: " . BASE_URL . "admin/dashboard.php#verificaciones");
        exit();
    }
    
    // Cambiar estado de barbero (activar/desactivar)
    if(isset($_POST['toggle_barbero'])) {
        $barbero_id = $_POST['barbero_id'];
        $activo = $_POST['activo'];
        
        $query = "UPDATE barberos SET is_available = :activo WHERE id = :id";
        $stmt = $user->conn->prepare($query);
        $stmt->bindParam(":activo", $activo);
        $stmt->bindParam(":id", $barbero_id);
        $stmt->execute();
        
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Estado del barbero actualizado'];
        header("Location: " . BASE_URL . "admin/dashboard.php#barberos");
        exit();
    }
    
    // Guardar configuración
    if(isset($_POST['guardar_config'])) {
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Configuración guardada'];
        header("Location: " . BASE_URL . "admin/dashboard.php#configuracion");
        exit();
    }
}

// ============================================
// OBTENER DATOS PARA EL DASHBOARD
// ============================================

// Totales generales
$stats_query = "SELECT 
    (SELECT COUNT(*) FROM users WHERE rol = 'cliente' AND is_active = 1) as total_clientes,
    (SELECT COUNT(*) FROM users WHERE rol = 'barbero' AND is_active = 1) as total_barberos,
    (SELECT COUNT(*) FROM barberos WHERE verificacion_status = 'pendiente') as verificaciones_pendientes,
    (SELECT COUNT(*) FROM barberos WHERE verificacion_status = 'verificado') as barberos_verificados,
    (SELECT COUNT(*) FROM servicios WHERE estado = 'completado') as total_servicios,
    (SELECT COUNT(*) FROM servicios WHERE DATE(fecha_solicitud) = CURDATE()) as servicios_hoy,
    (SELECT COUNT(*) FROM servicios WHERE estado = 'pendiente') as servicios_pendientes,
    (SELECT COALESCE(SUM(precio_total), 0) FROM servicios WHERE estado = 'completado' AND MONTH(fecha_fin) = MONTH(CURDATE())) as ingresos_mes";
$stats_result = $user->conn->query($stats_query);
$stats = $stats_result ? $stats_result->fetch(PDO::FETCH_ASSOC) : [
    'total_clientes' => 0, 'total_barberos' => 0, 'verificaciones_pendientes' => 0,
    'barberos_verificados' => 0, 'total_servicios' => 0, 'servicios_hoy' => 0,
    'servicios_pendientes' => 0, 'ingresos_mes' => 0
];

// Ingresos por mes (para gráfico)
$ingresos_meses = [];
for($i = 5; $i >= 0; $i--) {
    $mes = date('Y-m', strtotime("-$i months"));
    $query = "SELECT COALESCE(SUM(precio_total), 0) as total 
              FROM servicios 
              WHERE estado = 'completado' 
              AND DATE_FORMAT(fecha_fin, '%Y-%m') = :mes";
    $stmt = $user->conn->prepare($query);
    $stmt->bindParam(":mes", $mes);
    $stmt->execute();
    $total = $stmt->fetch(PDO::FETCH_ASSOC);
    $ingresos_meses[] = [
        'mes' => date('M', strtotime($mes)),
        'total' => $total['total']
    ];
}

// Servicios por tipo
$servicios_tipo = [];
$tipos = ['Corte', 'Barba', 'Corte+Barba', 'Tinte', 'Navaja'];
foreach($tipos as $tipo) {
    $query = "SELECT COUNT(*) as total FROM servicios WHERE tipo = :tipo AND estado = 'completado'";
    $stmt = $user->conn->prepare($query);
    $stmt->bindParam(":tipo", $tipo);
    $stmt->execute();
    $total = $stmt->fetch(PDO::FETCH_ASSOC);
    $servicios_tipo[] = ['tipo' => $tipo, 'total' => $total['total']];
}

// Top barberos
$top_barberos = [];
$query = "SELECT b.*, u.nombre, u.email, 
          (SELECT COUNT(*) FROM servicios WHERE barbero_id = b.id AND estado = 'completado') as total_servicios,
          (SELECT AVG(calificacion) FROM servicios WHERE barbero_id = b.id AND calificacion IS NOT NULL) as promedio
          FROM barberos b
          JOIN users u ON b.user_id = u.id
          WHERE b.verificacion_status = 'verificado'
          ORDER BY total_servicios DESC
          LIMIT 5";
$top_result = $user->conn->query($query);
$top_barberos = $top_result ? $top_result->fetchAll(PDO::FETCH_ASSOC) : [];

// Actividad reciente
$actividad_reciente = [];
$query = "SELECT s.*, 
          c.id as cliente_id, cu.nombre as cliente_nombre,
          b.id as barbero_id, bu.nombre as barbero_nombre
          FROM servicios s
          LEFT JOIN clientes c ON s.cliente_id = c.id
          LEFT JOIN users cu ON c.user_id = cu.id
          LEFT JOIN barberos b ON s.barbero_id = b.id
          LEFT JOIN users bu ON b.user_id = bu.id
          ORDER BY s.fecha_solicitud DESC
          LIMIT 10";
$actividad_result = $user->conn->query($query);
$actividad_reciente = $actividad_result ? $actividad_result->fetchAll(PDO::FETCH_ASSOC) : [];

// Barberos pendientes de verificación
$pendientes_verificacion = [];
$query = "SELECT b.*, u.nombre, u.email, u.telefono,
          (SELECT COUNT(*) FROM documentos_verificacion WHERE barbero_id = b.id) as documentos_subidos
          FROM barberos b
          JOIN users u ON b.user_id = u.id
          WHERE b.verificacion_status = 'pendiente'
          ORDER BY b.created_at ASC";
$pendientes_result = $user->conn->query($query);
$pendientes_verificacion = $pendientes_result ? $pendientes_result->fetchAll(PDO::FETCH_ASSOC) : [];

// Todos los barberos
$todos_barberos = [];
$query = "SELECT b.*, u.nombre, u.email, u.telefono,
          (SELECT COUNT(*) FROM servicios WHERE barbero_id = b.id AND estado = 'completado') as total_servicios,
          (SELECT AVG(calificacion) FROM servicios WHERE barbero_id = b.id AND calificacion IS NOT NULL) as promedio
          FROM barberos b
          JOIN users u ON b.user_id = u.id
          ORDER BY b.created_at DESC";
$barberos_result = $user->conn->query($query);
$todos_barberos = $barberos_result ? $barberos_result->fetchAll(PDO::FETCH_ASSOC) : [];

// Todos los clientes
$todos_clientes = [];
$query = "SELECT u.*, c.puntos,
          (SELECT COUNT(*) FROM servicios WHERE cliente_id = c.id) as total_servicios
          FROM users u
          JOIN clientes c ON u.id = c.user_id
          WHERE u.rol = 'cliente'
          ORDER BY u.created_at DESC";
$clientes_result = $user->conn->query($query);
$todos_clientes = $clientes_result ? $clientes_result->fetchAll(PDO::FETCH_ASSOC) : [];

// Productos
$productos = $productClass->getAll('todos', 100);

// Flash message
$flash = isset($_SESSION['flash']) ? $_SESSION['flash'] : null;
unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Cuts & Styles</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <?php include BASE_PATH . 'includes/pwa_head.php'; ?>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --primary: #1a1a2e;
            --secondary: #e94560;
            --success: #27ae60;
            --warning: #f39c12;
            --danger: #e74c3c;
            --info: #3498db;
            --light: #ecf0f1;
            --gray: #95a5a6;
            --white: #ffffff;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            overflow-x: hidden;
        }
        .admin-container { display: flex; min-height: 100vh; }
        
        /* Sidebar */
        .sidebar {
            width: 280px;
            background: var(--primary);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            transition: all 0.3s;
            z-index: 100;
        }
        .sidebar-header { padding: 25px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-header h2 { font-size: 24px; display: flex; align-items: center; gap: 10px; }
        .sidebar-header h2 i { color: var(--secondary); }
        .admin-info {
            padding: 20px 25px;
            background: rgba(255,255,255,0.05);
            margin: 15px;
            border-radius: 12px;
        }
        .admin-avatar {
            width: 50px; height: 50px;
            background: var(--secondary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            margin-bottom: 10px;
        }
        .admin-name { font-weight: 600; margin-bottom: 5px; }
        .admin-email { font-size: 12px; opacity: 0.7; }
        
        /* Navigation */
        .nav-menu { padding: 10px 0; }
        .nav-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 25px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s;
            border-left: 3px solid transparent;
            cursor: pointer;
        }
        .nav-item:hover { background: rgba(255,255,255,0.1); color: white; }
        .nav-item.active {
            background: rgba(233,69,96,0.1);
            color: white;
            border-left-color: var(--secondary);
        }
        .nav-item i { width: 20px; }
        .nav-badge {
            margin-left: auto;
            background: var(--secondary);
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 11px;
        }
        
        /* Main Content */
        .main-content { flex: 1; margin-left: 280px; padding: 25px; }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid rgba(0,0,0,0.05);
        }
        .header h1 { font-size: 28px; color: var(--primary); }
        .header h1 i { color: var(--secondary); margin-right: 10px; }
        
        /* Buttons */
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            font-size: 14px;
        }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--secondary); transform: translateY(-2px); }
        .btn-success { background: var(--success); color: white; }
        .btn-success:hover { background: #219653; transform: translateY(-2px); }
        .btn-danger { background: var(--danger); color: white; }
        .btn-warning { background: var(--warning); color: white; }
        .btn-sm { padding: 6px 12px; font-size: 12px; }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--secondary);
        }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: rgba(233,69,96,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
        }
        .stat-icon i { font-size: 24px; color: var(--secondary); }
        .stat-value { font-size: 32px; font-weight: 700; color: var(--primary); margin-bottom: 5px; }
        .stat-label { color: var(--gray); font-size: 14px; }
        
        /* Dashboard Layout */
        .dashboard-layout {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
            margin-bottom: 25px;
        }
        
        /* Cards */
        .card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .card-header {
            padding: 20px 25px;
            background: white;
            border-bottom: 1px solid var(--light);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .card-header h3 { color: var(--primary); font-size: 18px; }
        .card-header h3 i { color: var(--secondary); margin-right: 8px; }
        .card-body { padding: 20px 25px; }
        .chart-container { height: 300px; position: relative; }
        
        /* Tables */
        .table-container {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 25px;
        }
        .table-header {
            padding: 20px 25px;
            background: white;
            border-bottom: 1px solid var(--light);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .table-header h3 { color: var(--primary); }
        .search-box input {
            padding: 8px 15px;
            border: 1px solid var(--light);
            border-radius: 8px;
            width: 250px;
        }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 15px 20px; text-align: left; border-bottom: 1px solid var(--light); }
        th { background: #f8f9fa; font-weight: 600; color: var(--primary); }
        tr:hover { background: #f8f9fa; }
        
        /* Badges */
        .badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        .badge-success { background: #d5f4e6; color: var(--success); }
        .badge-warning { background: #fef5e7; color: var(--warning); }
        .badge-danger { background: #fdedec; color: var(--danger); }
        .badge-info { background: #e8f4fc; color: var(--info); }
        
        /* Activity List */
        .activity-list { max-height: 400px; overflow-y: auto; }
        .activity-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            border-bottom: 1px solid var(--light);
        }
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(233,69,96,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .activity-icon i { color: var(--secondary); }
        .activity-content { flex: 1; }
        .activity-title { font-weight: 600; margin-bottom: 5px; }
        .activity-time { font-size: 12px; color: var(--gray); }
        
        /* Sections */
        .section { display: none; animation: fadeIn 0.3s ease; }
        .section.active { display: block; }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Modals */
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
            border-radius: 16px;
            max-width: 700px;
            width: 90%;
            max-height: 85vh;
            overflow-y: auto;
        }
        .modal-header {
            padding: 20px;
            border-bottom: 1px solid var(--light);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-body { padding: 20px; }
        .modal-footer {
            padding: 20px;
            border-top: 1px solid var(--light);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        /* Forms */
        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--primary);
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid var(--light);
            border-radius: 8px;
            font-size: 14px;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        /* Image preview */
        .imagenes-preview {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 10px;
        }
        .preview-img {
            position: relative;
            width: 80px;
            height: 80px;
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
        }
        .preview-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .preview-img .badge-principal {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0,0,0,0.7);
            color: white;
            font-size: 10px;
            text-align: center;
        }
        .preview-img .btn-eliminar {
            position: absolute;
            top: 0;
            right: 0;
            background: red;
            color: white;
            border: none;
            border-radius: 0 0 0 5px;
            cursor: pointer;
            width: 20px;
            height: 20px;
            font-size: 12px;
        }
        
        /* Alert */
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-success { background: #d5f4e6; color: var(--success); border: 1px solid #a3e4d7; }
        .alert-danger { background: #fdedec; color: var(--danger); border: 1px solid #fadbd8; }
        
        /* Config Section */
        .config-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--light);
        }
        .config-section h4 { margin-bottom: 15px; color: var(--primary); }
        .config-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        
        /* Product images gallery */
        .product-images-gallery {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .product-thumb {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #ddd;
        }
        .product-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        @media (max-width: 1024px) {
            .dashboard-layout { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .sidebar { width: 70px; }
            .sidebar-header h2 span, .admin-info, .nav-item span { display: none; }
            .nav-item { justify-content: center; padding: 15px; }
            .main-content { margin-left: 70px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .config-row, .form-row { grid-template-columns: 1fr; }
            .table-container { overflow-x: auto; }
            table { min-width: 600px; }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-cut"></i> <span>Cuts & Styles</span></h2>
            </div>
            <div class="admin-info">
                <div class="admin-avatar"><i class="fas fa-user-shield"></i></div>
                <div class="admin-name"><?php echo htmlspecialchars($_SESSION['user_nombre'] ?? 'Administrador'); ?></div>
                <div class="admin-email"><?php echo htmlspecialchars($_SESSION['user_email']); ?></div>
            </div>
            <nav class="nav-menu">
                <a class="nav-item active" data-section="dashboard"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a>
                <a class="nav-item" data-section="verificaciones"><i class="fas fa-id-card"></i><span>Verificaciones</span>
                    <?php if(count($pendientes_verificacion) > 0): ?>
                    <span class="nav-badge"><?php echo count($pendientes_verificacion); ?></span>
                    <?php endif; ?>
                </a>
                <a class="nav-item" data-section="barberos"><i class="fas fa-user-tie"></i><span>Barberos</span></a>
                <a class="nav-item" data-section="clientes"><i class="fas fa-users"></i><span>Clientes</span></a>
                <a class="nav-item" data-section="productos"><i class="fas fa-box"></i><span>Productos</span></a>
                <a class="nav-item" data-section="configuracion"><i class="fas fa-cog"></i><span>Configuración</span></a>
                <a href="../logout.php" class="nav-item"><i class="fas fa-sign-out-alt"></i><span>Cerrar Sesión</span></a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="header">
                <h1 id="section-title"><i class="fas fa-tachometer-alt"></i> Dashboard</h1>
                <button class="btn btn-primary" onclick="location.reload()"><i class="fas fa-sync-alt"></i> Actualizar</button>
            </div>

            <?php if($flash): ?>
            <div class="alert alert-<?php echo $flash['type']; ?>">
                <i class="fas fa-<?php echo $flash['type'] == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo $flash['message']; ?>
            </div>
            <?php endif; ?>

            <!-- ============================================ -->
            <!-- DASHBOARD SECTION -->
            <!-- ============================================ -->
            <section id="dashboard-section" class="section active">
                <div class="stats-grid">
                    <div class="stat-card"><div class="stat-icon"><i class="fas fa-users"></i></div><div class="stat-value"><?php echo number_format($stats['total_clientes'] ?? 0); ?></div><div class="stat-label">Clientes Registrados</div></div>
                    <div class="stat-card"><div class="stat-icon"><i class="fas fa-user-tie"></i></div><div class="stat-value"><?php echo number_format($stats['total_barberos'] ?? 0); ?></div><div class="stat-label">Barberos</div></div>
                    <div class="stat-card"><div class="stat-icon"><i class="fas fa-check-circle"></i></div><div class="stat-value"><?php echo number_format($stats['barberos_verificados'] ?? 0); ?></div><div class="stat-label">Barberos Verificados</div></div>
                    <div class="stat-card"><div class="stat-icon"><i class="fas fa-cut"></i></div><div class="stat-value"><?php echo number_format($stats['total_servicios'] ?? 0); ?></div><div class="stat-label">Servicios Totales</div></div>
                    <div class="stat-card"><div class="stat-icon"><i class="fas fa-clock"></i></div><div class="stat-value"><?php echo $stats['servicios_pendientes'] ?? 0; ?></div><div class="stat-label">Pendientes</div></div>
                    <div class="stat-card"><div class="stat-icon"><i class="fas fa-dollar-sign"></i></div><div class="stat-value">$<?php echo number_format($stats['ingresos_mes'] ?? 0, 2); ?></div><div class="stat-label">Ingresos del Mes</div></div>
                </div>

                <div class="dashboard-layout">
                    <div class="card">
                        <div class="card-header"><h3><i class="fas fa-chart-line"></i> Ingresos Mensuales</h3></div>
                        <div class="card-body"><canvas id="ingresosChart" style="height: 250px;"></canvas></div>
                    </div>
                    <div class="card">
                        <div class="card-header"><h3><i class="fas fa-chart-pie"></i> Servicios por Tipo</h3></div>
                        <div class="card-body"><canvas id="serviciosChart" style="height: 250px;"></canvas></div>
                    </div>
                </div>

                <div class="card" style="margin-bottom: 25px;">
                    <div class="card-header"><h3><i class="fas fa-crown"></i> Top Barberos</h3><a href="#" class="btn btn-primary btn-sm" onclick="cambiarSeccion('barberos')">Ver todos</a></div>
                    <div class="card-body" style="padding: 0;">
                        <?php if(empty($top_barberos)): ?>
                            <div style="padding: 40px; text-align: center;">No hay barberos verificados aún</div>
                        <?php else: ?>
                        <table style="width: 100%;">
                            <thead><tr><th>Barbero</th><th>Servicios</th><th>Calificación</th><th>Estado</th></tr></thead>
                            <tbody>
                                <?php foreach($top_barberos as $barbero): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($barbero['nombre']); ?></td>
                                    <td><?php echo $barbero['total_servicios']; ?></td>
                                    <td>⭐ <?php echo number_format($barbero['promedio'] ?? 0, 1); ?></td>
                                    <td><span class="badge <?php echo $barbero['is_available'] ? 'badge-success' : 'badge-danger'; ?>"><?php echo $barbero['is_available'] ? 'Activo' : 'Inactivo'; ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h3><i class="fas fa-history"></i> Actividad Reciente</h3></div>
                    <div class="card-body" style="padding: 0;">
                        <?php if(empty($actividad_reciente)): ?>
                            <div style="padding: 40px; text-align: center;">No hay actividad reciente</div>
                        <?php else: ?>
                        <div class="activity-list">
                            <?php foreach($actividad_reciente as $actividad): ?>
                            <div class="activity-item">
                                <div class="activity-icon"><i class="fas fa-cut"></i></div>
                                <div class="activity-content">
                                    <div class="activity-title">Servicio #<?php echo $actividad['id']; ?> - <?php echo $actividad['tipo']; ?></div>
                                    <div class="activity-time">Cliente: <?php echo htmlspecialchars($actividad['cliente_nombre'] ?? 'N/A'); ?> | Barbero: <?php echo htmlspecialchars($actividad['barbero_nombre'] ?? 'Pendiente'); ?> | Estado: <?php echo ucfirst($actividad['estado']); ?></div>
                                </div>
                                <div class="activity-time"><?php echo date('d/m/Y H:i', strtotime($actividad['fecha_solicitud'])); ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <!-- ============================================ -->
            <!-- VERIFICACIONES SECTION -->
            <!-- ============================================ -->
            <section id="verificaciones-section" class="section">
                <div class="table-container">
                    <div class="table-header">
                        <h3><i class="fas fa-id-card"></i> Solicitudes de Verificación</h3>
                        <div class="search-box"><input type="text" id="search-verificacion" placeholder="Buscar barbero..."></div>
                    </div>
                    <?php if(empty($pendientes_verificacion)): ?>
                        <div style="padding: 40px; text-align: center;">No hay solicitudes pendientes</div>
                    <?php else: ?>
                    <table class="table">
                        <thead><tr><th>Nombre</th><th>Email</th><th>Teléfono</th><th>Documentos</th><th>Solicitado</th><th>Acciones</th></tr></thead>
                        <tbody id="verificaciones-body">
                            <?php foreach($pendientes_verificacion as $barbero): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($barbero['nombre']); ?></td>
                                <td><?php echo htmlspecialchars($barbero['email']); ?></td>
                                <td><?php echo htmlspecialchars($barbero['telefono']); ?></td>
                                <td><span class="badge badge-info"><i class="fas fa-file"></i> <?php echo $barbero['documentos_subidos']; ?> documentos</span></td>
                                <td><?php echo date('d/m/Y', strtotime($barbero['created_at'])); ?></td>
                                <td><button class="btn btn-success btn-sm" onclick="verificarBarbero(<?php echo $barbero['id']; ?>, '<?php echo htmlspecialchars($barbero['nombre']); ?>')"><i class="fas fa-check"></i> Verificar</button></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </section>

            <!-- ============================================ -->
            <!-- BARBEROS SECTION -->
            <!-- ============================================ -->
            <section id="barberos-section" class="section">
                <div class="table-container">
                    <div class="table-header">
                        <h3><i class="fas fa-user-tie"></i> Gestión de Barberos</h3>
                        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                            <button class="btn btn-success btn-sm" onclick="abrirCrearUsuario('barbero')"><i class="fas fa-user-plus"></i> Nuevo Barbero</button>
                            <div class="search-box"><input type="text" id="search-barbero" placeholder="Buscar barbero..."></div>
                        </div>
                    </div>
                    <?php if(empty($todos_barberos)): ?>
                        <div style="padding: 40px; text-align: center;">No hay barberos registrados</div>
                    <?php else: ?>
                    <table class="table">
                        <thead><tr><th>Barbero</th><th>Email</th><th>Teléfono</th><th>Servicios</th><th>Calificación</th><th>Estado</th><th>Acciones</th></tr></thead>
                        <tbody id="barberos-body">
                            <?php foreach($todos_barberos as $barbero): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($barbero['nombre']); ?></td>
                                <td><?php echo htmlspecialchars($barbero['email']); ?></td>
                                <td><?php echo htmlspecialchars($barbero['telefono']); ?></td>
                                <td><?php echo $barbero['total_servicios']; ?></td>
                                <td>⭐ <?php echo number_format($barbero['promedio'] ?? 0, 1); ?></td>
                                <td>
                                    <span class="badge <?php echo $barbero['verificacion_status'] == 'verificado' ? 'badge-success' : ($barbero['verificacion_status'] == 'pendiente' ? 'badge-warning' : 'badge-danger'); ?>"><?php echo ucfirst($barbero['verificacion_status']); ?></span>
                                    <span class="badge <?php echo $barbero['is_available'] ? 'badge-success' : 'badge-danger'; ?>" style="margin-left: 5px;"><?php echo $barbero['is_available'] ? 'Disponible' : 'No disponible'; ?></span>
                                </td>
                                <td><button class="btn btn-warning btn-sm" onclick="toggleBarbero(<?php echo $barbero['id']; ?>, <?php echo $barbero['is_available'] ? 0 : 1; ?>)"><i class="fas fa-<?php echo $barbero['is_available'] ? 'pause' : 'play'; ?>"></i></button></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </section>

            <!-- ============================================ -->
            <!-- CLIENTES SECTION -->
            <!-- ============================================ -->
            <section id="clientes-section" class="section">
                <div class="table-container">
                    <div class="table-header">
                        <h3><i class="fas fa-users"></i> Gestión de Clientes</h3>
                        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                            <button class="btn btn-success btn-sm" onclick="abrirCrearUsuario('cliente')"><i class="fas fa-user-plus"></i> Nuevo Cliente</button>
                            <div class="search-box"><input type="text" id="search-cliente" placeholder="Buscar cliente..."></div>
                        </div>
                    </div>
                    <?php if(empty($todos_clientes)): ?>
                        <div style="padding: 40px; text-align: center;">No hay clientes registrados</div>
                    <?php else: ?>
                    <table class="table">
                        <thead><tr><th>Cliente</th><th>Email</th><th>Teléfono</th><th>Servicios</th><th>Puntos</th><th>Registro</th></tr></thead>
                        <tbody id="clientes-body">
                            <?php foreach($todos_clientes as $cliente): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($cliente['nombre'] ?? 'Sin nombre'); ?></td>
                                <td><?php echo htmlspecialchars($cliente['email']); ?></td>
                                <td><?php echo htmlspecialchars($cliente['telefono'] ?? 'N/A'); ?></td>
                                <td><?php echo $cliente['total_servicios']; ?></td>
                                <td>⭐ <?php echo $cliente['puntos']; ?> pts</td>
                                <td><?php echo date('d/m/Y', strtotime($cliente['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </section>

            <!-- ============================================ -->
            <!-- PRODUCTOS SECTION (CON MÚLTIPLES IMÁGENES) -->
            <!-- ============================================ -->
            <section id="productos-section" class="section">
                <div class="table-container">
                    <div class="table-header">
                        <h3><i class="fas fa-box"></i> Gestión de Productos</h3>
                        <button class="btn btn-success btn-sm" onclick="agregarProducto()"><i class="fas fa-plus"></i> Nuevo Producto</button>
                    </div>
                    <?php if(empty($productos)): ?>
                        <div style="padding: 40px; text-align: center;">No hay productos registrados</div>
                    <?php else: ?>
                    <table class="table">
                        <thead><tr><th>ID</th><th>Imagen</th><th>Nombre</th><th>Precio</th><th>Stock</th><th>Categoría</th><th>Destacado</th><th>Estado</th><th>Acciones</th></tr></thead>
                        <tbody id="productos-body">
                            <?php foreach($productos as $producto): 
                                $imagen_principal = $producto['imagen_principal'] ?? 'default-product.png';
                            ?>
                            <tr>
                                <td><?php echo $producto['id']; ?></td>
                                <td><div class="product-thumb"><img src="../assets/uploads/productos/<?php echo $imagen_principal; ?>" alt="" onerror="this.src='../assets/img/no-image.png'"></div></td>
                                <td><?php echo htmlspecialchars($producto['nombre']); ?></td>
                                <td>$<?php echo number_format($producto['precio'], 2); ?></td>
                                <td><?php echo $producto['stock']; ?></td>
                                <td><?php echo $producto['categoria']; ?></td>
                                <td><?php echo $producto['destacado'] ? '✅ Sí' : '❌ No'; ?></td>
                                <td><span class="badge <?php echo $producto['estado'] == 'activo' ? 'badge-success' : 'badge-danger'; ?>"><?php echo ucfirst($producto['estado']); ?></span></td>
                                <td>
                                    <button class="btn btn-warning btn-sm" onclick="editarProducto(<?php echo $producto['id']; ?>)"><i class="fas fa-edit"></i></button>
                                    <button class="btn btn-danger btn-sm" onclick="eliminarProducto(<?php echo $producto['id']; ?>)"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </section>

            <!-- ============================================ -->
            <!-- CONFIGURACIÓN SECTION -->
            <!-- ============================================ -->
            <section id="configuracion-section" class="section">
                <div class="card">
                    <div class="card-header"><h3><i class="fas fa-cog"></i> Configuración del Sistema</h3></div>
                    <div class="card-body">
                        <form method="POST">
                            <?php echo csrf_field(); ?>
                            <div class="config-section">
                                <h4><i class="fas fa-percentage"></i> Comisiones</h4>
                                <div class="config-row">
                                    <div class="form-group"><label>Comisión de la plataforma (%)</label><input type="number" step="0.1" min="0" max="100" name="comision" value="20"><small>Porcentaje que recibe la plataforma por cada servicio</small></div>
                                </div>
                            </div>
                            <div class="config-section">
                                <h4><i class="fas fa-clock"></i> Tiempos</h4>
                                <div class="config-row">
                                    <div class="form-group"><label>Tiempo de cancelación (minutos)</label><input type="number" min="0" name="tiempo_cancelacion" value="60"></div>
                                    <div class="form-group"><label>Radio de búsqueda (km)</label><input type="number" min="1" name="radio_busqueda" value="10"></div>
                                </div>
                            </div>
                            <div class="config-section">
                                <h4><i class="fas fa-star"></i> Puntos</h4>
                                <div class="config-row">
                                    <div class="form-group"><label>Puntos por servicio</label><input type="number" min="0" name="puntos_servicio" value="10"></div>
                                    <div class="form-group"><label>Puntos por dólar gastado</label><input type="number" min="0" step="0.1" name="puntos_dolar" value="1"></div>
                                </div>
                            </div>
                            <div style="text-align: right;"><button type="submit" name="guardar_config" class="btn btn-primary"><i class="fas fa-save"></i> Guardar Configuración</button></div>
                        </form>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <!-- ============================================ -->
    <!-- MODAL VERIFICAR BARBERO -->
    <!-- ============================================ -->
    <div id="verificar-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h3>Verificar Barbero</h3><button class="btn" onclick="cerrarModal('verificar-modal')">&times;</button></div>
            <form method="POST">
                <?php echo csrf_field(); ?>
                <div class="modal-body">
                    <input type="hidden" name="barbero_id" id="verificar-barbero-id">
                    <p>¿Deseas verificar a <strong id="verificar-barbero-nombre"></strong>?</p>
                    <div class="form-group"><label>Comentario (opcional)</label><textarea name="comentario" rows="3" placeholder="Motivo de aprobación/rechazo..."></textarea></div>
                    <div class="form-group"><label>Decisión:</label><div style="display: flex; gap: 15px;"><label><input type="radio" name="aprobado" value="si" checked> ✅ Aprobar</label><label><input type="radio" name="aprobado" value="no"> ❌ Rechazar</label></div></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn" onclick="cerrarModal('verificar-modal')">Cancelar</button><button type="submit" name="verificar_barbero" class="btn btn-success">Confirmar</button></div>
            </form>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- MODAL PRODUCTO (CON MÚLTIPLES IMÁGENES) -->
    <!-- ============================================ -->
    <div id="usuario-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="usuario-modal-title">Nuevo Usuario</h3>
                <button class="btn" onclick="cerrarModal('usuario-modal')">&times;</button>
            </div>
            <form method="POST">
                <?php echo csrf_field(); ?>
                <div class="modal-body">
                    <input type="hidden" name="crear_usuario_admin" value="1">
                    <input type="hidden" name="rol" id="usuario-rol" value="cliente">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Nombre Completo *</label>
                            <input type="text" name="nombre" id="usuario-nombre" required>
                        </div>
                        <div class="form-group">
                            <label>Email *</label>
                            <input type="email" name="email" id="usuario-email" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Telefono</label>
                            <input type="text" name="telefono" id="usuario-telefono">
                        </div>
                        <div class="form-group">
                            <label>Contrasena *</label>
                            <input type="text" name="password" id="usuario-password" minlength="6" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Direccion</label>
                        <textarea name="direccion" id="usuario-direccion" rows="2" placeholder="Direccion del usuario"></textarea>
                    </div>
                    <div id="barbero-extra-fields" style="display:none;">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Especialidad</label>
                                <input type="text" name="especialidad" id="usuario-especialidad" placeholder="Corte moderno, barba, etc.">
                            </div>
                            <div class="form-group">
                                <label>Experiencia (anios)</label>
                                <input type="number" name="experiencia" id="usuario-experiencia" min="0" max="50" value="0">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Tarifa por Hora</label>
                                <input type="number" name="tarifa_hora" id="usuario-tarifa" min="0" step="0.01">
                            </div>
                            <div class="form-group">
                                <label>Estado de Verificacion</label>
                                <select name="verificacion_status" id="usuario-verificacion">
                                    <option value="pendiente">Pendiente</option>
                                    <option value="en_revision">En revision</option>
                                    <option value="verificado">Verificado</option>
                                    <option value="rechazado">Rechazado</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Descripcion Profesional</label>
                            <textarea name="descripcion" id="usuario-descripcion" rows="3" placeholder="Perfil profesional del barbero"></textarea>
                        </div>
                        <div class="form-group">
                            <label><input type="checkbox" name="is_available" id="usuario-disponible" value="1"> Marcar como disponible ahora</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" onclick="cerrarModal('usuario-modal')">Cancelar</button>
                    <button type="submit" class="btn btn-success">Crear Usuario</button>
                </div>
            </form>
        </div>
    </div>

    <div id="producto-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="producto-modal-title">Nuevo Producto</h3>
                <button class="btn" onclick="cerrarModal('producto-modal')">&times;</button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <?php echo csrf_field(); ?>
                <div class="modal-body">
                    <input type="hidden" name="producto_id" id="producto-id" value="0">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Nombre del Producto *</label>
                            <input type="text" name="nombre" id="producto-nombre" required>
                        </div>
                        <div class="form-group">
                            <label>Categoría *</label>
                            <select name="categoria" id="producto-categoria" required>
                                <option value="Ceras">Ceras</option>
                                <option value="Tijeras">Tijeras</option>
                                <option value="Máquinas">Máquinas</option>
                                <option value="Navajas">Navajas</option>
                                <option value="Productos Capilares">Productos Capilares</option>
                                <option value="Accesorios">Accesorios</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Descripción</label>
                        <textarea name="descripcion" id="producto-descripcion" rows="3" placeholder="Descripción detallada del producto..."></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Precio Regular ($)</label>
                            <input type="number" step="0.01" name="precio" id="producto-precio" required>
                        </div>
                        <div class="form-group">
                            <label>Descuento (%)</label>
                            <input type="number" name="descuento" id="producto-descuento" value="0" min="0" max="100">
                        </div>
                        <div class="form-group">
                            <label>Stock</label>
                            <input type="number" name="stock" id="producto-stock" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label><input type="checkbox" name="destacado" value="1" id="producto-destacado"> Producto Destacado</label>
                        </div>
                        <div class="form-group">
                            <label><input type="checkbox" name="en_oferta" value="1" id="producto-en-oferta"> En Oferta</label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Estado</label>
                        <select name="estado" id="producto-estado">
                            <option value="activo">Activo</option>
                            <option value="inactivo">Inactivo</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-images"></i> Imágenes del Producto (puedes seleccionar varias)</label>
                        <input type="file" name="imagenes[]" id="producto-imagenes" accept="image/*" multiple style="padding: 10px; border: 1px dashed #ddd; width: 100%;">
                        <small>Puedes seleccionar múltiples imágenes. La primera será la principal.</small>
                        <div id="imagenes-preview" class="imagenes-preview"></div>
                    </div>
                    
                    <div id="imagenes-existentes" style="display: none;">
                        <label>Imágenes actuales:</label>
                        <div id="galeria-imagenes" class="imagenes-preview"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" onclick="cerrarModal('producto-modal')">Cancelar</button>
                    <button type="submit" name="guardar_producto" class="btn btn-success">Guardar Producto</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const csrfToken = <?php echo json_encode(csrf_token()); ?>;

        // ============================================
        // NAVEGACIÓN ENTRE SECCIONES
        // ============================================
        function cambiarSeccion(section) {
            document.querySelectorAll('.nav-item').forEach(nav => nav.classList.remove('active'));
            document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
            document.getElementById(`${section}-section`).classList.add('active');
            document.getElementById('section-title').innerHTML = document.querySelector(`[data-section="${section}"] span`).innerHTML;
            document.querySelector(`[data-section="${section}"]`).classList.add('active');
        }
        
        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', function(e) {
                const section = this.getAttribute('data-section');
                if(section) {
                    e.preventDefault();
                    cambiarSeccion(section);
                }
            });
        });
        
        // ============================================
        // GRÁFICOS
        // ============================================
        const ingresosCtx = document.getElementById('ingresosChart')?.getContext('2d');
        if(ingresosCtx && <?php echo json_encode($ingresos_meses); ?> !== '[]') {
            new Chart(ingresosCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode(array_column($ingresos_meses, 'mes')); ?>,
                    datasets: [{
                        label: 'Ingresos ($)',
                        data: <?php echo json_encode(array_column($ingresos_meses, 'total')); ?>,
                        borderColor: '#e94560',
                        backgroundColor: 'rgba(233, 69, 96, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false }
            });
        }
        
        const serviciosCtx = document.getElementById('serviciosChart')?.getContext('2d');
        if(serviciosCtx && <?php echo json_encode($servicios_tipo); ?> !== '[]') {
            new Chart(serviciosCtx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode(array_column($servicios_tipo, 'tipo')); ?>,
                    datasets: [{
                        data: <?php echo json_encode(array_column($servicios_tipo, 'total')); ?>,
                        backgroundColor: ['#e94560', '#0f3460', '#27ae60', '#f39c12', '#3498db']
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false }
            });
        }
        
        // ============================================
        // VERIFICAR BARBERO
        // ============================================
        function verificarBarbero(id, nombre) {
            document.getElementById('verificar-barbero-id').value = id;
            document.getElementById('verificar-barbero-nombre').innerText = nombre;
            document.getElementById('verificar-modal').style.display = 'flex';
        }

        function abrirCrearUsuario(rol) {
            document.getElementById('usuario-rol').value = rol;
            document.getElementById('usuario-modal-title').innerText = rol === 'barbero' ? 'Nuevo Barbero' : 'Nuevo Cliente';
            document.getElementById('usuario-nombre').value = '';
            document.getElementById('usuario-email').value = '';
            document.getElementById('usuario-telefono').value = '';
            document.getElementById('usuario-password').value = '';
            document.getElementById('usuario-direccion').value = '';
            document.getElementById('usuario-especialidad').value = '';
            document.getElementById('usuario-experiencia').value = 0;
            document.getElementById('usuario-tarifa').value = '';
            document.getElementById('usuario-verificacion').value = rol === 'barbero' ? 'verificado' : 'pendiente';
            document.getElementById('usuario-descripcion').value = '';
            document.getElementById('usuario-disponible').checked = rol === 'barbero';
            document.getElementById('barbero-extra-fields').style.display = rol === 'barbero' ? 'block' : 'none';
            document.getElementById('usuario-modal').style.display = 'flex';
        }
        
        // ============================================
        // TOGGLE BARBERO
        // ============================================
        function toggleBarbero(id, estado) {
            if(confirm('¿Cambiar estado del barbero?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `<input type="hidden" name="csrf_token" value="${csrfToken}"><input type="hidden" name="toggle_barbero" value="1"><input type="hidden" name="barbero_id" value="${id}"><input type="hidden" name="activo" value="${estado}">`;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // ============================================
        // PRODUCTOS - CRUD CON MÚLTIPLES IMÁGENES
        // ============================================
        
        // Previsualización de imágenes seleccionadas
        document.getElementById('producto-imagenes')?.addEventListener('change', function(e) {
            const preview = document.getElementById('imagenes-preview');
            preview.innerHTML = '';
            const files = Array.from(e.target.files);
            
            files.forEach((file, index) => {
                const reader = new FileReader();
                reader.onload = function(event) {
                    const div = document.createElement('div');
                    div.className = 'preview-img';
                    div.innerHTML = `
                        <img src="${event.target.result}" alt="Vista previa">
                        ${index === 0 ? '<div class="badge-principal">Principal</div>' : ''}
                    `;
                    preview.appendChild(div);
                };
                reader.readAsDataURL(file);
            });
        });
        
        function agregarProducto() {
            document.getElementById('producto-modal-title').innerText = 'Nuevo Producto';
            document.getElementById('producto-id').value = '0';
            document.getElementById('producto-nombre').value = '';
            document.getElementById('producto-descripcion').value = '';
            document.getElementById('producto-precio').value = '';
            document.getElementById('producto-descuento').value = '0';
            document.getElementById('producto-stock').value = '';
            document.getElementById('producto-categoria').value = 'Ceras';
            document.getElementById('producto-destacado').checked = false;
            document.getElementById('producto-en-oferta').checked = false;
            document.getElementById('producto-estado').value = 'activo';
            document.getElementById('imagenes-preview').innerHTML = '';
            document.getElementById('imagenes-existentes').style.display = 'none';
            document.getElementById('producto-modal').style.display = 'flex';
        }
        
        function editarProducto(id) {
            fetch('../api/get_producto.php?id=' + id)
                .then(response => response.json())
                .then(producto => {
                    document.getElementById('producto-modal-title').innerText = 'Editar Producto';
                    document.getElementById('producto-id').value = producto.id;
                    document.getElementById('producto-nombre').value = producto.nombre;
                    document.getElementById('producto-descripcion').value = producto.descripcion || '';
                    document.getElementById('producto-precio').value = producto.precio;
                    document.getElementById('producto-descuento').value = producto.descuento || 0;
                    document.getElementById('producto-stock').value = producto.stock;
                    document.getElementById('producto-categoria').value = producto.categoria;
                    document.getElementById('producto-destacado').checked = producto.destacado == 1;
                    document.getElementById('producto-en-oferta').checked = producto.en_oferta == 1;
                    document.getElementById('producto-estado').value = producto.estado || 'activo';
                    document.getElementById('imagenes-preview').innerHTML = '';
                    
                    // Mostrar imágenes existentes
                    if(producto.imagenes && producto.imagenes.length > 0) {
                        const galeria = document.getElementById('galeria-imagenes');
                        galeria.innerHTML = '';
                        producto.imagenes.forEach(img => {
                            galeria.innerHTML += `
                                <div class="preview-img">
                                    <img src="../assets/uploads/productos/${img.imagen_url}" alt="">
                                    ${img.es_principal ? '<div class="badge-principal">Principal</div>' : ''}
                                    <button type="button" class="btn-eliminar" onclick="eliminarImagen(${img.id})">&times;</button>
                                </div>
                            `;
                        });
                        document.getElementById('imagenes-existentes').style.display = 'block';
                    } else {
                        document.getElementById('imagenes-existentes').style.display = 'none';
                    }
                    
                    document.getElementById('producto-modal').style.display = 'flex';
                })
                .catch(error => {
                    alert('Error al cargar el producto');
                });
        }
        
        function eliminarProducto(id) {
            if(confirm('¿Eliminar este producto permanentemente?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `<input type="hidden" name="csrf_token" value="${csrfToken}"><input type="hidden" name="eliminar_producto" value="1"><input type="hidden" name="producto_id" value="${id}">`;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function eliminarImagen(imagenId) {
            if(confirm('¿Eliminar esta imagen?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `<input type="hidden" name="csrf_token" value="${csrfToken}"><input type="hidden" name="eliminar_imagen" value="1"><input type="hidden" name="imagen_id" value="${imagenId}">`;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // ============================================
        // MODALES
        // ============================================
        function cerrarModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
        
        // ============================================
        // BÚSQUEDAS
        // ============================================
        document.getElementById('search-verificacion')?.addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            document.querySelectorAll('#verificaciones-body tr').forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(filter) ? '' : 'none';
            });
        });
        
        document.getElementById('search-barbero')?.addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            document.querySelectorAll('#barberos-body tr').forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(filter) ? '' : 'none';
            });
        });
        
        document.getElementById('search-cliente')?.addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            document.querySelectorAll('#clientes-body tr').forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(filter) ? '' : 'none';
            });
        });
        
        // ============================================
        // HASH URL PARA SECCIÓN ESPECÍFICA
        // ============================================
        if(window.location.hash) {
            const section = window.location.hash.substring(1);
            if(document.getElementById(`${section}-section`)) {
                cambiarSeccion(section);
            }
        }
    </script>
    <?php include BASE_PATH . 'includes/pwa_register.php'; ?>
</body>
</html>
