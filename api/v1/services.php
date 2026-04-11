<?php
// api/v1/services.php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$db = new Database();
$conn = $db->getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?: [];

$requireAuth = function () {
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'No autenticado']);
        exit;
    }
};

if ($method === 'POST') {
    $requireAuth();
    $action = $input['action'] ?? '';

    if ($action === 'request') {
        if ($_SESSION['user_rol'] !== 'cliente') {
            echo json_encode(['status' => 'error', 'message' => 'Rol no autorizado']);
            exit;
        }

        $clienteStmt = $conn->prepare("SELECT id FROM clientes WHERE user_id = :user_id LIMIT 1");
        $clienteStmt->bindParam(':user_id', $_SESSION['user_id']);
        $clienteStmt->execute();
        $cliente = $clienteStmt->fetch(PDO::FETCH_ASSOC);

        $tipo = trim((string) ($input['tipo'] ?? ''));
        $notas = trim((string) ($input['notas'] ?? ''));

        if (!$cliente || $tipo === '') {
            echo json_encode(['status' => 'error', 'message' => 'Tipo de servicio requerido']);
            exit;
        }

        $query = "INSERT INTO servicios (cliente_id, tipo, notas, estado) VALUES (:cliente_id, :tipo, :notas, 'pendiente')";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':cliente_id', $cliente['id']);
        $stmt->bindParam(':tipo', $tipo);
        $stmt->bindParam(':notas', $notas);

        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Servicio solicitado', 'service_id' => $conn->lastInsertId()]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error al solicitar servicio']);
        }
    } elseif ($action === 'accept') {
        if ($_SESSION['user_rol'] !== 'barbero') {
            echo json_encode(['status' => 'error', 'message' => 'Rol no autorizado']);
            exit;
        }

        $barberoStmt = $conn->prepare("SELECT id FROM barberos WHERE user_id = :user_id LIMIT 1");
        $barberoStmt->bindParam(':user_id', $_SESSION['user_id']);
        $barberoStmt->execute();
        $barbero = $barberoStmt->fetch(PDO::FETCH_ASSOC);

        $servicioId = (int) ($input['service_id'] ?? 0);
        $tiempoEstimado = (int) ($input['tiempo_estimado'] ?? 30);

        if (!$barbero || $servicioId <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Datos invalidos']);
            exit;
        }

        $query = "UPDATE servicios
                  SET barbero_id = :barbero_id, estado = 'aceptado', tiempo_estimado = :tiempo, fecha_aceptacion = NOW()
                  WHERE id = :id AND estado = 'pendiente'";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':barbero_id', $barbero['id']);
        $stmt->bindParam(':tiempo', $tiempoEstimado);
        $stmt->bindParam(':id', $servicioId);

        if ($stmt->execute() && $stmt->rowCount() > 0) {
            echo json_encode(['status' => 'success', 'message' => 'Servicio aceptado']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error al aceptar servicio']);
        }
    } elseif ($action === 'complete') {
        if ($_SESSION['user_rol'] !== 'barbero') {
            echo json_encode(['status' => 'error', 'message' => 'Rol no autorizado']);
            exit;
        }

        $barberoStmt = $conn->prepare("SELECT id FROM barberos WHERE user_id = :user_id LIMIT 1");
        $barberoStmt->bindParam(':user_id', $_SESSION['user_id']);
        $barberoStmt->execute();
        $barbero = $barberoStmt->fetch(PDO::FETCH_ASSOC);

        $servicioId = (int) ($input['service_id'] ?? 0);
        $duracionReal = (int) ($input['duracion_real'] ?? 0);

        if (!$barbero || $servicioId <= 0 || $duracionReal <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Datos invalidos']);
            exit;
        }

        $query = "UPDATE servicios
                  SET estado = 'completado', tiempo_real = :duracion, fecha_fin = NOW()
                  WHERE id = :id AND barbero_id = :barbero_id AND estado IN ('aceptado', 'en_proceso')";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':duracion', $duracionReal);
        $stmt->bindParam(':id', $servicioId);
        $stmt->bindParam(':barbero_id', $barbero['id']);

        if ($stmt->execute() && $stmt->rowCount() > 0) {
            $query2 = "UPDATE clientes c
                       JOIN servicios s ON c.id = s.cliente_id
                       SET c.puntos = c.puntos + 10
                       WHERE s.id = :id";
            $stmt2 = $conn->prepare($query2);
            $stmt2->bindParam(':id', $servicioId);
            $stmt2->execute();

            echo json_encode(['status' => 'success', 'message' => 'Servicio completado']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error al completar servicio']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Accion no valida']);
    }
} elseif ($method === 'GET') {
    if (isset($_GET['cliente_id']) || isset($_GET['barbero_id'])) {
        $requireAuth();
    }

    $clienteId = (int) ($_GET['cliente_id'] ?? 0);
    $barberoId = (int) ($_GET['barbero_id'] ?? 0);

    if ($clienteId > 0) {
        if ($_SESSION['user_rol'] !== 'cliente') {
            echo json_encode(['status' => 'error', 'message' => 'Rol no autorizado']);
            exit;
        }
        $query = "SELECT s.*, u.nombre as barbero_nombre
                  FROM servicios s
                  LEFT JOIN barberos b ON s.barbero_id = b.id
                  LEFT JOIN users u ON b.user_id = u.id
                  WHERE s.cliente_id = :cliente_id
                  ORDER BY s.fecha_solicitud DESC";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':cliente_id', $clienteId);
    } elseif ($barberoId > 0) {
        if ($_SESSION['user_rol'] !== 'barbero') {
            echo json_encode(['status' => 'error', 'message' => 'Rol no autorizado']);
            exit;
        }
        $query = "SELECT s.*, cu.nombre as cliente_nombre
                  FROM servicios s
                  JOIN clientes c ON s.cliente_id = c.id
                  JOIN users cu ON c.user_id = cu.id
                  WHERE s.barbero_id = :barbero_id
                  ORDER BY s.fecha_solicitud DESC";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':barbero_id', $barberoId);
    } else {
        $query = "SELECT s.*, cu.nombre as cliente_nombre, cu.telefono
                  FROM servicios s
                  JOIN clientes c ON s.cliente_id = c.id
                  JOIN users cu ON c.user_id = cu.id
                  WHERE s.estado = 'pendiente'
                  ORDER BY s.fecha_solicitud ASC";
        $stmt = $conn->prepare($query);
    }

    $stmt->execute();
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'count' => count($services), 'data' => $services]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Metodo no permitido']);
}
