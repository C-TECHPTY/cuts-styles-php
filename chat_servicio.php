<?php
require_once 'config/config.php';
require_once 'classes/ServiceChat.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_rol'], ['cliente', 'barbero'], true)) {
    redirect('login.php');
}

$serviceId = (int) ($_GET['servicio_id'] ?? $_POST['servicio_id'] ?? 0);
if ($serviceId <= 0) {
    setFlash('danger', 'Servicio no valido para chat.');
    redirect($_SESSION['user_rol'] === 'barbero' ? 'barbero.php' : 'cliente.php');
}

$chatService = new ServiceChat();
$role = $_SESSION['user_rol'];
$userId = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verificarCSRFToken($_POST['csrf_token'] ?? null);
    } catch (Exception $e) {
        setFlash('danger', 'Sesion invalida. Intenta nuevamente.');
        redirect('chat_servicio.php?servicio_id=' . $serviceId);
    }

    $result = ['success' => false, 'message' => 'Accion no valida.'];

    if (isset($_POST['send_quick_reply'])) {
        $result = $chatService->sendQuickReply($serviceId, $userId, $role, (string) ($_POST['preset_key'] ?? ''));
    } elseif (isset($_POST['send_free_text'])) {
        $result = $chatService->sendFreeText($serviceId, $userId, $role, (string) ($_POST['message_text'] ?? ''));
    } elseif (isset($_POST['report_abuse'])) {
        $result = $chatService->reportAbuse(
            $serviceId,
            $userId,
            $role,
            trim((string) ($_POST['report_reason'] ?? '')),
            trim((string) ($_POST['report_details'] ?? ''))
        );
    } elseif ($role === 'barbero' && isset($_POST['block_client'])) {
        $result = $chatService->blockClient($serviceId, $userId, trim((string) ($_POST['block_reason'] ?? '')));
    }

    setFlash($result['success'] ? 'success' : 'danger', $result['message']);
    redirect('chat_servicio.php?servicio_id=' . $serviceId);
}

$context = $chatService->getChatContextForUser($serviceId, $userId, $role);
if (!$context) {
    setFlash('danger', 'No tienes acceso a este chat.');
    redirect($role === 'barbero' ? 'barbero.php' : 'cliente.php');
}

