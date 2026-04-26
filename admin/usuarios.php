<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

verificarSesion();

if (!esAdmin()) {
    header("Location: ../dashboard.php");
    exit();
}

$mensaje = '';
$tipoMensaje = '';
$usuarioEditando = null;

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    switch ($_POST['accion']) {
        case 'crear':
            $cedula = $_POST['cedula'];
            $nombre = $_POST['nombre'];
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $rol = $_POST['rol'];
            
            $stmt = $conn->prepare("INSERT INTO usuarios (cedula, nombre, password, rol) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $cedula, $nombre, $password, $rol);
            
            if ($stmt->execute()) {
                $mensaje = "Usuario creado correctamente";
                $tipoMensaje = 'success';
            } else {
                $mensaje = "Error: La cédula ya existe";
                $tipoMensaje = 'error';
            }
            break;
            
        case 'editar':
            $id = $_POST['id'];
            $nombre = $_POST['nombre'];
            $rol = $_POST['rol'];
            
            $stmt = $conn->prepare("UPDATE usuarios SET nombre = ?, rol = ? WHERE id = ?");
            $stmt->bind_param("ssi", $nombre, $rol, $id);
            
            if ($stmt->execute()) {
                $mensaje = "Usuario actualizado correctamente";
                $tipoMensaje = 'success';
            } else {
                $mensaje = "Error al actualizar usuario";
                $tipoMensaje = 'error';
            }
            break;
            
        case 'eliminar':
            $id = $_POST['id'];
            
            // No permitir eliminarse a sí mismo
            if ($id == $_SESSION['usuario_id']) {
                $mensaje = "No puedes eliminarte a ti mismo";
                $tipoMensaje = 'error';
            } else {
                $stmt = $conn->prepare("UPDATE usuarios SET activo = FALSE WHERE id = ?");
                $stmt->bind_param("i", $id);
                
                if ($stmt->execute()) {
                    $mensaje = "Usuario eliminado correctamente";
                    $tipoMensaje = 'success';
                } else {
                    $mensaje = "Error al eliminar usuario";
                    $tipoMensaje = 'error';
                }
            }
            break;
            
        case 'cambiar_password':
            $id = $_POST['id'];
            $nueva_password = password_hash($_POST['nueva_password'], PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $nueva_password, $id);
            
            if ($stmt->execute()) {
                $mensaje = "Contraseña cambiada correctamente";
                $tipoMensaje = 'success';
            } else {
                $mensaje = "Error al cambiar contraseña";
                $tipoMensaje = 'error';
            }
            break;
    }
}

// Obtener usuario para editar
if (isset($_GET['editar'])) {
    $id = intval($_GET['editar']);
    $usuarioEditando = $conn->query("SELECT * FROM usuarios WHERE id = $id")->fetch_assoc();
}

