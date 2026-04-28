<?php
// Configuración de correo SMTP para notificaciones.
// Completa estos valores con tu cuenta de Gmail y una contraseña de aplicación.
// Para Gmail, usa una contraseña de aplicación y habilita el acceso SMTP.
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');
define('SMTP_USER', 'tu-email@gmail.com');
define('SMTP_PASS', 'tu-app-password');
define('SMTP_FROM_EMAIL', 'no-reply@tambo.local');
define('SMTP_FROM_NAME', 'SiCoDiEt');

// Si quieres enviar SMS vía email-to-SMS, configura aquí el dominio de pasarela.
// Ejemplo: 'txt.att.net' o 'sms.movistar.com'
define('SMS_GATEWAY_DOMAIN', '');