$service = $context['service'];
$chat = $context['chat'];
$messages = $chatService->getMessagesForService($serviceId);
$quickReplies = $chatService->getQuickReplies($role);
$flash = getFlash();
$backUrl = $role === 'barbero' ? 'barbero.php' : 'cliente.php';
$otherName = $role === 'cliente'
    ? ($service['barbero_nombre'] ?? 'Barbero pendiente')
    : ($service['cliente_nombre'] ?? 'Cliente');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat del Servicio #<?php echo $service['id']; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --primary: #2C3E50;
            --secondary: #E74C3C;
            --success: #27AE60;
            --warning: #F39C12;
            --light: #ECF0F1;
            --danger: #E74C3C;
            --muted: #7F8C8D;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            color: var(--primary);
            min-height: 100vh;
            padding: 24px;
        }
        .page {
            max-width: 1100px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 24px;
        }
        .card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        .card-header {
            padding: 20px;
            border-bottom: 1px solid #eef2f5;
        }
        .card-body { padding: 20px; }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 14px;
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }
        .status {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            background: #E8F4FC;
            color: #3498DB;
        }
        .status.closed { background: #FDEDEC; color: var(--danger); }
        .meta-list { display: grid; gap: 14px; }
        .meta-item label {
            display: block;
            font-size: 12px;
            color: var(--muted);
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .alert {
            margin-bottom: 16px;
            padding: 14px 16px;
            border-radius: 12px;
        }
        .alert-success { background: #D5F4E6; color: #1E8449; }
        .alert-danger { background: #FDEDEC; color: #C0392B; }
        .messages {
            display: flex;
            flex-direction: column;
            gap: 12px;
            max-height: 55vh;
            overflow-y: auto;
            padding-right: 6px;
        }
        .message {
            padding: 12px 14px;
            border-radius: 14px;
            max-width: 78%;
            line-height: 1.4;
        }
        .message.mine {
            margin-left: auto;
            background: #E8F4FC;
        }
        .message.other {
            background: #F7F9FA;
        }
        .message.system {
            margin: 0 auto;
            background: #FFF8E5;
            color: #8A6D3B;
            max-width: 90%;
            text-align: center;
        }
        .message small {
            display: block;
            margin-top: 8px;
            color: var(--muted);
            font-size: 11px;
        }
        .quick-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 18px;
        }
        .quick-grid form { margin: 0; }
        .btn, .quick-btn {
            border: none;
            border-radius: 10px;
            padding: 10px 14px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-primary, .quick-btn {
            background: var(--primary);
            color: #fff;
        }
        .btn-danger { background: var(--danger); color: #fff; }
        .btn-warning { background: var(--warning); color: #fff; }
        .btn-secondary {
            background: #eef2f5;
            color: var(--primary);
        }
        textarea, select {
            width: 100%;
            border: 2px solid #eef2f5;
            border-radius: 12px;
            padding: 12px;
            font: inherit;
        }
        .form-group { margin-top: 16px; }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }
        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 14px;
        }
        .note {
            margin-top: 12px;
            font-size: 13px;
            color: var(--muted);
        }
        .score-box {
            margin-top: 16px;
            padding: 14px;
            border-radius: 12px;
            background: #F7F9FA;
        }
        @media (max-width: 900px) {
            .page { grid-template-columns: 1fr; }
            .messages { max-height: none; }
        }
    </style>
</head>
<body>
    <div class="page">
        <aside class="card">
            <div class="card-header">
                <a class="back-link" href="<?php echo htmlspecialchars($backUrl); ?>">
                    <i class="fas fa-arrow-left"></i> Volver al panel
                </a>
                <h2>Servicio #<?php echo $service['id']; ?></h2>
                <p style="margin-top:8px;"><?php echo htmlspecialchars($service['tipo']); ?></p>
                <div style="margin-top:12px;">
                    <span class="status <?php echo ($chat && $chat['status'] !== 'open') ? 'closed' : ''; ?>">
                        <?php echo $chat ? htmlspecialchars($chat['status']) : 'sin chat'; ?>
                    </span>
                </div>
            </div>
            <div class="card-body">
                <div class="meta-list">
                    <div class="meta-item">
                        <label><?php echo $role === 'cliente' ? 'Barbero' : 'Cliente'; ?></label>
                        <div><?php echo htmlspecialchars($otherName); ?></div>
                    </div>
                    <div class="meta-item">
                        <label>Estado del servicio</label>
                        <div><?php echo htmlspecialchars($service['estado']); ?></div>
                    </div>
                    <div class="meta-item">
                        <label>Solicitado</label>
                        <div><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($service['fecha_solicitud']))); ?></div>
                    </div>
                    <?php if (!empty($service['fecha_aceptacion'])): ?>
                    <div class="meta-item">
                        <label>Aceptado</label>
                        <div><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($service['fecha_aceptacion']))); ?></div>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if ($role === 'barbero' && !empty($context['flags'])): ?>
                <div class="score-box">
                    <strong>Indicador interno del cliente</strong>
                    <div style="margin-top:8px;">Score: <?php echo (int) $context['flags']['score']; ?>/100</div>
                    <div>Cancelaciones: <?php echo (int) $context['flags']['cancellation_count']; ?></div>
                    <div>Reportes: <?php echo (int) $context['flags']['abusive_reports_count']; ?></div>
                    <div>Spam: <?php echo (int) $context['flags']['spam_incidents_count']; ?></div>
                </div>
                <?php endif; ?>

                <div class="form-group">
                    <label>Reportar abuso</label>
                    <form method="POST">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="servicio_id" value="<?php echo $service['id']; ?>">
                        <select name="report_reason" required>
                            <option value="">Seleccionar motivo</option>
                            <option value="spam">Spam</option>
                            <option value="lenguaje_ofensivo">Lenguaje ofensivo</option>
                            <option value="fuera_de_contexto">Mensajes fuera de contexto</option>
                            <option value="acoso">Acoso o mal uso</option>
                        </select>
                        <textarea name="report_details" rows="3" placeholder="Detalle adicional opcional"></textarea>
                        <div class="actions">
                            <button type="submit" name="report_abuse" class="btn btn-warning">
                                <i class="fas fa-flag"></i> Reportar
                            </button>
                        </div>
                    </form>
                </div>

                <?php if ($role === 'barbero'): ?>
                <div class="form-group">
                    <label>Bloqueo preventivo</label>
                    <form method="POST">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="servicio_id" value="<?php echo $service['id']; ?>">
                        <textarea name="block_reason" rows="2" placeholder="Motivo del bloqueo para este cliente"></textarea>
                        <div class="actions">
                            <button type="submit" name="block_client" class="btn btn-danger">
                                <i class="fas fa-user-slash"></i> Bloquear cliente
                            </button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </aside>

        <main class="card">
            <div class="card-header">
                <h2>Chat transaccional</h2>
                <p style="margin-top:8px;">Solo disponible durante el flujo activo del servicio. No se permiten enlaces, telefonos ni contacto directo.</p>
            </div>
            <div class="card-body">
                <?php if ($flash): ?>
                    <div class="alert alert-<?php echo htmlspecialchars($flash['type']); ?>">
                        <?php echo htmlspecialchars($flash['message']); ?>
                    </div>
                <?php endif; ?>

                <?php if (!$chat): ?>
                    <div class="alert alert-danger">
                        El chat todavia no esta disponible. Se abrira cuando el barbero acepte el servicio.
                    </div>
                <?php elseif (!$context['can_write']): ?>
                    <div class="alert alert-danger">
                        <?php echo htmlspecialchars($context['write_disabled_reason']); ?>
                    </div>
                <?php endif; ?>

                <div class="messages">
                    <?php if (empty($messages)): ?>
                        <div class="message system">Aun no hay mensajes en este chat.</div>
                    <?php endif; ?>

                    <?php foreach ($messages as $message): ?>
                        <?php
                        $class = 'other';
                        if (($message['sender_role'] ?? '') === 'system') {
                            $class = 'system';
                        } elseif ((int) ($message['sender_user_id'] ?? 0) === $userId) {
                            $class = 'mine';
                        }
                        ?>
                        <div class="message <?php echo $class; ?>">
                            <strong><?php echo htmlspecialchars($message['sender_role'] === 'system' ? 'Sistema' : ($message['sender_name'] ?? 'Usuario')); ?></strong><br>
                            <?php echo htmlspecialchars($message['message_text']); ?>
                            <small><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($message['created_at']))); ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($chat && $context['can_write']): ?>
                    <div class="quick-grid">
                        <?php foreach ($quickReplies as $presetKey => $label): ?>
                            <form method="POST">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="servicio_id" value="<?php echo $service['id']; ?>">
                                <input type="hidden" name="preset_key" value="<?php echo htmlspecialchars($presetKey); ?>">
                                <button type="submit" name="send_quick_reply" class="quick-btn">
                                    <i class="fas fa-paper-plane"></i> <?php echo htmlspecialchars($label); ?>
                                </button>
                            </form>
                        <?php endforeach; ?>
                    </div>

                    <?php if ((int) ($chat['allow_free_text'] ?? 0) === 1): ?>
                        <form method="POST" class="form-group">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="servicio_id" value="<?php echo $service['id']; ?>">
                            <label>Texto libre limitado</label>
                            <textarea name="message_text" rows="3" maxlength="180" placeholder="Maximo 180 caracteres. Sin links, telefonos ni contacto directo."></textarea>
                            <div class="actions">
                                <button type="submit" name="send_free_text" class="btn btn-primary">
                                    <i class="fas fa-comment-dots"></i> Enviar texto
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="note">Etapa 1 activa: respuestas rapidas. El texto libre limitado quedo preparado para activarse despues sin romper este flujo.</div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
