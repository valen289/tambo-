<?php
session_start();

// Si hay sesión activa, cerrarla para permitir recuperación de contraseña
if (isset($_SESSION['usuario_id'])) {
    session_destroy();
}

require_once 'includes/db.php';
require_once 'includes/functions.php';

$mensaje = '';
$error = '';
$tipoMensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'enviar_token') {
        $cedula = trim($_POST['cedula'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if (empty($cedula) || empty($email)) {
            $error = 'Todos los campos son obligatorios.';
        } else {
            $stmt = $conn->prepare("SELECT id, nombre, email FROM usuarios WHERE cedula = ? AND email = ? AND activo = TRUE");
            $stmt->bind_param("ss", $cedula, $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $usuario = $result->fetch_assoc();
                $token = bin2hex(random_bytes(32));
                $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

                $updateStmt = $conn->prepare("UPDATE usuarios SET reset_token = ?, reset_token_expiry = ? WHERE id = ?");
                $updateStmt->bind_param("ssi", $token, $expiry, $usuario['id']);
                $updateStmt->execute();

                $resetUrl = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/resetear.php?token=" . $token;

                $asunto = "SiCoDiEt - Recuperar contraseña";
                $cuerpo = "Hola {$usuario['nombre']},\n\n";
                $cuerpo .= "Recibimos una solicitud para restablecer tu contraseña.\n";
                $cuerpo .= "Hacé clic en el siguiente enlace para crear una nueva contraseña:\n\n";
                $cuerpo .= $resetUrl . "\n\n";
                $cuerpo .= "Este enlace es válido por 1 hora.\n";
                $cuerpo .= "Si no solicitaste este cambio, ignorá este mensaje.\n\n";
                $cuerpo .= "Saludos,\nEquipo SiCoDiEt";

                $enviado = enviarCorreo($usuario['email'], $asunto, $cuerpo);

                if ($enviado) {
                    $tipoMensaje = 'success';
                    $mensaje = 'Se envió un enlace de recuperación a tu correo electrónico. Revisá tu bandeja de entrada.';
                } else {
                    $tipoMensaje = 'info';
                    $mensaje = 'No se pudo enviar el correo. Contactá al administrador para restablecer tu contraseña.';
                }
            } else {
                $error = 'No se encontró un usuario con esa cédula y correo electrónico.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SiCoDiEt - Recuperar Contraseña</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-box">
            <div class="logo">
              
              
                <h1>Recuperar Contraseña</h1>
                <p>Ingresá tu cédula y correo para recibir un enlace de recuperación</p>
            </div>
            
            <?php if ($mensaje): ?>
                <div class="alert <?php echo $tipoMensaje === 'success' ? 'alert-success' : 'alert-info'; ?>">
                    <i class="fa-solid fa-circle-info"></i> <?php echo htmlspecialchars($mensaje); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fa-solid fa-circle-exclamation"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="login-form">
                <input type="hidden" name="accion" value="enviar_token">
                
                <div class="form-group">
                    <label for="cedula"><i class="fa-solid fa-id-card"></i> Cédula de Identidad</label>
                    <input type="text" id="cedula" name="cedula" required 
                           placeholder="Ej: 12345678" maxlength="8" 
                           value="<?php echo htmlspecialchars($_POST['cedula'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="email"><i class="fa-solid fa-envelope"></i> Correo Electrónico</label>
                    <input type="email" id="email" name="email" required 
                           placeholder="tu@email.com"
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fa-solid fa-paper-plane"></i> Enviar enlace de recuperación
                </button>
                
                <p style="text-align:center; margin-top: 20px; color: #7f8c8d; font-size: 14px;">
                    <a href="index.php" style="color: #588157; font-weight: 600; text-decoration: none;">
                        <i class="fa-solid fa-arrow-left"></i> Volver al inicio de sesión
                    </a>
                </p>
            </form>
        </div>
    </div>
</body>
</html>
