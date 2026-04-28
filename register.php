<?php
session_start();
require_once 'includes/db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $cedula = trim($_POST['cedula'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $contrasena = $_POST['contrasena'] ?? '';
    $confirmar = $_POST['confirmar'] ?? '';
    $rol = $_POST['rol'] ?? 'operario';

    if (empty($nombre) || empty($apellido) || empty($cedula) || empty($contrasena) || empty($email)) {
        $error = 'Todos los campos obligatorios deben estar completos.';
    } elseif (!preg_match('/^[0-9]{6,8}$/', $cedula)) {
        $error = 'La cédula de identidad debe contener solo números (6 a 8 dígitos).';
    } elseif (!in_array($rol, ['operario', 'usuario'])) {
        $error = 'Rol inválido.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Ingrese un correo electrónico válido.';
    } elseif ($telefono !== '' && !preg_match('/^[0-9+\-\s]+$/', $telefono)) {
        $error = 'Ingrese un número de teléfono válido.';
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
            $stmt = $conn->prepare("INSERT INTO usuarios (cedula, nombre, password, email, telefono, rol) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss", $cedula, $nombreCompleto, $hash, $email, $telefono, $rol);
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
    <title>SiCoDiEt - Registro</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@21.2.6/build/css/intlTelInput.css">
</head>
<body>
    <div class="login-page">
        <div class="login-container" style="max-width: 460px;">
            <div class="login-box">

                <div class="logo">
                    <h2>SiCoDiEt</h2>
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
                        <input type="text" id="cedula" name="cedula" placeholder="Ej: 12345678"
                            value="<?= htmlspecialchars($_POST['cedula'] ?? '') ?>"
                            inputmode="numeric" pattern="[0-9]{6,8}" maxlength="8"
                            title="Solo números, entre 6 y 8 dígitos" required>
                    </div>

                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" placeholder="Ej: correo@gmail.com"
                            value="<?= filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL) ? htmlspecialchars($_POST['email']) : '' ?>"
                            autocomplete="email" required>
                    </div>

                    <div class="form-group">
                        <label>Teléfono</label>
                        <input type="tel" id="phone" name="telefono" placeholder="Teléfono" 
                            value="<?= htmlspecialchars($_POST['telefono'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label>Contraseña</label>
                        <div class="password-input-wrapper">
                            <input type="password" id="contrasena" name="contrasena" placeholder="Mínimo 6 caracteres" required>
                            <button type="button" class="password-toggle" data-target="contrasena" aria-label="Mostrar contraseña">👁</button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Confirmar Contraseña</label>
                        <div class="password-input-wrapper">
                            <input type="password" id="confirmar" name="confirmar" placeholder="Repita su contraseña" required>
                            <button type="button" class="password-toggle" data-target="confirmar" aria-label="Mostrar contraseña">👁</button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">Registrarse</button>
                </form>

                <script src="https://cdn.jsdelivr.net/npm/intl-tel-input@21.2.6/build/js/intlTelInput.min.js"></script>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        // Cédula: solo números en tiempo real
                        var cedulaInput = document.getElementById('cedula');
                        if (cedulaInput) {
                            cedulaInput.addEventListener('input', function() {
                                this.value = this.value.replace(/[^0-9]/g, '');
                            });
                            cedulaInput.addEventListener('keypress', function(e) {
                                if (!/[0-9]/.test(e.key)) e.preventDefault();
                            });
                        }

                        var phoneInput = document.querySelector("#phone");
                        if (phoneInput) {
                            var iti = window.intlTelInput(phoneInput, {
                                initialCountry: "uy",
                                preferredCountries: ["uy", "ar", "br"],
                                utilsScript: "https://cdn.jsdelivr.net/npm/intl-tel-input@21.2.6/build/js/utils.js",
                                formatOnDisplay: true,
                                autoPlaceholder: "aggressive"
                            });

                            phoneInput.addEventListener('change', function() {
                                var fullNumber = iti.getNumber();
                                phoneInput.value = fullNumber || this.value;
                            });
                        }

                        document.querySelectorAll('.password-toggle').forEach(function(button) {
                            button.addEventListener('click', function() {
                                var targetId = button.getAttribute('data-target');
                                var input = document.getElementById(targetId);
                                if (!input) return;
                                if (input.type === 'password') {
                                    input.type = 'text';
                                      button.textContent = '👁';
                                    button.setAttribute('aria-label', 'Ocultar contraseña');
                                } else {
                                    input.type = 'password';
                                    button.textContent = '👁';
                                    button.setAttribute('aria-label', 'Mostrar contraseña');
                                }
                            });
                        });
                    });
                </script>

                <?php endif; ?>

                <p style="text-align:center; margin-top: 20px; color: #7f8c8d; font-size: 14px;">
                    ¿Ya tenés cuenta? <a href="index.php" style="color: #9a7f65; font-weight: 600;">Iniciar sesión</a>
                </p>

            </div>
        </div>
    </div>
</body>
</html>