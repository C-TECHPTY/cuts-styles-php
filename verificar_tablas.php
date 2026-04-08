<?php
// verificar_tablas.php
require_once 'config/config.php';
require_once 'classes/Product.php';

$product = new Product();

echo "<h2>Verificando tablas</h2>";

$tablas = ['pedidos', 'pedido_detalles', 'productos', 'clientes', 'users'];

foreach($tablas as $tabla) {
    try {
        $result = $product->conn->query("SHOW TABLES LIKE '$tabla'");
        if($result->rowCount() > 0) {
            echo "✅ Tabla '$tabla' existe<br>";
        } else {
            echo "❌ Tabla '$tabla' NO existe<br>";
        }
    } catch(PDOException $e) {
        echo "❌ Error verificando '$tabla': " . $e->getMessage() . "<br>";
    }
}
?>