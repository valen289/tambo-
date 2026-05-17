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
        $observaciones = trim($_POST['observaciones'] ?? '');
        $silo_ids = $_POST['silo_id'] ?? [];
        $cantidades = $_POST['cantidad_silo'] ?? [];

        if ($nombre === '' || $tipo_animal === '' || $cantidad_animales <= 0) {
            $errores[] = 'Nombre, tipo de animal y cantidad de animales son obligatorios.';
        }

        $consumo_estimado_diario = 0;
        $silosValidos = [];
        for ($i = 0; $i < count($silo_ids); $i++) {
            $insumo_id = intval($silo_ids[$i] ?? 0);
            $cantidad = floatval(str_replace(',', '.', trim($cantidades[$i] ?? '0')));
            if ($insumo_id > 0 && $cantidad > 0) {
                $silosValidos[] = ['insumo_id' => $insumo_id, 'cantidad' => $cantidad];
                $consumo_estimado_diario += $cantidad;
            }
        }

        if (empty($silosValidos)) {
            $errores[] = 'Debe agregar al menos un silo con cantidad.';
        }

        if (empty($errores)) {
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("INSERT INTO lotes (nombre, tipo_animal, cantidad_animales, consumo_estimado_diario, observaciones, activo) VALUES (?, ?, ?, ?, ?, TRUE)");
                $stmt->bind_param('ssids', $nombre, $tipo_animal, $cantidad_animales, $consumo_estimado_diario, $observaciones);
                $stmt->execute();
                $lote_id = $conn->insert_id;

                $insertStmt = $conn->prepare("INSERT INTO lote_insumos (lote_id, insumo_id, cantidad_requerida) VALUES (?, ?, ?)");
                foreach ($silosValidos as $silo) {
                    $insertStmt->bind_param('iid', $lote_id, $silo['insumo_id'], $silo['cantidad']);
                    $insertStmt->execute();
                }

                $conn->commit();
                $mensaje = 'Lote guardado correctamente con ' . count($silosValidos) . ' silo(s) asociado(s).';
            } catch (Exception $e) {
                $conn->rollback();
                $errores[] = 'No se pudo guardar el lote. Intenta nuevamente.';
            }
        }
    }

    if ($accion === 'eliminar_lote') {
        $lote_id = intval($_POST['lote_id'] ?? 0);
        if ($lote_id > 0) {
            $conn->begin_transaction();
            try {
                $conn->query("DELETE FROM consumo_diario WHERE insumo_id IN (SELECT insumo_id FROM consumos WHERE lote_id = $lote_id)");
                $conn->query("DELETE FROM consumos WHERE lote_id = $lote_id");
                $conn->query("DELETE FROM lote_insumos WHERE lote_id = $lote_id");
                $stmt = $conn->prepare("DELETE FROM lotes WHERE id = ?");
                $stmt->bind_param('i', $lote_id);
                $stmt->execute();
                $conn->commit();
                $mensaje = 'Lote y su historial de consumos eliminados correctamente.';
            } catch (Exception $e) {
                $conn->rollback();
                $errores[] = 'No se pudo eliminar el lote.';
            }
        }
    }

    if ($accion === 'editar_silos_lote') {
        $lote_id = intval($_POST['lote_id_editar'] ?? 0);
        $silo_ids = $_POST['silo_id'] ?? [];
        $cantidades = $_POST['cantidad_silo'] ?? [];

        if ($lote_id <= 0) {
            $errores[] = 'Seleccione un lote valido.';
        }

        $consumo_estimado_diario = 0;
        $silosValidos = [];
        for ($i = 0; $i < count($silo_ids); $i++) {
            $insumo_id = intval($silo_ids[$i] ?? 0);
            $cantidad = floatval(str_replace(',', '.', trim($cantidades[$i] ?? '0')));
            if ($insumo_id > 0 && $cantidad > 0) {
                $silosValidos[] = ['insumo_id' => $insumo_id, 'cantidad' => $cantidad];
                $consumo_estimado_diario += $cantidad;
            }
        }

        if (empty($silosValidos)) {
            $errores[] = 'Debe agregar al menos un silo con cantidad.';
        }

        if (empty($errores)) {
            $conn->begin_transaction();
            try {
                $conn->query("DELETE FROM lote_insumos WHERE lote_id = $lote_id");

                $updateLote = $conn->prepare("UPDATE lotes SET consumo_estimado_diario = ? WHERE id = ?");
                $updateLote->bind_param('di', $consumo_estimado_diario, $lote_id);
                $updateLote->execute();

                $insertStmt = $conn->prepare("INSERT INTO lote_insumos (lote_id, insumo_id, cantidad_requerida) VALUES (?, ?, ?)");
                foreach ($silosValidos as $silo) {
                    $insertStmt->bind_param('iid', $lote_id, $silo['insumo_id'], $silo['cantidad']);
                    $insertStmt->execute();
                }

                $conn->commit();
                $mensaje = 'Silos del lote actualizados correctamente.';
            } catch (Exception $e) {
                $conn->rollback();
                $errores[] = 'No se pudieron actualizar los silos.';
            }
        }
    }
}

