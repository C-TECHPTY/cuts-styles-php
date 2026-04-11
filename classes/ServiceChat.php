<?php
// classes/ServiceChat.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

class ServiceChat {
    public $conn;

    private const MESSAGE_LIMIT_PER_MINUTE = 5;
    private const FREE_TEXT_MAX_LENGTH = 180;
    private const CHAT_ACTIVE_STATES = ['aceptado', 'en_proceso'];
    private const CHAT_CLOSED_STATES = ['completado', 'cancelado'];

    private array $quickReplies = [
        'cliente' => [
            'cliente_llegue' => 'Ya estoy en la ubicacion',
            'cliente_cambio_hora' => 'Necesito cambiar la hora',
            'cliente_referencia' => 'Tengo referencia del corte',
            'cliente_tiempo' => 'Cuanto tardas?',
        ],
        'barbero' => [
            'barbero_camino' => 'Voy en camino',
            'barbero_10min' => 'Llego en 10 minutos',
            'barbero_direccion' => 'No encuentro la direccion',
            'barbero_confirmo' => 'Confirmo la cita',
            'barbero_retraso' => 'Tengo un retraso',
        ],
    ];

    private array $blockedWords = [
        'idiota', 'imbecil', 'estupido', 'estupida', 'maldito', 'maldita', 'mierda',
        'hp', 'hpta', 'gonorrea', 'perra', 'perro',
    ];

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function getQuickReplies(string $role): array {
        return $this->quickReplies[$role] ?? [];
    }

    public function getServiceContext(int $serviceId): ?array {
        $query = "SELECT s.*,
                  c.user_id as cliente_user_id,
                  cu.nombre as cliente_nombre,
                  b.user_id as barbero_user_id,
                  bu.nombre as barbero_nombre
                  FROM servicios s
                  JOIN clientes c ON s.cliente_id = c.id
                  JOIN users cu ON c.user_id = cu.id
                  LEFT JOIN barberos b ON s.barbero_id = b.id
                  LEFT JOIN users bu ON b.user_id = bu.id
                  WHERE s.id = :service_id
                  LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':service_id', $serviceId);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function userCanAccessService(int $serviceId, int $userId, string $role): bool {
        $context = $this->getServiceContext($serviceId);
        if (!$context) {
            return false;
        }

        if ($role === 'cliente') {
            return (int) $context['cliente_user_id'] === $userId;
        }

        if ($role === 'barbero') {
            return (int) ($context['barbero_user_id'] ?? 0) === $userId;
        }

        return $role === 'admin';
    }

    public function getChatContextForUser(int $serviceId, int $userId, string $role): ?array {
        $context = $this->getServiceContext($serviceId);
        if (!$context) {
            return null;
        }

        if (!$this->userCanAccessService($serviceId, $userId, $role)) {
            return null;
        }

        $this->syncChatLifecycle($context);
        $chat = $this->getChatByService($serviceId);

        $flags = null;
        $isBlocked = false;
        if (!empty($context['cliente_id'])) {
            $flags = $this->getBehaviorFlags((int) $context['cliente_id']);
        }
        if (!empty($context['barbero_id']) && !empty($context['cliente_id'])) {
            $isBlocked = $this->isClientBlockedByBarber((int) $context['cliente_id'], (int) $context['barbero_id']);
        }

        $canWrite = false;
        $writeDisabledReason = '';

        if (!$chat) {
            $writeDisabledReason = 'El chat estara disponible cuando el barbero acepte el servicio.';
        } elseif ($chat['status'] !== 'open') {
            $writeDisabledReason = 'El chat ya esta cerrado.';
        } elseif (!in_array($context['estado'], self::CHAT_ACTIVE_STATES, true)) {
            $writeDisabledReason = 'El chat solo permite mensajes mientras el servicio esta activo.';
        } elseif ($role === 'cliente' && $isBlocked) {
            $writeDisabledReason = 'Este barbero ha bloqueado el chat para este cliente.';
        } elseif ($role === 'cliente' && $this->isClientRestricted($flags)) {
            $writeDisabledReason = 'Tu chat esta restringido temporalmente por comportamiento reciente.';
        } else {
            $canWrite = true;
        }

        return [
            'service' => $context,
            'chat' => $chat,
            'flags' => $flags,
            'client_blocked' => $isBlocked,
            'can_write' => $canWrite,
            'write_disabled_reason' => $writeDisabledReason,
        ];
    }

