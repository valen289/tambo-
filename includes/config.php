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

define('MAIL_FALLBACK', false); // Cambia a true solo si tienes un servidor de correo local bien configurado.

// Umbrales de alerta para stock.
define('STOCK_ALERTA_MEDIA_PORCENTAJE', 50);
define('STOCK_ALERTA_CRITICA_PORCENTAJE', 20);
define('ALERTA_CRITICA_ENVIO_HORAS', 24);

// Si quieres enviar SMS vía email-to-SMS, configura aquí el dominio de pasarela.
// Ejemplo: 'txt.att.net' o 'sms.movistar.com'
define('SMS_GATEWAY_DOMAIN', '');
