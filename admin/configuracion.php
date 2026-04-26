<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

verificarSesion();

if (!esAdmin()) {
    header("Location: ../dashboard.php");
    exit();
}

$mensaje = '';
$tipoMensaje = '';

// Actualizar configuración general
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    switch ($_POST['accion']) {
        case 'actualizar_ganado':
            $total_vacas = $_POST['total_vacas'];
            $vacas_lechera = $_POST['vacas_lechera'];
            $vacas_seco = $_POST['vacas_seco'];
            $terneros = $_POST['terneros'];
            
            $stmt = $conn->prepare("INSERT INTO ganado (total_vacas, vacas_lechera, vacas_seco, terneros, fecha_registro, usuario_id) VALUES (?, ?, ?, ?, CURDATE(), ?)");
            $stmt->bind_param("iiiii", $total_vacas, $vacas_lechera, $vacas_seco, $terneros, $_SESSION['usuario_id']);
            
            if ($stmt->execute()) {
                $mensaje = "Datos de ganado actualizados correctamente";
                $tipoMensaje = 'success';
            } else {
                $mensaje = "Error al actualizar datos de ganado";
                $tipoMensaje = 'error';
            }
            break;
            
        case 'actualizar_alertas':
            $insumo_id = $_POST['insumo_id'];
            $stock_minimo = $_POST['stock_minimo'];
            
            $stmt = $conn->prepare("UPDATE insumos SET stock_minimo = ? WHERE id = ?");
            $stmt->bind_param("di", $stock_minimo, $insumo_id);
            
            if ($stmt->execute()) {
                $mensaje = "Stock mínimo actualizado correctamente";
                $tipoMensaje = 'success';
            } else {
                $mensaje = "Error al actualizar stock mínimo";
                $tipoMensaje = 'error';
            }
            break;
    }
}

// Obtener último registro de ganado
$ganado = $conn->query("SELECT * FROM ganado ORDER BY fecha_registro DESC LIMIT 1")->fetch_assoc();

// Obtener todos los insumos para configurar alertas
$insumos = $conn->query("SELECT * FROM insumos WHERE activo = TRUE ORDER BY nombre");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración - Gestión de Tambo Pro</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <h1>⚙️ Configuración del Sistema</h1>
        
        <?php if ($mensaje): ?>
            <div class="alert alert-<?php echo $tipoMensaje; ?>">
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>
        
        <div class="grid-config">
            <!-- Configuración de Ganado -->
            <div class="card">
                <h2>📊 Registro de Ganado</h2>
                <form method="POST">
                    <input type="hidden" name="accion" value="actualizar_ganado">
                    
                    <div class="form-group">
                        <label for="total_vacas">Total de Vacas</label>
                        <input type="number" id="total_vacas" name="total_vacas" 
                               value="<?php echo $ganado['total_vacas'] ?? 0; ?>" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="vacas_lechera">Vacas en Producción</label>
                            <input type="number" id="vacas_lechera" name="vacas_lechera" 
                                   value="<?php echo $ganado['vacas_lechera'] ?? 0; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="vacas_seco">Vacas Secas</label>
                            <input type="number" id="vacas_seco" name="vacas_seco" 
                                   value="<?php echo $ganado['vacas_seco'] ?? 0; ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="terneros">Terneros</label>
                        <input type="number" id="terneros" name="terneros" 
                               value="<?php echo $ganado['terneros'] ?? 0; ?>" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block">Guardar Datos de Ganado</button>
                </form>
            </div>
            
            <!-- Configuración de Alertas por Insumo -->
            <div class="card">
                <h2>Configuración de Alertas</h2>
                <p>Defina los niveles mínimos de stock para cada insumo:</p>
                
                <div class="lista-alertas">
                    <?php while ($insumo = $insumos->fetch_assoc()): ?>
                    <div class="item-alerta">
                        <div class="info-insumo">
                            <strong><?php echo htmlspecialchars($insumo['nombre']); ?></strong>
                            <small>Stock actual: <?php echo number_format($insumo['stock_actual'], 0, ',', '.'); ?> <?php echo $insumo['unidad']; ?></small>
                        </div>
                        
                        <form method="POST" class="form-inline">
                            <input type="hidden" name="accion" value="actualizar_alertas">
                            <input type="hidden" name="insumo_id" value="<?php echo $insumo['id']; ?>">
                            
                            <div class="form-group-small">
                                <label>Stock Mínimo:</label>
                                <input type="number" name="stock_minimo" 
                                       value="<?php echo $insumo['stock_minimo']; ?>" 
                                       step="0.01" required>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Actualizar</button>
                        </form>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
            
            <!-- Estadísticas del Sistema -->
            <div class="card">
                <h2>📈 Estadísticas del Sistema</h2>
                
                <?php
                $stats = $conn->query("
                    SELECT 
                        (SELECT COUNT(*) FROM usuarios WHERE activo = TRUE) as total_usuarios,
                        (SELECT COUNT(*) FROM insumos WHERE activo = TRUE) as total_insumos,
                        (SELECT COUNT(*) FROM consumo_diario WHERE fecha = CURDATE()) as consumos_hoy,
                        (SELECT COUNT(*) FROM consumo_diario WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)) as consumos_semana
                ")->fetch_assoc();
                ?>
                
                <div class="stats-grid">
                    <div class="stat-item">
                        <span class="stat-number"><?php echo $stats['total_usuarios']; ?></span>
                        <span class="stat-label">Usuarios Activos</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?php echo $stats['total_insumos']; ?></span>
                        <span class="stat-label">Insumos Registrados</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?php echo $stats['consumos_hoy']; ?></span>
                        <span class="stat-label">Consumos Hoy</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?php echo $stats['consumos_semana']; ?></span>
                        <span class="stat-label">Consumos esta Semana</span>
                    </div>
                </div>
            </div>
            
            <!-- Información del Sistema -->
            <div class="card">
                <h2> Información del Sistema</h2>
                <div class="info-sistema">
                    <p><strong>Versión:</strong> 1.0.0</p>
                    <p><strong>Base de Datos:</strong> <?php echo DB_NAME; ?></p>
                    <p><strong>Servidor:</strong> <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Desconocido'; ?></p>
                    <p><strong>PHP Version:</strong> <?php echo phpversion(); ?></p>
                    <p><strong>Último Backup:</strong> 
                        <?php
                        $ultimo_consumo = $conn->query("SELECT MAX(fecha) as fecha FROM consumo_diario")->fetch_assoc();
                        echo $ultimo_consumo['fecha'] ? fechaFormateada($ultimo_consumo['fecha']) : 'Sin registros';
                        ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/app.js.php"></script>
</body>
</html>