<?php
// actualizar_perfil.php
require_once 'config/config.php';
require_once 'classes/User.php';

if(!isset($_SESSION['user_id'])) {
    redirect('login.php');
}

$user = new User();
$user->id = $_SESSION['user_id'];

$query = "UPDATE users SET nombre = :nombre, telefono = :telefono, direccion = :direccion WHERE id = :id";
$stmt = $user->conn->prepare($query);
$stmt->bindParam(":nombre", $_POST['nombre']);
$stmt->bindParam(":telefono", $_POST['telefono']);
$stmt->bindParam(":direccion", $_POST['direccion']);
$stmt->bindParam(":id", $_SESSION['user_id']);

if($stmt->execute()) {
    $_SESSION['user_nombre'] = $_POST['nombre'];
    setFlash('success', 'Perfil actualizado exitosamente');
} else {
    setFlash('danger', 'Error al actualizar el perfil');
}

// Redirigir según rol
if($_SESSION['user_rol'] == 'cliente') {
    redirect('cliente.php');
} else {
    redirect('barbero.php');
}
?>