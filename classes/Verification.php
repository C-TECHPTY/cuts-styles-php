<?php
// classes/Verification.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

class Verification {
    public $conn;
    private $upload_path;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
        $this->upload_path = UPLOAD_PATH . 'verificacion/';

        if (!file_exists($this->upload_path)) {
            mkdir($this->upload_path, 0755, true);
        }
    }

    public function subirDocumento($barbero_id, $tipo, $archivo) {
        $allowedMimeTypes = ['image/jpeg', 'image/png', 'application/pdf'];
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf'];

        if (!isset($archivo['tmp_name'], $archivo['size']) || !is_uploaded_file($archivo['tmp_name'])) {
            return ['success' => false, 'message' => 'Archivo invalido'];
        }

        if ($archivo['size'] > MAX_FILE_SIZE) {
            return ['success' => false, 'message' => 'Archivo demasiado grande'];
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($archivo['tmp_name']);
        $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));

        if (!in_array($mimeType, $allowedMimeTypes, true) || !in_array($extension, $allowedExtensions, true)) {
            return ['success' => false, 'message' => 'Tipo de archivo no permitido'];
        }

        $filename = $barbero_id . '_' . preg_replace('/[^a-z_]/i', '', $tipo) . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
        $filepath = $this->upload_path . $filename;

        if (!move_uploaded_file($archivo['tmp_name'], $filepath)) {
            return ['success' => false, 'message' => 'Error al mover el archivo'];
        }

        $query = "INSERT INTO documentos_verificacion (barbero_id, tipo, ruta_archivo)
                  VALUES (:barbero_id, :tipo, :ruta)";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':barbero_id', $barbero_id);
        $stmt->bindParam(':tipo', $tipo);
        $stmt->bindParam(':ruta', $filename);

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Documento subido exitosamente'];
        }

        if (file_exists($filepath)) {
            unlink($filepath);
        }

        return ['success' => false, 'message' => 'Error al registrar el archivo'];
    }

    public function verificarBarbero($barbero_id, $aprobado, $comentario = '') {
        $estado = $aprobado ? 'verificado' : 'rechazado';
        $query = "UPDATE barberos
                  SET verificacion_status = :estado,
                      comentario_verificacion = :comentario
                  WHERE id = :barbero_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':estado', $estado);
        $stmt->bindParam(':comentario', $comentario);
        $stmt->bindParam(':barbero_id', $barbero_id);

        if ($stmt->execute() && $stmt->rowCount() > 0) {
            return ['success' => true];
        }
        return ['success' => false];
    }
}
