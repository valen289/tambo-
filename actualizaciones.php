<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
verificarSesion();

// Obtener últimas actualizaciones de consumo/stock
$actualizaciones = $conn->query(
    "SELECT cd.*, COALESCE(cd.tipo_movimiento, 'consumo') AS tipo_movimiento, i.unidad, i.nombre AS insumo_nombre, u.nombre AS usuario_nombre
     FROM consumo_diario cd
     LEFT JOIN insumos i ON cd.insumo_id = i.id
     LEFT JOIN usuarios u ON cd.usuario_id = u.id
     ORDER BY cd.fecha DESC, cd.hora DESC
     LIMIT 50"
);

$lista_actualizaciones = [];
while ($actualizacion = $actualizaciones->fetch_assoc()) {
    $lista_actualizaciones[] = $actualizacion;
}

$total_actualizaciones = count($lista_actualizaciones);
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
                <div class="section-actions">
                    <p>Consulta aquí todos los movimientos de consumo, ingreso y ajuste con sus observaciones.</p>
                    <a href="dashboard.php" class="btn btn-secondary">Volver al Dashboard</a>
                </div>
                <?php if ($total_actualizaciones === 0): ?>
                    <p>No hay actualizaciones registradas aún.</p>
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
</body>
</html>
