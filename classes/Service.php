<?php
// classes/Service.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

class Service {
    public $conn;
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }
    
    public function solicitarServicio($cliente_id, $tipo, $notas, $horarios) {
        $query = "INSERT INTO servicios
                  SET cliente_id = :cliente_id, tipo = :tipo, notas = :notas,
                      estado = 'pendiente'";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":cliente_id", $cliente_id);
        $stmt->bindParam(":tipo", $tipo);
        $stmt->bindParam(":notas", $notas);
        
        if($stmt->execute()) {
            $servicio_id = $this->conn->lastInsertId();
            return $servicio_id;
        }
        return false;
    }
    
    public function aceptarServicio($servicio_id, $barbero_id, $tiempo_estimado, $notas) {
        $query = "UPDATE servicios
                  SET barbero_id = :barbero_id, estado = 'aceptado',
                      fecha_aceptacion = NOW(), tiempo_estimado = :tiempo_estimado,
                      comentario_barbero = :notas
                  WHERE id = :servicio_id AND estado = 'pendiente'";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":barbero_id", $barbero_id);
        $stmt->bindParam(":servicio_id", $servicio_id);
        $stmt->bindParam(":tiempo_estimado", $tiempo_estimado);
        $stmt->bindParam(":notas", $notas);
        
        if($stmt->execute()) {
            return true;
        }
        return false;
    }
    
    public function completarServicio($servicio_id, $duracion_real, $notas) {
        $query = "UPDATE servicios 
                  SET estado = 'completado', fecha_fin = NOW(), 
                      tiempo_real = :duracion_real, 
                      comentario_barbero = CONCAT(COALESCE(comentario_barbero, ''), '\n', :notas)
                  WHERE id = :servicio_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":duracion_real", $duracion_real);
        $stmt->bindParam(":notas", $notas);
        $stmt->bindParam(":servicio_id", $servicio_id);
        
        if($stmt->execute()) {
            // Actualizar puntos del cliente
            $this->actualizarPuntos($servicio_id);
            return true;
        }
        return false;
    }
    
    private function actualizarPuntos($servicio_id) {
        // Obtener cliente_id del servicio
        $query = "SELECT cliente_id FROM servicios WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $servicio_id);
        $stmt->execute();
        $servicio = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($servicio) {
            $puntos = PUNTOS_POR_SERVICIO;
            $update = "UPDATE clientes SET puntos = puntos + :puntos WHERE id = :cliente_id";
            $stmt2 = $this->conn->prepare($update);
            $stmt2->bindParam(":puntos", $puntos);
            $stmt2->bindParam(":cliente_id", $servicio['cliente_id']);
            $stmt2->execute();
        }
    }
}
?>