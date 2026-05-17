<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
verificarSesion();

// Obtener insumos activos y calcular estado según porcentaje de stock y consumo real
$insumos = $conn->query("SELECT i.* FROM insumos i WHERE i.activo = TRUE ORDER BY i.nombre");

$lista_insumos = [];
while ($insumo = $insumos->fetch_assoc()) {
    $capacidad = floatval($insumo['capacidad_maxima']);
    $stock_actual = floatval($insumo['stock_actual']);
    $stock_porcentaje = $capacidad > 0 ? ($stock_actual / $capacidad) * 100 : 0;
    $stock_porcentaje = min(100, max(0, $stock_porcentaje));

    $consumo_promedio_diario = obtenerConsumoPromedioDiarioPorInsumo($conn, intval($insumo['id']));
    if ($consumo_promedio_diario <= 0 && !empty($insumo['consumo_promedio_diario'])) {
        $consumo_promedio_diario = floatval($insumo['consumo_promedio_diario']);
    }

    $dias_restantes = null;
    if ($consumo_promedio_diario > 0) {
        $dias_restantes = $stock_actual / $consumo_promedio_diario;
    }

    if (!defined('STOCK_ALERTA_MEDIA_PORCENTAJE')) {
        define('STOCK_ALERTA_MEDIA_PORCENTAJE', 50);
    }
    if (!defined('STOCK_ALERTA_CRITICA_PORCENTAJE')) {
        define('STOCK_ALERTA_CRITICA_PORCENTAJE', 20);
    }

    if ($stock_porcentaje <= STOCK_ALERTA_CRITICA_PORCENTAJE) {
        $estado_alerta = 'critico';
        if ($dias_restantes !== null) {
            $mensaje_alerta = 'Stock crítico – quedan ' . max(0, ceil($dias_restantes)) . ' días de consumo';
        } else {
            $mensaje_alerta = 'Stock crítico – consumo diario no estimado';
        }
    } elseif ($stock_porcentaje <= STOCK_ALERTA_MEDIA_PORCENTAJE) {
        $estado_alerta = 'media';
        $mensaje_alerta = 'Stock en nivel medio – considerar reposición';
    } else {
        $estado_alerta = 'normal';
        $mensaje_alerta = '';
    }

    $insumo['stock_porcentaje'] = $stock_porcentaje;
    $insumo['consumo_promedio_diario'] = $consumo_promedio_diario;
    $insumo['dias_restantes'] = $dias_restantes;
    $insumo['estado_alerta'] = $estado_alerta;
    $insumo['mensaje_alerta'] = $mensaje_alerta;

    if ($estado_alerta === 'critico') {
        notificarStockBajo($conn, intval($insumo['id']), intval($_SESSION['usuario_id']), $consumo_promedio_diario);
    }

    $lista_insumos[] = $insumo;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Silos - SiCoDiEt</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h2>Últimas Actualizaciones</h2>
            </div>
            <div class="card-body">
                <p>Ve todas las actualizaciones y observaciones en un apartado separado.</p>
                <a href="actualizaciones.php" class="btn btn-primary"><i class="fa-solid fa-list-ul"></i> Ir a Últimas Actualizaciones</a>
            </div>
        </div>

        <!-- Sección Usuario -->
        <div class="card">
            <h3>Mi Usuario</h3>
            <div class="card-body">
                <p><strong>Nombre:</strong> <?php echo htmlspecialchars($_SESSION['nombre']); ?></p>
                <p><strong>Cédula:</strong> <?php echo htmlspecialchars($_SESSION['cedula'] ?? 'N/A'); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($_SESSION['email'] ?? 'No registrado'); ?></p>
                <p><strong>Teléfono:</strong> <?php echo htmlspecialchars($_SESSION['telefono'] ?? 'No registrado'); ?></p>
                <p><strong>Rol:</strong> <?php echo ucfirst($_SESSION['rol']); ?></p>
                
              
            </div>
        </div>

        <!-- Control de Stock -->
        <div class="section-header">
            <h2>Silos</h2>
            <?php if (esUsuario() || esAdmin()): ?>
                <button class="btn btn-primary" onclick="abrirModalNuevoInsumo()"><i class="fa-solid fa-plus"></i> Agregar Silo</button>
            <?php endif; ?>
        </div>
        <div class="grid-insumos">
            <?php foreach ($lista_insumos as $insumo): ?>
            <div class="card insumo-card estado-<?php echo $insumo['estado_alerta']; ?>">
                <div class="card-header">
                    <div>
                        <h3><?php echo htmlspecialchars($insumo['nombre']); ?></h3>
                        <small>Tipo: <?php echo htmlspecialchars($insumo['tipo_insumo'] ?? 'N/A'); ?></small>
                        <small>Unidad: <?php echo htmlspecialchars($insumo['unidad'] ?? 'N/A'); ?></small>
                        <small>Capacidad: <?php echo number_format($insumo['capacidad_maxima'] ?? 0, 0, ',', '.'); ?> <?php echo htmlspecialchars($insumo['unidad'] ?? ''); ?></small>
                    </div>
                    <?php if (esUsuario() || esAdmin()): ?>
                        <button class="btn-icon-edit" onclick="editarInsumo(<?php echo $insumo['id']; ?>)"><i class="fa-solid fa-pen"></i> Editar</button>
                    <?php endif; ?>
                </div>
                
                <div class="card-body">
                        <div class="stock-info">
                        <span class="stock-actual"><?php echo number_format($insumo['stock_actual'], 0, ',', '.'); ?></span>
                        <span class="stock-total">/<?php echo number_format($insumo['capacidad_maxima'], 0, ',', '.'); ?> <?php echo htmlspecialchars($insumo['unidad'] ?? ''); ?></span>
                    </div>

                    <div class="consumo-info">
                        <div><span>Porcentaje</span><strong><?php echo number_format($insumo['stock_porcentaje'], 0); ?>%</strong></div>
                        <div><span>Días restantes</span><strong><?php echo $insumo['dias_restantes'] !== null ? max(0, ceil($insumo['dias_restantes'])) : 'N/E'; ?></strong></div>
                    </div>

                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo number_format($insumo['stock_porcentaje'], 2, '.', ''); ?>%"></div>
                    </div>

                    <?php if ($insumo['estado_alerta'] === 'media'): ?>
                        <div class="alerta stock-media"><?php echo htmlspecialchars($insumo['mensaje_alerta']); ?></div>
                    <?php elseif ($insumo['estado_alerta'] === 'critico'): ?>
                        <div class="alerta stock-critico"><?php echo htmlspecialchars($insumo['mensaje_alerta']); ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Modal para Editar/Crear Insumo -->
    <div id="modalEditarInsumo" class="modal">
        <div class="modal-content">
            <h3 id="modalEditarTitulo">Editar Silo</h3>
            <form id="formEditarInsumo">
                <input type="hidden" id="editarInsumoId" name="insumo_id">
                <div class="form-group">
                    <label>Nombre del Silo</label>
                    <input type="text" id="editarNombre" name="nombre" placeholder="Nombre del Silo" required>
                </div>
                <div class="form-group">
                    <label>Tipo de Insumo</label>
                    <input type="text" id="editarTipoInsumo" name="tipo_insumo" placeholder="Ej: Maíz, Soja, Mezcla" required>
                </div>
                <div class="form-group">
                    <label>Unidad</label>
                    <input type="text" id="editarUnidad" name="unidad" placeholder="Unidad (kg, fardos)" required>
                </div>
                <div class="form-group">
                    <label>Capacidad Máxima</label>
                    <input type="number" id="editarCapacidad" name="capacidad_maxima" step="0.01" placeholder="Capacidad Máxima" required>
                </div>
                <div class="form-group">
                    <label>Stock Actual</label>
                    <input type="number" id="editarStock" name="stock_actual" step="0.01" placeholder="Stock Actual" required>
                </div>
                <input type="hidden" id="editarMinimo" name="stock_minimo" value="0">
                <input type="hidden" id="editarConsumo" name="consumo_promedio_diario" value="0">
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="cerrarEditarModal()"><i class="fa-solid fa-xmark"></i> Cancelar</button>
                    <button type="button" id="btnEliminarInsumo" class="btn btn-danger" onclick="confirmarEliminar()"><i class="fa-solid fa-trash"></i> Eliminar</button>
                    <button type="submit" id="btnGuardarInsumo" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <div id="toastContainer" class="toast-container" aria-live="polite" aria-atomic="true"></div>

    <script src="assets/js/app.js.php"></script>
</body>
</html>
