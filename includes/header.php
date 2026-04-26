<header>
    <div class="header-left">
        <h1> Gestión de Tambo Pro</h1>
    </div>
    <div class="header-right">
        <span><?php echo htmlspecialchars($_SESSION['nombre']); ?></span>
        <span class="badge"><?php echo ucfirst($_SESSION['rol']); ?></span>
        <a href="logout.php" class="btn btn-secondary">Cerrar Sesión</a>
    </div>
</header>