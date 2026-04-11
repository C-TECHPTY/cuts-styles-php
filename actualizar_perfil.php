<?php
// actualizar_perfil.php
require_once 'config/config.php';
require_once 'classes/User.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect($_SESSION['user_rol'] === 'cliente' ? 'cliente.php' : 'barbero.php');
}

try {
    verificarCSRFToken($_POST['csrf_token'] ?? null);
} catch (Exception $e) {
    setFlash('danger', 'Sesion invalida. Intenta nuevamente.');
    redirect($_SESSION['user_rol'] === 'cliente' ? 'cliente.php' : 'barbero.php');
}

$user = new User();
$query = "UPDATE users SET nombre = :nombre, telefono = :telefono, direccion = :direccion WHERE id = :id";
$stmt = $user->conn->prepare($query);

$nombre = trim((string) ($_POST['nombre'] ?? ''));
$telefono = trim((string) ($_POST['telefono'] ?? ''));
$direccion = trim((string) ($_POST['direccion'] ?? ''));
$userId = $_SESSION['user_id'];

$stmt->bindParam(':nombre', $nombre);
$stmt->bindParam(':telefono', $telefono);
$stmt->bindParam(':direccion', $direccion);
$stmt->bindParam(':id', $userId);

if ($stmt->execute()) {
    $_SESSION['user_nombre'] = $nombre;
    setFlash('success', 'Perfil actualizado exitosamente');
} else {
    setFlash('danger', 'Error al actualizar el perfil');
}

redirect($_SESSION['user_rol'] === 'cliente' ? 'cliente.php' : 'barbero.php');
