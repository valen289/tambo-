<?php
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

/**
 * Formatea una fecha en formato legible
 * @param string $fecha Fecha en formato YYYY-MM-DD
 * @return string Fecha formateada en español
 */
function fechaFormateada($fecha) {
    $meses = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
    ];
    
    if (empty($fecha)) {
        return '';
    }
    
    $timestamp = strtotime($fecha);
    $dia = date('d', $timestamp);
    $mes = $meses[(int)date('m', $timestamp)];
    $año = date('Y', $timestamp);
    
    return "$dia de $mes de $año";
}

/**
 * Formatea una fecha y hora en formato legible
 * @param string $fechaHora Fecha y hora en formato YYYY-MM-DD HH:MM:SS
 * @return string Fecha y hora formateada en español
 */
function fechaHoraFormateada($fechaHora) {
    $meses = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
    ];
    
    if (empty($fechaHora)) {
        return '';
    }
    
    $timestamp = strtotime($fechaHora);
    $dia = date('d', $timestamp);
    $mes = $meses[(int)date('m', $timestamp)];
    $año = date('Y', $timestamp);
    $hora = date('H:i', $timestamp);
    
    return "$dia de $mes de $año a las $hora";
}

/**
 * Obtiene resumen de consumo por período
 * @param object $conn Conexión a la BD
 * @param string $periodo Período: '7d', '30d', '3m', '6m', '1y', o rango 'YYYY-MM-DD,YYYY-MM-DD'
 * @return array Resumen de consumo por insumo
 */
function obtenerResumenConsumo($conn, $periodo = '30d') {
    $fecha_fin = date('Y-m-d');
    
    // Determinar fecha inicio según período
    if ($periodo === '7d') {
        $fecha_inicio = date('Y-m-d', strtotime('-7 days'));
    } elseif ($periodo === '30d') {
        $fecha_inicio = date('Y-m-d', strtotime('-30 days'));
    } elseif ($periodo === '3m') {
        $fecha_inicio = date('Y-m-d', strtotime('-3 months'));
    } elseif ($periodo === '6m') {
        $fecha_inicio = date('Y-m-d', strtotime('-6 months'));
    } elseif ($periodo === '1y') {
        $fecha_inicio = date('Y-m-d', strtotime('-1 year'));
    } elseif (strpos($periodo, ',') !== false) {
        list($fecha_inicio, $fecha_fin) = explode(',', $periodo);
    } else {
        $fecha_inicio = date('Y-m-d', strtotime('-30 days'));
    }
    
    $query = "
        SELECT 
            i.nombre AS insumo_nombre,
            i.unidad,
            COALESCE(SUM(CASE WHEN cd.tipo_movimiento IN ('consumo', 'ajuste_negativo') THEN cd.cantidad ELSE 0 END), 0) AS total_consumo,
            COALESCE(SUM(CASE WHEN cd.tipo_movimiento IN ('ingreso', 'ajuste_positivo') THEN cd.cantidad ELSE 0 END), 0) AS total_ingreso,
            COUNT(*) AS total_movimientos,
            cd.insumo_id
        FROM insumos i
        LEFT JOIN consumo_diario cd ON i.id = cd.insumo_id 
            AND DATE(cd.fecha) BETWEEN '{$fecha_inicio}' AND '{$fecha_fin}'
        GROUP BY i.id, i.nombre, i.unidad, cd.insumo_id
        ORDER BY total_consumo DESC
    ";
    
    $result = $conn->query($query);
    $resumen = [];
    
    while ($row = $result->fetch_assoc()) {
        $resumen[] = $row;
    }
    
    return $resumen;
}

/**
 * Obtiene el rango de fechas para un período
 * @param string $periodo Período: '7d', '30d', '3m', '6m', '1y'
 * @return array Array con 'inicio' y 'fin' en formato legible
 */
function obtenerRangoFechas($periodo = '30d') {
    $fecha_fin = date('Y-m-d');
    
    switch($periodo) {
        case '7d':
            $fecha_inicio = date('Y-m-d', strtotime('-7 days'));
            $label = 'Últimos 7 días';
            break;
        case '30d':
            $fecha_inicio = date('Y-m-d', strtotime('-30 days'));
            $label = 'Últimos 30 días';
            break;
        case '3m':
            $fecha_inicio = date('Y-m-d', strtotime('-3 months'));
            $label = 'Últimos 3 meses';
            break;
        case '6m':
            $fecha_inicio = date('Y-m-d', strtotime('-6 months'));
            $label = 'Últimos 6 meses';
            break;
        case '1y':
            $fecha_inicio = date('Y-m-d', strtotime('-1 year'));
            $label = 'Último año';
            break;
        default:
            $fecha_inicio = date('Y-m-d', strtotime('-30 days'));
            $label = 'Últimos 30 días';
    }
    
    return [
        'inicio' => $fecha_inicio,
        'fin' => $fecha_fin,
        'label' => $label
    ];
}

