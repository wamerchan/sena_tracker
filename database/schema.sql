-- Crear la base de datos
CREATE DATABASE IF NOT EXISTS sena_tracker CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sena_tracker;

-- Tabla 1: Instructor
CREATE TABLE IF NOT EXISTS instructor (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombres VARCHAR(100) NOT NULL,
    apellidos VARCHAR(100) NOT NULL,
    cedula VARCHAR(20) UNIQUE NOT NULL,
    correo VARCHAR(100) UNIQUE NOT NULL,
    telefono VARCHAR(20),
    cargo VARCHAR(50) DEFAULT 'Instructor SENA'
) ENGINE=InnoDB;

-- Tabla 2: Aprendices
CREATE TABLE IF NOT EXISTS aprendices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombres VARCHAR(100) NOT NULL,
    apellidos VARCHAR(100) NOT NULL,
    cedula VARCHAR(20) UNIQUE NOT NULL,
    correo VARCHAR(100) UNIQUE NOT NULL,
    telefono VARCHAR(20),
    codigo_curso VARCHAR(50) NOT NULL
) ENGINE=InnoDB;

-- Tabla 3: Evidencias
CREATE TABLE IF NOT EXISTS evidencias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fase ENUM('Fase I Análisis', 'Fase II Planeación', 'Fase III Ejecución') NOT NULL,
    guia_aprendizaje ENUM('GA1', 'GA2', 'GA3', 'GA4', 'GA5', 'GA6', 'GA7', 'GA8', 'GA9') NOT NULL,
    codigo_evidencia VARCHAR(50) NOT NULL,
    descripcion TEXT,
    observaciones TEXT,
    fecha_entrega DATETIME NOT NULL
) ENGINE=InnoDB;

-- Tabla 4: Calificaciones (Tabla relacional o transaccional)
CREATE TABLE IF NOT EXISTS calificaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_aprendiz INT NOT NULL,
    id_evidencia INT NOT NULL,
    estado_calificacion ENUM('Sin calificar', 'Aprobada', 'Devuelta') DEFAULT 'Sin calificar',
    fecha_calificacion TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_aprendiz) REFERENCES aprendices(id) ON DELETE CASCADE,
    FOREIGN KEY (id_evidencia) REFERENCES evidencias(id) ON DELETE CASCADE,
    UNIQUE(id_aprendiz, id_evidencia)
) ENGINE=InnoDB;

-- Insertar Datos de Prueba Básicos (Opcional, para testear)
INSERT INTO instructor (nombres, apellidos, cedula, correo) VALUES 
('Juan', 'Pérez', '12345678', 'jperez@sena.edu.co') 
ON DUPLICATE KEY UPDATE nombres=nombres;
