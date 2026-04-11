<?php
// actualizar_perfil_barbero.php
require_once 'config/config.php';
require_once 'classes/User.php';

requireRole('barbero');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('barbero.php');
}

try {
    verificarCSRFToken($_POST['csrf_token'] ?? null);
} catch (Exception $e) {
    setFlash('danger', 'Sesion invalida. Intenta nuevamente.');
    redirect('barbero.php');
}

$user = new User();

$nombre = trim((string) ($_POST['nombre'] ?? ''));
$telefono = trim((string) ($_POST['telefono'] ?? ''));
$especialidad = trim((string) ($_POST['especialidad'] ?? ''));
$experiencia = (int) ($_POST['experiencia'] ?? 0);
$descripcion = trim((string) ($_POST['descripcion'] ?? ''));
$tarifaHora = $_POST['tarifa_hora'] !== '' ? (float) $_POST['tarifa_hora'] : null;

$query = "UPDATE users SET nombre = :nombre, telefono = :telefono WHERE id = :id";
$stmt = $user->conn->prepare($query);
$stmt->bindParam(':nombre', $nombre);
$stmt->bindParam(':telefono', $telefono);
$stmt->bindParam(':id', $_SESSION['user_id']);
$stmt->execute();

$query2 = "UPDATE barberos
           SET especialidad = :especialidad, experiencia = :experiencia,
               descripcion = :descripcion, tarifa_hora = :tarifa_hora
           WHERE user_id = :user_id";
$stmt2 = $user->conn->prepare($query2);
$stmt2->bindParam(':especialidad', $especialidad);
$stmt2->bindParam(':experiencia', $experiencia);
$stmt2->bindParam(':descripcion', $descripcion);
$stmt2->bindParam(':tarifa_hora', $tarifaHora);
$stmt2->bindParam(':user_id', $_SESSION['user_id']);
$stmt2->execute();

$_SESSION['user_nombre'] = $nombre;
setFlash('success', 'Perfil actualizado exitosamente');
redirect('barbero.php');
