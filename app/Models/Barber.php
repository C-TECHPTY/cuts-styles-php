<?php
namespace App\Models;

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';

class Barber
{
    private $conn;

    public function __construct()
    {
        $database = new \Database();
        $this->conn = $database->getConnection();
    }

    public function getBarbersNearby($lat, $lng, $radio = 10)
    {
        $query = "SELECT u.id, u.nombre, u.email, u.telefono, b.*,
                  (6371 * acos(cos(radians(:lat)) * cos(radians(b.latitud))
                  * cos(radians(b.longitud) - radians(:lng))
                  + sin(radians(:lat)) * sin(radians(b.latitud)))) AS distance
                  FROM barberos b
                  JOIN users u ON b.user_id = u.id
                  WHERE b.verificacion_status = 'verificado' AND b.is_available = 1
                  HAVING distance < :radio
                  ORDER BY distance ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':lat', $lat);
        $stmt->bindParam(':lng', $lng);
        $stmt->bindParam(':radio', $radio);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getBarberById($id)
    {
        $query = "SELECT u.id, u.nombre, u.email, u.telefono, b.*
                  FROM barberos b
                  JOIN users u ON b.user_id = u.id
                  WHERE b.id = :id
                  LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function updateAvailability($barberoId, $isAvailable)
    {
        $query = "UPDATE barberos SET is_available = :available WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':available', $isAvailable);
        $stmt->bindParam(':id', $barberoId);
        return $stmt->execute() && $stmt->rowCount() > 0;
    }
}