    public function getChatByService(int $serviceId): ?array {
        $query = "SELECT * FROM service_chats WHERE servicio_id = :service_id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':service_id', $serviceId);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getMessagesForService(int $serviceId): array {
        $chat = $this->getChatByService($serviceId);
        if (!$chat) {
            return [];
        }

        $query = "SELECT m.*, u.nombre as sender_name
                  FROM service_chat_messages m
                  JOIN users u ON m.sender_user_id = u.id
                  WHERE m.chat_id = :chat_id
                  ORDER BY m.created_at ASC, m.id ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':chat_id', $chat['id']);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function openChatForService(int $serviceId): bool {
        $context = $this->getServiceContext($serviceId);
        if (!$context || empty($context['barbero_id'])) {
            return false;
        }

        if (!in_array($context['estado'], self::CHAT_ACTIVE_STATES, true)) {
            return false;
        }

        $existing = $this->getChatByService($serviceId);
        if ($existing) {
            if ($existing['status'] !== 'open') {
                $query = "UPDATE service_chats
                          SET status = 'open', closed_at = NULL, closed_reason = NULL
                          WHERE id = :id";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':id', $existing['id']);
                $stmt->execute();
            }
            return true;
        }

        $query = "INSERT INTO service_chats
                  (servicio_id, cliente_id, barbero_id, status, opened_at)
                  VALUES (:servicio_id, :cliente_id, :barbero_id, 'open', NOW())";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':servicio_id', $serviceId);
        $stmt->bindParam(':cliente_id', $context['cliente_id']);
        $stmt->bindParam(':barbero_id', $context['barbero_id']);

        if (!$stmt->execute()) {
            return false;
        }

        $this->insertSystemMessage((int) $this->conn->lastInsertId(), 'Chat habilitado para este servicio.');
        return true;
    }