/**
 * Envía un correo usando SMTP con Gmail o fallback a mail().
 *
 * Requiere configurar SMTP_HOST, SMTP_PORT, SMTP_SECURE, SMTP_USER y SMTP_PASS.
 */
function smtpEnviarCorreo($to, $subject, $body, $fromEmail, $fromName) {
    if (!defined('SMTP_HOST') || !SMTP_HOST || !defined('SMTP_USER') || !SMTP_USER || !defined('SMTP_PASS') || !SMTP_PASS) {
        return false;
    }

    $host = SMTP_HOST;
    $port = defined('SMTP_PORT') ? SMTP_PORT : 587;
    $secure = defined('SMTP_SECURE') ? strtolower(SMTP_SECURE) : 'tls';
    $username = SMTP_USER;
    $password = SMTP_PASS;

    $transportHost = ($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $socket = stream_socket_client($transportHost, $errno, $errstr, 30, STREAM_CLIENT_CONNECT);
    if (!$socket) {
        error_log("SMTP connection failed: {$errstr} ({$errno})");
        return false;
    }

    stream_set_timeout($socket, 30);

    $read = function() use ($socket) {
        $data = '';
        while (($line = fgets($socket, 515)) !== false) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $data;
    };

    $send = function($command) use ($socket) {
        fwrite($socket, $command . "\r\n");
    };

    $response = $read();
    if (strpos($response, '220') !== 0) {
        error_log('SMTP welcome failed: ' . $response);
        fclose($socket);
        return false;
    }

    $send("EHLO localhost");
    $response = $read();
    if (strpos($response, '250') !== 0) {
        error_log('SMTP EHLO failed: ' . $response);
        fclose($socket);
        return false;
    }

    if ($secure === 'tls') {
        $send("STARTTLS");
        $response = $read();
        if (strpos($response, '220') !== 0) {
            error_log('SMTP STARTTLS failed: ' . $response);
            fclose($socket);
            return false;
        }
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            error_log('SMTP TLS handshake failed');
            fclose($socket);
            return false;
        }
        $send("EHLO localhost");
        $response = $read();
        if (strpos($response, '250') !== 0) {
            error_log('SMTP EHLO after STARTTLS failed: ' . $response);
            fclose($socket);
            return false;
        }
    }

    $send('AUTH LOGIN');
    $response = $read();
    if (strpos($response, '334') !== 0) {
        error_log('SMTP AUTH LOGIN not accepted: ' . $response);
        fclose($socket);
        return false;
    }
    $send(base64_encode($username));
    $response = $read();
    if (strpos($response, '334') !== 0) {
        error_log('SMTP username not accepted: ' . $response);
        fclose($socket);
        return false;
    }
    $send(base64_encode($password));
    $response = $read();
    if (strpos($response, '235') !== 0) {
        error_log('SMTP password not accepted: ' . $response);
        fclose($socket);
        return false;
    }

    $send("MAIL FROM:<{$fromEmail}>");
    $response = $read();
    if (strpos($response, '250') !== 0) {
        error_log('SMTP MAIL FROM failed: ' . $response);
        fclose($socket);
        return false;
    }

    $toAddresses = array_map('trim', explode(',', $to));
    foreach ($toAddresses as $recipient) {
        $send("RCPT TO:<{$recipient}>");
        $response = $read();
        if (strpos($response, '250') !== 0 && strpos($response, '251') !== 0) {
            error_log('SMTP RCPT TO failed: ' . $response);
            fclose($socket);
            return false;
        }
    }

    $send('DATA');
    $response = $read();
    if (strpos($response, '354') !== 0) {
        error_log('SMTP DATA command failed: ' . $response);
        fclose($socket);
        return false;
    }

    $headers = [];
    $headers[] = 'From: ' . $fromName . ' <' . $fromEmail . '>';
    $headers[] = 'To: ' . $to;
    $headers[] = 'Subject: ' . $subject;
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/plain; charset=UTF-8';
    $headers[] = 'Content-Transfer-Encoding: 8bit';

    $message = implode("\r\n", $headers) . "\r\n\r\n" . str_replace("\n.", "\n..", $body);
    $send($message . "\r\n.\r\n");
    $response = $read();

    $send('QUIT');
    fclose($socket);

    return strpos($response, '250') === 0;
}

