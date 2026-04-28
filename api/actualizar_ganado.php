<?php
@ini_set('display_errors', 0);
@ini_set('display_startup_errors', 0);
error_reporting(E_ERROR | E_PARSE);

require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');
verificarSesion();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$cambio = isset($input['cambio']) ? intval($input['cambio']) : 0;

if ($cambio === 0) {
    echo json_encode(['success' => false, 'message' => 'No se recibió un cambio válido']);
    exit();
}

$ganado = $conn->query("SELECT * FROM ganado ORDER BY fecha_registro DESC LIMIT 1")->fetch_assoc();

if (!$ganado) {
    $ganado = [
        'total_vacas' => 0,
        'vacas_lechera' => 0,
        'vacas_seco' => 0,
        'terneros' => 0
    ];
}

$nuevoTotal = max(0, intval($ganado['total_vacas']) + $cambio);

$stmt = $conn->prepare("INSERT INTO ganado (total_vacas, vacas_lechera, vacas_seco, terneros, fecha_registro, usuario_id) VALUES (?, ?, ?, ?, CURDATE(), ?)");
$stmt->bind_param(
    "iiiii",
    $nuevoTotal,
    $ganado['vacas_lechera'],
    $ganado['vacas_seco'],
    $ganado['terneros'],
    $_SESSION['usuario_id']
);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Ganado actualizado correctamente', 'total_vacas' => $nuevoTotal]);
} else {
    echo json_encode(['success' => false, 'message' => 'Error al actualizar el ganado']);
}
?>