$lotes_data = [];
$lotes = $conn->query("SELECT * FROM lotes ORDER BY fecha_creacion DESC");
while ($row = $lotes->fetch_assoc()) {
    $lotes_data[] = $row;
}

$insumos_data = [];
$insumos = $conn->query("SELECT id, nombre, tipo_insumo, unidad, stock_actual FROM insumos WHERE activo = TRUE ORDER BY nombre");
while ($row = $insumos->fetch_assoc()) {
    $insumos_data[] = $row;
}

$planillas = [];
$stmtLoteInsumos = $conn->prepare("SELECT li.insumo_id, li.cantidad_requerida, i.nombre AS silo_nombre, i.unidad FROM lote_insumos li JOIN insumos i ON li.insumo_id = i.id WHERE li.lote_id = ? ORDER BY i.nombre");

foreach ($lotes_data as $lote) {
    $planilla = ['lote' => $lote, 'items' => []];
    $stmtLoteInsumos->bind_param('i', $lote['id']);
    $stmtLoteInsumos->execute();
    $result = $stmtLoteInsumos->get_result();
    while ($item = $result->fetch_assoc()) {
        $planilla['items'][] = $item;
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
                <h2>Nuevo Lote</h2>
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

                <form method="post" class="form-grid" id="formNuevoLote">
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
                        <input type="text" id="consumoCalculado" value="0,00" readonly style="background: #f5f5f5; font-weight: bold; color: #2e7d32;">
                        <input type="hidden" name="consumo_estimado_diario" id="consumoHidden" value="0">
                        <small style="color: #666;">Se calcula automaticamente al agregar silos</small>
                    </div>
                    <div class="form-group form-full">
                        <label>Observaciones</label>
                        <textarea name="observaciones" rows="2"></textarea>
                    </div>
                    <div class="form-group form-full">
                        <label>Silos y cantidades requeridas</label>
                        <div id="silosNuevoContainer">
                            <div class="silo-row" style="display:flex;gap:0.75rem;align-items:flex-start;margin-bottom:0.75rem;flex-wrap:wrap;">
                                <select name="silo_id[]" required style="flex:1;min-width:220px;" onchange="calcularConsumo()">
                                    <option value="">Selecciona un silo</option>
                                    <?php foreach ($insumos_data as $insumo): ?>
                                        <option value="<?php echo intval($insumo['id']); ?>"><?php echo htmlspecialchars($insumo['nombre']); ?> (<?php echo htmlspecialchars($insumo['tipo_insumo']); ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="number" step="0.01" min="0" name="cantidad_silo[]" placeholder="Cantidad" style="width:160px;" required oninput="calcularConsumo()">
                                <button type="button" class="btn btn-secondary" onclick="agregarFilaSilo('silosNuevoContainer')" style="height:42px;"><i class="fa-solid fa-plus"></i> Agregar silo</button>
                            </div>
                        </div>
                    </div>
                    <div class="form-group form-full">
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Guardar Lote</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2>Lotes Registrados</h2>
            </div>
            <div class="card-body">
                <?php if (count($planillas) === 0): ?>
                    <p>No hay lotes registrados todavia.</p>
                <?php else: ?>
                    <?php foreach ($planillas as $planilla): ?>
                        <div class="lote-card">
                            <div class="lote-header">
                                <div>
                                    <h3><?php echo htmlspecialchars($planilla['lote']['nombre']); ?></h3>
                                    <p class="lote-meta">
                                        <strong>Tipo:</strong> <?php echo htmlspecialchars($planilla['lote']['tipo_animal']); ?>
                                        | <strong>Animales:</strong> <?php echo intval($planilla['lote']['cantidad_animales']); ?>
                                        | <strong>Consumo diario:</strong> <?php echo number_format($planilla['lote']['consumo_estimado_diario'], 2, ',', '.'); ?>
                                    </p>
                                    <?php if ($planilla['lote']['observaciones']): ?>
                                        <p class="lote-meta"><strong>Observaciones:</strong> <?php echo htmlspecialchars($planilla['lote']['observaciones']); ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="btn-group">
                                    <button type="button" class="btn btn-secondary btn-small" onclick="toggleEditarSilos(<?php echo intval($planilla['lote']['id']); ?>)"><i class="fa-solid fa-pen"></i> Editar silos</button>
                                    <form method="post" style="display:inline-block;">
                                        <input type="hidden" name="accion" value="eliminar_lote">
                                        <input type="hidden" name="lote_id" value="<?php echo intval($planilla['lote']['id']); ?>">
                                        <button type="submit" class="btn btn-danger btn-small" onclick="return confirm('¿Eliminar este lote?')"><i class="fa-solid fa-trash"></i> Eliminar</button>
                                    </form>
                                </div>
                            </div>

                            <div id="silosView_<?php echo intval($planilla['lote']['id']); ?>">
                                <?php if (count($planilla['items']) === 0): ?>
                                    <p class="planilla-empty">Este lote no tiene silos asociados.</p>
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
                                                    <td><?php echo number_format($item['cantidad_requerida'], 2, ',', '.'); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>

                            <div id="silosEdit_<?php echo intval($planilla['lote']['id']); ?>" class="silos-edit-card" style="display:none;">
                                <form method="post">
                                    <input type="hidden" name="accion" value="editar_silos_lote">
                                    <input type="hidden" name="lote_id_editar" value="<?php echo intval($planilla['lote']['id']); ?>">
                                    <div id="silosEditRows_<?php echo intval($planilla['lote']['id']); ?>">
                                        <?php foreach ($planilla['items'] as $item): ?>
                                            <div class="silo-row" style="display:flex;gap:0.75rem;align-items:flex-start;margin-bottom:0.75rem;flex-wrap:wrap;">
                                                <select name="silo_id[]" required style="flex:1;min-width:220px;">
                                                    <option value="">Selecciona un silo</option>
                                                    <?php foreach ($insumos_data as $insumo): ?>
                                                        <option value="<?php echo intval($insumo['id']); ?>" <?php echo intval($insumo['id']) === intval($item['insumo_id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($insumo['nombre']); ?> (<?php echo htmlspecialchars($insumo['tipo_insumo']); ?>)</option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <input type="number" step="0.01" min="0" name="cantidad_silo[]" value="<?php echo htmlspecialchars($item['cantidad_requerida']); ?>" style="width:160px;" required>
                                                <button type="button" class="btn btn-secondary" onclick="eliminarFilaSilo(this)" style="height:42px;"><i class="fa-solid fa-trash"></i> Eliminar</button>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <button type="button" class="btn btn-secondary" onclick="agregarFilaSiloEdit(<?php echo intval($planilla['lote']['id']); ?>)" style="margin-bottom: 0.75rem;"><i class="fa-solid fa-plus"></i> Agregar silo</button>
                                    <div class="silos-edit-actions">
                                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Guardar cambios</button>
                                        <button type="button" class="btn btn-cancel" onclick="toggleEditarSilos(<?php echo intval($planilla['lote']['id']); ?>)"><i class="fa-solid fa-xmark"></i> Cancelar</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script>
        const insumosData = <?php echo json_encode($insumos_data); ?>;

        function calcularConsumo() {
            var total = 0;
            var cantidades = document.querySelectorAll('#formNuevoLote input[name="cantidad_silo[]"]');
            cantidades.forEach(function(input) {
                var val = parseFloat(input.value) || 0;
                total += val;
            });
            document.getElementById('consumoCalculado').value = total.toFixed(2).replace('.', ',');
            document.getElementById('consumoHidden').value = total.toFixed(2);
        }

        function agregarFilaSilo(containerId) {
            var container = document.getElementById(containerId);
            var row = document.createElement('div');
            row.className = 'silo-row';
            row.style = 'display:flex;gap:0.75rem;align-items:flex-start;margin-bottom:0.75rem;flex-wrap:wrap;';
            row.innerHTML = `
                <select name="silo_id[]" required style="flex:1;min-width:220px;" onchange="calcularConsumo()">
                    <option value="">Selecciona un silo</option>
                    ${insumosData.map(function(insumo) {
                        return `<option value="${insumo.id}">${insumo.nombre} (${insumo.tipo_insumo})</option>`;
                    }).join('')}
                </select>
                <input type="number" step="0.01" min="0" name="cantidad_silo[]" placeholder="Cantidad" style="width:160px;" required oninput="calcularConsumo()">
                <button type="button" class="btn btn-secondary" onclick="eliminarFilaSilo(this)" style="height:42px;">Eliminar</button>
            `;
            container.appendChild(row);
        }

        function agregarFilaSiloEdit(loteId) {
            var container = document.getElementById('silosEditRows_' + loteId);
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
                <input type="number" step="0.01" min="0" name="cantidad_silo[]" placeholder="Cantidad" style="width:160px;" required>
                <button type="button" class="btn btn-secondary" onclick="eliminarFilaSilo(this)" style="height:42px;">Eliminar</button>
            `;
            container.appendChild(row);
        }

        function eliminarFilaSilo(button) {
            var row = button.closest('.silo-row');
            if (row) {
                row.remove();
                calcularConsumo();
            }
        }

        function toggleEditarSilos(loteId) {
            var viewDiv = document.getElementById('silosView_' + loteId);
            var editDiv = document.getElementById('silosEdit_' + loteId);
            if (editDiv.style.display === 'none') {
                viewDiv.style.display = 'none';
                editDiv.style.display = 'block';
            } else {
                viewDiv.style.display = 'block';
                editDiv.style.display = 'none';
            }
        }
    </script>
</body>
</html>
