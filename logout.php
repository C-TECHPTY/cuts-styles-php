<?php
// logout.php
require_once 'config/config.php';

// Verificar token CSRF si viene por POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['csrf_token'])) {
            verificarCSRFToken($_POST['csrf_token']);
        }
    } catch (Exception $e) {
        // Si hay error, igual continuar con logout
        error_log("CSRF error en logout: " . $e->getMessage());
    }
}

// Guardar información para log
$user_id = $_SESSION['user_id'] ?? null;
$user_email = $_SESSION['user_email'] ?? null;

// Limpiar todas las variables de sesión
$_SESSION = array();

// Destruir la cookie de sesión
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destruir la sesión
session_destroy();

// Log de cierre de sesión
if ($user_id) {
    error_log("Logout: Usuario $user_email (ID: $user_id) cerró sesión en " . date('Y-m-d H:i:s'));
}

// Regenerar ID de sesión por seguridad
session_start();
session_regenerate_id(true);

// Redirigir al login
header("Location: login.php");
exit();
?>