    public function closeChatForService(int $serviceId, string $reason = 'service_closed'): bool {
        $chat = $this->getChatByService($serviceId);
        if (!$chat || $chat['status'] === 'closed') {
            return true;
        }

        $query = "UPDATE service_chats
                  SET status = 'closed', closed_at = NOW(), closed_reason = :reason
                  WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':reason', $reason);
        $stmt->bindParam(':id', $chat['id']);
        $ok = $stmt->execute();
        if ($ok) {
            $this->insertSystemMessage((int) $chat['id'], 'El chat fue cerrado automaticamente.');
        }
        return $ok;
    }

    public function sendQuickReply(int $serviceId, int $userId, string $role, string $presetKey): array {
        $options = $this->getQuickReplies($role);
        if (!isset($options[$presetKey])) {
            return ['success' => false, 'message' => 'Respuesta rapida no valida.'];
        }

        return $this->sendMessage($serviceId, $userId, $role, $options[$presetKey], 'quick_reply', $presetKey);
    }

    public function sendFreeText(int $serviceId, int $userId, string $role, string $message): array {
        $context = $this->getChatContextForUser($serviceId, $userId, $role);
        if (!$context || !$context['chat']) {
            return ['success' => false, 'message' => 'No tienes acceso a este chat.'];
        }

        if ((int) $context['chat']['allow_free_text'] !== 1) {
            return ['success' => false, 'message' => 'El texto libre aun no esta habilitado en esta etapa.'];
        }

        return $this->sendMessage($serviceId, $userId, $role, $message, 'free_text', null);
    }

    private function sendMessage(int $serviceId, int $userId, string $role, string $message, string $type, ?string $presetKey): array {
        $context = $this->getChatContextForUser($serviceId, $userId, $role);
        if (!$context || !$context['chat']) {
            return ['success' => false, 'message' => 'No tienes acceso a este chat.'];
        }

        if (!$context['can_write']) {
            return ['success' => false, 'message' => $context['write_disabled_reason'] ?: 'No puedes escribir en este chat.'];
        }

        if (!$this->canSendMessage((int) $context['chat']['id'], $userId)) {
            return ['success' => false, 'message' => 'Espera un momento antes de enviar mas mensajes.'];
        }

        $cleanMessage = $this->sanitizeMessage($message, $type === 'free_text');
        if ($cleanMessage === null) {
            if ($role === 'cliente' && $type === 'free_text') {
                $this->registerBehaviorEvent((int) $context['service']['cliente_id'], $serviceId, 'spam_message', -5, 'Mensaje bloqueado por filtros');
            }
            return ['success' => false, 'message' => 'El mensaje no cumple las reglas del chat.'];
        }

        $query = "INSERT INTO service_chat_messages
                  (chat_id, sender_user_id, sender_role, message_type, preset_key, message_text, created_at)
                  VALUES (:chat_id, :sender_user_id, :sender_role, :message_type, :preset_key, :message_text, NOW())";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':chat_id', $context['chat']['id']);
        $stmt->bindParam(':sender_user_id', $userId);
        $stmt->bindParam(':sender_role', $role);
        $stmt->bindParam(':message_type', $type);
        $stmt->bindParam(':preset_key', $presetKey);
        $stmt->bindParam(':message_text', $cleanMessage);
        $stmt->execute();

        $update = "UPDATE service_chats SET last_message_at = NOW() WHERE id = :id";
        $stmt2 = $this->conn->prepare($update);
        $stmt2->bindParam(':id', $context['chat']['id']);
        $stmt2->execute();

        return ['success' => true, 'message' => 'Mensaje enviado.'];
    }

    public function reportAbuse(int $serviceId, int $userId, string $role, string $reason, string $details = ''): array {
        $context = $this->getChatContextForUser($serviceId, $userId, $role);
        if (!$context) {
            return ['success' => false, 'message' => 'No tienes acceso a este servicio.'];
        }

        $chat = $context['chat'];
        if (!$chat) {
            return ['success' => false, 'message' => 'No existe chat para este servicio.'];
        }

        $reason = trim($reason);
        if ($reason === '') {
            return ['success' => false, 'message' => 'Selecciona un motivo de reporte.'];
        }

        $reportedUserId = $role === 'cliente'
            ? (int) ($context['service']['barbero_user_id'] ?? 0)
            : (int) $context['service']['cliente_user_id'];

        if ($reportedUserId <= 0) {
            return ['success' => false, 'message' => 'No fue posible identificar al usuario reportado.'];
        }

        $query = "INSERT INTO service_chat_reports
                  (chat_id, servicio_id, reporter_user_id, reporter_role, reported_user_id, reason, details, created_at)
                  VALUES (:chat_id, :service_id, :reporter_user_id, :reporter_role, :reported_user_id, :reason, :details, NOW())";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':chat_id', $chat['id']);
        $stmt->bindParam(':service_id', $serviceId);
        $stmt->bindParam(':reporter_user_id', $userId);
        $stmt->bindParam(':reporter_role', $role);
        $stmt->bindParam(':reported_user_id', $reportedUserId);
        $stmt->bindParam(':reason', $reason);
        $stmt->bindParam(':details', $details);
        $stmt->execute();

        if ($role === 'barbero') {
            $this->registerBehaviorEvent((int) $context['service']['cliente_id'], $serviceId, 'abuse_report', -15, 'Reporte de barbero: ' . $reason);
        }

        $this->logIncident($serviceId, $chat['id'], $userId, 'abuse_report', $reason . ($details !== '' ? ' | ' . $details : ''));

        return ['success' => true, 'message' => 'Reporte registrado correctamente.'];
    }

    public function blockClient(int $serviceId, int $barberUserId, string $reason): array {
        $context = $this->getChatContextForUser($serviceId, $barberUserId, 'barbero');
        if (!$context) {
            return ['success' => false, 'message' => 'No tienes permiso para bloquear este cliente.'];
        }

        $barberoId = (int) ($context['service']['barbero_id'] ?? 0);
        $clienteId = (int) $context['service']['cliente_id'];
        if ($barberoId <= 0 || $clienteId <= 0) {
            return ['success' => false, 'message' => 'No fue posible completar el bloqueo.'];
        }

        $existing = $this->isClientBlockedByBarber($clienteId, $barberoId);
        if (!$existing) {
            $query = "INSERT INTO barber_client_blocks
                      (barbero_id, cliente_id, reason, active, created_at)
                      VALUES (:barbero_id, :cliente_id, :reason, 1, NOW())";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':barbero_id', $barberoId);
            $stmt->bindParam(':cliente_id', $clienteId);
            $stmt->bindParam(':reason', $reason);
            $stmt->execute();
        }

        $this->registerBehaviorEvent($clienteId, $serviceId, 'barber_block', -25, 'Cliente bloqueado por barbero');
        $this->applyRestrictionIfNeeded($clienteId);
        $chat = $context['chat'];
        if ($chat) {
            $this->logIncident($serviceId, (int) $chat['id'], $barberUserId, 'barber_block', $reason);
        }

        return ['success' => true, 'message' => 'Cliente bloqueado para este barbero.'];
    }

    public function isClientRestrictedByUserId(int $userId): bool {
        $query = "SELECT c.id
                  FROM clientes c
                  JOIN users u ON c.user_id = u.id
                  WHERE u.id = :user_id
                  LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        $clienteId = (int) $stmt->fetchColumn();
        if ($clienteId <= 0) {
            return false;
        }
        return $this->isClientRestricted($this->getBehaviorFlags($clienteId), 'service');
    }

    public function registerClientCancellation(int $serviceId, int $clienteId, string $details = ''): void {
        $this->registerBehaviorEvent($clienteId, $serviceId, 'service_cancelled', -10, $details !== '' ? $details : 'Cancelacion del cliente');
        $this->closeChatForService($serviceId, 'service_cancelled');
    }

    public function getBehaviorFlags(int $clienteId): ?array {
        $query = "SELECT * FROM client_behavior_flags WHERE cliente_id = :cliente_id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':cliente_id', $clienteId);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return $row;
        }

        $insert = "INSERT INTO client_behavior_flags (cliente_id, score, created_at, updated_at)
                   VALUES (:cliente_id, 100, NOW(), NOW())";
        $stmt2 = $this->conn->prepare($insert);
        $stmt2->bindParam(':cliente_id', $clienteId);
        $stmt2->execute();

        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function syncChatLifecycle(array $serviceContext): void {
        $serviceId = (int) $serviceContext['id'];
        if (in_array($serviceContext['estado'], self::CHAT_ACTIVE_STATES, true) && !empty($serviceContext['barbero_id'])) {
            $this->openChatForService($serviceId);
            return;
        }

        if (in_array($serviceContext['estado'], self::CHAT_CLOSED_STATES, true)) {
            $reason = $serviceContext['estado'] === 'completado' ? 'service_completed' : 'service_cancelled';
            $this->closeChatForService($serviceId, $reason);
        }
    }

    private function canSendMessage(int $chatId, int $userId): bool {
        $query = "SELECT COUNT(*) FROM service_chat_messages
                  WHERE chat_id = :chat_id
                  AND sender_user_id = :user_id
                  AND created_at >= (NOW() - INTERVAL 1 MINUTE)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':chat_id', $chatId);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        return (int) $stmt->fetchColumn() < self::MESSAGE_LIMIT_PER_MINUTE;
    }

    private function sanitizeMessage(string $message, bool $strict = false): ?string {
        $message = trim(preg_replace('/\s+/', ' ', strip_tags($message)));
        if ($message === '') {
            return null;
        }

        if (mb_strlen($message) > self::FREE_TEXT_MAX_LENGTH) {
            return null;
        }

        if (preg_match('/https?:\/\/|www\./i', $message)) {
            return null;
        }

        if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $message)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $message);
        if (strlen($digits) >= 7) {
            return null;
        }

        if ($strict) {
            $lower = mb_strtolower($message);
            foreach ($this->blockedWords as $word) {
                if (str_contains($lower, $word)) {
                    return null;
                }
            }
        }

        return $message;
    }

    private function insertSystemMessage(int $chatId, string $message): void {
        $query = "INSERT INTO service_chat_messages
                  (chat_id, sender_user_id, sender_role, message_type, preset_key, message_text, created_at)
                  VALUES (:chat_id, 0, 'system', 'system', NULL, :message_text, NOW())";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':chat_id', $chatId);
        $stmt->bindParam(':message_text', $message);
        $stmt->execute();
    }

    private function isClientBlockedByBarber(int $clienteId, int $barberoId): bool {
        $query = "SELECT id FROM barber_client_blocks
                  WHERE cliente_id = :cliente_id AND barbero_id = :barbero_id AND active = 1
                  LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':cliente_id', $clienteId);
        $stmt->bindParam(':barbero_id', $barberoId);
        $stmt->execute();
        return (bool) $stmt->fetchColumn();
    }

    private function registerBehaviorEvent(int $clienteId, int $serviceId, string $eventType, int $scoreDelta, string $details): void {
        $query = "INSERT INTO client_behavior_events
                  (cliente_id, servicio_id, event_type, score_delta, details, created_at)
                  VALUES (:cliente_id, :servicio_id, :event_type, :score_delta, :details, NOW())";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':cliente_id', $clienteId);
        $stmt->bindParam(':servicio_id', $serviceId);
        $stmt->bindParam(':event_type', $eventType);
        $stmt->bindParam(':score_delta', $scoreDelta);
        $stmt->bindParam(':details', $details);
        $stmt->execute();

        $flags = $this->getBehaviorFlags($clienteId);
        if (!$flags) {
            return;
        }

        $updates = [
            'score' => max(0, ((int) $flags['score']) + $scoreDelta),
            'cancellation_count' => (int) $flags['cancellation_count'],
            'abusive_reports_count' => (int) $flags['abusive_reports_count'],
            'spam_incidents_count' => (int) $flags['spam_incidents_count'],
        ];

        if ($eventType === 'service_cancelled') {
            $updates['cancellation_count']++;
        } elseif ($eventType === 'abuse_report' || $eventType === 'barber_block') {
            $updates['abusive_reports_count']++;
        } elseif ($eventType === 'spam_message') {
            $updates['spam_incidents_count']++;
        }

        $query2 = "UPDATE client_behavior_flags
                   SET score = :score,
                       cancellation_count = :cancellation_count,
                       abusive_reports_count = :abusive_reports_count,
                       spam_incidents_count = :spam_incidents_count,
                       updated_at = NOW()
                   WHERE cliente_id = :cliente_id";
        $stmt2 = $this->conn->prepare($query2);
        $stmt2->bindParam(':score', $updates['score']);
        $stmt2->bindParam(':cancellation_count', $updates['cancellation_count']);
        $stmt2->bindParam(':abusive_reports_count', $updates['abusive_reports_count']);
        $stmt2->bindParam(':spam_incidents_count', $updates['spam_incidents_count']);
        $stmt2->bindParam(':cliente_id', $clienteId);
        $stmt2->execute();

        $this->applyRestrictionIfNeeded($clienteId);
    }

    private function applyRestrictionIfNeeded(int $clienteId): void {
        $flags = $this->getBehaviorFlags($clienteId);
        if (!$flags) {
            return;
        }

        $score = (int) $flags['score'];
        $chatRestricted = 0;
        $serviceRestricted = 0;
        $restrictedUntil = null;

        if ((int) $flags['abusive_reports_count'] >= 4 || $score <= 35) {
            $chatRestricted = 1;
            $serviceRestricted = 1;
            $restrictedUntil = date('Y-m-d H:i:s', strtotime('+14 days'));
        } elseif ((int) $flags['abusive_reports_count'] >= 2 || (int) $flags['cancellation_count'] >= 3 || $score <= 60) {
            $chatRestricted = 1;
            $restrictedUntil = date('Y-m-d H:i:s', strtotime('+7 days'));
        }

        $query = "UPDATE client_behavior_flags
                  SET is_chat_restricted = :chat_restricted,
                      is_service_restricted = :service_restricted,
                      restricted_until = :restricted_until,
                      updated_at = NOW()
                  WHERE cliente_id = :cliente_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':chat_restricted', $chatRestricted);
        $stmt->bindParam(':service_restricted', $serviceRestricted);
        $stmt->bindParam(':restricted_until', $restrictedUntil);
        $stmt->bindParam(':cliente_id', $clienteId);
        $stmt->execute();
    }

    private function isClientRestricted(?array $flags, string $scope = 'chat'): bool {
        if (!$flags) {
            return false;
        }

        $restrictedUntil = $flags['restricted_until'] ?? null;
        $isCurrentlyRestricted = $restrictedUntil && strtotime($restrictedUntil) > time();
        if (!$isCurrentlyRestricted) {
            return false;
        }

        if ($scope === 'service') {
            return (int) ($flags['is_service_restricted'] ?? 0) === 1;
        }

        return (int) ($flags['is_chat_restricted'] ?? 0) === 1;
    }

    private function logIncident(int $serviceId, int $chatId, int $actorUserId, string $incidentType, string $details): void {
        $query = "INSERT INTO service_chat_incidents
                  (servicio_id, chat_id, actor_user_id, incident_type, details, created_at)
                  VALUES (:servicio_id, :chat_id, :actor_user_id, :incident_type, :details, NOW())";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':servicio_id', $serviceId);
        $stmt->bindParam(':chat_id', $chatId);
        $stmt->bindParam(':actor_user_id', $actorUserId);
        $stmt->bindParam(':incident_type', $incidentType);
        $stmt->bindParam(':details', $details);
        $stmt->execute();
    }
}
?>
