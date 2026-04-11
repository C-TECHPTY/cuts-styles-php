<?php
// ver_servicio.php
require_once 'config/config.php';
require_once 'config/database.php';

$servicio_id = $_GET['id'] ?? 0;

if(!$servicio_id) {
    echo "<p>Servicio no encontrado</p>";
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    $query = "SELECT s.*, 
              c.id as cliente_id, c.user_id as cliente_user_id, cu.nombre as cliente_nombre, cu.telefono as cliente_telefono,
              b.id as barbero_id, b.user_id as barbero_user_id, bu.nombre as barbero_nombre, bu.telefono as barbero_telefono
              FROM servicios s
              LEFT JOIN clientes c ON s.cliente_id = c.id
              LEFT JOIN users cu ON c.user_id = cu.id
              LEFT JOIN barberos b ON s.barbero_id = b.id
              LEFT JOIN users bu ON b.user_id = bu.id
              WHERE s.id = :id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(":id", $servicio_id);
    $stmt->execute();
    $servicio = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if(!$servicio) {
        echo "<p>Servicio no encontrado</p>";
        exit;
    }
    $sessionRole = $_SESSION['user_rol'] ?? null;
    $sessionUserId = (int) ($_SESSION['user_id'] ?? 0);
    $allowed = false;

    if ($sessionRole === 'admin') {
        $allowed = true;
    } elseif ($sessionRole === 'cliente' && (int) ($servicio['cliente_user_id'] ?? 0) === $sessionUserId) {
        $allowed = true;
    } elseif ($sessionRole === 'barbero' && (int) ($servicio['barbero_user_id'] ?? 0) === $sessionUserId) {
        $allowed = true;
    }

    if (!$allowed) {
        http_response_code(403);
        echo "<p>No tienes permiso para ver este servicio</p>";
        exit;
    }
    ?>
    <style>
        .detalle-row {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
            display: flex;
            flex-wrap: wrap;
        }
        .detalle-label {
            width: 120px;
            font-weight: bold;
            color: #2C3E50;
        }
        .detalle-value {
            flex: 1;
            color: #555;
        }
        .estado-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 12px;
        }
        .estado-pendiente { background: #FEF5E7; color: #F39C12; }
        .estado-aceptado { background: #E8F4FC; color: #3498DB; }
        .estado-en_proceso { background: #E8F4FC; color: #3498DB; }
        .estado-completado { background: #D5F4E6; color: #27AE60; }
        .estado-cancelado { background: #FDEDEC; color: #E74C3C; }
        .close-btn {
            float: right;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #999;
            padding: 0 5px;
        }
        .close-btn:hover { color: #E74C3C; }
        @media (max-width: 600px) {
            .detalle-label { width: 100%; margin-bottom: 5px; }
            .detalle-value { width: 100%; }
        }
    </style>
    
    <div style="position: relative; padding: 10px;">
        <button class="close-btn" onclick="cerrarModal()">&times;</button>
        <h3 style="margin-bottom: 20px;">Detalle del Servicio #<?php echo $servicio['id']; ?></h3>
        
        <div class="detalle-row">
            <div class="detalle-label">Estado:</div>
            <div class="detalle-value">
                <span class="estado-badge estado-<?php echo $servicio['estado']; ?>">
                    <?php 
                    $estados = [
                        'pendiente' => '⏳ Pendiente',
                        'aceptado' => '✅ Aceptado',
                        'en_proceso' => '✂️ En Proceso',
                        'completado' => '🎉 Completado',
                        'cancelado' => '❌ Cancelado'
                    ];
                    echo $estados[$servicio['estado']] ?? ucfirst($servicio['estado']);
                    ?>
                </span>
            </div>
        </div>
        
        <div class="detalle-row">
            <div class="detalle-label">Tipo:</div>
            <div class="detalle-value"><?php echo htmlspecialchars($servicio['tipo']); ?></div>
        </div>
        
        <div class="detalle-row">
            <div class="detalle-label">Cliente:</div>
            <div class="detalle-value">
                <strong><?php echo htmlspecialchars($servicio['cliente_nombre'] ?? 'N/A'); ?></strong><br>
                <small>📞 Teléfono: <?php echo htmlspecialchars($servicio['cliente_telefono'] ?? 'No disponible'); ?></small>
            </div>
        </div>
        
        <?php if($servicio['barbero_nombre']): ?>
        <div class="detalle-row">
            <div class="detalle-label">Barbero:</div>
            <div class="detalle-value">
                <strong><?php echo htmlspecialchars($servicio['barbero_nombre']); ?></strong><br>
                <small>📞 Teléfono: <?php echo htmlspecialchars($servicio['barbero_telefono'] ?? 'No disponible'); ?></small>
            </div>
        </div>
        <?php else: ?>
        <div class="detalle-row">
            <div class="detalle-label">Barbero:</div>
            <div class="detalle-value">Buscando barbero disponible...</div>
        </div>
        <?php endif; ?>
        
        <div class="detalle-row">
            <div class="detalle-label">Solicitado:</div>
            <div class="detalle-value"><?php echo date('d/m/Y H:i', strtotime($servicio['fecha_solicitud'])); ?></div>
        </div>
        
        <?php if($servicio['fecha_aceptacion']): ?>
        <div class="detalle-row">
            <div class="detalle-label">Aceptado:</div>
            <div class="detalle-value"><?php echo date('d/m/Y H:i', strtotime($servicio['fecha_aceptacion'])); ?></div>
        </div>
        <?php endif; ?>
        
        <?php if($servicio['fecha_fin']): ?>
        <div class="detalle-row">
            <div class="detalle-label">Completado:</div>
            <div class="detalle-value"><?php echo date('d/m/Y H:i', strtotime($servicio['fecha_fin'])); ?></div>
        </div>
        <?php endif; ?>
        
        <?php if($servicio['tiempo_estimado']): ?>
        <div class="detalle-row">
            <div class="detalle-label">Tiempo estimado:</div>
            <div class="detalle-value"><?php echo $servicio['tiempo_estimado']; ?> minutos</div>
        </div>
        <?php endif; ?>
        
        <?php if($servicio['tiempo_real']): ?>
        <div class="detalle-row">
            <div class="detalle-label">Duración real:</div>
            <div class="detalle-value"><?php echo $servicio['tiempo_real']; ?> minutos</div>
        </div>
        <?php endif; ?>
        
        <?php if($servicio['notas']): ?>
        <div class="detalle-row">
            <div class="detalle-label">Notas del cliente:</div>
            <div class="detalle-value"><?php echo nl2br(htmlspecialchars($servicio['notas'])); ?></div>
        </div>
        <?php endif; ?>
        
        <?php if($servicio['comentario_barbero']): ?>
        <div class="detalle-row">
            <div class="detalle-label">Comentario del barbero:</div>
            <div class="detalle-value"><?php echo nl2br(htmlspecialchars($servicio['comentario_barbero'])); ?></div>
        </div>
        <?php endif; ?>
        
        <?php if($servicio['calificacion']): ?>
        <div class="detalle-row">
            <div class="detalle-label">Calificación:</div>
            <div class="detalle-value"><?php echo str_repeat('⭐', $servicio['calificacion']); ?></div>
        </div>
        <?php endif; ?>
        <?php if(in_array($servicio['estado'], ['aceptado', 'en_proceso'], true) && !empty($servicio['barbero_id'])): ?>
        <div class="detalle-row">
            <div class="detalle-label">Chat:</div>
            <div class="detalle-value">
                <a href="chat_servicio.php?servicio_id=<?php echo (int) $servicio['id']; ?>" style="display:inline-block; padding:8px 14px; background:#2C3E50; color:#fff; border-radius:8px; text-decoration:none;">
                    Abrir chat del servicio
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <script>
        function cerrarModal() {
            // Buscar el modal padre y cerrarlo
            var modal = document.getElementById('detalles-modal');
            if(modal) {
                modal.style.display = 'none';
            }
            // También buscar por clase si es necesario
            var modals = document.getElementsByClassName('modal');
            for(var i = 0; i < modals.length; i++) {
                if(modals[i].style.display === 'flex') {
                    modals[i].style.display = 'none';
                }
            }
        }
        
        // Cerrar con tecla ESC
        document.addEventListener('keydown', function(e) {
            if(e.key === 'Escape') {
                cerrarModal();
            }
        });
    </script>
    <?php
} catch(PDOException $e) {
    echo "<p style='color:red; padding:20px;'>Error al cargar detalles: " . $e->getMessage() . "</p>";
}
?>
