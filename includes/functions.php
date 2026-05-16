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
    $useSmtp = defined('SMTP_HOST') && SMTP_HOST && defined('SMTP_USER') && SMTP_USER && defined('SMTP_PASS') && SMTP_PASS
        && SMTP_USER !== 'tu-email@gmail.com' && SMTP_PASS !== 'tu-app-password';

    if ($useSmtp) {
        $enviado = smtpEnviarCorreo($destinatario, $asunto, $mensaje, $fromEmail, $fromName);
        if ($enviado) {
            return true;
        }
    }

    if (defined('MAIL_FALLBACK') && MAIL_FALLBACK) {
        $cabeceras = "From: {$fromName} <{$fromEmail}>\r\n";
        $cabeceras .= "Reply-To: {$fromEmail}\r\n";
        $cabeceras .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $mailResult = @mail($destinatario, $asunto, $mensaje, $cabeceras);
        if (!$mailResult) {
            error_log("PHP mail failed for {$destinatario}");
        }

        return $mailResult;
    }

    return false;
}

function obtenerConsumoPromedioDiarioPorInsumo($conn, $insumo_id, $dias = 30) {
    $stmt = $conn->prepare("SELECT SUM(cantidad) AS total_consumo, MIN(fecha) AS fecha_min, MAX(fecha) AS fecha_max FROM consumos WHERE insumo_id = ? AND fecha >= DATE_SUB(CURDATE(), INTERVAL ? DAY)");
    $stmt->bind_param('ii', $insumo_id, $dias);
    $stmt->execute();
    $resultado = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!empty($resultado['total_consumo']) && !empty($resultado['fecha_min']) && !empty($resultado['fecha_max'])) {
        $diasTotales = max(1, floor((strtotime($resultado['fecha_max']) - strtotime($resultado['fecha_min'])) / 86400) + 1);
        return floatval($resultado['total_consumo'] / $diasTotales);
    }

    $stmt = $conn->prepare("SELECT SUM(cantidad) AS total_consumo, MIN(fecha) AS fecha_min, MAX(fecha) AS fecha_max FROM consumo_diario WHERE insumo_id = ? AND tipo_movimiento = 'consumo'");
    $stmt->bind_param('i', $insumo_id);
    $stmt->execute();
    $resultado = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!empty($resultado['total_consumo']) && !empty($resultado['fecha_min']) && !empty($resultado['fecha_max'])) {
        $diasTotales = max(1, floor((strtotime($resultado['fecha_max']) - strtotime($resultado['fecha_min'])) / 86400) + 1);
        return floatval($resultado['total_consumo'] / $diasTotales);
    }

    return 0;
}

function puedeEnviarAlertaCritica($insumo) {
    if (!defined('ALERTA_CRITICA_ENVIO_HORAS') || ALERTA_CRITICA_ENVIO_HORAS <= 0) {
        return true;
    }

    if (empty($insumo['ultimo_alerta_critica'])) {
        return true;
    }

    $ultimaAlerta = strtotime($insumo['ultimo_alerta_critica']);
    if ($ultimaAlerta === false) {
        return true;
    }

    return (time() - $ultimaAlerta) >= ALERTA_CRITICA_ENVIO_HORAS * 3600;
}

function registrarHistoricoAlerta($conn, $insumo_id, $tipo, $mensaje) {
    $stmt = $conn->prepare("INSERT INTO alertas (insumo_id, tipo, mensaje) VALUES (?, ?, ?)");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('iss', $insumo_id, $tipo, $mensaje);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

/**
 * Envía una alerta de stock bajo para silo de kilos.
 */
function notificarStockBajo($conn, $insumo_id, $usuario_id, $consumoPromedioDiario = null) {
    if (!$insumo_id || !$usuario_id) {
        return false;
    }

    $stmt = $conn->prepare("SELECT nombre, tipo_insumo, unidad, stock_actual, capacidad_maxima, stock_minimo, consumo_promedio_diario, ultimo_alerta_critica FROM insumos WHERE id = ?");
    $stmt->bind_param("i", $insumo_id);
    $stmt->execute();
    $insumo = $stmt->get_result()->fetch_assoc();

    if (!$insumo) {
        return false;
    }

    $porcentajeStock = $insumo['capacidad_maxima'] > 0 ? ($insumo['stock_actual'] / $insumo['capacidad_maxima']) * 100 : 0;
    if (!defined('STOCK_ALERTA_CRITICA_PORCENTAJE')) {
        define('STOCK_ALERTA_CRITICA_PORCENTAJE', 20);
    }
    if ($porcentajeStock >= STOCK_ALERTA_CRITICA_PORCENTAJE) {
        return false;
    }

    if (!puedeEnviarAlertaCritica($insumo)) {
        return false;
    }

    if ($consumoPromedioDiario === null) {
        $consumoPromedioDiario = !empty($insumo['consumo_promedio_diario']) ? floatval($insumo['consumo_promedio_diario']) : 0;
    }

    $diasRestantes = null;
    $consumoDisponible = $consumoPromedioDiario > 0;
    if ($consumoDisponible) {
        $diasRestantes = $insumo['stock_actual'] / $consumoPromedioDiario;
    }

    $stmt = $conn->prepare("SELECT nombre, email, telefono FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $usuario = $stmt->get_result()->fetch_assoc();

    if (!$usuario) {
        return false;
    }

    $mensaje = "Hola {$usuario['nombre']},\n\n";
    $mensaje .= "El silo '{$insumo['nombre']}' ({$insumo['tipo_insumo']}) está en estado crítico.\n";
    $mensaje .= "Stock actual: {$insumo['stock_actual']} {$insumo['unidad']} de {$insumo['capacidad_maxima']} {$insumo['unidad']}.\n";

    if ($consumoDisponible && $diasRestantes !== null) {
        $diasRestantesRedondeados = max(0, ceil($diasRestantes));
        $mensaje .= "Quedan aproximadamente {$diasRestantesRedondeados} día(s) de consumo.\n";
        $asunto = "Alerta crítica: quedan {$diasRestantesRedondeados} días de {$insumo['nombre']}";
    } else {
        $mensaje .= "El consumo diario no está disponible, por favor carga histórico de consumos para estimar los días restantes.\n";
        $asunto = "Alerta crítica: {$insumo['nombre']} requiere reposición";
    }

    $mensaje .= "Por favor reponga este silo cuanto antes para evitar quedarse sin stock.\n\n";
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
            $smsAsunto = 'SMS: Alerta crítica de stock';
            $smsEmail = $smsDestino . '@' . SMS_GATEWAY_DOMAIN;
            $enviadoSms = enviarCorreo($smsEmail, $smsAsunto, $mensaje);
            if (!$enviadoSms) {
                error_log("No se pudo enviar SMS de alerta a {$smsEmail} para usuario ID {$usuario_id}");
            }
        }
    }

    $resultadoEnviado = $enviadoEmail || $enviadoSms;
    if ($resultadoEnviado) {
        $stmt = $conn->prepare("UPDATE insumos SET ultimo_alerta_critica = NOW() WHERE id = ?");
        $stmt->bind_param('i', $insumo_id);
        $stmt->execute();
        $stmt->close();
        registrarHistoricoAlerta($conn, $insumo_id, 'stock_critico', $mensaje);
    }

    if (!$resultadoEnviado) {
        error_log("No se envió ninguna alerta crítica para insumo ID {$insumo_id}. Email: {$usuario['email']}, Teléfono: {$usuario['telefono']}");
    }

    return $resultadoEnviado;
}
