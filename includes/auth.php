<?php
session_start();

function verificarSesion() {
    if (!isset($_SESSION['usuario_id'])) {
        header("Location: index.php");
        exit();
    }
}

function esAdmin() {
    return isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin';
}

function esOperario() {
    return isset($_SESSION['rol']) && $_SESSION['rol'] === 'operario';
}

function esUsuario() {
    return isset($_SESSION['rol']) && $_SESSION['rol'] === 'usuario';
}

function cerrarSesion() {
    session_destroy();
    header("Location: index.php");
    exit();
}
?>