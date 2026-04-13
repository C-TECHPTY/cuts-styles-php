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
        } catch (PDOException $e) {
            error_log("Error en getPuntosCliente: " . $e->getMessage());
            return 0;
        }
    }

    public function canjearRecompensa($cliente_id, $recompensa_id) {
        try {
            $this->conn->beginTransaction();

            $recompensaQuery = "SELECT * FROM recompensas WHERE id = :id AND is_active = 1 AND stock > 0";
            $recompensaStmt = $this->conn->prepare($recompensaQuery);
            $recompensaStmt->bindParam(":id", $recompensa_id);
            $recompensaStmt->execute();
            $recompensa = $recompensaStmt->fetch(PDO::FETCH_ASSOC);

            if (!$recompensa) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Recompensa no disponible'];
            }

            $puntosActuales = $this->getPuntosCliente($cliente_id);
            if ($puntosActuales < $recompensa['puntos_requeridos']) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Puntos insuficientes'];
            }

            $canjeQuery = "INSERT INTO canjes_recompensas (cliente_id, recompensa_id, puntos_usados)
                           VALUES (:cliente_id, :recompensa_id, :puntos)";
            $canjeStmt = $this->conn->prepare($canjeQuery);
            $canjeStmt->bindParam(":cliente_id", $cliente_id);
            $canjeStmt->bindParam(":recompensa_id", $recompensa_id);
            $canjeStmt->bindParam(":puntos", $recompensa['puntos_requeridos']);
            $canjeStmt->execute();

            $updateCliente = "UPDATE clientes
                              SET puntos = puntos - :puntos
                              WHERE id = :cliente_id";
            $updateStmt = $this->conn->prepare($updateCliente);
            $updateStmt->bindParam(":puntos", $recompensa['puntos_requeridos']);
            $updateStmt->bindParam(":cliente_id", $cliente_id);
            $updateStmt->execute();

            $updateStock = "UPDATE recompensas
                            SET stock = stock - 1
                            WHERE id = :recompensa_id AND stock > 0";
            $stockStmt = $this->conn->prepare($updateStock);
            $stockStmt->bindParam(":recompensa_id", $recompensa_id);
            $stockStmt->execute();

            if ($stockStmt->rowCount() === 0) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'La recompensa ya no tiene stock disponible'];
            }

            $loyaltyManager = new LoyaltyManager($this->conn);
            $registered = $loyaltyManager->registerRedeem((int) $cliente_id, (int) $recompensa_id, (int) $recompensa['puntos_requeridos']);
            if (!$registered) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'No se pudo registrar el historial del canje'];
            }

            $this->conn->commit();
            return ['success' => true, 'message' => 'Recompensa canjeada exitosamente'];
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
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
        } catch (PDOException $e) {
            error_log("Error en getRecompensasDisponibles: " . $e->getMessage());
            return [];
        }
    }

    public function getRecompensasAdmin() {
        try {
            $query = "SELECT * FROM recompensas ORDER BY updated_at DESC, created_at DESC";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log("Error en getRecompensasAdmin: " . $e->getMessage());
            return [];
        }
    }

    public function guardarRecompensa(array $data) {
        try {
            $id = (int) ($data['id'] ?? 0);
            $nombre = trim((string) ($data['nombre'] ?? ''));
            $descripcion = trim((string) ($data['descripcion'] ?? ''));
            $puntos = max(0, (int) ($data['puntos_requeridos'] ?? 0));
            $stock = max(0, (int) ($data['stock'] ?? 0));
            $isActive = !empty($data['is_active']) ? 1 : 0;

            if ($nombre === '' || $puntos <= 0) {
                return ['success' => false, 'message' => 'Nombre y puntos requeridos son obligatorios.'];
            }

            if ($id > 0) {
                $query = "UPDATE recompensas
                          SET nombre = :nombre,
                              descripcion = :descripcion,
                              puntos_requeridos = :puntos,
                              stock = :stock,
                              is_active = :is_active,
                              updated_at = NOW()
                          WHERE id = :id";
            } else {
                $query = "INSERT INTO recompensas
                          (nombre, descripcion, puntos_requeridos, stock, is_active, created_at, updated_at)
                          VALUES
                          (:nombre, :descripcion, :puntos, :stock, :is_active, NOW(), NOW())";
            }

            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(':nombre', $nombre);
            $stmt->bindValue(':descripcion', $descripcion);
            $stmt->bindValue(':puntos', $puntos, PDO::PARAM_INT);
            $stmt->bindValue(':stock', $stock, PDO::PARAM_INT);
            $stmt->bindValue(':is_active', $isActive, PDO::PARAM_INT);
            if ($id > 0) {
                $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            }
            $stmt->execute();

            return ['success' => true, 'message' => $id > 0 ? 'Recompensa actualizada.' : 'Recompensa creada.'];
        } catch (Throwable $e) {
            error_log("Error en guardarRecompensa: " . $e->getMessage());
            return ['success' => false, 'message' => 'No se pudo guardar la recompensa.'];
        }
    }

    public function cambiarEstadoRecompensa(int $rewardId, int $isActive) {
        try {
            $stmt = $this->conn->prepare("UPDATE recompensas SET is_active = :is_active, updated_at = NOW() WHERE id = :id");
            $stmt->bindValue(':is_active', $isActive ? 1 : 0, PDO::PARAM_INT);
            $stmt->bindValue(':id', $rewardId, PDO::PARAM_INT);
            $stmt->execute();
            return ['success' => true, 'message' => 'Estado de recompensa actualizado.'];
        } catch (Throwable $e) {
            error_log("Error en cambiarEstadoRecompensa: " . $e->getMessage());
            return ['success' => false, 'message' => 'No se pudo actualizar el estado de la recompensa.'];
        }
    }

    public function ajustarPuntosCliente(int $clienteId, int $pointsDelta, string $description = 'Ajuste manual de puntos') {
        if ($clienteId <= 0 || $pointsDelta === 0) {
            return ['success' => false, 'message' => 'Debes indicar un cliente y una variacion distinta de cero.'];
        }

        try {
            $this->conn->beginTransaction();

            $balanceActual = (int) $this->getPuntosCliente($clienteId);
            $nuevoBalance = $balanceActual + $pointsDelta;
            if ($nuevoBalance < 0) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'El ajuste dejaria al cliente con puntos negativos.'];
            }

            $update = $this->conn->prepare("UPDATE clientes SET puntos = puntos + :delta WHERE id = :cliente_id");
            $update->bindValue(':delta', $pointsDelta, PDO::PARAM_INT);
            $update->bindValue(':cliente_id', $clienteId, PDO::PARAM_INT);
            $update->execute();

            $loyaltyManager = new LoyaltyManager($this->conn);
            $ok = $loyaltyManager->registerManualAdjustment($clienteId, $pointsDelta, $description);
            if (!$ok) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'No se pudo registrar el ajuste en historial.'];
            }

            $this->conn->commit();
            return ['success' => true, 'message' => 'Puntos ajustados correctamente.'];
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log("Error en ajustarPuntosCliente: " . $e->getMessage());
            return ['success' => false, 'message' => 'No se pudo ajustar los puntos del cliente.'];
        }
    }

    public function getHistorialPuntos($cliente_id) {
        try {
            $check = $this->conn->query("SHOW TABLES LIKE 'puntos_historial'");
            if ($check->rowCount() == 0) {
                return [];
            }

            $query = "SELECT ph.*, s.tipo as servicio_tipo, r.nombre as recompensa_nombre
                      FROM puntos_historial ph
                      LEFT JOIN servicios s ON ph.servicio_id = s.id
                      LEFT JOIN recompensas r ON ph.recompensa_id = r.id
                      WHERE ph.cliente_id = :cliente_id
                      ORDER BY ph.fecha DESC
                      LIMIT 50";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":cliente_id", $cliente_id);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error en getHistorialPuntos: " . $e->getMessage());
            return [];
        }
    }

    public function getHistorialPuntosAdmin(int $limit = 100) {
        try {
            $limit = max(1, min(200, $limit));
            $query = "SELECT ph.*, u.nombre AS cliente_nombre, u.email AS cliente_email, s.tipo AS servicio_tipo, r.nombre AS recompensa_nombre
                      FROM puntos_historial ph
                      JOIN clientes c ON ph.cliente_id = c.id
                      JOIN users u ON c.user_id = u.id
                      LEFT JOIN servicios s ON ph.servicio_id = s.id
                      LEFT JOIN recompensas r ON ph.recompensa_id = r.id
                      ORDER BY ph.fecha DESC
                      LIMIT {$limit}";
            $stmt = $this->conn->query($query);
            return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Throwable $e) {
            error_log("Error en getHistorialPuntosAdmin: " . $e->getMessage());
            return [];
        }
    }
}
?>
