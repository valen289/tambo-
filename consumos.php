<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
verificarSesion();

$mensaje = '';
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'registrar_consumo') {
    $lote_id = intval($_POST['lote_id'] ?? 0);
    $insumo_ids = $_POST['insumo_id'] ?? [];
    $cantidades = $_POST['cantidad'] ?? [];
    $fecha = $_POST['fecha'] ?? date('Y-m-d');
    $observaciones = trim($_POST['observaciones'] ?? '');
    $silos = [];
    $selectedSilos = [];
    $selectedCantidades = [];

    if ($lote_id <= 0) {
        $errores[] = 'El lote es obligatorio.';
    }

    if (!is_array($insumo_ids) || !is_array($cantidades) || count($insumo_ids) === 0) {
        $errores[] = 'Debe seleccionar al menos un silo y cantidad.';
    }

    $silo_ids_seen = [];
    for ($i = 0; $i < count($insumo_ids); $i++) {
        $insumo_id = intval($insumo_ids[$i] ?? 0);
        $cantidad = floatval($cantidades[$i] ?? 0);
        $selectedSilos[] = $insumo_id;
        $selectedCantidades[] = $cantidad;

        if ($insumo_id <= 0 || $cantidad <= 0) {
            $errores[] = 'Cada silo debe tener una cantidad válida.';
            continue;
        }

        if (isset($silo_ids_seen[$insumo_id])) {
            $errores[] = 'No puede repetir el mismo silo varias veces.';
        }
        $silo_ids_seen[$insumo_id] = true;
        $silos[$insumo_id] = $cantidad;
    }

    if (empty($errores) && count($silos) > 0) {
        $placeholders = implode(',', array_fill(0, count($silos), '?'));
        $types = str_repeat('i', count($silos));
        $stmt = $conn->prepare("SELECT id, stock_actual FROM insumos WHERE activo = TRUE AND id IN ($placeholders)");
        $ids = array_keys($silos);
        $params = array_merge([$types], $ids);
        $tmp = [];
        foreach ($params as $key => $value) {
            $tmp[$key] = &$params[$key];
        }
        call_user_func_array([$stmt, 'bind_param'], $tmp);
        $stmt->execute();
        $result = $stmt->get_result();
        $stockMap = [];
        while ($row = $result->fetch_assoc()) {
            $stockMap[intval($row['id'])] = floatval($row['stock_actual']);
        }

        foreach ($silos as $insumo_id => $cantidad) {
            if (!isset($stockMap[$insumo_id])) {
                $errores[] = 'Uno de los silos seleccionados no está disponible.';
            } elseif ($cantidad > $stockMap[$insumo_id]) {
                $errores[] = 'La cantidad del silo con ID ' . intval($insumo_id) . ' supera el stock disponible.';
            }
        }
    }

    if (empty($errores) && count($silos) > 0) {
        $conn->begin_transaction();
        try {
            $updateStmt = $conn->prepare("UPDATE insumos SET stock_actual = stock_actual - ? WHERE id = ?");
            $insertConsumo = $conn->prepare("INSERT INTO consumos (lote_id, insumo_id, usuario_id, cantidad, fecha, hora, observaciones) VALUES (?, ?, ?, ?, ?, CURTIME(), ?)");
            $insertDiario = $conn->prepare("INSERT INTO consumo_diario (insumo_id, usuario_id, cantidad, fecha, hora, observaciones, tipo_movimiento) VALUES (?, ?, ?, ?, CURTIME(), ?, 'consumo')");

            foreach ($silos as $insumo_id => $cantidad) {
                $updateStmt->bind_param('di', $cantidad, $insumo_id);
                $updateStmt->execute();

                $insertConsumo->bind_param('iiidss', $lote_id, $insumo_id, $_SESSION['usuario_id'], $cantidad, $fecha, $observaciones);
                $insertConsumo->execute();

                $insertDiario->bind_param('iidss', $insumo_id, $_SESSION['usuario_id'], $cantidad, $fecha, $observaciones);
                $insertDiario->execute();
            }

            $conn->commit();
            $mensaje = 'Consumo registrado correctamente.';
            $selectedSilos = [];
            $selectedCantidades = [];
        } catch (Exception $e) {
            $conn->rollback();
            $errores[] = 'No se pudo registrar el consumo.';
        }
    }
}

$selectedSilos = $selectedSilos ?? [];
$selectedCantidades = $selectedCantidades ?? [];
$selectedLote = isset($lote_id) ? $lote_id : 0;
$selectedFecha = htmlspecialchars(isset($fecha) ? $fecha : date('Y-m-d'));
$selectedObservaciones = htmlspecialchars(isset($observaciones) ? $observaciones : '');

