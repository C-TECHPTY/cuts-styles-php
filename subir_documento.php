<?php
// subir_documento.php
require_once 'config/config.php';
require_once 'classes/Verification.php';

if(!isset($_SESSION['user_id']) || $_SESSION['user_rol'] != 'barbero') {
    redirect('login.php');
}

// Obtener ID del barbero
$user = new User();
$query = "SELECT id FROM barberos WHERE user_id = :user_id";
$stmt = $user->conn->prepare($query);
$stmt->bindParam(":user_id", $_SESSION['user_id']);
$stmt->execute();
$barbero = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$barbero) {
    redirect('barbero.php');
}

$verification = new Verification();

// Subir documentos
if(isset($_FILES['cedula']) && $_FILES['cedula']['error'] == 0) {
    $verification->subirDocumento($barbero['id'], 'cedula', $_FILES['cedula']);
}

if(isset($_FILES['selfie']) && $_FILES['selfie']['error'] == 0) {
    $verification->subirDocumento($barbero['id'], 'selfie', $_FILES['selfie']);
}

if(isset($_FILES['certificado']) && $_FILES['certificado']['error'] == 0) {
    $verification->subirDocumento($barbero['id'], 'certificado', $_FILES['certificado']);
}

// Actualizar estado a "en_revision"
$update = "UPDATE barberos SET verificacion_status = 'en_revision' WHERE id = :id";
$stmt = $user->conn->prepare($update);
$stmt->bindParam(":id", $barbero['id']);
$stmt->execute();

setFlash('success', 'Documentos enviados para verificación. Te contactaremos pronto.');
redirect('barbero.php');
?>