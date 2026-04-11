<?php
// classes/Service.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/ServiceChat.php';

class Service {
    public $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function solicitarServicio($cliente_id, $tipo, $notas, $horarios = []) {
        $tipo = trim((string) $tipo);
        if ($cliente_id <= 0 || $tipo === '') {
            return false;
        }

        $horariosTexto = is_array($horarios) ? implode(', ', array_map('sanitizar', $horarios)) : '';
        $notasCompletas = trim((string) $notas);
        if ($horariosTexto !== '') {
            $notasCompletas .= ($notasCompletas !== '' ? PHP_EOL : '') . 'Horarios preferidos: ' . $horariosTexto;
        }

        $query = "INSERT INTO servicios
                  SET cliente_id = :cliente_id, tipo = :tipo, notas = :notas, estado = 'pendiente'";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':cliente_id', $cliente_id);
        $stmt->bindParam(':tipo', $tipo);
        $stmt->bindParam(':notas', $notasCompletas);

        if ($stmt->execute()) {
            return (int) $this->conn->lastInsertId();
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
        $stmt->bindParam(':barbero_id', $barbero_id);
        $stmt->bindParam(':servicio_id', $servicio_id);
        $stmt->bindParam(':tiempo_estimado', $tiempo_estimado);
        $stmt->bindParam(':notas', $notas);

        if (!$stmt->execute() || $stmt->rowCount() === 0) {
            return false;
        }

        try {
            $chat = new ServiceChat();
            $chat->openChatForService((int) $servicio_id);
        } catch (Throwable $e) {
            logError('No se pudo abrir el chat del servicio: ' . $e->getMessage(), __FILE__, __LINE__);
        }

        return true;
    }

    public function completarServicio($servicio_id, $duracion_real, $notas) {
        try {
            $this->conn->beginTransaction();

            $query = "UPDATE servicios
                      SET estado = 'completado', fecha_fin = NOW(),
                          tiempo_real = :duracion_real,
                          comentario_barbero = CONCAT(COALESCE(comentario_barbero, ''), :salto, :notas)
                      WHERE id = :servicio_id AND estado IN ('aceptado', 'en_proceso')";

            $stmt = $this->conn->prepare($query);
            $salto = $notas !== '' ? PHP_EOL : '';
            $stmt->bindParam(':duracion_real', $duracion_real);
            $stmt->bindParam(':salto', $salto);
            $stmt->bindParam(':notas', $notas);
            $stmt->bindParam(':servicio_id', $servicio_id);
            $stmt->execute();

            if ($stmt->rowCount() === 0) {
                $this->conn->rollBack();
                return false;
            }

            $this->actualizarPuntos($servicio_id);
            try {
                $chat = new ServiceChat();
                $chat->closeChatForService((int) $servicio_id, 'service_completed');
            } catch (Throwable $e) {
                logError('No se pudo cerrar el chat del servicio: ' . $e->getMessage(), __FILE__, __LINE__);
            }
            $this->conn->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            logError('Error completando servicio: ' . $e->getMessage(), __FILE__, __LINE__);
            return false;
        }
    }

    public function getServiciosPendientes() {
        $query = "SELECT s.*, c.id as cliente_id, u.nombre as cliente_nombre, u.telefono, u.direccion
                  FROM servicios s
                  JOIN clientes c ON s.cliente_id = c.id
                  JOIN users u ON c.user_id = u.id
                  WHERE s.estado = 'pendiente'
                  ORDER BY s.fecha_solicitud ASC";
        $stmt = $this->conn->query($query);
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    public function getServiciosByCliente($cliente_id) {
        $query = "SELECT s.*, u.nombre as barbero_nombre
                  FROM servicios s
                  LEFT JOIN barberos b ON s.barbero_id = b.id
                  LEFT JOIN users u ON b.user_id = u.id
                  WHERE s.cliente_id = :cliente_id
                  ORDER BY s.fecha_solicitud DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':cliente_id', $cliente_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function cancelarServicio($servicio_id, $cliente_id, $motivo = '') {
        try {
            $this->conn->beginTransaction();

            $query = "UPDATE servicios
                      SET estado = 'cancelado',
                          fecha_fin = NOW(),
                          notas = CONCAT(COALESCE(notas, ''), :salto, :motivo)
                      WHERE id = :servicio_id
                        AND cliente_id = :cliente_id
                        AND estado IN ('pendiente', 'aceptado')";
            $stmt = $this->conn->prepare($query);
            $salto = trim((string) $motivo) !== '' ? PHP_EOL : '';
            $motivoTexto = trim((string) $motivo) !== '' ? 'Cancelado por cliente: ' . trim((string) $motivo) : '';
            $stmt->bindParam(':salto', $salto);
            $stmt->bindParam(':motivo', $motivoTexto);
            $stmt->bindParam(':servicio_id', $servicio_id);
            $stmt->bindParam(':cliente_id', $cliente_id);
            $stmt->execute();

            if ($stmt->rowCount() === 0) {
                $this->conn->rollBack();
                return false;
            }

            try {
                $chat = new ServiceChat();
                $chat->registerClientCancellation((int) $servicio_id, (int) $cliente_id, $motivoTexto);
            } catch (Throwable $e) {
                logError('No se pudo registrar la cancelacion en comportamiento/chat: ' . $e->getMessage(), __FILE__, __LINE__);
            }

            $this->conn->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            logError('Error cancelando servicio: ' . $e->getMessage(), __FILE__, __LINE__);
            return false;
        }
    }

    private function actualizarPuntos($servicio_id) {
        $query = "SELECT cliente_id FROM servicios WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $servicio_id);
        $stmt->execute();
        $servicio = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($servicio) {
            $puntos = PUNTOS_POR_SERVICIO;
            $update = "UPDATE clientes SET puntos = puntos + :puntos WHERE id = :cliente_id";
            $stmt2 = $this->conn->prepare($update);
            $stmt2->bindParam(':puntos', $puntos);
            $stmt2->bindParam(':cliente_id', $servicio['cliente_id']);
            $stmt2->execute();
        }
    }
}
