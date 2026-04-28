<?php
@ini_set('display_errors', 0);
@ini_set('display_startup_errors', 0);
error_reporting(E_ERROR | E_PARSE);

require_once '../includes/db.php';

header('Content-Type: application/json');

$nombre = $_POST['nombre'];
$tipo_insumo = $_POST['tipo_insumo'] ?? '';
$unidad = $_POST['unidad'];
$capacidad = $_POST['capacidad'];
$stock = $_POST['stock'];
$minimo = $_POST['minimo'];
$consumo = $_POST['consumo'] ?? 0;

$stmt = $conn->prepare("INSERT INTO insumos (nombre, tipo_insumo, unidad, capacidad_maxima, stock_actual, stock_minimo, consumo_promedio_diario) VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssddddd", $nombre, $tipo_insumo, $unidad, $capacidad, $stock, $minimo, $consumo);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Error al agregar insumo']);
}
?>