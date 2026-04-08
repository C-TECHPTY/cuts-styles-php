<?php
require_once 'config/config.php';
require_once 'classes/User.php';

$error = null;
$success = null;

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user = new User();
    $user->email = $_POST['email'];
    $user->password = $_POST['password'];
    $user->nombre = $_POST['nombre'];
    $user->telefono = $_POST['telefono'];
    $user->rol = $_POST['rol'];
    
    // Validaciones
    if($_POST['password'] !== $_POST['confirm_password']) {
        $error = "Las contraseñas no coinciden";
    } elseif(strlen($_POST['password']) < 6) {
        $error = "La contraseña debe tener al menos 6 caracteres";
    } else {
        if($user->register()) {
            $success = "Cuenta creada exitosamente. Ahora puedes iniciar sesión.";
        } else {
            $error = "Error al crear la cuenta. El email puede estar en uso.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - Cuts & Styles</title>
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
        .register-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 500px;
            overflow: hidden;
        }
        .register-header {
            background: #2C3E50;
            color: white;
            padding: 30px;
            text-align: center;
        }
        .register-header i {
            font-size: 50px;
            color: #E74C3C;
            margin-bottom: 10px;
        }
        .register-form {
            padding: 30px;
        }
        .form-group {
            margin-bottom: 20px;
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
        .input-group input, .input-group select {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 2px solid #ECF0F1;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s;
        }
        .input-group input:focus, .input-group select:focus {
            border-color: #E74C3C;
            outline: none;
        }
        .role-options {
            display: flex;
            gap: 15px;
            margin-top: 10px;
        }
        .role-option {
            flex: 1;
            padding: 15px;
            border: 2px solid #ECF0F1;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        .role-option.selected {
            border-color: #E74C3C;
            background: #FDEDEC;
        }
        .role-option i {
            font-size: 24px;
            color: #E74C3C;
            margin-bottom: 5px;
        }
        .role-option input {
            display: none;
        }
        .btn {
            width: 100%;
            padding: 15px;
            background: #27AE60;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn:hover {
            background: #219653;
            transform: translateY(-2px);
        }
        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alert-error {
            background: #FDEDEC;
            color: #C0392B;
            border: 1px solid #FADBD8;
        }
        .alert-success {
            background: #D5F4E6;
            color: #27AE60;
            border: 1px solid #A3E4D7;
        }
        .login-link {
            text-align: center;
            margin-top: 20px;
            color: #7F8C8D;
        }
        .login-link a {
            color: #E74C3C;
            text-decoration: none;
        }
        .terms {
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <i class="fas fa-user-plus"></i>
            <h1>Crear Cuenta</h1>
            <p>Únete a Cuts & Styles</p>
        </div>
        
        <div class="register-form">
            <?php if($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label>Nombre Completo</label>
                    <div class="input-group">
                        <i class="fas fa-user"></i>
                        <input type="text" name="nombre" required placeholder="Tu nombre completo">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Email</label>
                    <div class="input-group">
                        <i class="fas fa-envelope"></i>
                        <input type="email" name="email" required placeholder="tucorreo@ejemplo.com">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Teléfono</label>
                    <div class="input-group">
                        <i class="fas fa-phone"></i>
                        <input type="tel" name="telefono" required placeholder="+57 300 123 4567">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Contraseña</label>
                    <div class="input-group">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="password" required placeholder="Mínimo 6 caracteres">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Confirmar Contraseña</label>
                    <div class="input-group">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="confirm_password" required placeholder="Repite tu contraseña">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Registrarse como:</label>
                    <div class="role-options">
                        <label class="role-option" id="cliente-option">
                            <i class="fas fa-user"></i>
                            <div>Cliente</div>
                            <input type="radio" name="rol" value="cliente" checked>
                        </label>
                        <label class="role-option" id="barbero-option">
                            <i class="fas fa-cut"></i>
                            <div>Barbero</div>
                            <input type="radio" name="rol" value="barbero">
                        </label>
                    </div>
                </div>
                
                <div class="form-group terms">
                    <input type="checkbox" required>
                    <label>Acepto los <a href="#">Términos y Condiciones</a></label>
                </div>
                
                <button type="submit" class="btn">
                    <i class="fas fa-user-plus"></i> Registrarse
                </button>
            </form>
            
            <div class="login-link">
                ¿Ya tienes cuenta? <a href="login.php">Inicia sesión aquí</a>
            </div>
        </div>
    </div>
    
    <script>
        // Selección de rol con estilo visual
        document.querySelectorAll('.role-option').forEach(option => {
            option.addEventListener('click', function() {
                document.querySelectorAll('.role-option').forEach(opt => opt.classList.remove('selected'));
                this.classList.add('selected');
                this.querySelector('input').checked = true;
            });
        });
        
        // Seleccionar la opción por defecto
        document.getElementById('cliente-option').classList.add('selected');
    </script>
</body>
</html>