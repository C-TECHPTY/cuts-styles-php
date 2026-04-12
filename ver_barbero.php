<?php
// ver_barbero.php
require_once 'config/config.php';
require_once 'classes/User.php';

$barbero_id = $_GET['id'] ?? 0;

$user = new User();
$query = "SELECT b.*, u.nombre, u.email, u.telefono 
          FROM barberos b
          JOIN users u ON b.user_id = u.id
          WHERE b.id = :id AND b.verificacion_status = 'verificado'";
$stmt = $user->conn->prepare($query);
$stmt->bindParam(":id", $barbero_id);
$stmt->execute();
$barbero = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$barbero) {
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($barbero['nombre']); ?> - Cuts & Styles</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include BASE_PATH . 'includes/pwa_head.php'; ?>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            padding: 2rem;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
        }
        .card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .header {
            background: linear-gradient(135deg, #2C3E50, #1a252f);
            color: white;
            text-align: center;
            padding: 2rem;
        }
        .avatar {
            width: 100px;
            height: 100px;
            background: #E74C3C;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 3rem;
        }
        .content {
            padding: 2rem;
        }
        .info-row {
            padding: 0.8rem 0;
            border-bottom: 1px solid #eee;
        }
        .rating {
            color: #ffc107;
            margin: 1rem 0;
        }
        .btn {
            display: block;
            width: 100%;
            padding: 1rem;
            background: #E74C3C;
            color: white;
            text-align: center;
            text-decoration: none;
            border-radius: 8px;
            margin-top: 1rem;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <div class="avatar">
                    <i class="fas fa-user-tie"></i>
                </div>
                <h1><?php echo htmlspecialchars($barbero['nombre']); ?></h1>
                <div class="rating">
                    <?php 
                    $rating = round($barbero['calificacion_promedio'], 1);
                    for($i = 1; $i <= 5; $i++): 
                        if($i <= $rating): ?>
                            <i class="fas fa-star"></i>
                        <?php elseif($i - 0.5 <= $rating): ?>
                            <i class="fas fa-star-half-alt"></i>
                        <?php else: ?>
                            <i class="far fa-star"></i>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <span>(<?php echo $barbero['total_calificaciones']; ?> calificaciones)</span>
                </div>
            </div>
            <div class="content">
                <div class="info-row">
                    <strong>Especialidad:</strong> <?php echo $barbero['especialidad'] ?? 'Barbero Profesional'; ?>
                </div>
                <div class="info-row">
                    <strong>Experiencia:</strong> <?php echo $barbero['experiencia']; ?> años
                </div>
                <div class="info-row">
                    <strong>Teléfono:</strong> <?php echo $barbero['telefono']; ?>
                </div>
                <div class="info-row">
                    <strong>Servicios realizados:</strong> <?php echo $barbero['total_servicios']; ?>
                </div>
                <?php if($barbero['descripcion']): ?>
                <div class="info-row">
                    <strong>Sobre mí:</strong><br>
                    <?php echo nl2br(htmlspecialchars($barbero['descripcion'])); ?>
                </div>
                <?php endif; ?>
                <a href="register.php" class="btn">Solicitar Servicio</a>
            </div>
        </div>
    </div>
    <?php include BASE_PATH . 'includes/pwa_register.php'; ?>
</body>
</html>
