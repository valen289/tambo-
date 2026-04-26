<?php
session_start();
require_once 'includes/db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $cedula = trim($_POST['cedula'] ?? '');
    $contrasena = $_POST['contrasena'] ?? '';
    $confirmar = $_POST['confirmar'] ?? '';
    $rol = $_POST['rol'] ?? 'operario';

    if (empty($nombre) || empty($apellido) || empty($cedula) || empty($contrasena)) {
        $error = 'Todos los campos son obligatorios.';
    } elseif (!in_array($rol, ['operario', 'usuario'])) {
        $error = 'Rol inválido.';
    } elseif ($contrasena !== $confirmar) {
        $error = 'Las contraseñas no coinciden.';
    } elseif (strlen($contrasena) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres.';
    } else {
        $nombreCompleto = trim("$nombre $apellido");

        // Verificar si la cédula ya existe
        $stmt = $conn->prepare("SELECT id FROM usuarios WHERE cedula = ?");
        $stmt->bind_param("s", $cedula);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->fetch_assoc()) {
            $error = 'Ya existe un usuario con esa cédula.';
        } else {
            $hash = password_hash($contrasena, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO usuarios (cedula, nombre, password, rol) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $cedula, $nombreCompleto, $hash, $rol);
            $stmt->execute();
            $success = 'Usuario registrado exitosamente.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Tambo Pro - Registro</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="login-page">
        <div class="login-container" style="max-width: 460px;">
            <div class="login-box">

                <div class="logo">
                    <h2>Gestión de Tambo Pro</h2>
                    <p>Crear nueva cuenta</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <?= htmlspecialchars($success) ?>
                        <br><a href="index.php" style="color: var(--bg-soft); font-weight:600;">Ir al login</a>
                    </div>
                <?php else: ?>

                <form method="POST">
                    <div class="form-grid" style="grid-template-columns: 1fr 1fr;">
                        <div class="form-group">
                            <label>Nombre</label>
                            <input type="text" name="nombre" placeholder="Ej: Juan"
                                value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Apellido</label>
                            <input type="text" name="apellido" placeholder="Ej: Pérez"
                                value="<?= htmlspecialchars($_POST['apellido'] ?? '') ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Rol</label>
                        <select name="rol" required>
                            <option value="operario" <?= (($_POST['rol'] ?? '') === 'operario') ? 'selected' : '' ?>>Operario</option>
                            <option value="usuario" <?= (($_POST['rol'] ?? '') === 'usuario') ? 'selected' : '' ?>>Usuario</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Cédula de Identidad</label>
                        <input type="text" name="cedula" placeholder="Ej: 12345678"
                            value="<?= htmlspecialchars($_POST['cedula'] ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Contraseña</label>
                        <input type="password" name="contrasena" placeholder="Mínimo 6 caracteres" required>
                    </div>

                    <div class="form-group">
                        <label>Confirmar Contraseña</label>
                        <input type="password" name="confirmar" placeholder="Repita su contraseña" required>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">Registrarse</button>
                </form>

                <?php endif; ?>

                <p style="text-align:center; margin-top: 20px; color: #7f8c8d; font-size: 14px;">
                    ¿Ya tenés cuenta? <a href="index.php" style="color: #9a7f65; font-weight: 600;">Iniciar sesión</a>
                </p>

            </div>
        </div>
    </div>
</body>
</html>