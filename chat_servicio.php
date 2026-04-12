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
$chatService->markMessagesRead($serviceId, $userId, $role);
$messages = $chatService->getMessagesForService($serviceId);
$quickReplies = $chatService->getQuickReplies($role);
$flash = getFlash();
$backUrl = $role === 'barbero' ? 'barbero.php' : 'cliente.php';
$otherName = $role === 'cliente'
    ? ($service['barbero_nombre'] ?? 'Barbero pendiente')
    : ($service['cliente_nombre'] ?? 'Cliente');
$serializedMessages = [];
foreach ($messages as $message) {
    $serializedMessages[] = $chatService->serializeMessage($message, $userId);
}
$csrfToken = csrf_token();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat del Servicio #<?php echo $service['id']; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include BASE_PATH . 'includes/pwa_head.php'; ?>
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
        .message-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-top: 8px;
            font-size: 11px;
            color: var(--muted);
        }
        .message-status {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            white-space: nowrap;
        }
        .message-status .check {
            font-weight: 700;
            letter-spacing: -1px;
        }
        .message-status.sent .check,
        .message-status.delivered .check {
            color: #7F8C8D;
        }
        .message-status.read .check {
            color: #3498DB;
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
        .top-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 14px;
        }
        .chat-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }
        .chat-live {
            font-size: 12px;
            font-weight: 700;
            color: #1E8449;
            background: #EAF7EE;
            padding: 6px 10px;
            border-radius: 999px;
        }
        .chat-live.offline {
            color: #856404;
            background: #FFF3CD;
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
                <div class="top-actions">
                    <a class="btn btn-secondary" href="<?php echo htmlspecialchars($backUrl); ?>">
                        <i class="fas fa-house"></i> Regresar al dashboard
                    </a>
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
            <div class="card-body" id="chat-app"
                 data-service-id="<?php echo (int) $service['id']; ?>"
                 data-user-id="<?php echo (int) $userId; ?>"
                 data-role="<?php echo htmlspecialchars($role); ?>"
                 data-csrf-token="<?php echo htmlspecialchars($csrfToken); ?>"
                 data-back-url="<?php echo htmlspecialchars($backUrl); ?>">
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

                <div class="chat-toolbar">
                    <div class="chat-live" id="chat-live-indicator">Tiempo real activo</div>
                    <button type="button" class="btn btn-secondary" id="chat-refresh-btn">
                        <i class="fas fa-rotate"></i> Actualizar
                    </button>
                </div>

                <div class="messages" id="messages-container">
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
                        <div class="message <?php echo $class; ?>" data-message-id="<?php echo (int) $message['id']; ?>">
                            <strong><?php echo htmlspecialchars($message['sender_role'] === 'system' ? 'Sistema' : ($message['sender_name'] ?? 'Usuario')); ?></strong><br>
                            <?php echo htmlspecialchars($message['message_text']); ?>
                            <div class="message-meta">
                                <small><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($message['created_at']))); ?></small>
                                <?php if ((int) ($message['sender_user_id'] ?? 0) === $userId && ($message['sender_role'] ?? '') !== 'system'): ?>
                                    <?php
                                    $status = 'sent';
                                    if (!empty($message['read_at'])) {
                                        $status = 'read';
                                    } elseif (!empty($message['delivered_at'])) {
                                        $status = 'delivered';
                                    }
                                    ?>
                                    <span class="message-status <?php echo $status; ?>" data-status-for="<?php echo (int) $message['id']; ?>">
                                        <?php if ($status === 'sent'): ?>
                                            <span class="check">✓</span> Enviado
                                        <?php elseif ($status === 'delivered'): ?>
                                            <span class="check">✓✓</span> Entregado
                                        <?php else: ?>
                                            <span class="check">✓✓</span> Leido
                                        <?php endif; ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($chat && $context['can_write']): ?>
                    <div class="quick-grid">
                        <?php foreach ($quickReplies as $presetKey => $label): ?>
                            <form method="POST" class="quick-reply-form">
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
                        <form method="POST" class="form-group" id="free-text-form">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="servicio_id" value="<?php echo $service['id']; ?>">
                            <label>Texto libre limitado</label>
                            <textarea name="message_text" id="free-text-input" rows="3" maxlength="180" placeholder="Maximo 180 caracteres. Sin links, telefonos ni contacto directo."></textarea>
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

    <script>
        const initialMessages = <?php echo json_encode($serializedMessages, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const chatApp = document.getElementById('chat-app');
        const messagesContainer = document.getElementById('messages-container');
        const liveIndicator = document.getElementById('chat-live-indicator');
        const refreshBtn = document.getElementById('chat-refresh-btn');

        if (chatApp && messagesContainer) {
            const serviceId = chatApp.dataset.serviceId;
            const csrfToken = chatApp.dataset.csrfToken;
            let lastMessageId = initialMessages.reduce((max, item) => Math.max(max, Number(item.id || 0)), 0);
            let pollingHandle = null;
            let isPolling = false;

            function escapeHtml(value) {
                return String(value)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function statusMarkup(status, messageId) {
                if (!status) {
                    status = 'sent';
                }
                let label = 'Enviado';
                let checks = '✓';
                if (status === 'delivered') {
                    label = 'Entregado';
                    checks = '✓✓';
                } else if (status === 'read') {
                    label = 'Leido';
                    checks = '✓✓';
                }
                return `<span class="message-status ${status}" data-status-for="${messageId}"><span class="check">${checks}</span> ${label}</span>`;
            }

            function renderMessage(message) {
                const wrapper = document.createElement('div');
                const cssClass = message.is_system ? 'system' : (message.is_mine ? 'mine' : 'other');
                wrapper.className = `message ${cssClass}`;
                wrapper.dataset.messageId = message.id;

                const sender = escapeHtml(message.sender_name || 'Usuario');
                const text = escapeHtml(message.message_text || '');
                const created = escapeHtml(message.created_at || '');

                let meta = `<div class="message-meta"><small>${created}</small>`;
                if (message.is_mine && !message.is_system) {
                    meta += statusMarkup(message.status, message.id);
                }
                meta += `</div>`;

                wrapper.innerHTML = `<strong>${sender}</strong><br>${text}${meta}`;
                return wrapper;
            }

            function appendMessages(messages) {
                if (!Array.isArray(messages) || messages.length === 0) {
                    return;
                }

                const emptyNode = messagesContainer.querySelector('.message.system');
                if (emptyNode && messagesContainer.children.length === 1 && emptyNode.textContent.includes('Aun no hay mensajes')) {
                    emptyNode.remove();
                }

                messages.forEach(message => {
                    if (messagesContainer.querySelector(`[data-message-id="${message.id}"]`)) {
                        updateMessageStatus(message.id, message.status);
                        return;
                    }
                    messagesContainer.appendChild(renderMessage(message));
                    lastMessageId = Math.max(lastMessageId, Number(message.id || 0));
                });
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }

            function updateMessageStatus(messageId, status) {
                const node = document.querySelector(`[data-status-for="${messageId}"]`);
                if (!node) {
                    return;
                }
                node.outerHTML = statusMarkup(status, messageId);
            }

            function applyStatuses(statuses) {
                if (!Array.isArray(statuses)) {
                    return;
                }
                statuses.forEach(item => updateMessageStatus(item.id, item.status));
            }

            async function pollMessages(force = false) {
                if (isPolling && !force) {
                    return;
                }
                isPolling = true;
                try {
                    const url = `chat_poll.php?servicio_id=${encodeURIComponent(serviceId)}&after_id=${encodeURIComponent(lastMessageId)}&mark_read=1`;
                    const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                    if (!response.ok) {
                        throw new Error('poll_failed');
                    }
                    const data = await response.json();
                    appendMessages(data.messages || []);
                    applyStatuses(data.message_statuses || []);
                    liveIndicator.textContent = 'Tiempo real activo';
                    liveIndicator.classList.remove('offline');
                } catch (error) {
                    liveIndicator.textContent = 'Tiempo real en reintento';
                    liveIndicator.classList.add('offline');
                } finally {
                    isPolling = false;
                }
            }

            async function sendForm(form, submitter = null) {
                const formData = new FormData(form);
                if (submitter && submitter.name) {
                    formData.append(submitter.name, submitter.value || '1');
                }
                try {
                    const response = await fetch('chat_send.php', {
                        method: 'POST',
                        body: formData,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    const data = await response.json();
                    if (!response.ok || !data.success) {
                        throw new Error(data.message || 'send_failed');
                    }
                    appendMessages(data.messages || []);
                    if (data.message_status) {
                        updateMessageStatus(data.message_status.id, data.message_status.status);
                    }
                    pollMessages(true);
                    return true;
                } catch (error) {
                    return false;
                }
            }

            document.querySelectorAll('.quick-reply-form').forEach(form => {
                form.addEventListener('submit', async function(event) {
                    event.preventDefault();
                    const ok = await sendForm(form, event.submitter || null);
                    if (!ok) {
                        form.submit();
                    }
                });
            });

            const freeTextForm = document.getElementById('free-text-form');
            if (freeTextForm) {
                freeTextForm.addEventListener('submit', async function(event) {
                    event.preventDefault();
                    const ok = await sendForm(freeTextForm, event.submitter || null);
                    if (ok) {
                        const input = document.getElementById('free-text-input');
                        if (input) input.value = '';
                    } else {
                        freeTextForm.submit();
                    }
                });
            }

            if (refreshBtn) {
                refreshBtn.addEventListener('click', function() {
                    pollMessages(true);
                });
            }

            pollingHandle = window.setInterval(() => pollMessages(false), 3000);
            pollMessages(true);
        }
    </script>
    <?php include BASE_PATH . 'includes/pwa_register.php'; ?>
</body>
</html>
