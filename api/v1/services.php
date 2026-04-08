<?php
// api/v1/services.php
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$db = new Database();
$conn = $db->getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

if ($method === 'POST') {
    $action = $input['action'] ?? '';
    
    if ($action === 'request') {
        // Solicitar servicio
        $clienteId = $input['cliente_id'] ?? 0;
        $tipo = $input['tipo'] ?? '';
        $notas = $input['notas'] ?? '';
        
        if (empty($tipo)) {
            echo json_encode(['status' => 'error', 'message' => 'Tipo de servicio requerido']);
            exit;
        }
        
        $query = "INSERT INTO servicios (cliente_id, tipo, notas, estado) VALUES (:cliente_id, :tipo, :notas, 'pendiente')";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':cliente_id', $clienteId);
        $stmt->bindParam(':tipo', $tipo);
        $stmt->bindParam(':notas', $notas);
        
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Servicio solicitado', 'service_id' => $conn->lastInsertId()]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error al solicitar servicio']);
        }
        
    } elseif ($action === 'accept') {
        // Aceptar servicio (barbero)
        $servicioId = $input['service_id'] ?? 0;
        $barberoId = $input['barbero_id'] ?? 0;
        $tiempoEstimado = $input['tiempo_estimado'] ?? 30;
        
        $query = "UPDATE servicios SET barbero_id = :barbero_id, estado = 'aceptado', tiempo_estimado = :tiempo, fecha_aceptacion = NOW() WHERE id = :id AND estado = 'pendiente'";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':barbero_id', $barberoId);
        $stmt->bindParam(':tiempo', $tiempoEstimado);
        $stmt->bindParam(':id', $servicioId);
        
        if ($stmt->execute() && $stmt->rowCount() > 0) {
            echo json_encode(['status' => 'success', 'message' => 'Servicio aceptado']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error al aceptar servicio']);
        }
        
    } elseif ($action === 'complete') {
        // Completar servicio
        $servicioId = $input['service_id'] ?? 0;
        $duracionReal = $input['duracion_real'] ?? 0;
        
        $query = "UPDATE servicios SET estado = 'completado', tiempo_real = :duracion, fecha_fin = NOW() WHERE id = :id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':duracion', $duracionReal);
        $stmt->bindParam(':id', $servicioId);
        
        if ($stmt->execute()) {
            // Sumar puntos al cliente
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
        echo json_encode(['status' => 'error', 'message' => 'Acción no válida']);
    }
    
} elseif ($method === 'GET') {
    $clienteId = $_GET['cliente_id'] ?? 0;
    $barberoId = $_GET['barbero_id'] ?? 0;
    
    if ($clienteId > 0) {
        // Historial del cliente
        $query = "SELECT s.*, u.nombre as barbero_nombre 
                  FROM servicios s
                  LEFT JOIN barberos b ON s.barbero_id = b.id
                  LEFT JOIN users u ON b.user_id = u.id
                  WHERE s.cliente_id = :cliente_id
                  ORDER BY s.fecha_solicitud DESC";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':cliente_id', $clienteId);
    } elseif ($barberoId > 0) {
        // Servicios del barbero
        $query = "SELECT s.*, cu.nombre as cliente_nombre 
                  FROM servicios s
                  JOIN clientes c ON s.cliente_id = c.id
                  JOIN users cu ON c.user_id = cu.id
                  WHERE s.barbero_id = :barbero_id
                  ORDER BY s.fecha_solicitud DESC";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':barbero_id', $barberoId);
    } else {
        // Servicios pendientes
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
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
}
?>