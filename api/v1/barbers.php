<?php
// api/v1/barbers.php
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$db = new Database();
$conn = $db->getConnection();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Obtener lista de barberos
    $lat = $_GET['lat'] ?? null;
    $lng = $_GET['lng'] ?? null;
    $radio = $_GET['radio'] ?? 10;
    
    if ($lat && $lng) {
        // Barberos cercanos (geolocalización)
        $query = "SELECT u.id, u.nombre, u.email, u.telefono, b.*,
                  (6371 * acos(cos(radians(:lat)) * cos(radians(b.latitud)) 
                  * cos(radians(b.longitud) - radians(:lng)) 
                  + sin(radians(:lat)) * sin(radians(b.latitud)))) AS distance
                  FROM barberos b
                  JOIN users u ON b.user_id = u.id
                  WHERE b.verificacion_status = 'verificado' AND b.is_available = 1
                  HAVING distance < :radio
                  ORDER BY distance ASC";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':lat', $lat);
        $stmt->bindParam(':lng', $lng);
        $stmt->bindParam(':radio', $radio);
    } else {
        // Todos los barberos verificados
        $query = "SELECT u.id, u.nombre, u.email, u.telefono, b.* 
                  FROM barberos b
                  JOIN users u ON b.user_id = u.id
                  WHERE b.verificacion_status = 'verificado'
                  ORDER BY b.calificacion_promedio DESC";
        $stmt = $conn->prepare($query);
    }
    
    $stmt->execute();
    $barbers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'status' => 'success',
        'count' => count($barbers),
        'data' => $barbers
    ]);
    
} elseif ($method === 'POST') {
    // Actualizar disponibilidad del barbero
    $input = json_decode(file_get_contents('php://input'), true);
    $userId = $input['user_id'] ?? 0;
    $isAvailable = $input['is_available'] ?? 0;
    
    $query = "UPDATE barberos SET is_available = :available WHERE user_id = :user_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':available', $isAvailable);
    $stmt->bindParam(':user_id', $userId);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Estado actualizado']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error al actualizar']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
}
?>