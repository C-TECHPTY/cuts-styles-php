<?php
// login.php
require_once 'config/config.php';

$error = null;
$email = '';

// Generar token CSRF
$csrf_token = generarCSRFToken();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Verificar token CSRF
        verificarCSRFToken($_POST['csrf_token']);
        
        // Sanitizar entradas
        $email = sanitizarEmail($_POST['email']);
        $password = $_POST['password'];
        
        // Validar email
        if (!validarEmail($email)) {
            throw new Exception('Email inválido');
        }
        
        if (empty($password)) {
            throw new Exception('La contraseña es requerida');
        }
        
        // Intentar login
        require_once 'classes/User.php';
        
        $user = new User();
        $user->email = $email;
        $user->password = $password;
        
        if ($user->login()) {
            // Regenerar ID de sesión por seguridad
            session_regenerate_id(true);
            
            // Generar nuevo token CSRF después del login
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            
            $_SESSION['user_id'] = $user->id;
            $_SESSION['user_email'] = $user->email;
            $_SESSION['user_rol'] = $user->rol;
            $_SESSION['user_nombre'] = $user->nombre;
            $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
            
            // Redirigir según el rol
            switch ($user->rol) {
                case 'admin':
                    redirect('admin/dashboard.php');
                    break;
                case 'barbero':
                    redirect('barbero.php');
                    break;
                default:
                    redirect('cliente.php');
            }
        } else {
            throw new Exception('Email o contraseña incorrectos');
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
        logError($error, __FILE__, __LINE__);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Cuts & Styles</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 450px;
            overflow: hidden;
        }
        .login-header {
            background: #2C3E50;
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        .login-header i {
            font-size: 50px;
            color: #E74C3C;
            margin-bottom: 15px;
        }
        .login-header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        .login-header p {
            opacity: 0.8;
            font-size: 14px;
        }
        .login-form {
            padding: 40px 30px;
        }
        .form-group {
            margin-bottom: 25px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2C3E50;
        }
        .input-group {
            position: relative;
        }
        .input-group i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #95A5A6;
        }
        .input-group input {
            width: 100%;
            padding: 15px 15px 15px 45px;
            border: 2px solid #ECF0F1;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s;
        }
        .input-group input:focus {
            border-color: #E74C3C;
            outline: none;
        }
        .btn {
            width: 100%;
            padding: 15px;
            background: #E74C3C;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn:hover {
            background: #c0392b;
            transform: translateY(-2px);
        }
        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            background: #FDEDEC;
            color: #C0392B;
            border: 1px solid #FADBD8;
        }
        .register-link {
            text-align: center;
            margin-top: 20px;
            color: #7F8C8D;
        }
        .register-link a {
            color: #E74C3C;
            text-decoration: none;
        }
        .register-link a:hover {
            text-decoration: underline;
        }
        .demo-users {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #ECF0F1;
            font-size: 12px;
            color: #95A5A6;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <i class="fas fa-cut"></i>
            <h1>Cuts & Styles</h1>
            <p>Inicia sesión para continuar</p>
        </div>
        
        <div class="login-form">
            <?php if($error): ?>
                <div class="alert"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php $flash = getFlash(); if($flash): ?>
                <div class="alert alert-<?php echo $flash['type']; ?>">
                    <?php echo htmlspecialchars($flash['message']); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="form-group">
                    <label>Email</label>
                    <div class="input-group">
                        <i class="fas fa-envelope"></i>
                        <input type="email" name="email" required 
                               value="<?php echo htmlspecialchars($email); ?>"
                               placeholder="admin@cutsstyles.com">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Contraseña</label>
                    <div class="input-group">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="password" required placeholder="••••••••">
                    </div>
                </div>
                
                <button type="submit" class="btn">
                    <i class="fas fa-sign-in-alt"></i> Iniciar Sesión
                </button>
            </form>
            
            <div class="register-link">
                ¿No tienes cuenta? <a href="register.php">Regístrate aquí</a>
            </div>
            
            <div class="demo-users">
                <strong>Usuarios de prueba:</strong><br>
                👑 Admin: admin@cutsstyles.com / Admin123<br>
                👤 Cliente: cliente@test.com / cliente123<br>
                ✂️ Barbero: barbero@test.com / barbero123
            </div>
        </div>
    </div>
</body>
</html>