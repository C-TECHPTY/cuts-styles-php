<?php
// login.php
require_once 'config/config.php';
require_once 'classes/User.php';

$error = null;

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    $user = new User();
    $user->email = $email;
    $user->password = $password;
    
    if($user->login()) {
        $_SESSION['user_id'] = $user->id;
        $_SESSION['user_email'] = $user->email;
        $_SESSION['user_rol'] = $user->rol;
        $_SESSION['user_nombre'] = $user->nombre;
        
        // Redirigir según el rol
        switch($user->rol) {
            case 'admin':
                header("Location: admin/dashboard.php");
                break;
            case 'barbero':
                header("Location: barbero.php");
                break;
            default:
                header("Location: cliente.php");
        }
        exit();
    } else {
        $error = "Email o contraseña incorrectos";
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
                <div class="alert"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label>Email</label>
                    <div class="input-group">
                        <i class="fas fa-envelope"></i>
                        <input type="email" name="email" required placeholder="admin@cutsstyles.com">
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
                Admin: admin@cutsstyles.com / Admin123<br>
                Cliente: cliente@test.com / cliente123<br>
                Barbero: barbero@test.com / barbero123
            </div>
        </div>
    </div>
</body>
</html>