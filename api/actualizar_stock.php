<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

verificarSesion();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

$accion = $_POST['accion'] ?? '';

switch ($accion) {
    case 'agregar_stock':
        // Agregar stock (compra/reposición)
        $insumo_id = intval($_POST['insumo_id']);
        $cantidad = floatval($_POST['cantidad']);
        $observaciones = $_POST['observaciones'] ?? '';
        
        if ($cantidad <= 0) {
            echo json_encode(['success' => false, 'message' => 'La cantidad debe ser mayor a 0']);
            exit();
        }
        
        // Verificar insumo
        $stmt = $conn->prepare("SELECT stock_actual, capacidad_maxima FROM insumos WHERE id = ?");
        $stmt->bind_param("i", $insumo_id);
        $stmt->execute();
        $insumo = $stmt->get_result()->fetch_assoc();
        
        if (!$insumo) {
            echo json_encode(['success' => false, 'message' => 'Insumo no encontrado']);
            exit();
        }
        
        $nuevoStock = $insumo['stock_actual'] + $cantidad;
        
        // Verificar capacidad máxima
        if ($nuevoStock > $insumo['capacidad_maxima']) {
            echo json_encode([
                'success' => false, 
                'message' => 'El stock excede la capacidad máxima (' . $insumo['capacidad_maxima'] . ')'
            ]);
            exit();
        }
        
        $conn->begin_transaction();
        
        try {
            // Actualizar stock
            $stmt = $conn->prepare("UPDATE insumos SET stock_actual = ? WHERE id = ?");
            $stmt->bind_param("di", $nuevoStock, $insumo_id);
            $stmt->execute();
            
            // Registrar en historial (cantidad negativa indica ingreso)
            $cantidad_negativa = -$cantidad;
            $stmt = $conn->prepare("
                INSERT INTO consumo_diario (insumo_id, usuario_id, cantidad, fecha, hora, observaciones, tipo_movimiento)
                VALUES (?, ?, ?, CURDATE(), CURTIME(), ?, 'ingreso')
            ");
            $stmt->bind_param("iids", $insumo_id, $_SESSION['usuario_id'], $cantidad_negativa, $observaciones);
            $stmt->execute();
            
            $conn->commit();
            
            echo json_encode([
                'success' => true, 
                'message' => 'Stock actualizado correctamente',
                'nuevo_stock' => $nuevoStock
            ]);
            
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;
        
    case 'ajustar_stock':
        // Ajuste manual de stock (inventario)
        $insumo_id = intval($_POST['insumo_id']);
        $nuevo_stock = floatval($_POST['nuevo_stock']);
        $motivo = $_POST['motivo'] ?? 'Ajuste de inventario';
        
        if ($nuevo_stock < 0) {
            echo json_encode(['success' => false, 'message' => 'El stock no puede ser negativo']);
            exit();
        }
        
        // Verificar insumo
        $stmt = $conn->prepare("SELECT stock_actual FROM insumos WHERE id = ?");
        $stmt->bind_param("i", $insumo_id);
        $stmt->execute();
        $insumo = $stmt->get_result()->fetch_assoc();
        
        if (!$insumo) {
            echo json_encode(['success' => false, 'message' => 'Insumo no encontrado']);
            exit();
        }
        
        $diferencia = $nuevo_stock - $insumo['stock_actual'];
        
        $conn->begin_transaction();
        
        try {
            // Actualizar stock
            $stmt = $conn->prepare("UPDATE insumos SET stock_actual = ? WHERE id = ?");
            $stmt->bind_param("di", $nuevo_stock, $insumo_id);
            $stmt->execute();
            
            // Registrar ajuste
            if ($diferencia != 0) {
                $tipo = $diferencia > 0 ? 'ajuste_positivo' : 'ajuste_negativo';
                $diferencia_abs = abs($diferencia);
                $stmt = $conn->prepare("
                    INSERT INTO consumo_diario (insumo_id, usuario_id, cantidad, fecha, hora, observaciones, tipo_movimiento)
                    VALUES (?, ?, ?, CURDATE(), CURTIME(), ?, ?)
                ");
                $stmt->bind_param("iids", $insumo_id, $_SESSION['usuario_id'], $diferencia_abs, $motivo, $tipo);
                $stmt->execute();
            }
            
            $conn->commit();
            
            echo json_encode([
                'success' => true, 
                'message' => 'Stock ajustado correctamente',
                'nuevo_stock' => $nuevo_stock,
                'diferencia' => $diferencia
            ]);
            
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;
        
    case 'obtener_insumo':
        // Obtener datos de un insumo
        $insumo_id = intval($_POST['insumo_id']);
        
        $stmt = $conn->prepare("SELECT * FROM insumos WHERE id = ?");
        $stmt->bind_param("i", $insumo_id);
        $stmt->execute();
        $insumo = $stmt->get_result()->fetch_assoc();
        
        if ($insumo) {
            echo json_encode(['success' => true, 'insumo' => $insumo]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Insumo no encontrado']);
        }
        break;

    case 'editar_insumo':
        $insumo_id = intval($_POST['insumo_id']);
        $nombre = trim($_POST['nombre'] ?? '');
        $unidad = trim($_POST['unidad'] ?? '');
        $capacidad_maxima = floatval($_POST['capacidad_maxima'] ?? 0);
        $stock_actual = floatval($_POST['stock_actual'] ?? 0);
        $stock_minimo = floatval($_POST['stock_minimo'] ?? 0);
        $consumo_promedio_diario = floatval($_POST['consumo_promedio_diario'] ?? 0);

        if (empty($nombre) || empty($unidad) || $capacidad_maxima <= 0 || $stock_minimo < 0 || $stock_actual < 0) {
            echo json_encode(['success' => false, 'message' => 'Datos inválidos para actualizar el insumo']);
            exit();
        }

        if ($stock_actual > $capacidad_maxima) {
            echo json_encode(['success' => false, 'message' => 'El stock actual no puede superar la capacidad máxima']);
            exit();
        }

        $stmt = $conn->prepare("UPDATE insumos SET nombre = ?, unidad = ?, capacidad_maxima = ?, stock_actual = ?, stock_minimo = ?, consumo_promedio_diario = ? WHERE id = ?");
        $stmt->bind_param("ssdddi", $nombre, $unidad, $capacidad_maxima, $stock_actual, $stock_minimo, $consumo_promedio_diario, $insumo_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Insumo actualizado correctamente']);
        } else {
            echo json_encode(['success' => false, 'message' => 'No se pudo actualizar el insumo']);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
        break;
}
?>