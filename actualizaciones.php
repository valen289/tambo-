<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
verificarSesion();

$periodo = $_GET['periodo'] ?? '30d';

// Validar período
$periodos_validos = ['7d', '30d', '3m', '6m', '1y'];
if (!in_array($periodo, $periodos_validos)) {
    $periodo = '30d';
}

// Calcular rango de fechas
$fecha_fin = date('Y-m-d');
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
}

// Obtener últimas actualizaciones de consumo/stock filtradas por período
$actualizaciones = $conn->query(
    "SELECT cd.*, COALESCE(cd.tipo_movimiento, 'consumo') AS tipo_movimiento, i.unidad, i.nombre AS insumo_nombre, u.nombre AS usuario_nombre
     FROM consumo_diario cd
     LEFT JOIN insumos i ON cd.insumo_id = i.id
     LEFT JOIN usuarios u ON cd.usuario_id = u.id
     WHERE DATE(cd.fecha) BETWEEN '{$fecha_inicio}' AND '{$fecha_fin}'
     ORDER BY cd.fecha DESC, cd.hora DESC
     LIMIT 100"
);

$lista_actualizaciones = [];
while ($actualizacion = $actualizaciones->fetch_assoc()) {
    $lista_actualizaciones[] = $actualizacion;
}

$total_actualizaciones = count($lista_actualizaciones);

// Obtener resumen de consumo
$resumen = obtenerResumenConsumo($conn, $periodo);
$rango = obtenerRangoFechas($periodo);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Últimas Actualizaciones - SiCoDiEt</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="container">
        <div class="card actualizaciones-card">
            <div class="card-header">
                <h2>Últimas Actualizaciones</h2>
            </div>
            <div class="card-body">
                <div class="actualizaciones-wrapper">
                    <!-- Filtros de período al costado -->
                    <div class="periodo-filtros">
                        <h4>Período a Analizar</h4>
                        <div class="filtros-container">
                            <a href="?periodo=7d" class="filtro-btn <?php echo ($periodo === '7d') ? 'activo' : ''; ?>">
                                7 días
                            </a>
                            <a href="?periodo=30d" class="filtro-btn <?php echo ($periodo === '30d') ? 'activo' : ''; ?>">
                                30 días
                            </a>
                            <a href="?periodo=3m" class="filtro-btn <?php echo ($periodo === '3m') ? 'activo' : ''; ?>">
                                3 meses
                            </a>
                            <a href="?periodo=6m" class="filtro-btn <?php echo ($periodo === '6m') ? 'activo' : ''; ?>">
                                6 meses
                            </a>
                            <a href="?periodo=1y" class="filtro-btn <?php echo ($periodo === '1y') ? 'activo' : ''; ?>">
                                1 año
                            </a>
                        </div>
                        
                        <!-- Resumen de consumo -->
                        <div class="resumen-consumo">
                            <h4>Resumen de Consumo</h4>
                            <p class="rango-fechas"><?php echo $rango['label']; ?><br>
                                <small><?php echo date('d/m/Y', strtotime($fecha_inicio)); ?> - <?php echo date('d/m/Y', strtotime($fecha_fin)); ?></small>
                            </p>
                            
                            <?php if (!empty($resumen)): ?>
                                <div class="resumen-items">
                                    <?php foreach ($resumen as $item): ?>
                                        <?php if ($item['total_consumo'] > 0 || $item['total_ingreso'] > 0): ?>
                                            <div class="resumen-item">
                                                <div class="item-header">
                                                    <span class="item-nombre"><?php echo htmlspecialchars($item['insumo_nombre']); ?></span>
                                                    <span class="item-unidad"><?php echo htmlspecialchars($item['unidad']); ?></span>
                                                </div>
                                                <?php if ($item['total_consumo'] > 0): ?>
                                                    <div class="item-stat consumo">
                                                        <span>Consumo:</span>
                                                        <strong><?php echo number_format($item['total_consumo'], 2, ',', '.'); ?></strong>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ($item['total_ingreso'] > 0): ?>
                                                    <div class="item-stat ingreso">
                                                        <span>Ingreso:</span>
                                                        <strong><?php echo number_format($item['total_ingreso'], 2, ',', '.'); ?></strong>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="item-movimientos">
                                                    <small><?php echo $item['total_movimientos']; ?> movimiento(s)</small>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="no-data">No hay datos para este período</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Listado de actualizaciones -->
                    <div class="actualizaciones-main">
                        <div class="section-actions">
                            <p>Consulta aquí todos los movimientos de consumo, ingreso y ajuste con sus observaciones.</p>
                            <a href="dashboard.php" class="btn btn-secondary">Volver a silos</a>
                        </div>
                        
                        <?php if ($total_actualizaciones === 0): ?>
                            <p>No hay actualizaciones registradas en este período.</p>
                        <?php else: ?>
                            <div class="actualizaciones-container">
                                <?php foreach ($lista_actualizaciones as $actualizacion): ?>
                                    <?php
                                        $tipo = $actualizacion['tipo_movimiento'];
                                        $unidad = htmlspecialchars($actualizacion['unidad'] ?? '');
                                        $insumoNombre = htmlspecialchars($actualizacion['insumo_nombre'] ?? 'Insumo');
                                        $usuarioNombre = htmlspecialchars($actualizacion['usuario_nombre'] ?? 'Usuario');
                                        $cantidad = number_format($actualizacion['cantidad'], 2, ',', '.');
                                        $fechaHora = date('d/m/Y H:i', strtotime($actualizacion['fecha'] . ' ' . $actualizacion['hora']));
                                        $observaciones = htmlspecialchars($actualizacion['observaciones'] ?? '');
                                        $signo = in_array($tipo, ['ingreso', 'ajuste_positivo']) ? '+' : '-';
                                        if ($tipo === 'ingreso') {
                                            $tipoLabel = 'Ingreso';
                                            $tipoClass = 'ingreso';
                                        } elseif ($tipo === 'ajuste_positivo') {
                                            $tipoLabel = 'Ajuste positivo';
                                            $tipoClass = 'ajuste-positivo';
                                        } elseif ($tipo === 'ajuste_negativo') {
                                            $tipoLabel = 'Ajuste negativo';
                                            $tipoClass = 'ajuste-negativo';
                                        } else {
                                            $tipoLabel = 'Consumo';
                                            $tipoClass = 'consumo';
                                        }
                                    ?>
                                    <div class="actualizacion-item tipo-<?php echo $tipoClass; ?>">
                                        <div class="actualizacion-header">
                                            <div class="actualizacion-info">
                                                <span class="badge badge-<?php echo $tipoClass; ?>"><?php echo $tipoLabel; ?></span>
                                                <span class="insumo-nombre"><?php echo $insumoNombre; ?></span>
                                                <span class="cantidad"><?php echo $signo . $cantidad; ?> <?php echo $unidad; ?></span>
                                            </div>
                                            <div class="actualizacion-meta">
                                                <span class="fecha"><?php echo $fechaHora; ?></span>
                                                <span class="usuario"><?php echo $usuarioNombre; ?></span>
                                            </div>
                                        </div>
                                        <?php if (!empty($observaciones)): ?>
                                            <div class="actualizacion-observaciones">
                                                <strong>Observaciones:</strong> <?php echo $observaciones; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