// Obtener todos los usuarios
$usuarios = $conn->query("SELECT * FROM usuarios ORDER BY fecha_creacion DESC");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - Gestión de Tambo Pro</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <h1>👥 Gestión de Usuarios</h1>
        
        <?php if ($mensaje): ?>
            <div class="alert alert-<?php echo $tipoMensaje; ?>">
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>
        
        <div class="grid-2-cols">
            <!-- Formulario de Usuario -->
            <div class="card">
                <h2><?php echo $usuarioEditando ? 'Editar Usuario' : 'Nuevo Usuario'; ?></h2>
                
                <form method="POST">
                    <?php if ($usuarioEditando): ?>
                        <input type="hidden" name="accion" value="editar">
                        <input type="hidden" name="id" value="<?php echo $usuarioEditando['id']; ?>">
                        
                        <div class="form-group">
                            <label>Cédula</label>
                            <input type="text" value="<?php echo htmlspecialchars($usuarioEditando['cedula']); ?>" disabled>
                            <small>La cédula no se puede modificar</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="nombre">Nombre Completo</label>
                            <input type="text" id="nombre" name="nombre" 
                                   value="<?php echo htmlspecialchars($usuarioEditando['nombre']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="rol">Rol</label>
                            <select id="rol" name="rol" required>
                                <option value="operario" <?php echo $usuarioEditando['rol'] === 'operario' ? 'selected' : ''; ?>>Operario</option>
                                <option value="admin" <?php echo $usuarioEditando['rol'] === 'admin' ? 'selected' : ''; ?>>Administrador</option>
                            </select>
                        </div>
                        
                        <div class="form-actions">
                            <a href="usuarios.php" class="btn btn-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-primary">Actualizar Usuario</button>
                        </div>
                        
                    <?php else: ?>
                        <input type="hidden" name="accion" value="crear">
                        
                        <div class="form-group">
                            <label for="cedula">Cédula de Identidad</label>
                            <input type="text" id="cedula" name="cedula" 
                                   placeholder="Ej: 12345678" maxlength="8" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="nombre">Nombre Completo</label>
                            <input type="text" id="nombre" name="nombre" 
                                   placeholder="Nombre y apellido" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="password">Contraseña</label>
                            <input type="password" id="password" name="password" 
                                   placeholder="Mínimo 6 caracteres" minlength="6" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="rol">Rol</label>
                            <select id="rol" name="rol" required>
                                <option value="operario">Operario</option>
                                <option value="admin">Administrador</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-block">Crear Usuario</button>
                    <?php endif; ?>
                </form>
            </div>
            
            <!-- Cambiar Contraseña -->
            <div class="card">
                <h2>🔑 Cambiar Contraseña</h2>
                <form method="POST">
                    <input type="hidden" name="accion" value="cambiar_password">
                    
                    <div class="form-group">
                        <label for="user_password">Seleccionar Usuario</label>
                        <select id="user_password" name="id" required>
                            <option value="">-- Seleccione un usuario --</option>
                            <?php 
                            $usuarios->data_seek(0);
                            while ($user = $usuarios->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $user['id']; ?>">
                                    <?php echo htmlspecialchars($user['nombre']); ?> (<?php echo $user['cedula']; ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="nueva_password">Nueva Contraseña</label>
                        <input type="password" id="nueva_password" name="nueva_password" 
                               placeholder="Mínimo 6 caracteres" minlength="6" required>
                    </div>
                    
                    <button type="submit" class="btn btn-warning btn-block">Cambiar Contraseña</button>
                </form>
            </div>
        </div>
        
        <!-- Lista de Usuarios -->
        <div class="card">
            <h2>Usuarios Registrados</h2>
            
            <div class="tabla-container">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>Cédula</th>
                            <th>Nombre</th>
                            <th>Rol</th>
                            <th>Estado</th>
                            <th>Último Acceso</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $usuarios->data_seek(0);
                        while ($user = $usuarios->fetch_assoc()): 
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['cedula']); ?></td>
                            <td><?php echo htmlspecialchars($user['nombre']); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $user['rol']; ?>">
                                    <?php echo ucfirst($user['rol']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($user['activo']): ?>
                                    <span class="estado activo">● Activo</span>
                                <?php else: ?>
                                    <span class="estado inactivo">● Inactivo</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo $user['ultimo_acceso'] ? fechaHoraFormateada($user['ultimo_acceso']) : 'Nunca'; ?>
                            </td>
                            <td class="acciones">
                                <?php if ($user['id'] != $_SESSION['usuario_id']): ?>
                                    <a href="?editar=<?php echo $user['id']; ?>" class="btn-icon-small" title="Editar">Editar</a>
                                    
                                    <form method="POST" style="display: inline;" 
                                          onsubmit="return confirm('¿Está seguro de eliminar este usuario?');">
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" class="btn-icon-small btn-delete" title="Eliminar">Eliminar</button>
                                    </form>
                                <?php else: ?>
                                    <span style="color: #999;">(Usted)</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/app.js.php"></script>
</body>
</html>