<?php
require_once 'config/config.php';
require_once 'classes/ServiceChat.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metodo no permitido.']);
    exit;
}

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_rol'], ['cliente', 'barbero'], true)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autenticado.']);
    exit;
}

try {
    verificarCSRFToken($_POST['csrf_token'] ?? null);
} catch (Exception $e) {
    http_response_code(419);
    echo json_encode(['success' => false, 'message' => 'Sesion invalida.']);
    exit;
}

$serviceId = (int) ($_POST['servicio_id'] ?? 0);
if ($serviceId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Servicio invalido.']);
    exit;
}

$chatService = new ServiceChat();
$role = $_SESSION['user_rol'];
$userId = (int) $_SESSION['user_id'];
$result = ['success' => false, 'message' => 'Accion no valida.'];

if (isset($_POST['send_quick_reply'])) {
    $result = $chatService->sendQuickReply($serviceId, $userId, $role, (string) ($_POST['preset_key'] ?? ''));
} elseif (isset($_POST['send_free_text'])) {
    $result = $chatService->sendFreeText($serviceId, $userId, $role, (string) ($_POST['message_text'] ?? ''));
}

if (!$result['success']) {
    http_response_code(422);
    echo json_encode($result);
    exit;
}

$messages = $chatService->getMessagesAfterIdForUser($serviceId, $userId, $role, max(0, ((int) ($result['message_id'] ?? 1)) - 1));
$serialized = [];
foreach ($messages as $message) {
    $serialized[] = $chatService->serializeMessage($message, $userId);
}

echo json_encode([
    'success' => true,
    'message' => $result['message'],
    'messages' => $serialized,
    'message_status' => $result['message_status'] ?? null,
]);
?>
