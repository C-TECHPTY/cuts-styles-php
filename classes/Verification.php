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
        
        if(!file_exists($this->upload_path)) {
            mkdir($this->upload_path, 0777, true);
        }
    }
    
    public function subirDocumento($barbero_id, $tipo, $archivo) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
        $max_size = MAX_FILE_SIZE;
        
        if(!in_array($archivo['type'], $allowed_types)) {
            return ['success' => false, 'message' => 'Tipo de archivo no permitido'];
        }
        
        if($archivo['size'] > $max_size) {
            return ['success' => false, 'message' => 'Archivo demasiado grande'];
        }
        
        $extension = pathinfo($archivo['name'], PATHINFO_EXTENSION);
        $filename = $barbero_id . '_' . $tipo . '_' . time() . '.' . $extension;
        $filepath = $this->upload_path . $filename;
        
        if(move_uploaded_file($archivo['tmp_name'], $filepath)) {
            $query = "INSERT INTO documentos_verificacion (barbero_id, tipo, ruta_archivo)
                      VALUES (:barbero_id, :tipo, :ruta)";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":barbero_id", $barbero_id);
            $stmt->bindParam(":tipo", $tipo);
            $stmt->bindParam(":ruta", $filename);
            
            if($stmt->execute()) {
                return ['success' => true, 'message' => 'Documento subido exitosamente'];
            }
        }
        
        return ['success' => false, 'message' => 'Error al subir el archivo'];
    }
    
    public function verificarBarbero($barbero_id, $aprobado, $comentario = '') {
        $estado = $aprobado ? 'verificado' : 'rechazado';
        $query = "UPDATE barberos SET verificacion_status = :estado WHERE id = :barbero_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":estado", $estado);
        $stmt->bindParam(":barbero_id", $barbero_id);
        
        if($stmt->execute()) {
            return ['success' => true];
        }
        return ['success' => false];
    }
}
?>