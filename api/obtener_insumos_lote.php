<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
verificarSesion();

header('Content-Type: application/json');

$lote_id = intval($_GET['lote_id'] ?? 0);

if ($lote_id <= 0) {
    echo json_encode(['error' => 'Lote no válido']);
    exit;
}

$stmt = $conn->prepare("
    SELECT li.cantidad_requerida, i.nombre AS insumo_nombre, i.tipo_insumo, i.unidad, i.stock_actual
    FROM lote_insumos li
    JOIN insumos i ON li.insumo_id = i.id
    WHERE li.lote_id = ? AND i.activo = TRUE
    ORDER BY i.nombre
");
$stmt->bind_param('i', $lote_id);
$stmt->execute();
$result = $stmt->get_result();

$insumos = [];
while ($row = $result->fetch_assoc()) {
    $insumos[] = $row;
}

echo json_encode($insumos);
?>