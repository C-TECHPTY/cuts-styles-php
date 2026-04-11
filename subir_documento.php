<?php
// subir_documento.php
require_once 'config/config.php';
require_once 'classes/User.php';
require_once 'classes/Verification.php';

requireRole('barbero');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('barbero.php');
}

try {
    verificarCSRFToken($_POST['csrf_token'] ?? null);
} catch (Exception $e) {
    setFlash('danger', 'Sesion invalida. Intenta nuevamente.');
    redirect('barbero.php');
}

$user = new User();
$query = "SELECT id FROM barberos WHERE user_id = :user_id";
$stmt = $user->conn->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$barbero = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$barbero) {
    redirect('barbero.php');
}

$verification = new Verification();
$tipos = ['cedula', 'selfie', 'certificado'];
$subido = false;
$errores = [];

foreach ($tipos as $tipo) {
    if (isset($_FILES[$tipo]) && ($_FILES[$tipo]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        $resultado = $verification->subirDocumento($barbero['id'], $tipo, $_FILES[$tipo]);
        if ($resultado['success']) {
            $subido = true;
        } else {
            $errores[] = $resultado['message'];
        }
    }
}

if ($subido) {
    $update = "UPDATE barberos SET verificacion_status = 'en_revision' WHERE id = :id";
    $stmt = $user->conn->prepare($update);
    $stmt->bindParam(':id', $barbero['id']);
    $stmt->execute();
    setFlash('success', 'Documentos enviados para verificacion. Te contactaremos pronto.');
} elseif ($errores) {
    setFlash('danger', implode(' ', array_unique($errores)));
} else {
    setFlash('warning', 'No se selecciono ningun documento.');
}

redirect('barbero.php');
