<?php
require_once 'config/config.php';
require_once 'classes/ServiceChat.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_rol'], ['cliente', 'barbero'], true)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autenticado.']);
    exit;
}

$serviceId = (int) ($_GET['servicio_id'] ?? 0);
$afterId = (int) ($_GET['after_id'] ?? 0);
$markRead = isset($_GET['mark_read']) ? (int) $_GET['mark_read'] === 1 : true;

if ($serviceId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Servicio invalido.']);
    exit;
}

$chatService = new ServiceChat();
$role = $_SESSION['user_rol'];
$userId = (int) $_SESSION['user_id'];
$context = $chatService->getChatContextForUser($serviceId, $userId, $role);

if (!$context) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sin acceso al chat.']);
    exit;
}

if ($markRead) {
    $chatService->markMessagesRead($serviceId, $userId, $role);
} else {
    $chatService->markMessagesDelivered($serviceId, $userId, $role);
}

$messages = $chatService->getMessagesAfterIdForUser($serviceId, $userId, $role, $afterId);
$payload = [];
foreach ($messages as $message) {
    $payload[] = $chatService->serializeMessage($message, $userId);
}

$allMessages = $chatService->getMessagesForService($serviceId);
$statuses = [];
foreach ($allMessages as $message) {
    if ((int) ($message['sender_user_id'] ?? 0) === $userId) {
        $summary = $chatService->getMessageStatusSummary((int) $message['id']);
        if ($summary) {
            $statuses[] = $summary;
        }
    }
}

echo json_encode([
    'success' => true,
    'messages' => $payload,
    'message_statuses' => $statuses,
    'chat_status' => $context['chat']['status'] ?? 'closed',
    'can_write' => $context['can_write'],
    'write_disabled_reason' => $context['write_disabled_reason'],
]);
?>