function enviarCorreo($destinatario, $asunto, $mensaje) {
    $fromEmail = defined('SMTP_FROM_EMAIL') && SMTP_FROM_EMAIL ? SMTP_FROM_EMAIL : 'no-reply@tambo.local';
    $fromName = defined('SMTP_FROM_NAME') && SMTP_FROM_NAME ? SMTP_FROM_NAME : 'SiCoDiEt';

    if (defined('SMTP_HOST') && SMTP_HOST && defined('SMTP_USER') && SMTP_USER && defined('SMTP_PASS') && SMTP_PASS) {
        $enviado = smtpEnviarCorreo($destinatario, $asunto, $mensaje, $fromEmail, $fromName);
        if ($enviado) {
            return true;
        }
    }

    $cabeceras = "From: {$fromName} <{$fromEmail}>\r\n";
    $cabeceras .= "Reply-To: {$fromEmail}\r\n";
    $cabeceras .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $mailResult = mail($destinatario, $asunto, $mensaje, $cabeceras);
    if (!$mailResult) {
        error_log("PHP mail failed for {$destinatario}");
    }

    return $mailResult;
}

/**
 * Envía una alerta de stock bajo para silo de kilos.
 */
function notificarStockBajo($conn, $insumo_id, $usuario_id) {
    if (!$insumo_id || !$usuario_id) {
        return false;
    }

    $stmt = $conn->prepare("SELECT nombre, unidad, stock_actual, consumo_promedio_diario FROM insumos WHERE id = ?");
    $stmt->bind_param("i", $insumo_id);
    $stmt->execute();
    $insumo = $stmt->get_result()->fetch_assoc();

    if (!$insumo) {
        return false;
    }

    if (empty($insumo['consumo_promedio_diario']) || $insumo['consumo_promedio_diario'] <= 0) {
        return false;
    }

    $diasRestantes = $insumo['stock_actual'] / $insumo['consumo_promedio_diario'];
    $esSiloKg = stripos($insumo['nombre'], 'silo') !== false && strtolower(trim($insumo['unidad'])) === 'kg';

    if (!$esSiloKg || $diasRestantes > 3) {
        return false;
    }

    $stmt = $conn->prepare("SELECT nombre, email, telefono FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $usuario = $stmt->get_result()->fetch_assoc();

    if (!$usuario) {
        return false;
    }

    $diasRestantesRedondeados = ceil($diasRestantes);
    $asunto = "Alerta de stock bajo: quedan {$diasRestantesRedondeados} días de {$insumo['nombre']}";
    $mensaje = "Hola {$usuario['nombre']},\n\n";
    $mensaje .= "El silo '{$insumo['nombre']}' tiene {$insumo['stock_actual']} kg disponibles y un consumo promedio estimado de {$insumo['consumo_promedio_diario']} kg/día.\n";
    $mensaje .= "Esto indica que quedan aproximadamente {$diasRestantesRedondeados} día(s) de consumo.\n\n";
    $mensaje .= "Por favor, reponga el silo cuanto antes para evitar quedarse sin stock.\n\n";
    $mensaje .= "Saludos,\nEquipo SiCoDiEt";

    $enviadoEmail = false;
    $enviadoSms = false;

    if (!empty($usuario['email'])) {
        $enviadoEmail = enviarCorreo($usuario['email'], $asunto, $mensaje);
        if (!$enviadoEmail) {
            error_log("No se pudo enviar email de alerta a {$usuario['email']} para usuario ID {$usuario_id}");
        }
    }

    if (!empty($usuario['telefono']) && defined('SMS_GATEWAY_DOMAIN') && SMS_GATEWAY_DOMAIN) {
        $smsDestino = preg_replace('/[^0-9]/', '', $usuario['telefono']);
        if (!empty($smsDestino)) {
            $smsAsunto = 'SMS: Alerta de stock bajo';
            $smsEmail = $smsDestino . '@' . SMS_GATEWAY_DOMAIN;
            $enviadoSms = enviarCorreo($smsEmail, $smsAsunto, $mensaje);
            if (!$enviadoSms) {
                error_log("No se pudo enviar SMS de alerta a {$smsEmail} para usuario ID {$usuario_id}");
            }
        }
    }

    if (!$enviadoEmail && !$enviadoSms) {
        error_log("No se envió ninguna alerta de stock bajo para usuario ID {$usuario_id}. Email: {$usuario['email']}, Teléfono: {$usuario['telefono']}");
    }

    return $enviadoEmail || $enviadoSms;
}
