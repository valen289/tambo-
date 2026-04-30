<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
verificarSesion();

$mensaje = '';
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'guardar_lote') {
        $nombre = trim($_POST['nombre'] ?? '');
        $tipo_animal = trim($_POST['tipo_animal'] ?? '');
        $cantidad_animales = intval($_POST['cantidad_animales'] ?? 0);
        $consumo_estimado_diario = floatval($_POST['consumo_estimado_diario'] ?? 0);
        $observaciones = trim($_POST['observaciones'] ?? '');

        if ($nombre === '' || $tipo_animal === '' || $cantidad_animales <= 0) {
            $errores[] = 'Nombre, tipo de animal y cantidad de animales son obligatorios.';
        }

        if (empty($errores)) {
            $stmt = $conn->prepare("INSERT INTO lotes (nombre, tipo_animal, cantidad_animales, consumo_estimado_diario, observaciones, activo) VALUES (?, ?, ?, ?, ?, TRUE)");
            $stmt->bind_param('ssids', $nombre, $tipo_animal, $cantidad_animales, $consumo_estimado_diario, $observaciones);
            if ($stmt->execute()) {
                $mensaje = 'Lote guardado correctamente.';
            } else {
                $errores[] = 'No se pudo guardar el lote. Intenta nuevamente.';
            }
        }
    }

    if ($accion === 'eliminar_lote') {
        $lote_id = intval($_POST['lote_id'] ?? 0);
        if ($lote_id > 0) {
            $stmt = $conn->prepare("UPDATE lotes SET activo = FALSE WHERE id = ?");
            $stmt->bind_param('i', $lote_id);
            if ($stmt->execute()) {
                $mensaje = 'Lote eliminado correctamente.';
            } else {
                $errores[] = 'No se pudo eliminar el lote.';
            }
        }
    }
}

$lotes_data = [];
$lotes = $conn->query("SELECT * FROM lotes WHERE activo = TRUE ORDER BY fecha_creacion DESC");
while ($row = $lotes->fetch_assoc()) {
    $lotes_data[] = $row;
}

$planillas = [];
$stmt = $conn->prepare("SELECT fecha, hora FROM consumos WHERE lote_id = ? ORDER BY fecha DESC, hora DESC LIMIT 1");
$stmt2 = $conn->prepare("SELECT c.cantidad, i.nombre AS silo_nombre, i.unidad FROM consumos c JOIN insumos i ON c.insumo_id = i.id WHERE c.lote_id = ? AND c.fecha = ? AND c.hora = ? ORDER BY i.nombre");

