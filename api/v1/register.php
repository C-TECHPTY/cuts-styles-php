<?php
// api/v1/register.php
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$input = json_decode(file_get_contents('php://input'), true);

$nombre = $input['nombre'] ?? '';
$email = $input['email'] ?? '';
$password = $input['password'] ?? '';
$telefono = $input['telefono'] ?? '';
$rol = $input['rol'] ?? 'cliente';

// Validaciones
if (empty($nombre) || empty($email) || empty($password)) {
    echo json_encode(['status' => 'error', 'message' => 'Nombre, email y contraseña son requeridos']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => 'Email inválido']);
    exit;
}

if (strlen($password) < 6) {
    echo json_encode(['status' => 'error', 'message' => 'La contraseña debe tener al menos 6 caracteres']);
    exit;
}

$db = new Database();
$conn = $db->getConnection();

// Verificar si el email ya existe
$check = $conn->prepare("SELECT id FROM users WHERE email = :email");
$check->bindParam(':email', $email);
$check->execute();

if ($check->rowCount() > 0) {
    echo json_encode(['status' => 'error', 'message' => 'El email ya está registrado']);
    exit;
}

// Hash de la contraseña
$hash = password_hash($password, PASSWORD_DEFAULT);

// Insertar usuario
$query = "INSERT INTO users (email, password_hash, nombre, telefono, rol) VALUES (:email, :hash, :nombre, :telefono, :rol)";
$stmt = $conn->prepare($query);
$stmt->bindParam(':email', $email);
$stmt->bindParam(':hash', $hash);
$stmt->bindParam(':nombre', $nombre);
$stmt->bindParam(':telefono', $telefono);
$stmt->bindParam(':rol', $rol);

if ($stmt->execute()) {
    $userId = $conn->lastInsertId();
    
    // Crear perfil según rol
    if ($rol === 'cliente') {
        $perfil = $conn->prepare("INSERT INTO clientes (user_id) VALUES (:user_id)");
        $perfil->bindParam(':user_id', $userId);
        $perfil->execute();
    } elseif ($rol === 'barbero') {
        $perfil = $conn->prepare("INSERT INTO barberos (user_id) VALUES (:user_id)");
        $perfil->bindParam(':user_id', $userId);
        $perfil->execute();
    }
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Usuario registrado exitosamente',
        'user_id' => $userId
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Error al registrar usuario']);
}
?>