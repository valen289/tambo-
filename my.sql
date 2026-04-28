-- Creación de la base de datos
CREATE DATABASE gestion_tambo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE gestion_tambo;

-- Tabla de Usuarios
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cedula VARCHAR(20) UNIQUE NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(150) NULL,
    telefono VARCHAR(30) NULL,
    rol ENUM('admin', 'operario', 'usuario') NOT NULL DEFAULT 'operario',
    activo BOOLEAN DEFAULT TRUE,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ultimo_acceso TIMESTAMP NULL
);

-- Tabla de Insumos
CREATE TABLE insumos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    tipo_insumo VARCHAR(100) NOT NULL DEFAULT '',
    unidad VARCHAR(50) NOT NULL, -- kg, fardos, litros, etc.
    capacidad_maxima DECIMAL(10,2) NOT NULL,
    stock_actual DECIMAL(10,2) NOT NULL DEFAULT 0,
    stock_minimo DECIMAL(10,2) NOT NULL,
    consumo_promedio_diario DECIMAL(10,2) DEFAULT 0,
    dias_restantes INT DEFAULT 0,
    activo BOOLEAN DEFAULT TRUE,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de Lotes de Ganado
CREATE TABLE lotes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    tipo_animal VARCHAR(100) NOT NULL,
    cantidad_animales INT NOT NULL DEFAULT 0,
    consumo_estimado_diario DECIMAL(10,2) DEFAULT 0,
    observaciones TEXT,
    activo BOOLEAN DEFAULT TRUE,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de Consumos por Lote
CREATE TABLE consumos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lote_id INT NOT NULL,
    insumo_id INT NOT NULL,
    usuario_id INT NOT NULL,
    cantidad DECIMAL(10,2) NOT NULL,
    fecha DATE NOT NULL,
    hora TIME NOT NULL,
    observaciones TEXT,
    FOREIGN KEY (lote_id) REFERENCES lotes(id),
    FOREIGN KEY (insumo_id) REFERENCES insumos(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

-- Tabla de Registro de Consumo
CREATE TABLE consumo_diario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    insumo_id INT NOT NULL,
    usuario_id INT NOT NULL,
    cantidad DECIMAL(10,2) NOT NULL,
    fecha DATE NOT NULL,
    hora TIME NOT NULL,
    observaciones TEXT,
    FOREIGN KEY (insumo_id) REFERENCES insumos(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

-- Tabla de Ganado
CREATE TABLE ganado (
    id INT AUTO_INCREMENT PRIMARY KEY,
    total_vacas INT NOT NULL DEFAULT 0,
    vacas_lechera INT DEFAULT 0,
    vacas_seco INT DEFAULT 0,
    terneros INT DEFAULT 0,
    fecha_registro DATE NOT NULL,
    usuario_id INT,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

-- Tabla de Alertas
CREATE TABLE alertas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    insumo_id INT,
    tipo ENUM('stock_bajo', 'stock_critico', 'vencimiento') NOT NULL,
    mensaje TEXT NOT NULL,
    leida BOOLEAN DEFAULT FALSE,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (insumo_id) REFERENCES insumos(id)
);

-- Usuario admin por defecto (password: admin123)
INSERT INTO usuarios (cedula, nombre, password, rol) VALUES 
('12345678', 'Administrador', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Insumos de ejemplo
INSERT INTO insumos (nombre, tipo_insumo, unidad, capacidad_maxima, stock_actual, stock_minimo) VALUES
('Maíz en Grano', 'Maíz', 'kg', 10000, 8500, 2000),
('Soja', 'Soja', 'kg', 5000, 4200, 1000),
('Silo de Maíz', 'Maíz', 'kg', 40000, 25000, 5000),
('Alfalfa', 'Alfalfa', 'fardos', 2000, 1200, 300),
('Arroz', 'Arroz', 'kg', 4000, 2000, 500);

-- Registro inicial de ganado
INSERT INTO ganado (total_vacas, vacas_lechera, vacas_seco, terneros, fecha_registro) VALUES
(198, 150, 30, 18, CURDATE());

-- Tabla de Logs de Actividad
CREATE TABLE logs_actividad (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT,
    accion VARCHAR(100) NOT NULL,
    descripcion TEXT,
    fecha_hora TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

-- Agregar campo tipo_movimiento a consumo_diario
ALTER TABLE consumo_diario 
ADD COLUMN tipo_movimiento ENUM('consumo', 'ingreso', 'ajuste_positivo', 'ajuste_negativo') DEFAULT 'consumo' AFTER observaciones;

-- Agregar campo tipo_insumo a insumos
ALTER TABLE insumos
ADD COLUMN IF NOT EXISTS tipo_insumo VARCHAR(100) NOT NULL DEFAULT '' AFTER nombre;

-- Crear tablas de lotes y consumos si no existen
CREATE TABLE IF NOT EXISTS lotes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    tipo_animal VARCHAR(100) NOT NULL,
    cantidad_animales INT NOT NULL DEFAULT 0,
    consumo_estimado_diario DECIMAL(10,2) DEFAULT 0,
    observaciones TEXT,
    activo BOOLEAN DEFAULT TRUE,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS consumos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lote_id INT NOT NULL,
    insumo_id INT NOT NULL,
    usuario_id INT NOT NULL,
    cantidad DECIMAL(10,2) NOT NULL,
    fecha DATE NOT NULL,
    hora TIME NOT NULL,
    observaciones TEXT,
    FOREIGN KEY (lote_id) REFERENCES lotes(id),
    FOREIGN KEY (insumo_id) REFERENCES insumos(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

-- Agregar campos de contacto a usuarios
ALTER TABLE usuarios
ADD COLUMN IF NOT EXISTS email VARCHAR(150) NULL AFTER password,
ADD COLUMN IF NOT EXISTS telefono VARCHAR(30) NULL AFTER email;