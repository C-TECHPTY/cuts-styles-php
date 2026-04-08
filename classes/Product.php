<?php
// classes/Product.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

class Product {
    public $conn;
    private $table_name = "productos";
    private $images_table = "producto_imagenes";
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }
    
    // Obtener todos los productos activos
    public function getAll($categoria = 'todos', $limite = null) {
        if($categoria == 'todos') {
            $query = "SELECT * FROM " . $this->table_name . " WHERE estado = 'activo' ORDER BY destacado DESC, created_at DESC";
        } else {
            $query = "SELECT * FROM " . $this->table_name . " WHERE categoria = :categoria AND estado = 'activo' ORDER BY destacado DESC";
        }
        
        if($limite) {
            $query .= " LIMIT " . intval($limite);
        }
        
        $stmt = $this->conn->prepare($query);
        if($categoria != 'todos') {
            $stmt->bindParam(":categoria", $categoria);
        }
        $stmt->execute();
        $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Cargar imágenes para cada producto
        foreach($productos as &$producto) {
            $producto['imagenes'] = $this->getImagenes($producto['id']);
            $producto['imagen_principal'] = $this->getImagenPrincipal($producto['id']);
        }
        
        return $productos;
    }
    
    // Obtener productos destacados
    public function getDestacados($limite = 6) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE destacado = 1 AND stock > 0 AND estado = 'activo' ORDER BY created_at DESC LIMIT " . intval($limite);
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach($productos as &$producto) {
            $producto['imagen_principal'] = $this->getImagenPrincipal($producto['id']);
        }
        
        return $productos;
    }
    
    // Obtener productos en oferta
    public function getEnOferta($limite = 6) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE en_oferta = 1 AND stock > 0 AND estado = 'activo' ORDER BY created_at DESC LIMIT " . intval($limite);
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach($productos as &$producto) {
            $producto['imagen_principal'] = $this->getImagenPrincipal($producto['id']);
        }
        
        return $productos;
    }
    
    // Obtener producto por ID con todas sus imágenes
    public function getById($id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->execute();
        $producto = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($producto) {
            $producto['imagenes'] = $this->getImagenes($id);
            $producto['imagen_principal'] = $this->getImagenPrincipal($id);
        }
        
        return $producto;
    }
    
    // Obtener todas las imágenes de un producto
    public function getImagenes($producto_id) {
        $query = "SELECT * FROM " . $this->images_table . " WHERE producto_id = :producto_id ORDER BY orden ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":producto_id", $producto_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Obtener imagen principal
    public function getImagenPrincipal($producto_id) {
        $query = "SELECT imagen_url FROM " . $this->images_table . " WHERE producto_id = :producto_id AND es_principal = 1 LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":producto_id", $producto_id);
        $stmt->execute();
        $img = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($img) {
            return $img['imagen_url'];
        }
        
        // Si no hay imagen principal, obtener la primera
        $query2 = "SELECT imagen_url FROM " . $this->images_table . " WHERE producto_id = :producto_id ORDER BY orden ASC LIMIT 1";
        $stmt2 = $this->conn->prepare($query2);
        $stmt2->bindParam(":producto_id", $producto_id);
        $stmt2->execute();
        $img2 = $stmt2->fetch(PDO::FETCH_ASSOC);
        
        return $img2 ? $img2['imagen_url'] : 'default-product.png';
    }
    
    // Guardar producto (insertar o actualizar)
    public function guardar($data, $archivos = null) {
        $id = $data['id'] ?? 0;
        
        if($id > 0) {
            // Actualizar
            $query = "UPDATE " . $this->table_name . " 
                      SET nombre = :nombre, descripcion = :descripcion, precio = :precio, 
                          descuento = :descuento, en_oferta = :en_oferta, stock = :stock, 
                          categoria = :categoria, destacado = :destacado, estado = :estado
                      WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":id", $id);
        } else {
            // Insertar
            $query = "INSERT INTO " . $this->table_name . " 
                      (nombre, descripcion, precio, descuento, en_oferta, stock, categoria, destacado, estado) 
                      VALUES (:nombre, :descripcion, :precio, :descuento, :en_oferta, :stock, :categoria, :destacado, :estado)";
            $stmt = $this->conn->prepare($query);
        }
        
        $stmt->bindParam(":nombre", $data['nombre']);
        $stmt->bindParam(":descripcion", $data['descripcion']);
        $stmt->bindParam(":precio", $data['precio']);
        $stmt->bindParam(":descuento", $data['descuento']);
        $stmt->bindParam(":en_oferta", $data['en_oferta']);
        $stmt->bindParam(":stock", $data['stock']);
        $stmt->bindParam(":categoria", $data['categoria']);
        $stmt->bindParam(":destacado", $data['destacado']);
        $stmt->bindParam(":estado", $data['estado']);
        
        if($stmt->execute()) {
            if($id == 0) {
                $id = $this->conn->lastInsertId();
            }
            
            // Subir imágenes
            if($archivos && isset($archivos['imagenes'])) {
                $this->subirImagenes($id, $archivos['imagenes']);
            }
            
            return ['success' => true, 'id' => $id];
        }
        
        return ['success' => false, 'message' => 'Error al guardar producto'];
    }
    
    // Subir múltiples imágenes
    public function subirImagenes($producto_id, $archivos) {
        $upload_path = UPLOAD_PATH . 'productos/';
        if(!file_exists($upload_path)) {
            mkdir($upload_path, 0777, true);
        }
        
        $orden = $this->getMaxOrden($producto_id);
        
        foreach($archivos['tmp_name'] as $key => $tmp_name) {
            if($archivos['error'][$key] == 0) {
                $extension = pathinfo($archivos['name'][$key], PATHINFO_EXTENSION);
                $filename = 'producto_' . $producto_id . '_' . time() . '_' . $key . '.' . $extension;
                $filepath = $upload_path . $filename;
                
                if(move_uploaded_file($tmp_name, $filepath)) {
                    $es_principal = ($orden == 0) ? 1 : 0;
                    $query = "INSERT INTO " . $this->images_table . " (producto_id, imagen_url, orden, es_principal) 
                              VALUES (:producto_id, :imagen_url, :orden, :es_principal)";
                    $stmt = $this->conn->prepare($query);
                    $stmt->bindParam(":producto_id", $producto_id);
                    $stmt->bindParam(":imagen_url", $filename);
                    $stmt->bindParam(":orden", $orden);
                    $stmt->bindParam(":es_principal", $es_principal);
                    $stmt->execute();
                    $orden++;
                }
            }
        }
        
        return true;
    }
    
    // Eliminar imagen
    public function eliminarImagen($imagen_id) {
        $query = "SELECT imagen_url FROM " . $this->images_table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $imagen_id);
        $stmt->execute();
        $imagen = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($imagen) {
            $filepath = UPLOAD_PATH . 'productos/' . $imagen['imagen_url'];
            if(file_exists($filepath)) {
                unlink($filepath);
            }
            
            $delete = "DELETE FROM " . $this->images_table . " WHERE id = :id";
            $stmt2 = $this->conn->prepare($delete);
            $stmt2->bindParam(":id", $imagen_id);
            return $stmt2->execute();
        }
        
        return false;
    }
    
    // Obtener máximo orden de imágenes
    private function getMaxOrden($producto_id) {
        $query = "SELECT COALESCE(MAX(orden), 0) + 1 as max_orden FROM " . $this->images_table . " WHERE producto_id = :producto_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":producto_id", $producto_id);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['max_orden'];
    }
    
    // Eliminar producto
    public function eliminar($id) {
        // Eliminar imágenes físicas
        $imagenes = $this->getImagenes($id);
        foreach($imagenes as $img) {
            $filepath = UPLOAD_PATH . 'productos/' . $img['imagen_url'];
            if(file_exists($filepath)) {
                unlink($filepath);
            }
        }
        
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        return $stmt->execute();
    }
    
    // Obtener categorías
    public function getCategorias() {
        $query = "SELECT DISTINCT categoria FROM " . $this->table_name . " WHERE categoria IS NOT NULL AND estado = 'activo'";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_column($results, 'categoria');
    }
    
    // Actualizar stock
    public function updateStock($id, $cantidad) {
        $query = "UPDATE " . $this->table_name . " SET stock = stock - :cantidad WHERE id = :id AND stock >= :cantidad";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->bindParam(":cantidad", $cantidad);
        return $stmt->execute();
    }
    
    // Obtener precio con descuento
    public function getPrecioFinal($producto) {
        if($producto['en_oferta'] && $producto['descuento'] > 0) {
            return $producto['precio'] * (1 - $producto['descuento'] / 100);
        }
        return $producto['precio'];
    }
}
?>