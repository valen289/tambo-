<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

verificarSesion();

$mensaje_exito = '';
$mensaje_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    if ($accion === 'enviar_prueba') {
        $destinatario = $_SESSION['email'] ?? '';
        
        if (empty($destinatario)) {
            $mensaje_error = 'No tienes un correo registrado en tu perfil. Por favor actualiza tu email en el panel de usuario.';
        } else {
            $asunto = 'Prueba de Email - SiCoDiEt';
            $mensaje = "Hola " . $_SESSION['nombre'] . ",\n\n";
            $mensaje .= "Este es un email de prueba para validar que tu correo está configurado correctamente en el sistema SiCoDiEt.\n\n";
            $mensaje .= "Si recibes este email, significa que tu configuración SMTP está funcionando correctamente.\n\n";
            $mensaje .= "Fecha y hora del envío: " . date('d/m/Y H:i:s') . "\n";
            $mensaje .= "Remitente: " . $_SESSION['nombre'] . " (" . $_SESSION['cedula'] . ")\n\n";
            $mensaje .= "Saludos,\nEquipo SiCoDiEt";
            
            $enviado = enviarCorreo($destinatario, $asunto, $mensaje);
            
            if ($enviado) {
                $mensaje_exito = '✓ Email de prueba enviado correctamente a ' . htmlspecialchars($destinatario);
            } else {
                $mensaje_error = '✗ Error al enviar el email. Verifica la configuración SMTP en includes/config.php';
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
    <title>Prueba de Email - SiCoDiEt</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="card" style="max-width: 600px; margin: 30px auto;">
            <div class="card-header">
                <h2> Prueba de Email</h2>
            </div>
            
            <div class="card-body">
                <p style="color: #666; margin-bottom: 20px;">
                    Esta herramienta te permite enviar un email de prueba para validar que tu configuración SMTP está funcionando correctamente.
                </p>
                
                <?php if (!empty($mensaje_exito)): ?>
                    <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #c3e6cb;">
                        <?php echo $mensaje_exito; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($mensaje_error)): ?>
                    <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #f5c6cb;">
                        <?php echo $mensaje_error; ?>
                    </div>
                <?php endif; ?>
                
                <div style="background: #f5f7f4; padding: 20px; border-radius: 5px; margin-bottom: 20px;">
                    <h4 style="margin-top: 0;">Información de envío:</h4>
                    <p><strong>Para:</strong> <?php echo htmlspecialchars($_SESSION['email'] ?? 'No registrado'); ?></p>
                    <p><strong>Usuario:</strong> <?php echo htmlspecialchars($_SESSION['nombre']); ?> (<?php echo htmlspecialchars($_SESSION['cedula'] ?? 'N/A'); ?>)</p>
                    <p><strong>Asunto:</strong> Prueba de Email - SiCoDiEt</p>
                </div>
                
                <form method="POST" style="display: flex; gap: 10px;">
                    <input type="hidden" name="accion" value="enviar_prueba">
                    
                    <?php if (!empty($_SESSION['email'])): ?>
                        <button type="submit" class="btn btn-primary" style="flex: 1;">
                             Enviar Email de Prueba
                        </button>
                    <?php else: ?>
                        <button type="submit" disabled class="btn" style="flex: 1; opacity: 0.5; cursor: not-allowed;">
                             Completa tu email primero
                        </button>
                    <?php endif; ?>
                    
                    <a href="dashboard.php" class="btn btn-secondary" style="flex: 1; text-decoration: none; text-align: center; display: flex; align-items: center; justify-content: center;">
                        ← Volver al Dashboard
                    </a>
                </form>
                
                <div style="margin-top: 30px; border-top: 1px solid #ddd; padding-top: 20px;">
                    <h4> Configuración requerida:</h4>
                    <p style="font-size: 13px; color: #666; margin-bottom: 10px;">
                        Para que los emails se envíen correctamente, asegúrate de que <code>includes/config.php</code> tenga configurados estos valores:
                    </p>
                    <ul style="font-size: 13px; color: #666; margin-left: 20px;">
                        <li><strong>SMTP_USER</strong>: tu-email@gmail.com</li>
                        <li><strong>SMTP_PASS</strong>: contraseña de aplicación de Gmail</li>
                        <li><strong>SMTP_HOST</strong>: smtp.gmail.com</li>
                        <li><strong>SMTP_PORT</strong>: 587</li>
                        <li><strong>SMTP_FROM_EMAIL</strong>: el email del remitente</li>
                    </ul>
                </div>
                
                <div style="margin-top: 20px; background: #e7f3ff; padding: 15px; border-radius: 5px; border-left: 4px solid #2196F3;">
                    <p style="font-size: 13px; color: #1976D2; margin: 0;">
                        <strong>💡 Tip:</strong> Si no recibas el email, revisa tu carpeta de spam. 
                        Si el error persiste, verifica los valores en <code>includes/config.php</code> y los logs de errores de PHP.
                    </p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
