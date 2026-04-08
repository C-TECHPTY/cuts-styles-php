<?php
// crear_admin_final.php
$host = 'localhost';
$dbname = 'cuts_styles_db';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Generar hash de la contraseña Admin123
    $password = 'Admin123';
    $hash = password_hash($password, PASSWORD_DEFAULT);
    
    echo "Hash generado: " . $hash . "<br><br>";
    
    // Verificar si ya existe
    $check = $pdo->prepare("SELECT * FROM users WHERE email = 'admin@cutsstyles.com'");
    $check->execute();
    
    if($check->rowCount() > 0) {
        // Actualizar
        $update = $pdo->prepare("UPDATE users SET password_hash = ?, nombre = ? WHERE email = 'admin@cutsstyles.com'");
        $update->execute([$hash, 'Administrador']);
        echo "✅ Usuario ADMIN actualizado correctamente!<br>";
    } else {
        // Crear nuevo
        $insert = $pdo->prepare("INSERT INTO users (email, password_hash, nombre, rol, is_active) VALUES (?, ?, ?, 'admin', 1)");
        $insert->execute(['admin@cutsstyles.com', $hash, 'Administrador']);
        echo "✅ Usuario ADMIN creado correctamente!<br>";
    }
    
    // Verificar que funciona
    $test = $pdo->prepare("SELECT * FROM users WHERE email = 'admin@cutsstyles.com'");
    $test->execute();
    $admin = $test->fetch(PDO::FETCH_ASSOC);
    
    echo "<br>";
    echo "<strong>Credenciales:</strong><br>";
    echo "📧 Email: admin@cutsstyles.com<br>";
    echo "🔑 Contraseña: Admin123<br>";
    echo "<br>";
    
    if(password_verify('Admin123', $admin['password_hash'])) {
        echo "✅ <span style='color:green'>VERIFICACIÓN EXITOSA! El login funcionará correctamente.</span><br>";
    } else {
        echo "❌ <span style='color:red'>ERROR: La verificación falló</span><br>";
    }
    
    echo "<br>";
    echo "<a href='login.php' style='background:#e94560;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;'>Ir al Login</a>";
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>