<?php
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/includes/config.php';
require_once INCLUDES_PATH . '/db.php';
require_once INCLUDES_PATH . '/auth.php';
require_once INCLUDES_PATH . '/functions.php';

verificarSesion();

$periodo = $_GET['periodo'] ?? '30d';

$periodos_validos = ['7d', '30d', '3m', '6m', '1y'];
if (!in_array($periodo, $periodos_validos)) {
    $periodo = '30d';
}

$resumen = obtenerResumenConsumo($conn, $periodo);
$rango = obtenerRangoFechas($periodo);

echo json_encode([
    'success' => true,
    'rango' => $rango,
    'resumen' => $resumen
]);
?>