$lotes = $conn->query("SELECT id, nombre FROM lotes WHERE activo = TRUE ORDER BY nombre");
$insumos_data = [];
$insumos = $conn->query("SELECT id, nombre, tipo_insumo, unidad, stock_actual FROM insumos WHERE activo = TRUE ORDER BY nombre");
while ($row = $insumos->fetch_assoc()) {
    $insumos_data[] = $row;
}
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
                                <option value="<?php echo intval($lote['id']); ?>" <?php echo $lote['id'] === $selectedLote ? 'selected' : ''; ?>><?php echo htmlspecialchars($lote['nombre']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group form-full">
                        <label>Silos y cantidades</label>
                        <div id="siloRows">
                            <?php
                            if (empty($selectedSilos)) {
                                $selectedSilos = [0];
                                $selectedCantidades = [''];
                            }
                            for ($i = 0; $i < count($selectedSilos); $i++):
                                $selectedId = intval($selectedSilos[$i] ?? 0);
                                $cantidadValue = htmlspecialchars($selectedCantidades[$i] ?? '');
                            ?>
                                <div class="silo-row" style="display:flex; gap:0.5rem; align-items:flex-end; margin-bottom:0.5rem;">
                                    <select name="insumo_id[]" required style="flex:2;">
                                        <option value="">Selecciona un silo</option>
                                        <?php foreach ($insumos_data as $insumo): ?>
                                            <option value="<?php echo intval($insumo['id']); ?>" <?php echo $insumo['id'] === $selectedId ? 'selected' : ''; ?>><?php echo htmlspecialchars($insumo['nombre'] . ' (' . $insumo['tipo_insumo'] . ' - ' . $insumo['unidad'] . ') - ' . $insumo['stock_actual'] . ' en stock'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="number" name="cantidad[]" step="0.01" min="0.01" value="<?php echo $cantidadValue; ?>" placeholder="Cantidad" required style="flex:1;">
                                    <button type="button" class="btn btn-secondary remove-row">Eliminar</button>
                                </div>
                            <?php endfor; ?>
                        </div>
                        <button type="button" id="addSiloBtn" class="btn btn-secondary">Agregar silo</button>
                    </div>
                    <div class="form-group">
                        <label>Fecha</label>
                        <input type="date" name="fecha" value="<?php echo $selectedFecha; ?>" required>
                    </div>
                    <div class="form-group form-full">
                        <label>Observaciones</label>
                        <textarea name="observaciones" rows="3"><?php echo $selectedObservaciones; ?></textarea>
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
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var insumos = <?php echo json_encode($insumos_data, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>;
            var siloRows = document.getElementById('siloRows');
            var addBtn = document.getElementById('addSiloBtn');

            function createOption(insumo) {
                var option = document.createElement('option');
                option.value = insumo.id;
                option.textContent = insumo.nombre + ' (' + insumo.tipo_insumo + ' - ' + insumo.unidad + ') - ' + insumo.stock_actual + ' en stock';
                return option;
            }

            function createRow(selectedId, cantidadValue) {
                var row = document.createElement('div');
                row.className = 'silo-row';
                row.style.display = 'flex';
                row.style.gap = '0.5rem';
                row.style.alignItems = 'flex-end';
                row.style.marginBottom = '0.5rem';

                var select = document.createElement('select');
                select.name = 'insumo_id[]';
                select.required = true;
                select.style.flex = '2';
                var defaultOption = document.createElement('option');
                defaultOption.value = '';
                defaultOption.textContent = 'Selecciona un silo';
                select.appendChild(defaultOption);
                insumos.forEach(function(insumo) {
                    var option = createOption(insumo);
                    if (String(insumo.id) === String(selectedId)) {
                        option.selected = true;
                    }
                    select.appendChild(option);
                });
                row.appendChild(select);

                var input = document.createElement('input');
                input.type = 'number';
                input.name = 'cantidad[]';
                input.step = '0.01';
                input.min = '0.01';
                input.required = true;
                input.value = cantidadValue || '';
                input.placeholder = 'Cantidad';
                input.style.flex = '1';
                row.appendChild(input);

                var remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'btn btn-secondary remove-row';
                remove.textContent = 'Eliminar';
                remove.addEventListener('click', function() {
                    row.remove();
                });
                row.appendChild(remove);

                return row;
            }

            addBtn.addEventListener('click', function() {
                siloRows.appendChild(createRow('', ''));
            });

            siloRows.querySelectorAll('.remove-row').forEach(function(btn) {
                btn.addEventListener('click', function(event) {
                    event.currentTarget.closest('.silo-row').remove();
                });
            });
        });
    </script>
</body>
</html>
