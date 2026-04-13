<?php

require_once 'config/config.php';
require_once 'classes/User.php';
require_once 'classes/MonetizationManager.php';
require_once 'classes/SubscriptionPaymentManager.php';
require_once BASE_PATH . 'includes/app_logo.php';

requireRole('barbero');

$user = new User();
$user->id = $_SESSION['user_id'];
$profile = $user->getProfile();

$stmt = $user->conn->prepare("SELECT id FROM barberos WHERE user_id = :user_id LIMIT 1");
$stmt->bindValue(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
$stmt->execute();
$barbero = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$barberoId = (int) ($barbero['id'] ?? 0);

$monetizationManager = new MonetizationManager($user->conn);
$paymentManager = new SubscriptionPaymentManager($user->conn);
$monetizationProfile = $barberoId ? $monetizationManager->getBarberProfile($barberoId) : [];
$paymentConfig = $paymentManager->getPaymentConfig();
$paymentHistory = $barberoId ? $paymentManager->getBarberRequests($barberoId, 8) : [];
$currentPlan = (string) ($monetizationProfile['plan_type'] ?? 'monthly');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Pago de Suscripcion - Cuts & Styles</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include BASE_PATH . 'includes/pwa_head.php'; ?>
    <style>
        :root {
            --primary: #2C3E50;
            --secondary: #E74C3C;
            --light: #F4F6F8;
            --dark: #1F2D3D;
            --success: #27AE60;
            --warning: #F39C12;
            --danger: #C0392B;
            --muted: #6C7A89;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(180deg, #f8fafc 0%, #eef2f7 100%);
            color: var(--dark);
        }
        .app-logo {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: inherit;
        }
        .app-logo__image {
            width: 76px;
            height: 76px;
            object-fit: contain;
            flex-shrink: 0;
        }
        .app-logo__text {
            font-size: 22px;
            font-weight: 700;
            line-height: 1.1;
        }
        .page {
            max-width: 1100px;
            margin: 0 auto;
            padding: 24px;
        }
        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 24px;
        }
        .topbar-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
            border-radius: 10px;
            padding: 12px 18px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
        }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-outline { background: #fff; color: var(--primary); border: 1px solid #d5dbe3; }
        .grid {
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            gap: 24px;
        }
        .card {
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 14px 30px rgba(31, 45, 61, 0.08);
            padding: 24px;
        }
        .card h2, .card h3 {
            margin-top: 0;
            color: var(--primary);
        }
        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border-radius: 999px;
            padding: 8px 14px;
            background: #eef3f8;
            color: var(--primary);
            font-weight: 600;
        }
        .meta {
            margin-top: 16px;
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }
        .meta-item {
            background: var(--light);
            border-radius: 12px;
            padding: 14px;
        }
        .meta-label {
            display: block;
            font-size: 12px;
            color: var(--muted);
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        .meta-value {
            font-size: 20px;
            font-weight: 700;
        }
        .alert {
            border-radius: 12px;
            padding: 14px 16px;
            margin-bottom: 18px;
        }
        .alert-success { background: #eafaf1; color: #1e8449; }
        .alert-danger { background: #fdecea; color: var(--danger); }
        .alert-warning { background: #fff4e5; color: #b9770e; }
        .form-group { margin-bottom: 16px; }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border-radius: 10px;
            border: 1px solid #d8dee6;
            font: inherit;
        }
        .small {
            color: var(--muted);
            font-size: 13px;
        }
        .history-table {
            width: 100%;
            border-collapse: collapse;
        }
        .history-table th,
        .history-table td {
            padding: 12px 10px;
            border-bottom: 1px solid #edf1f5;
            text-align: left;
            vertical-align: top;
        }
        .history-table th {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--muted);
        }
        .status-badge {
            border-radius: 999px;
            padding: 6px 10px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .status-pending { background: #fff4e5; color: #b9770e; }
        .status-approved { background: #eafaf1; color: #1e8449; }
        .status-rejected { background: #fdecea; color: var(--danger); }
        .instructions {
            white-space: pre-wrap;
            background: var(--light);
            border-radius: 12px;
            padding: 14px;
            color: var(--dark);
        }
        @media (max-width: 900px) {
            .grid {
                grid-template-columns: 1fr;
            }
            .meta {
                grid-template-columns: 1fr;
            }
            .topbar {
                flex-direction: column;
                align-items: flex-start;
            }
            .app-logo__text {
                font-size: 19px;
            }
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="topbar">
            <div>
                <?php echo render_app_logo('billing'); ?>
                <p class="small" style="margin: 10px 0 0;">Gestiona tu renovacion sin salir del flujo actual del panel.</p>
            </div>
            <div class="topbar-actions">
                <a href="barbero.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Volver al panel</a>
            </div>
        </div>

        <?php $flash = getFlash(); if ($flash): ?>
            <div class="alert alert-<?php echo htmlspecialchars($flash['type']); ?>">
                <?php echo htmlspecialchars($flash['message']); ?>
            </div>
        <?php endif; ?>
        <?php if (empty($paymentConfig['subscription_enabled'])): ?>
            <div class="alert alert-warning">La suscripcion para barberos esta desactivada desde la configuracion global en este momento.</div>
        <?php endif; ?>

        <div class="grid">
            <div class="card">
                <h2><i class="fas fa-credit-card"></i> Pagar Suscripcion</h2>
                <p class="small">El comprobante queda pendiente hasta revision del administrador. No se activa automaticamente.</p>

                <div class="status-pill">
                    <i class="fas fa-wallet"></i>
                    Estado actual: <?php echo htmlspecialchars(ucfirst((string) ($monetizationProfile['status'] ?? 'free'))); ?>
                </div>

                <div class="meta">
                    <div class="meta-item">
                        <span class="meta-label">Plan mensual</span>
                        <div class="meta-value">$<?php echo number_format((float) ($paymentConfig['monthly_price'] ?? 0), 2); ?></div>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Plan anual</span>
                        <div class="meta-value">$<?php echo number_format((float) ($paymentConfig['annual_price'] ?? 0), 2); ?></div>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Vencimiento actual</span>
                        <div class="meta-value" style="font-size:16px;"><?php echo !empty($monetizationProfile['subscription_ends_at']) ? date('d/m/Y H:i', strtotime($monetizationProfile['subscription_ends_at'])) : 'Sin fecha'; ?></div>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Metodo visible</span>
                        <div class="meta-value" style="font-size:16px;"><?php echo htmlspecialchars($paymentConfig['payment_method'] ?: 'No configurado'); ?></div>
                    </div>
                </div>

                <form method="POST" action="subir_comprobante_suscripcion.php" enctype="multipart/form-data" style="margin-top: 22px;">
                    <?php echo csrf_field(); ?>
                    <div class="form-group">
                        <label for="plan_type">Plan</label>
                        <select id="plan_type" name="plan_type">
                            <option value="monthly" <?php echo $currentPlan !== 'annual' ? 'selected' : ''; ?>>Mensual</option>
                            <option value="annual" <?php echo $currentPlan === 'annual' ? 'selected' : ''; ?>>Anual</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="payment_reference">Referencia o numero de pago</label>
                        <input type="text" id="payment_reference" name="payment_reference" maxlength="120" required>
                    </div>
                    <div class="form-group">
                        <label for="customer_notes">Observaciones</label>
                        <textarea id="customer_notes" name="customer_notes" rows="3" placeholder="Opcional: banco emisor, fecha del pago o cualquier dato util"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="receipt_file">Comprobante</label>
                        <input type="file" id="receipt_file" name="receipt_file" accept=".jpg,.jpeg,.png,.webp,.pdf" <?php echo !empty($paymentConfig['manual_receipt_enabled']) ? 'required' : ''; ?>>
                        <div class="small">Formatos permitidos: JPG, PNG, WEBP o PDF. Tamano maximo: <?php echo (int) round(MAX_FILE_SIZE / 1024 / 1024); ?> MB.</div>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> Enviar comprobante</button>
                </form>
            </div>

            <div class="card">
                <h3><i class="fas fa-circle-info"></i> Instrucciones de Pago</h3>
                <?php if (!empty($paymentConfig['payment_instructions'])): ?>
                    <div class="instructions"><?php echo htmlspecialchars($paymentConfig['payment_instructions']); ?></div>
                <?php else: ?>
                    <div class="alert alert-warning">Admin aun no ha configurado instrucciones detalladas de pago.</div>
                <?php endif; ?>

                <?php if (!empty($paymentConfig['payment_link'])): ?>
                    <p style="margin-top: 16px;">
                        <a class="btn btn-outline" href="<?php echo htmlspecialchars($paymentConfig['payment_link']); ?>" target="_blank" rel="noopener noreferrer">
                            <i class="fas fa-arrow-up-right-from-square"></i> Abrir link de pago
                        </a>
                    </p>
                <?php endif; ?>

                <h3 style="margin-top: 28px;"><i class="fas fa-history"></i> Solicitudes recientes</h3>
                <?php if (empty($paymentHistory)): ?>
                    <p class="small">Aun no has enviado comprobantes de suscripcion.</p>
                <?php else: ?>
                    <div style="overflow-x:auto;">
                        <table class="history-table">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Plan</th>
                                    <th>Monto</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($paymentHistory as $paymentRequest): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y H:i', strtotime($paymentRequest['created_at'])); ?></td>
                                        <td><?php echo htmlspecialchars($paymentRequest['plan_type'] === 'annual' ? 'Anual' : 'Mensual'); ?></td>
                                        <td>$<?php echo number_format((float) ($paymentRequest['amount'] ?? 0), 2); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo htmlspecialchars($paymentRequest['status']); ?>">
                                                <?php echo htmlspecialchars($paymentRequest['status']); ?>
                                            </span>
                                            <?php if (!empty($paymentRequest['admin_notes'])): ?>
                                                <div class="small" style="margin-top:6px;"><?php echo htmlspecialchars($paymentRequest['admin_notes']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php include BASE_PATH . 'includes/pwa_register.php'; ?>
</body>
</html>
