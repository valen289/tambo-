<?php
session_start();
require_once 'includes/db.php';

if (isset($_SESSION['usuario_id'])) {
    header("Location: silos.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cedula = trim($_POST['cedula'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT id, cedula, nombre, password, rol, email, telefono FROM usuarios WHERE cedula = ? AND activo = TRUE");
    $stmt->bind_param("s", $cedula);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows === 1) {
        $usuario = $result->fetch_assoc();
        if (password_verify($password, $usuario['password'])) {
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['nombre'] = $usuario['nombre'];
            $_SESSION['rol'] = $usuario['rol'];
            $_SESSION['cedula'] = $usuario['cedula'];
            $_SESSION['email'] = $usuario['email'];
            $_SESSION['telefono'] = $usuario['telefono'];

            header("Location: silos.php");
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-box">
            <div class="logo">
               
                
                <h1>Bienvenido</h1>
                <p>Ingresá tus credenciales para acceder al sistema</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fa-solid fa-circle-exclamation"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="login-form">
                <div class="form-group">
                    <label for="cedula"><i class="fa-solid fa-id-card"></i> Cédula de Identidad</label>
                    <input type="text" id="cedula" name="cedula" required 
                           placeholder="Ej: 12345678" maxlength="8"
                           value="<?php echo htmlspecialchars($_POST['cedula'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="password"><i class="fa-solid fa-lock"></i> Contraseña</label>
                    <input type="password" id="password" name="password" required 
                           placeholder="Ingrese su contraseña">
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fa-solid fa-right-to-bracket"></i> Ingresar
                </button>
                
                <p style="text-align:center; margin-top: 15px; color: #7f8c8d; font-size: 14px;">
                    <a href="recuperar.php" style="color: #588157; font-weight: 600; text-decoration: none;">
                        <i class="fa-solid fa-key"></i> ¿Olvidaste tu contraseña?
                    </a>
                </p>
                
                <p style="text-align:center; margin-top: 10px; color: #7f8c8d; font-size: 14px;">
                    ¿No tenés cuenta? <a href="register.php" style="color: #9a7f65; font-weight: 600; text-decoration: none;">
                        <i class="fa-solid fa-user-plus"></i> Registrarse
                    </a>
                </p>
            </form>
        </div>
    </div>
</body>
</html>
