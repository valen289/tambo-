<?php
require_once '../includes/db.php';

header('Content-Type: application/json');

$nombre = $_POST['nombre'];
$unidad = $_POST['unidad'];
$capacidad = $_POST['capacidad'];
$stock = $_POST['stock'];
$minimo = $_POST['minimo'];
$consumo = $_POST['consumo'] ?? 0;

$stmt = $conn->prepare("INSERT INTO insumos (nombre, unidad, capacidad_maxima, stock_actual, stock_minimo, consumo_promedio_diario) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssddid", $nombre, $unidad, $capacidad, $stock, $minimo, $consumo);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Error al agregar insumo']);
}
?>