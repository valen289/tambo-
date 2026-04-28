<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'gestion_tambo');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

// Migraciones simples para esquema mínimo del sistema
// Asegura que la columna tipo_insumo exista antes de usarla.
$result = $conn->query("SHOW COLUMNS FROM insumos LIKE 'tipo_insumo'");
if ($result && $result->num_rows === 0) {
    $conn->query("ALTER TABLE insumos ADD COLUMN tipo_insumo VARCHAR(100) NOT NULL DEFAULT '' AFTER nombre");
}

// Asegura que las tablas de lotes y consumos existan.
$conn->query("CREATE TABLE IF NOT EXISTS lotes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    tipo_animal VARCHAR(100) NOT NULL,
    cantidad_animales INT NOT NULL DEFAULT 0,
    consumo_estimado_diario DECIMAL(10,2) DEFAULT 0,
    observaciones TEXT,
    activo BOOLEAN DEFAULT TRUE,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

try {
    $conn->query("CREATE TABLE IF NOT EXISTS consumos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        lote_id INT NOT NULL,
        insumo_id INT NOT NULL,
        usuario_id INT NULL,
        cantidad DECIMAL(10,2) NOT NULL,
        fecha DATE NOT NULL,
        hora TIME NOT NULL,
        observaciones TEXT,
        FOREIGN KEY (lote_id) REFERENCES lotes(id) ON DELETE CASCADE,
        FOREIGN KEY (insumo_id) REFERENCES insumos(id) ON DELETE CASCADE,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {
    error_log('No se pudo crear la tabla consumos: ' . $e->getMessage());
}

?>