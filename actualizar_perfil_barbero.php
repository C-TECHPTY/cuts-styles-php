<?php
// actualizar_perfil_barbero.php
require_once 'config/config.php';
require_once 'classes/User.php';
require_once 'classes/ZoneManager.php';

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
$zona = trim((string) ($_POST['zona_cobertura'] ?? ''));
$sectores = trim((string) ($_POST['sectores_cobertura'] ?? ''));

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

$barberoLookup = $user->conn->prepare("SELECT id FROM barberos WHERE user_id = :user_id LIMIT 1");
$barberoLookup->bindParam(':user_id', $_SESSION['user_id']);
$barberoLookup->execute();
$barberoId = (int) ($barberoLookup->fetchColumn() ?: 0);

if ($barberoId > 0) {
    $zoneManager = new ZoneManager($user->conn);
    if ($zoneManager->isEnabled()) {
        $zoneManager->saveBarberCoverage($barberoId, $zona, $sectores);
    }
}

$_SESSION['user_nombre'] = $nombre;
setFlash('success', 'Perfil actualizado exitosamente');
redirect('barbero.php');
