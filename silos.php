<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
verificarSesion();

// Obtener insumos con alertas
$insumos = $conn->query("
    SELECT i.*, 
           CASE 
               WHEN i.stock_actual <= i.stock_minimo * 0.5 THEN 'critico'
               WHEN i.stock_actual <= i.stock_minimo THEN 'bajo'
               ELSE 'normal'
           END as estado_alerta
    FROM insumos i 
    WHERE i.activo = TRUE
    ORDER BY 
        CASE 
            WHEN i.stock_actual <= i.stock_minimo * 0.5 THEN 1
            WHEN i.stock_actual <= i.stock_minimo THEN 2
            ELSE 3
        END,
        i.nombre
");

// Calcular días restantes y consumo promedio
$lista_insumos = [];
while ($insumo = $insumos->fetch_assoc()) {
    if ($insumo['consumo_promedio_diario'] > 0) {
        $insumo['dias_restantes'] = floor($insumo['stock_actual'] / $insumo['consumo_promedio_diario']);
    } else {
        $insumo['dias_restantes'] = 999;
    }
    
    // Detectar si es silo en kg con <= 1000 kilos
    $esSiloKg = stripos($insumo['nombre'], 'silo') !== false && strtolower(trim($insumo['unidad'])) === 'kg';
    $insumo['alerta_1000_kilos'] = $esSiloKg && $insumo['stock_actual'] <= 1000;
    
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
                <a href="actualizaciones.php" class="btn btn-primary">Ir a Últimas Actualizaciones</a>
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
                <p><strong>Último acceso:</strong> <?php echo htmlspecialchars($_SESSION['ultimo_acceso'] ?? 'Nunca'); ?></p>
                <?php if (esOperario()): ?>
                    <p>Esta es tu sección de operario. Aquí puedes registrar consumos y ver el stock disponible.</p>
                <?php elseif (esUsuario()): ?>
                    <p>Esta es tu sección de usuario administrativo. Aquí puedes gestionar y editar silos.</p>
                <?php else: ?>
                    <p>Esta es tu sección administrativa. Desde aquí puedes gestionar silos, usuarios y ajustes.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Control de Stock -->
        <div class="section-header">
            <h2>Silos</h2>
            <?php if (esUsuario() || esAdmin()): ?>
                <button class="btn btn-primary" onclick="abrirModalNuevoInsumo()">+ Agregar Silo</button>
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
                        <button class="btn-icon-edit" onclick="editarInsumo(<?php echo $insumo['id']; ?>)">Editar</button>
                    <?php endif; ?>
                </div>
                
                <div class="card-body">
                    <div class="stock-info">
                        <span class="stock-actual"><?php echo number_format($insumo['stock_actual'], 0, ',', '.'); ?></span>
                        <span class="stock-total">/<?php echo number_format($insumo['capacidad_maxima'], 0, ',', '.'); ?> <?php echo $insumo['unidad']; ?></span>
                    </div>
                    
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo min(100, max(0, ($insumo['stock_actual'] / $insumo['capacidad_maxima']) * 100)); ?>%"></div>
                    </div>
                    
                    <?php if ($insumo['estado_alerta'] === 'bajo'): ?>
                    <div class="alerta stock-bajo">Stock bajo</div>
                    <?php elseif ($insumo['estado_alerta'] === 'critico'): ?>
                    <div class="alerta stock-critico">Stock crítico</div>
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
                    <button type="button" class="btn btn-secondary" onclick="cerrarEditarModal()">Cancelar</button>
                    <button type="button" id="btnEliminarInsumo" class="btn btn-danger" onclick="confirmarEliminar()">Eliminar</button>
                    <button type="submit" id="btnGuardarInsumo" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <div id="toastContainer" class="toast-container" aria-live="polite" aria-atomic="true"></div>

    <script src="assets/js/app.js.php"></script>
</body>
</html>
