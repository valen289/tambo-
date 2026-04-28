<?php
@ini_set('display_errors', 0);
@ini_set('display_startup_errors', 0);
error_reporting(E_ERROR | E_PARSE);

session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

$insumo_id = $_POST['insumo_id'] ?? null;
$cantidad = $_POST['cantidad'] ?? null;
$usuario_id = $_POST['usuario_id'] ?? null;
$observaciones = $_POST['observaciones'] ?? '';

if (!$usuario_id && isset($_SESSION['usuario_id'])) {
    $usuario_id = $_SESSION['usuario_id'];
}

if (!$insumo_id || !$cantidad || !$usuario_id) {
    echo json_encode(['success' => false, 'message' => 'Faltan datos obligatorios para registrar el consumo']);
    exit();
}

$insumo_id = (int) $insumo_id;
$usuario_id = (int) $usuario_id;
$cantidad = (float) str_replace(',', '.', $cantidad);

if ($cantidad <= 0) {
    echo json_encode(['success' => false, 'message' => 'La cantidad debe ser mayor que cero']);
    exit();
}

try {
    $stmt = $conn->prepare("SELECT stock_actual FROM insumos WHERE id = ?");
    $stmt->bind_param("i", $insumo_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $insumo = $result->fetch_assoc();

    if (!$insumo) {
        echo json_encode(['success' => false, 'message' => 'El insumo no existe']);
        exit();
    }

    if ($insumo['stock_actual'] < $cantidad) {
        echo json_encode(['success' => false, 'message' => 'Stock insuficiente']);
        exit();
    }

    $conn->begin_transaction();
    $transactionStarted = true;

    $stmt = $conn->prepare("INSERT INTO consumo_diario (insumo_id, usuario_id, cantidad, fecha, hora, observaciones) VALUES (?, ?, ?, CURDATE(), CURTIME(), ?)");
    $stmt->bind_param("iids", $insumo_id, $usuario_id, $cantidad, $observaciones);
    $stmt->execute();
    
    $stmt = $conn->prepare("UPDATE insumos SET stock_actual = stock_actual - ? WHERE id = ?");
    $stmt->bind_param("di", $cantidad, $insumo_id);
    $stmt->execute();
    
    $stmt = $conn->prepare(
        "UPDATE insumos i
         SET consumo_promedio_diario = (
             SELECT COALESCE(AVG(cantidad), 0)
             FROM consumo_diario cd
             WHERE cd.insumo_id = i.id
               AND cd.fecha >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
         )
         WHERE i.id = ?"
    );
    $stmt->bind_param("i", $insumo_id);
    $stmt->execute();
    
    $conn->commit();

    // Notificar si el silo en kilos está por quedarse sin stock
    notificarStockBajo($conn, $insumo_id, $usuario_id);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if (!empty($transactionStarted)) {
        $conn->rollback();
    }
    echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
}
?>