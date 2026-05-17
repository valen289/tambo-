<?php
session_start();
require_once 'includes/db.php';

if (isset($_SESSION['usuario_id'])) {
    header("Location: silos.php");
    exit();
}

$mensaje = '';
$error = '';
$tipoMensaje = '';
$tokenValido = false;

$token = $_GET['token'] ?? '';

if (empty($token)) {
    $error = 'Token de recuperación no válido.';
} else {
    $stmt = $conn->prepare("SELECT id, nombre FROM usuarios WHERE reset_token = ? AND reset_token_expiry > NOW() AND activo = TRUE");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $tokenValido = true;
        $usuario = $result->fetch_assoc();
    } else {
        $error = 'El enlace de recuperación es inválido o ha expirado. Solicitá uno nuevo.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenValido) {
    $password = $_POST['password'] ?? '';
    $confirmar = $_POST['confirmar'] ?? '';

    if (empty($password) || empty($confirmar)) {
        $error = 'Todos los campos son obligatorios.';
    } elseif (strlen($password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres.';
    } elseif ($password !== $confirmar) {
        $error = 'Las contraseñas no coinciden.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $updateStmt = $conn->prepare("UPDATE usuarios SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE reset_token = ?");
        $updateStmt->bind_param("ss", $hash, $token);

        if ($updateStmt->execute()) {
            $tipoMensaje = 'success';
            $mensaje = 'Contraseña actualizada correctamente. Ya podés iniciar sesión.';
            $tokenValido = false;
        } else {
            $error = 'Error al actualizar la contraseña. Intentá nuevamente.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SiCoDiEt - Nueva Contraseña</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-box">
            <div class="logo">
                <svg class="logo-icon-large" viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="4" y1="58" x2="60" y2="58"/>
                    <rect x="6" y="24" width="10" height="34"/>
                    <rect x="8" y="28" width="6" height="4"/>
                    <rect x="8" y="36" width="6" height="4"/>
                    <rect x="8" y="44" width="6" height="4"/>
                    <rect x="8" y="52" width="6" height="4"/>
                    <path d="M6 24c8-6 20-8 32-8s24 2 32 8"/>
                    <rect x="18" y="16" width="18" height="42"/>
                    <rect x="22" y="22" width="4" height="5"/>
                    <rect x="28" y="22" width="4" height="5"/>
                    <rect x="38" y="12" width="18" height="46"/>
                    <rect x="42" y="18" width="4" height="5"/>
                    <rect x="48" y="18" width="4" height="5"/>
                    <rect x="42" y="28" width="4" height="5"/>
                    <rect x="48" y="28" width="4" height="5"/>
                </svg>
                <h1>Nueva Contraseña</h1>
                <?php if ($tokenValido): ?>
                    <p>Ingresá tu nueva contraseña para <?php echo htmlspecialchars($usuario['nombre']); ?></p>
                <?php else: ?>
                    <p>Restablecimiento de contraseña</p>
                <?php endif; ?>
            </div>
            
            <?php if ($mensaje): ?>
                <div class="alert alert-success">
                    <i class="fa-solid fa-circle-check"></i> <?php echo htmlspecialchars($mensaje); ?>
                    <p style="margin-top: 10px;">
                        <a href="index.php" style="color: #588157; font-weight: 600;">
                            <i class="fa-solid fa-arrow-right"></i> Ir al inicio de sesión
                        </a>
                    </p>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fa-solid fa-circle-exclamation"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($tokenValido): ?>
            <form method="POST" class="login-form">
                <div class="form-group">
                    <label for="password"><i class="fa-solid fa-lock"></i> Nueva Contraseña</label>
                    <input type="password" id="password" name="password" required 
                           placeholder="Mínimo 6 caracteres" minlength="6">
                </div>
                
                <div class="form-group">
                    <label for="confirmar"><i class="fa-solid fa-lock"></i> Confirmar Contraseña</label>
                    <input type="password" id="confirmar" name="confirmar" required 
                           placeholder="Repetí tu contraseña" minlength="6">
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fa-solid fa-check"></i> Cambiar Contraseña
                </button>
            </form>
            <?php else: ?>
                <?php if (!$mensaje): ?>
                <p style="text-align:center; margin-top: 20px; color: #7f8c8d; font-size: 14px;">
                    <a href="recuperar.php" style="color: #588157; font-weight: 600; text-decoration: none;">
                        <i class="fa-solid fa-rotate"></i> Solicitar nuevo enlace
                    </a>
                </p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
