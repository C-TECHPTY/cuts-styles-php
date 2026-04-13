<?php

require_once 'config/config.php';
require_once 'classes/User.php';
require_once 'classes/SubscriptionPaymentManager.php';

requireRole('barbero');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('barbero_suscripcion.php');
}

try {
    verificarCSRFToken($_POST['csrf_token'] ?? null);
} catch (Exception $e) {
    setFlash('danger', 'Sesion invalida. Intenta nuevamente.');
    redirect('barbero_suscripcion.php');
}

$user = new User();
$stmt = $user->conn->prepare("SELECT id FROM barberos WHERE user_id = :user_id LIMIT 1");
$stmt->bindValue(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
$stmt->execute();
$barbero = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$barberoId = (int) ($barbero['id'] ?? 0);

if ($barberoId <= 0) {
    setFlash('danger', 'No se encontro el perfil del barbero.');
    redirect('barbero.php');
}

$paymentManager = new SubscriptionPaymentManager($user->conn);
$result = $paymentManager->createPaymentRequest($barberoId, $_POST, $_FILES['receipt_file'] ?? null);
setFlash($result['success'] ? 'success' : 'danger', $result['message']);
redirect('barbero_suscripcion.php');
