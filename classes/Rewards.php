<?php
// classes/Rewards.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/LoyaltyManager.php';

class Rewards {
    public $conn;
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }
    
    public function getPuntosCliente($cliente_id) {
        try {
            $query = "SELECT puntos FROM clientes WHERE id = :cliente_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":cliente_id", $cliente_id);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['puntos'] : 0;
        } catch(PDOException $e) {
            error_log("Error en getPuntosCliente: " . $e->getMessage());
            return 0;
        }
    }
    
    public function canjearRecompensa($cliente_id, $recompensa_id) {
        try {
            // Obtener información de la recompensa
            $recompensa_query = "SELECT * FROM recompensas WHERE id = :id AND is_active = 1 AND stock > 0";
            $recompensa_stmt = $this->conn->prepare($recompensa_query);
            $recompensa_stmt->bindParam(":id", $recompensa_id);
            $recompensa_stmt->execute();
            $recompensa = $recompensa_stmt->fetch(PDO::FETCH_ASSOC);
            
            if(!$recompensa) {
                return ['success' => false, 'message' => 'Recompensa no disponible'];
            }
            
            // Verificar puntos del cliente
            $puntos_actuales = $this->getPuntosCliente($cliente_id);
            
            if($puntos_actuales < $recompensa['puntos_requeridos']) {
                return ['success' => false, 'message' => 'Puntos insuficientes'];
            }
            
            // Registrar canje
            $canje_query = "INSERT INTO canjes_recompensas (cliente_id, recompensa_id, puntos_usados)
                            VALUES (:cliente_id, :recompensa_id, :puntos)";
            $canje_stmt = $this->conn->prepare($canje_query);
            $canje_stmt->bindParam(":cliente_id", $cliente_id);
            $canje_stmt->bindParam(":recompensa_id", $recompensa_id);
            $canje_stmt->bindParam(":puntos", $recompensa['puntos_requeridos']);
            $canje_stmt->execute();
            
            // Restar puntos del cliente
            $update_cliente = "UPDATE clientes 
                               SET puntos = puntos - :puntos 
                               WHERE id = :cliente_id";
            $update_stmt = $this->conn->prepare($update_cliente);
            $update_stmt->bindParam(":puntos", $recompensa['puntos_requeridos']);
            $update_stmt->bindParam(":cliente_id", $cliente_id);
            $update_stmt->execute();

            $loyaltyManager = new LoyaltyManager($this->conn);
            $loyaltyManager->registerRedeem((int) $cliente_id, (int) $recompensa_id, (int) $recompensa['puntos_requeridos']);
            
            return ['success' => true, 'message' => 'Recompensa canjeada exitosamente'];
            
        } catch(PDOException $e) {
            error_log("Error en canjearRecompensa: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error al canjear recompensa'];
        }
    }
    
    public function getRecompensasDisponibles() {
        try {
            $query = "SELECT * FROM recompensas WHERE is_active = 1 AND stock > 0 ORDER BY puntos_requeridos ASC";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error en getRecompensasDisponibles: " . $e->getMessage());
            return [];
        }
    }
    
    public function getHistorialPuntos($cliente_id) {
        try {
            // Verificar si la tabla existe
            $check = $this->conn->query("SHOW TABLES LIKE 'puntos_historial'");
            if($check->rowCount() == 0) {
                return []; // Tabla no existe, retornar vacío
            }
            
            $query = "SELECT ph.*, s.tipo as servicio_tipo 
                      FROM puntos_historial ph
                      LEFT JOIN servicios s ON ph.servicio_id = s.id
                      WHERE ph.cliente_id = :cliente_id
                      ORDER BY ph.fecha DESC
                      LIMIT 50";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":cliente_id", $cliente_id);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error en getHistorialPuntos: " . $e->getMessage());
            return [];
        }
    }
}
?>
