<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
verificarSesion();

$mensaje = '';
$errores = [];
$isUsuario = esUsuario();
$isOperario = esOperario();
$selectedSilos = $_POST['silo_id'] ?? [];
$selectedCantidades = $_POST['cantidad'] ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'registrar_consumo') {
    $lote_id = intval($_POST['lote_id'] ?? 0);
    $fecha = $_POST['fecha'] ?? date('Y-m-d');
    $observaciones = trim($_POST['observaciones'] ?? '');

    if ($lote_id <= 0) {
        $errores[] = 'El lote es obligatorio.';
    }

    if (empty($errores)) {
        if ($isUsuario) {
            $silo_ids = array_map('intval', (array)$selectedSilos);
            $cantidades = (array)$selectedCantidades;
            $registroItems = [];

            for ($i = 0; $i < count($silo_ids); $i++) {
                $insumo_id = $silo_ids[$i];
                $cantidad = floatval(str_replace(',', '.', trim($cantidades[$i] ?? '0')));

                if ($insumo_id <= 0) {
                    continue;
                }
                if ($cantidad <= 0) {
                    $errores[] = 'La cantidad debe ser mayor a cero en la fila ' . ($i + 1) . '.';
                }

                $registroItems[] = [
                    'insumo_id' => $insumo_id,
                    'cantidad' => $cantidad,
                ];
            }

            if (empty($registroItems)) {
                $errores[] = 'Debes agregar al menos un silo con cantidad.';
            }

            $insumoIds = array_unique(array_column($registroItems, 'insumo_id'));
            if (empty($errores) && !empty($insumoIds)) {
                $placeholders = implode(',', array_fill(0, count($insumoIds), '?'));
                $types = str_repeat('i', count($insumoIds));
                $stmt = $conn->prepare("SELECT id, stock_actual FROM insumos WHERE id IN ($placeholders) AND activo = TRUE");
                $stmt->bind_param($types, ...$insumoIds);
                $stmt->execute();
                $result = $stmt->get_result();
                $stockMap = [];
                while ($row = $result->fetch_assoc()) {
                    $stockMap[intval($row['id'])] = floatval($row['stock_actual']);
                }

                foreach ($registroItems as $item) {
                    if (!isset($stockMap[$item['insumo_id']])) {
                        $errores[] = 'El silo seleccionado no está disponible.';
                        continue;
                    }
                    if ($item['cantidad'] > $stockMap[$item['insumo_id']]) {
                        $errores[] = 'La cantidad requerida del silo ' . $item['insumo_id'] . ' supera el stock disponible.';
                    }
                }
            }

            if (empty($errores)) {
                $conn->begin_transaction();
                try {
                    $updateStmt = $conn->prepare("UPDATE insumos SET stock_actual = stock_actual - ? WHERE id = ?");
                    $insertConsumo = $conn->prepare("INSERT INTO consumos (lote_id, insumo_id, usuario_id, cantidad, fecha, hora, observaciones) VALUES (?, ?, ?, ?, ?, CURTIME(), ?)");
                    $insertDiario = $conn->prepare("INSERT INTO consumo_diario (insumo_id, usuario_id, cantidad, fecha, hora, observaciones, tipo_movimiento) VALUES (?, ?, ?, ?, CURTIME(), ?, 'consumo')");

                    foreach ($registroItems as $item) {
                        $updateStmt->bind_param('di', $item['cantidad'], $item['insumo_id']);
                        $updateStmt->execute();

                        $insertConsumo->bind_param('iiidss', $lote_id, $item['insumo_id'], $_SESSION['usuario_id'], $item['cantidad'], $fecha, $observaciones);
                        $insertConsumo->execute();

                        $insertDiario->bind_param('iidss', $item['insumo_id'], $_SESSION['usuario_id'], $item['cantidad'], $fecha, $observaciones);
                        $insertDiario->execute();
                    }

                    $conn->commit();
                    $mensaje = 'Consumo registrado correctamente.';
                    $selectedSilos = [];
                    $selectedCantidades = [];
                } catch (Exception $e) {
                    $conn->rollback();
                    $errores[] = 'No se pudo registrar el consumo del lote.';
                }
            }
        } else {
            $stmt = $conn->prepare("SELECT li.insumo_id, li.cantidad_requerida, i.stock_actual FROM lote_insumos li JOIN insumos i ON li.insumo_id = i.id WHERE li.lote_id = ? AND i.activo = TRUE");
            $stmt->bind_param('i', $lote_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $insumos_lote = $result->fetch_all(MYSQLI_ASSOC);

            if (empty($insumos_lote)) {
                $errores[] = 'Este lote no tiene silos asociados.';
            } else {
                foreach ($insumos_lote as $insumo) {
                    if ($insumo['cantidad_requerida'] > $insumo['stock_actual']) {
                        $errores[] = 'La cantidad requerida del silo ' . $insumo['insumo_id'] . ' supera el stock disponible.';
                    }
                }
            }

            if (empty($errores) && !empty($insumos_lote)) {
                $conn->begin_transaction();
                try {
                    $updateStmt = $conn->prepare("UPDATE insumos SET stock_actual = stock_actual - ? WHERE id = ?");
                    $insertConsumo = $conn->prepare("INSERT INTO consumos (lote_id, insumo_id, usuario_id, cantidad, fecha, hora, observaciones) VALUES (?, ?, ?, ?, ?, CURTIME(), ?)");
                    $insertDiario = $conn->prepare("INSERT INTO consumo_diario (insumo_id, usuario_id, cantidad, fecha, hora, observaciones, tipo_movimiento) VALUES (?, ?, ?, ?, CURTIME(), ?, 'consumo')");

                    foreach ($insumos_lote as $insumo) {
                        $updateStmt->bind_param('di', $insumo['cantidad_requerida'], $insumo['insumo_id']);
                        $updateStmt->execute();

                        $insertConsumo->bind_param('iiidss', $lote_id, $insumo['insumo_id'], $_SESSION['usuario_id'], $insumo['cantidad_requerida'], $fecha, $observaciones);
                        $insertConsumo->execute();

                        $insertDiario->bind_param('iidss', $insumo['insumo_id'], $_SESSION['usuario_id'], $insumo['cantidad_requerida'], $fecha, $observaciones);
                        $insertDiario->execute();
                    }

                    $conn->commit();
                    $mensaje = 'Armado del lote confirmado y registrado correctamente.';
                } catch (Exception $e) {
                    $conn->rollback();
                    $errores[] = 'No se pudo registrar el consumo del lote.';
                }
            }
        }
    }
}

$selectedLote = isset($lote_id) ? $lote_id : 0;
$selectedFecha = htmlspecialchars(isset($fecha) ? $fecha : date('Y-m-d'));
$selectedObservaciones = htmlspecialchars(isset($observaciones) ? $observaciones : '');

$insumos_lote = [];
if ($isOperario && $selectedLote > 0) {
    $stmt = $conn->prepare("SELECT li.cantidad_requerida, i.nombre AS insumo_nombre, i.tipo_insumo, i.unidad, i.stock_actual
        FROM lote_insumos li
        JOIN insumos i ON li.insumo_id = i.id
        WHERE li.lote_id = ? AND i.activo = TRUE
        ORDER BY i.nombre");
    $stmt->bind_param('i', $selectedLote);
    $stmt->execute();
    $result = $stmt->get_result();
    $insumos_lote = $result->fetch_all(MYSQLI_ASSOC);
}

$manualRows = [];
if ($isUsuario) {
    $selectedSilos = (array)$selectedSilos;
    $selectedCantidades = (array)$selectedCantidades;

    for ($i = 0; $i < max(count($selectedSilos), count($selectedCantidades)); $i++) {
        $siloId = intval($selectedSilos[$i] ?? 0);
        $cantidad = trim($selectedCantidades[$i] ?? '');
        if ($siloId > 0 || $cantidad !== '') {
            $manualRows[] = [
                'silo_id' => $siloId,
                'cantidad' => $cantidad,
            ];
        }
    }

    if (empty($manualRows)) {
        $manualRows[] = ['silo_id' => 0, 'cantidad' => ''];
    }
}

$lotesData = [];
$lotes = $conn->query("SELECT id, nombre, tipo_animal, cantidad_animales, consumo_estimado_diario, observaciones FROM lotes WHERE activo = TRUE ORDER BY nombre");
while ($row = $lotes->fetch_assoc()) {
    $lotesData[] = $row;
}
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
    ORDER BY COALESCE(l.nombre, 'Sin lote') ASC, c.fecha DESC, c.hora DESC");

$consumos_por_lote = [];
while ($row = $consumos->fetch_assoc()) {
    $loteNombre = $row['lote_nombre'] ?: 'Sin lote';
    if (!isset($consumos_por_lote[$loteNombre])) {
        $consumos_por_lote[$loteNombre] = [];
    }
    $consumos_por_lote[$loteNombre][] = $row;
}
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
                        <select name="lote_id" id="loteSelect" required <?php echo $isOperario ? 'onchange="cargarInsumosLote()"' : ''; ?> >
                            <option value="">Selecciona un lote</option>
                            <?php foreach ($lotesData as $lote): ?>
                                <option value="<?php echo intval($lote['id']); ?>" <?php echo $lote['id'] === $selectedLote ? 'selected' : ''; ?>><?php echo htmlspecialchars($lote['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php if ($isUsuario): ?>
                        <div class="form-group form-full">
                            <label>Silos y cantidades</label>
                            <div id="manualSilosContainer">
                                <?php foreach ($manualRows as $rowIndex => $manualRow): ?>
                                    <div class="silo-row" style="display:flex;gap:0.75rem;align-items:flex-start;margin-bottom:0.75rem;flex-wrap:wrap;">
                                        <select name="silo_id[]" required style="flex:1;min-width:220px;">
                                            <option value="">Selecciona un silo</option>
                                            <?php foreach ($insumos_data as $insumo): ?>
                                                <option value="<?php echo intval($insumo['id']); ?>" <?php echo intval($insumo['id']) === intval($manualRow['silo_id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($insumo['nombre']); ?> (<?php echo htmlspecialchars($insumo['tipo_insumo']); ?>)</option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input type="number" step="0.01" min="0" name="cantidad[]" placeholder="Cantidad" value="<?php echo htmlspecialchars($manualRow['cantidad']); ?>" style="width:160px;" required>
                                        <button type="button" class="btn btn-secondary" onclick="eliminarFila(this)" style="height:42px;">Eliminar</button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="btn btn-primary" onclick="agregarFilaSilo()">Agregar silo</button>
                        </div>
                    <?php else: ?>
                        <div class="form-group form-full">
                            <div class="planilla-card">
                                <div class="planilla-top">
                                    <div>
                                        <h3>Planilla de armado por lote</h3>
                                        <p class="planilla-meta"><strong>Lote:</strong> <span id="loteNombre"></span></p>
                                        <p class="planilla-meta"><strong>Tipo:</strong> <span id="loteTipo"></span> | <strong>Animales:</strong> <span id="loteAnimales"></span></p>
                                    </div>
                                    <div class="planilla-top-right">
                                        <span>Consumo diario estimado</span>
                                        <strong id="loteConsumoEstimado"></strong>
                                    </div>
                                </div>
                                <div class="planilla-summary">
                                    <span><strong>Observaciones:</strong> <span id="loteObservaciones"></span></span>
                                </div>
                                <div id="insumosLoteContainer" style="display:none;">
                                    <table class="table table-planilla" id="insumosLoteTable">
                                        <thead>
                                            <tr>
                                                <th>Silo</th>
                                                <th>Unidad</th>
                                                <th>Cantidad a retirar</th>
                                                <th>Stock disponible</th>
                                            </tr>
                                        </thead>
                                        <tbody id="insumosLoteBody"></tbody>
                                    </table>
                                </div>
                                <p id="noInsumosMsg" class="planilla-empty" style="display:none;"></p>
                                <p style="margin-top:1rem;color:#555;">Revise las cantidades indicadas y retire el material de cada silo. Cuando termine, presione "Confirmar armado".</p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label>Fecha</label>
                        <input type="date" name="fecha" value="<?php echo $selectedFecha; ?>" required>
                    </div>
                    <div class="form-group form-full">
                        <label>Observaciones</label>
                        <textarea name="observaciones" rows="3"><?php echo $selectedObservaciones; ?></textarea>
                    </div>
                    <?php if ($isUsuario): ?>
                        <div class="form-group form-full">
                            <button type="submit" class="btn btn-primary">Registrar Consumo</button>
                        </div>
                    <?php elseif ($isOperario): ?>
                        <div class="form-group form-full" id="operarioConfirmBtn" style="display:none;">
                            <button type="submit" class="btn btn-primary">Confirmar armado completado</button>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2>Historial de Consumos</h2>
            </div>
            <div class="card-body">
                <?php if (empty($consumos_por_lote)): ?>
                    <p>No hay consumos registrados aún.</p>
                <?php else: ?>
                    <?php foreach ($consumos_por_lote as $loteNombre => $rows): ?>
                        <div class="consumo-lote-group" style="margin-bottom: 1.5rem;">
                            <h3 style="margin-bottom: 0.75rem;">Lote: <?php echo htmlspecialchars($loteNombre); ?></h3>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Silo</th>
                                        <th>Cantidad</th>
                                        <th>Usuario</th>
                                        <th>Observaciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rows as $consumo): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($consumo['fecha'] . ' ' . $consumo['hora']); ?></td>
                                            <td><?php echo htmlspecialchars($consumo['insumo_nombre']); ?></td>
                                            <td><?php echo number_format($consumo['cantidad'], 2, ',', '.'); ?></td>
                                            <td><?php echo htmlspecialchars($consumo['usuario_nombre']); ?></td>
                                            <td><?php echo htmlspecialchars($consumo['observaciones']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script>
        const insumosData = <?php echo json_encode($insumos_data); ?>;
        const lotesData = <?php echo json_encode($lotesData); ?>;

        function agregarFilaSilo() {
            var container = document.getElementById('manualSilosContainer');
            var row = document.createElement('div');
            row.className = 'silo-row';
            row.style = 'display:flex;gap:0.75rem;align-items:flex-start;margin-bottom:0.75rem;flex-wrap:wrap;';
            row.innerHTML = `
                <select name="silo_id[]" required style="flex:1;min-width:220px;">
                    <option value="">Selecciona un silo</option>
                    ${insumosData.map(function(insumo) {
                        return `<option value="${insumo.id}">${insumo.nombre} (${insumo.tipo_insumo})</option>`;
                    }).join('')}
                </select>
                <input type="number" step="0.01" min="0" name="cantidad[]" placeholder="Cantidad" style="width:160px;" required>
                <button type="button" class="btn btn-secondary" onclick="eliminarFila(this)" style="height:42px;">Eliminar</button>
            `;
            container.appendChild(row);
        }

        function eliminarFila(button) {
            var row = button.closest('.silo-row');
            if (row) {
                row.remove();
            }
        }

        <?php if ($isOperario): ?>
        document.addEventListener('DOMContentLoaded', function() {
            if (document.getElementById('loteSelect').value) {
                cargarInsumosLote();
            }
        });

        function cargarInsumosLote() {
            var loteId = document.getElementById('loteSelect').value;
            var container = document.getElementById('insumosLoteContainer');
            var tableBody = document.getElementById('insumosLoteBody');
            var noInsumosMsg = document.getElementById('noInsumosMsg');
            var loteInfo = document.getElementById('loteInfo');
            var loteNombre = document.getElementById('loteNombre');
            var loteTipo = document.getElementById('loteTipo');
            var loteAnimales = document.getElementById('loteAnimales');
            var loteConsumoEstimado = document.getElementById('loteConsumoEstimado');
            var loteObservaciones = document.getElementById('loteObservaciones');
            var confirmBtn = document.getElementById('operarioConfirmBtn');

            if (!loteId) {
                if (container) container.style.display = 'none';
                if (noInsumosMsg) noInsumosMsg.style.display = 'none';
                if (loteInfo) loteInfo.style.display = 'none';
                if (confirmBtn) confirmBtn.style.display = 'none';
                return;
            }

            var loteSeleccionado = lotesData.find(function(item) {
                return item.id == loteId;
            });

            if (loteSeleccionado && loteInfo) {
                loteNombre.textContent = loteSeleccionado.nombre;
                loteTipo.textContent = loteSeleccionado.tipo_animal || 'N/A';
                loteAnimales.textContent = loteSeleccionado.cantidad_animales || '0';
                loteConsumoEstimado.textContent = parseFloat(loteSeleccionado.consumo_estimado_diario).toFixed(2).replace('.', ',');
                loteObservaciones.textContent = loteSeleccionado.observaciones || '-';
                loteInfo.style.display = 'block';
            }

            if (!tableBody) return;
            tableBody.innerHTML = '';

            fetch('api/obtener_insumos_lote.php?lote_id=' + loteId)
                .then(response => response.json())
                .then(data => {
                    if (data.length > 0) {
                        data.forEach(function(insumo) {
                            var row = document.createElement('tr');
                            var cantidadRequerida = parseFloat(insumo.cantidad_requerida);
                            var stockActual = parseFloat(insumo.stock_actual);
                            var stockOk = stockActual >= cantidadRequerida;
                            row.innerHTML = `
                                <td>${insumo.insumo_nombre} (${insumo.tipo_insumo})</td>
                                <td>${insumo.unidad}</td>
                                <td style="font-weight:bold;color:#2e7d32;">${cantidadRequerida.toFixed(2).replace('.', ',')}</td>
                                <td style="color:${stockOk ? '#555' : '#c62828'};">${stockActual.toFixed(2).replace('.', ',')} ${!stockOk ? '⚠ Stock insuficiente' : ''}</td>
                            `;
                            tableBody.appendChild(row);
                        });
                        if (container) container.style.display = 'block';
                        if (noInsumosMsg) noInsumosMsg.style.display = 'none';
                        if (confirmBtn) confirmBtn.style.display = 'block';
                    } else {
                        if (container) container.style.display = 'none';
                        if (noInsumosMsg) {
                            noInsumosMsg.textContent = 'Este lote no tiene silos asociados.';
                            noInsumosMsg.style.display = 'block';
                        }
                        if (confirmBtn) confirmBtn.style.display = 'none';
                    }
                })
                .catch(error => {
                    console.error('Error cargando insumos:', error);
                    if (container) container.style.display = 'none';
                    if (noInsumosMsg) {
                        noInsumosMsg.textContent = 'No se pudo cargar la información del lote.';
                        noInsumosMsg.style.display = 'block';
                    }
                    if (confirmBtn) confirmBtn.style.display = 'none';
                });
        }
        <?php endif; ?>
    </script>
</body>
</html>