foreach ($lotes_data as $lote) {
    $planilla = ['lote' => $lote, 'fecha' => null, 'hora' => null, 'items' => []];
    $stmt->bind_param('i', $lote['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($ultimo = $result->fetch_assoc()) {
        $planilla['fecha'] = $ultimo['fecha'];
        $planilla['hora'] = $ultimo['hora'];
        $stmt2->bind_param('iss', $lote['id'], $ultimo['fecha'], $ultimo['hora']);
        $stmt2->execute();
        $result2 = $stmt2->get_result();
        while ($item = $result2->fetch_assoc()) {
            $planilla['items'][] = $item;
        }
    }
    $planillas[] = $planilla;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lotes - SiCoDiEt</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container">
        <div class="card">
            <div class="card-header">
                <h2>Gestión de Lotes</h2>
            </div>
            <div class="card-body">
                <?php if ($mensaje): ?>
                    <div class="alerta stock-bajo"><?php echo htmlspecialchars($mensaje); ?></div>
                <?php endif; ?>
                <?php if ($errores): ?>
                    <div class="alerta stock-critico">
                        <?php foreach ($errores as $error): ?>
                            <p><?php echo htmlspecialchars($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form method="post" class="form-grid">
                    <input type="hidden" name="accion" value="guardar_lote">
                    <div class="form-group">
                        <label>Nombre del Lote</label>
                        <input type="text" name="nombre" required>
                    </div>
                    <div class="form-group">
                        <label>Tipo de Animal</label>
                        <input type="text" name="tipo_animal" required>
                    </div>
                    <div class="form-group">
                        <label>Cantidad de Animales</label>
                        <input type="number" name="cantidad_animales" min="1" required>
                    </div>
                    <div class="form-group">
                        <label>Consumo Estimado Diario</label>
                        <input type="number" name="consumo_estimado_diario" step="0.01" min="0">
                    </div>
                    <div class="form-group form-full">
                        <label>Observaciones</label>
                        <textarea name="observaciones" rows="3"></textarea>
                    </div>
                    <div class="form-group form-full">
                        <button type="submit" class="btn btn-primary">Guardar Lote</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2>Planilla de armado por lote</h2>
            </div>
            <div class="card-body">
                <?php if (count($planillas) === 0): ?>
                    <p>No hay lotes registrados todavía.</p>
                <?php else: ?>
                    <?php foreach ($planillas as $planilla): ?>
                        <div class="planilla-card">
                            <div class="planilla-top">
                                <div>
                                    <h3><?php echo htmlspecialchars($planilla['lote']['nombre']); ?></h3>
                                    <p class="planilla-meta">
                                        <strong>Tipo:</strong> <?php echo htmlspecialchars($planilla['lote']['tipo_animal']); ?>
                                        &nbsp;|&nbsp;
                                        <strong>Animales:</strong> <?php echo intval($planilla['lote']['cantidad_animales']); ?>
                                    </p>
                                </div>
                                <div class="planilla-top-right">
                                    <span>Último armado</span>
                                    <strong>
                                        <?php if ($planilla['fecha'] && $planilla['hora']): ?>
                                            <?php echo htmlspecialchars($planilla['fecha'] . ' ' . $planilla['hora']); ?>
                                        <?php else: ?>
                                            Sin registro
                                        <?php endif; ?>
                                    </strong>
                                </div>
                            </div>

                            <div class="planilla-summary">
                                <span><strong>Consumo diario estimado:</strong> <?php echo number_format($planilla['lote']['consumo_estimado_diario'], 2, ',', '.'); ?></span>
                                <span><strong>Observaciones:</strong> <?php echo htmlspecialchars($planilla['lote']['observaciones']); ?></span>
                            </div>

                            <?php if (count($planilla['items']) === 0): ?>
                                <p class="planilla-empty">Aún no hay cantidades registradas para este lote.</p>
                            <?php else: ?>
                                <table class="table table-planilla">
                                    <thead>
                                        <tr>
                                            <th>Silo</th>
                                            <th>Unidad</th>
                                            <th>Cantidad</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($planilla['items'] as $item): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($item['silo_nombre']); ?></td>
                                                <td><?php echo htmlspecialchars($item['unidad']); ?></td>
                                                <td><?php echo number_format($item['cantidad'], 2, ',', '.'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2>Lotes Activos</h2>
            </div>
            <div class="card-body">
                <?php if (count($lotes_data) === 0): ?>
                    <p>No hay lotes registrados todavía.</p>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Tipo</th>
                                <th>Cantidad</th>
                                <th>Consumo Diario</th>
                                <th>Creado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lotes_data as $lote): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($lote['nombre']); ?></td>
                                    <td><?php echo htmlspecialchars($lote['tipo_animal']); ?></td>
                                    <td><?php echo intval($lote['cantidad_animales']); ?></td>
                                    <td><?php echo number_format($lote['consumo_estimado_diario'], 2, ',', '.'); ?></td>
                                    <td><?php echo htmlspecialchars($lote['fecha_creacion']); ?></td>
                                    <td>
                                        <form method="post" style="display:inline-block;">
                                            <input type="hidden" name="accion" value="eliminar_lote">
                                            <input type="hidden" name="lote_id" value="<?php echo intval($lote['id']); ?>">
                                            <button type="submit" class="btn btn-danger btn-small">Eliminar</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
