<?php
session_start();
require_once 'includes/db.php';

if (isset($_SESSION['usuario_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cedula = $_POST['cedula'];
    $password = $_POST['password'];
    
    $stmt = $conn->prepare("SELECT id, cedula, nombre, password, rol FROM usuarios WHERE cedula = ? AND activo = TRUE");
    $stmt->bind_param("s", $cedula);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $usuario = $result->fetch_assoc();
        if (password_verify($password, $usuario['password'])) {
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['nombre'] = $usuario['nombre'];
            $_SESSION['rol'] = $usuario['rol'];
            $_SESSION['cedula'] = $usuario['cedula'];
            
            $conn->query("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = {$usuario['id']}");
            
            header("Location: dashboard.php");
            exit();
        }
    }
    
    $error = "Cédula o contraseña incorrectos";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SiCoDiEt - Login</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-box">
            <div class="logo">
                <span class="logo-icon"></span>
                <h1>Bienvenido</h1>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" class="login-form">
                <div class="form-group">
                    <label for="cedula">Cédula de Identidad</label>
                    <input type="text" id="cedula" name="cedula" required 
                           placeholder="Ej: 12345678" maxlength="8">
                </div>
                
                <div class="form-group">
                    <label for="password">Contraseña</label>
                    <input type="password" id="password" name="password" required 
                           placeholder="Ingrese su contraseña">
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">Ingresar</button>
                <p style="text-align:center; margin-top: 20px; color: #7f8c8d; font-size: 14px;">
    ¿No tenés cuenta? <a href="register.php" style="color: #9a7f65; font-weight: 600;">Registrarse</a>
</p>
            </form>
        </div>
    </div>
</body>
</html>