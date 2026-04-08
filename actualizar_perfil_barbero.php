<?php
// actualizar_perfil_barbero.php
require_once 'config/config.php';
require_once 'classes/User.php';

if(!isset($_SESSION['user_id']) || $_SESSION['user_rol'] != 'barbero') {
    redirect('login.php');
}

$user = new User();

// Actualizar datos básicos
$query = "UPDATE users SET nombre = :nombre, telefono = :telefono WHERE id = :id";
$stmt = $user->conn->prepare($query);
$stmt->bindParam(":nombre", $_POST['nombre']);
$stmt->bindParam(":telefono", $_POST['telefono']);
$stmt->bindParam(":id", $_SESSION['user_id']);
$stmt->execute();

// Actualizar datos del barbero
$query2 = "UPDATE barberos SET especialidad = :especialidad, experiencia = :experiencia, 
           descripcion = :descripcion, tarifa_hora = :tarifa_hora WHERE user_id = :user_id";
$stmt2 = $user->conn->prepare($query2);
$stmt2->bindParam(":especialidad", $_POST['especialidad']);
$stmt2->bindParam(":experiencia", $_POST['experiencia']);
$stmt2->bindParam(":descripcion", $_POST['descripcion']);
$stmt2->bindParam(":tarifa_hora", $_POST['tarifa_hora']);
$stmt2->bindParam(":user_id", $_SESSION['user_id']);
$stmt2->execute();

$_SESSION['user_nombre'] = $_POST['nombre'];
setFlash('success', 'Perfil actualizado exitosamente');
redirect('barbero.php');
?>