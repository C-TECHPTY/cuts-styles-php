<?php
// crear_admin.php
require_once 'config/config.php';
require_once 'classes/User.php';

$user = new User();

// Primero, verificar la conexión
if(!$user->conn) {
    die("Error de conexión a la base de datos");
}

// Verificar si ya existe
$check = "SELECT * FROM users WHERE email = 'admin@cutsstyles.com'";
$result = $user->conn->query($check);

if($result->rowCount() > 0) {
    // Si existe, actualizar la contraseña
    $new_password = 'Admin123';
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    $update = "UPDATE users SET password_hash = :password WHERE email = 'admin@cutsstyles.com'";
    $stmt = $user->conn->prepare($update);
    $stmt->bindParam(":password", $hashed_password);
    
    if($stmt->execute()) {
        echo "✅ Contraseña actualizada correctamente!<br>";
        echo "📧 Email: admin@cutsstyles.com<br>";
        echo "🔑 Nueva Contraseña: Admin123<br>";
        echo "<br>🔐 Hash generado: " . $hashed_password;
    } else {
        echo "❌ Error al actualizar la contraseña";
    }
} else {
    // Crear nuevo admin
    $password = 'Admin123';
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    $query = "INSERT INTO users (email, password_hash, nombre, rol, is_active, created_at) 
              VALUES (:email, :password, :nombre, :rol, 1, NOW())";
    
    $stmt = $user->conn->prepare($query);
    $stmt->bindParam(":email", $email);
    $stmt->bindParam(":password", $hashed_password);
    $stmt->bindParam(":nombre", $nombre);
    $stmt->bindParam(":rol", $rol);
    
    $email = 'admin@cutsstyles.com';
    $nombre = 'Administrador';
    $rol = 'admin';
    
    if($stmt->execute()) {
        echo "✅ Usuario administrador creado exitosamente!<br>";
        echo "📧 Email: admin@cutsstyles.com<br>";
        echo "🔑 Contraseña: Admin123<br>";
        echo "<br>🔐 Hash generado: " . $hashed_password;
    } else {
        echo "❌ Error al crear el usuario: " . print_r($stmt->errorInfo(), true);
    }
}

// Mostrar todos los usuarios para verificar
echo "<hr><h3>Usuarios en la base de datos:</h3>";
$all_users = "SELECT id, email, nombre, rol FROM users";
$users_result = $user->conn->query($all_users);
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Email</th><th>Nombre</th><th>Rol</th></tr>";
while($row = $users_result->fetch(PDO::FETCH_ASSOC)) {
    echo "<tr>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . $row['email'] . "</td>";
    echo "<td>" . $row['nombre'] . "</td>";
    echo "<td>" . $row['rol'] . "</td>";
    echo "</tr>";
}
echo "</table>";
?>