<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/SystemSettings.php';
require_once __DIR__ . '/MonetizationManager.php';

class SubscriptionPaymentManager
{
    private PDO $conn;
    private SystemSettings $settings;
    private MonetizationManager $monetizationManager;
    private array $tableCache = [];

    public function __construct(?PDO $conn = null)
    {
        if ($conn instanceof PDO) {
            $this->conn = $conn;
        } else {
            $database = new Database();
            $this->conn = $database->getConnection();
        }

        $this->settings = new SystemSettings($this->conn);
        $this->monetizationManager = new MonetizationManager($this->conn);
    }

    public function hasTable(string $table): bool
    {
        if (array_key_exists($table, $this->tableCache)) {
            return $this->tableCache[$table];
        }

        try {
            $stmt = $this->conn->query("SHOW TABLES LIKE " . $this->conn->quote($table));
            $this->tableCache[$table] = $stmt && $stmt->fetchColumn() !== false;
        } catch (Throwable $e) {
            $this->tableCache[$table] = false;
        }

        return $this->tableCache[$table];
    }

    public function isReady(): bool
    {
        return $this->hasTable('subscription_payment_requests');
    }

    public function getPaymentConfig(): array
    {
        return [
            'subscription_enabled' => $this->settings->getBool('barber_subscription_enabled', false),
            'monthly_price' => $this->settings->getFloat('barber_subscription_monthly_price', 0.0),
            'annual_price' => $this->settings->getFloat('barber_subscription_annual_price', 0.0),
            'payment_method' => trim($this->settings->get('barber_subscription_payment_method', 'Transferencia bancaria')),
            'payment_instructions' => trim($this->settings->get('barber_subscription_payment_instructions', '')),
            'payment_link' => trim($this->settings->get('barber_subscription_payment_link', '')),
            'manual_receipt_enabled' => $this->settings->getBool('barber_subscription_manual_receipt_enabled', true),
        ];
    }

