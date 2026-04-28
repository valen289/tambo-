<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
verificarSesion();

$mensaje = '';
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'registrar_consumo') {
    $lote_id = intval($_POST['lote_id'] ?? 0);
    $insumo_id = intval($_POST['insumo_id'] ?? 0);
    $cantidad = floatval($_POST['cantidad'] ?? 0);
    $fecha = $_POST['fecha'] ?? date('Y-m-d');
    $observaciones = trim($_POST['observaciones'] ?? '');

    if ($lote_id <= 0 || $insumo_id <= 0 || $cantidad <= 0) {
        $errores[] = 'Lote, silo e cantidad son obligatorios.';
    }

    $stmt = $conn->prepare("SELECT stock_actual, capacidad_maxima FROM insumos WHERE id = ? AND activo = TRUE");
    $stmt->bind_param('i', $insumo_id);
    $stmt->execute();
    $insumo = $stmt->get_result()->fetch_assoc();

    if (!$insumo) {
        $errores[] = 'Silo no encontrado o inactivo.';
    } elseif ($cantidad > $insumo['stock_actual']) {
        $errores[] = 'La cantidad supera el stock actual del silo.';
    }

    if (empty($errores)) {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("UPDATE insumos SET stock_actual = stock_actual - ? WHERE id = ?");
            $stmt->bind_param('di', $cantidad, $insumo_id);
            $stmt->execute();

            $stmt = $conn->prepare("INSERT INTO consumos (lote_id, insumo_id, usuario_id, cantidad, fecha, hora, observaciones) VALUES (?, ?, ?, ?, ?, CURTIME(), ?)");
            $stmt->bind_param('iiidss', $lote_id, $insumo_id, $_SESSION['usuario_id'], $cantidad, $fecha, $observaciones);
            $stmt->execute();

            $stmt = $conn->prepare("INSERT INTO consumo_diario (insumo_id, usuario_id, cantidad, fecha, hora, observaciones, tipo_movimiento) VALUES (?, ?, ?, ?, CURTIME(), ?, 'consumo')");
            $stmt->bind_param('iidss', $insumo_id, $_SESSION['usuario_id'], $cantidad, $fecha, $observaciones);
            $stmt->execute();

            $conn->commit();
            $mensaje = 'Consumo registrado correctamente.';
        } catch (Exception $e) {
            $conn->rollback();
            $errores[] = 'No se pudo registrar el consumo.';
        }
    }
}

$lotes = $conn->query("SELECT id, nombre FROM lotes WHERE activo = TRUE ORDER BY nombre");
$insumos = $conn->query("SELECT id, nombre, tipo_insumo, unidad, stock_actual FROM insumos WHERE activo = TRUE ORDER BY nombre");
$consumos = $conn->query("SELECT c.*, l.nombre AS lote_nombre, i.nombre AS insumo_nombre, i.tipo_insumo, u.nombre AS usuario_nombre
    FROM consumos c
    LEFT JOIN lotes l ON c.lote_id = l.id
    LEFT JOIN insumos i ON c.insumo_id = i.id
    LEFT JOIN usuarios u ON c.usuario_id = u.id
    ORDER BY c.fecha DESC, c.hora DESC");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consumos - SiCoDiEt</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container">
        <div class="card">
            <div class="card-header">
                <h2>Registrar Consumo</h2>
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
                    <input type="hidden" name="accion" value="registrar_consumo">
                    <div class="form-group">
                        <label>Lote</label>
                        <select name="lote_id" required>
                            <option value="">Selecciona un lote</option>
                            <?php while ($lote = $lotes->fetch_assoc()): ?>
                                <option value="<?php echo intval($lote['id']); ?>"><?php echo htmlspecialchars($lote['nombre']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Silo</label>
                        <select name="insumo_id" required>
                            <option value="">Selecciona un silo</option>
                            <?php while ($insumo = $insumos->fetch_assoc()): ?>
                                <option value="<?php echo intval($insumo['id']); ?>"><?php echo htmlspecialchars($insumo['nombre'] . ' (' . $insumo['tipo_insumo'] . ' - ' . $insumo['unidad'] . ') - ' . $insumo['stock_actual'] . ' en stock'); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Cantidad</label>
                        <input type="number" name="cantidad" step="0.01" min="0.01" required>
                    </div>
                    <div class="form-group">
                        <label>Fecha</label>
                        <input type="date" name="fecha" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group form-full">
                        <label>Observaciones</label>
                        <textarea name="observaciones" rows="3"></textarea>
                    </div>
                    <div class="form-group form-full">
                        <button type="submit" class="btn btn-primary">Registrar Consumo</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2>Historial de Consumos</h2>
            </div>
            <div class="card-body">
                <?php if ($consumos->num_rows === 0): ?>
                    <p>No hay consumos registrados aún.</p>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Lote</th>
                                <th>Silo</th>
                                <th>Cantidad</th>
                                <th>Usuario</th>
                                <th>Observaciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($consumo = $consumos->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($consumo['fecha'] . ' ' . $consumo['hora']); ?></td>
                                    <td><?php echo htmlspecialchars($consumo['lote_nombre']); ?></td>
                                    <td><?php echo htmlspecialchars($consumo['insumo_nombre']); ?></td>
                                    <td><?php echo number_format($consumo['cantidad'], 2, ',', '.'); ?></td>
                                    <td><?php echo htmlspecialchars($consumo['usuario_nombre']); ?></td>
                                    <td><?php echo htmlspecialchars($consumo['observaciones']); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
