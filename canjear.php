<?php
// canjear.php
require_once 'config/config.php';
require_once 'classes/Rewards.php';

if(!isset($_SESSION['user_id']) || $_SESSION['user_rol'] != 'cliente') {
    redirect('login.php');
}

// Obtener ID del cliente
$database = new Database();
$conn = $database->getConnection();
$query = "SELECT id FROM clientes WHERE user_id = :user_id";
$stmt = $conn->prepare($query);
$stmt->bindParam(":user_id", $_SESSION['user_id']);
$stmt->execute();
$cliente = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$cliente) {
    redirect('cliente.php');
}

$rewards = new Rewards();
$recompensa_id = $_GET['id'] ?? 0;

if($recompensa_id) {
    $resultado = $rewards->canjearRecompensa($cliente['id'], $recompensa_id);
    setFlash($resultado['success'] ? 'success' : 'danger', $resultado['message']);
}

redirect('cliente.php#puntos');
?>