    public function createPaymentRequest(int $barberId, array $data, ?array $file): array
    {
        if ($barberId <= 0) {
            return ['success' => false, 'message' => 'Barbero invalido.'];
        }

        if (!$this->isReady()) {
            return ['success' => false, 'message' => 'La migracion de pagos de suscripcion todavia no esta disponible.'];
        }

        $config = $this->getPaymentConfig();
        $planType = ($data['plan_type'] ?? '') === 'annual' ? 'annual' : 'monthly';
        $expectedAmount = $planType === 'annual' ? $config['annual_price'] : $config['monthly_price'];
        $reference = trim((string) ($data['payment_reference'] ?? ''));
        $notes = trim((string) ($data['customer_notes'] ?? ''));
        $paymentMethod = trim((string) ($data['payment_method'] ?? $config['payment_method']));
        $paymentLinkSnapshot = trim((string) ($config['payment_link'] ?? ''));

        if ($reference === '') {
            return ['success' => false, 'message' => 'Debes ingresar una referencia o numero de pago.'];
        }

        if ($expectedAmount < 0) {
            $expectedAmount = 0.0;
        }

        $receiptPath = null;
        if (!empty($config['manual_receipt_enabled'])) {
            $uploadResult = $this->storeReceiptFile($barberId, $file);
            if (!$uploadResult['success']) {
                return $uploadResult;
            }
            $receiptPath = $uploadResult['receipt_path'];
        }

        try {
            $stmt = $this->conn->prepare("INSERT INTO subscription_payment_requests
                (barber_id, plan_type, amount, payment_reference, payment_method, payment_link_snapshot, receipt_path, customer_notes, status, created_at, updated_at)
                VALUES
                (:barber_id, :plan_type, :amount, :payment_reference, :payment_method, :payment_link_snapshot, :receipt_path, :customer_notes, 'pending', NOW(), NOW())");
            $stmt->bindValue(':barber_id', $barberId, PDO::PARAM_INT);
            $stmt->bindValue(':plan_type', $planType);
            $stmt->bindValue(':amount', $expectedAmount);
            $stmt->bindValue(':payment_reference', $reference);
            $stmt->bindValue(':payment_method', $paymentMethod);
            $stmt->bindValue(':payment_link_snapshot', $paymentLinkSnapshot !== '' ? $paymentLinkSnapshot : null);
            $stmt->bindValue(':receipt_path', $receiptPath);
            $stmt->bindValue(':customer_notes', $notes !== '' ? $notes : null);
            $stmt->execute();

            return ['success' => true, 'message' => 'Comprobante enviado. Tu pago quedo pendiente de revision administrativa.'];
        } catch (Throwable $e) {
            if ($receiptPath) {
                $absolutePath = BASE_PATH . ltrim(str_replace(['../', '..\\'], '', $receiptPath), '/\\');
                if (is_file($absolutePath)) {
                    @unlink($absolutePath);
                }
            }

            if (function_exists('logError')) {
                logError('No se pudo registrar el pago de suscripcion: ' . $e->getMessage(), __FILE__, __LINE__);
            }

            return ['success' => false, 'message' => 'No se pudo registrar el comprobante de pago.'];
        }
    }

    public function approveRequest(int $requestId, int $adminUserId, string $adminNotes = ''): array
    {
        $request = $this->getRequestById($requestId);
        if (!$request) {
            return ['success' => false, 'message' => 'Solicitud de pago no encontrada.'];
        }

        if (($request['status'] ?? '') !== 'pending') {
            return ['success' => false, 'message' => 'La solicitud ya fue revisada previamente.'];
        }

        try {
            $ownsTransaction = !$this->conn->inTransaction();
            if ($ownsTransaction) {
                $this->conn->beginTransaction();
            }

            $startAt = $this->resolveSubscriptionStart((int) $request['barber_id']);
            $activation = $this->monetizationManager->activateSubscription((int) $request['barber_id'], (string) $request['plan_type'], $startAt);
            if (!$activation['success']) {
                throw new RuntimeException($activation['message'] ?? 'No se pudo activar la suscripcion.');
            }

            if ($this->hasTable('barber_subscriptions')) {
                $syncAmount = $this->conn->prepare("UPDATE barber_subscriptions
                    SET amount = :amount, updated_at = NOW()
                    WHERE barber_id = :barber_id
                      AND status = 'active'
                    ORDER BY id DESC
                    LIMIT 1");
                $syncAmount->bindValue(':amount', (float) ($request['amount'] ?? 0));
                $syncAmount->bindValue(':barber_id', (int) $request['barber_id'], PDO::PARAM_INT);
                $syncAmount->execute();
            }

            $update = $this->conn->prepare("UPDATE subscription_payment_requests
                SET status = 'approved',
                    admin_notes = :admin_notes,
                    reviewed_by = :reviewed_by,
                    reviewed_at = NOW(),
                    updated_at = NOW()
                WHERE id = :id
                  AND status = 'pending'");
            $update->bindValue(':admin_notes', $adminNotes !== '' ? $adminNotes : null);
            $update->bindValue(':reviewed_by', $adminUserId, PDO::PARAM_INT);
            $update->bindValue(':id', $requestId, PDO::PARAM_INT);
            $update->execute();

            if ($update->rowCount() < 1) {
                throw new RuntimeException('No se pudo marcar la solicitud como aprobada.');
            }

            if ($ownsTransaction && $this->conn->inTransaction()) {
                $this->conn->commit();
            }

            return ['success' => true, 'message' => 'Pago aprobado y suscripcion activada correctamente.'];
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            if (function_exists('logError')) {
                logError('No se pudo aprobar el pago de suscripcion: ' . $e->getMessage(), __FILE__, __LINE__);
            }
            return ['success' => false, 'message' => 'No se pudo aprobar el pago de suscripcion.'];
        }
    }

    public function rejectRequest(int $requestId, int $adminUserId, string $adminNotes = ''): array
    {
        if (!$this->isReady()) {
            return ['success' => false, 'message' => 'La migracion de pagos de suscripcion todavia no esta disponible.'];
        }

        try {
            $stmt = $this->conn->prepare("UPDATE subscription_payment_requests
                SET status = 'rejected',
                    admin_notes = :admin_notes,
                    reviewed_by = :reviewed_by,
                    reviewed_at = NOW(),
                    updated_at = NOW()
                WHERE id = :id
                  AND status = 'pending'");
            $stmt->bindValue(':admin_notes', $adminNotes !== '' ? $adminNotes : null);
            $stmt->bindValue(':reviewed_by', $adminUserId, PDO::PARAM_INT);
            $stmt->bindValue(':id', $requestId, PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->rowCount() < 1) {
                return ['success' => false, 'message' => 'La solicitud no estaba pendiente o no existe.'];
            }

            return ['success' => true, 'message' => 'Pago rechazado correctamente.'];
        } catch (Throwable $e) {
            if (function_exists('logError')) {
                logError('No se pudo rechazar el pago de suscripcion: ' . $e->getMessage(), __FILE__, __LINE__);
            }
            return ['success' => false, 'message' => 'No se pudo rechazar el pago de suscripcion.'];
        }
    }

    public function getRequests(string $status = 'all', int $limit = 50): array
    {
        if (!$this->isReady()) {
            return [];
        }

        $limit = max(1, min(200, $limit));
        $allowedStatus = ['pending', 'approved', 'rejected'];
        $filterStatus = in_array($status, $allowedStatus, true) ? $status : null;

        try {
            $sql = "SELECT spr.*, u.nombre AS barber_name, u.email AS barber_email, admin_u.nombre AS reviewed_by_name
                FROM subscription_payment_requests spr
                JOIN barberos b ON spr.barber_id = b.id
                JOIN users u ON b.user_id = u.id
                LEFT JOIN users admin_u ON spr.reviewed_by = admin_u.id";
            if ($filterStatus !== null) {
                $sql .= " WHERE spr.status = :status";
            }
            $sql .= " ORDER BY CASE spr.status WHEN 'pending' THEN 0 ELSE 1 END, spr.created_at DESC LIMIT " . $limit;

            $stmt = $this->conn->prepare($sql);
            if ($filterStatus !== null) {
                $stmt->bindValue(':status', $filterStatus);
            }
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            if (function_exists('logError')) {
                logError('No se pudieron consultar pagos de suscripcion: ' . $e->getMessage(), __FILE__, __LINE__);
            }
            return [];
        }
    }

    public function getBarberRequests(int $barberId, int $limit = 10): array
    {
        if ($barberId <= 0 || !$this->isReady()) {
            return [];
        }

        $limit = max(1, min(50, $limit));

        try {
            $stmt = $this->conn->prepare("SELECT *
                FROM subscription_payment_requests
                WHERE barber_id = :barber_id
                ORDER BY created_at DESC
                LIMIT " . $limit);
            $stmt->bindValue(':barber_id', $barberId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            if (function_exists('logError')) {
                logError('No se pudieron consultar solicitudes del barbero: ' . $e->getMessage(), __FILE__, __LINE__);
            }
            return [];
        }
    }

    public function getSummary(): array
    {
        $summary = [
            'pending' => 0,
            'approved' => 0,
            'rejected' => 0,
            'pending_amount' => 0.0,
        ];

        if (!$this->isReady()) {
            return $summary;
        }

        try {
            $stmt = $this->conn->query("SELECT
                COALESCE(SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END), 0) AS pending_total,
                COALESCE(SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END), 0) AS approved_total,
                COALESCE(SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END), 0) AS rejected_total,
                COALESCE(SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END), 0) AS pending_amount
                FROM subscription_payment_requests");
            $row = $stmt ? ($stmt->fetch(PDO::FETCH_ASSOC) ?: []) : [];

            return [
                'pending' => (int) ($row['pending_total'] ?? 0),
                'approved' => (int) ($row['approved_total'] ?? 0),
                'rejected' => (int) ($row['rejected_total'] ?? 0),
                'pending_amount' => (float) ($row['pending_amount'] ?? 0),
            ];
        } catch (Throwable $e) {
            if (function_exists('logError')) {
                logError('No se pudo obtener el resumen de pagos de suscripcion: ' . $e->getMessage(), __FILE__, __LINE__);
            }
            return $summary;
        }
    }

    public function getRequestById(int $requestId): ?array
    {
        if ($requestId <= 0 || !$this->isReady()) {
            return null;
        }

        try {
            $stmt = $this->conn->prepare("SELECT *
                FROM subscription_payment_requests
                WHERE id = :id
                LIMIT 1");
            $stmt->bindValue(':id', $requestId, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }

    private function resolveSubscriptionStart(int $barberId): string
    {
        $profile = $this->monetizationManager->getBarberProfile($barberId);
        $subscriptionEnd = trim((string) ($profile['subscription_ends_at'] ?? ''));
        if ($subscriptionEnd !== '' && strtotime($subscriptionEnd) !== false && strtotime($subscriptionEnd) > time()) {
            return date('Y-m-d H:i:s', strtotime($subscriptionEnd . ' +1 second'));
        }

        return date('Y-m-d H:i:s');
    }

    private function storeReceiptFile(int $barberId, ?array $file): array
    {
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['success' => false, 'message' => 'Debes adjuntar un comprobante de pago.'];
        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'No se pudo subir el comprobante.'];
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > MAX_FILE_SIZE) {
            return ['success' => false, 'message' => 'El comprobante supera el tamano permitido.'];
        }

        $tmpName = $file['tmp_name'] ?? '';
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            return ['success' => false, 'message' => 'Archivo temporal invalido.'];
        }

        $allowedMime = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
        ];

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? finfo_file($finfo, $tmpName) : '';
        if ($finfo) {
            finfo_close($finfo);
        }

        if (!isset($allowedMime[$mime])) {
            return ['success' => false, 'message' => 'Formato de comprobante no permitido. Usa JPG, PNG, WEBP o PDF.'];
        }

        $uploadDir = UPLOAD_PATH . 'subscription_receipts/';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            return ['success' => false, 'message' => 'No se pudo preparar el almacenamiento del comprobante.'];
        }

        $extension = $allowedMime[$mime];
        $filename = 'receipt_' . $barberId . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $extension;
        $destination = $uploadDir . $filename;

        if (!move_uploaded_file($tmpName, $destination)) {
            return ['success' => false, 'message' => 'No se pudo guardar el comprobante.'];
        }

        return [
            'success' => true,
            'receipt_path' => 'assets/uploads/subscription_receipts/' . $filename,
        ];
    }
}
