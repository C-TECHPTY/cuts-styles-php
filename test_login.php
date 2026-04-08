<?php
// test_login.php
require_once 'config/config.php';
require_once 'classes/User.php';

// Probar login directamente
$email = 'admin@cutsstyles.com';
$password = 'Admin123';

echo "<h2>Probando login...</h2>";
echo "Email: " . $email . "<br>";
echo "Password ingresada: " . $password . "<br><br>";

$user = new User();

// Buscar usuario
$query = "SELECT * FROM users WHERE email = :email";
$stmt = $user->conn->prepare($query);
$stmt->bindParam(":email", $email);
$stmt->execute();

if($stmt->rowCount() > 0) {
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "✅ Usuario encontrado en BD<br>";
    echo "ID: " . $row['id'] . "<br>";
    echo "Email: " . $row['email'] . "<br>";
    echo "Rol: " . $row['rol'] . "<br>";
    echo "Hash en BD: " . $row['password_hash'] . "<br><br>";
    
    // Verificar contraseña
    if(password_verify($password, $row['password_hash'])) {
        echo "✅ CONTRASEÑA CORRECTA!<br>";
        echo "Puedes iniciar sesión correctamente.";
    } else {
        echo "❌ CONTRASEÑA INCORRECTA<br>";
        echo "Vamos a actualizar la contraseña...<br>";
        
        // Actualizar contraseña
        $new_hash = password_hash($password, PASSWORD_DEFAULT);
        $update = "UPDATE users SET password_hash = :hash WHERE id = :id";
        $update_stmt = $user->conn->prepare($update);
        $update_stmt->bindParam(":hash", $new_hash);
        $update_stmt->bindParam(":id", $row['id']);
        
        if($update_stmt->execute()) {
            echo "✅ Contraseña actualizada! Nuevo hash: " . $new_hash . "<br>";
            echo "Ahora intenta iniciar sesión nuevamente.";
        }
    }
} else {
    echo "❌ Usuario no encontrado. Vamos a crearlo...<br>";
    
    // Crear usuario
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $insert = "INSERT INTO users (email, password_hash, nombre, rol, is_active, created_at) 
               VALUES (:email, :hash, 'Administrador', 'admin', 1, NOW())";
    $insert_stmt = $user->conn->prepare($insert);
    $insert_stmt->bindParam(":email", $email);
    $insert_stmt->bindParam(":hash", $hash);
    
    if($insert_stmt->execute()) {
        echo "✅ Usuario creado exitosamente!<br>";
        echo "Hash guardado: " . $hash . "<br>";
        echo "Ahora intenta iniciar sesión.";
    } else {
        echo "❌ Error al crear usuario: " . print_r($insert_stmt->errorInfo(), true);
    }
}
?>