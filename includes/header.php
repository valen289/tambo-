<header>
    <div class="header-left">
        <h1>SiCoDiEt</h1>
        <nav class="main-nav">
            <a href="silos.php">Silos</a>
            <a href="lotes.php">Lotes</a>
            <a href="consumos.php">Consumos</a>
        </nav>
    </div>
    <div class="header-right">
        <span><?php echo htmlspecialchars($_SESSION['nombre']); ?></span>
        <span class="badge"><?php echo ucfirst($_SESSION['rol']); ?></span>
        <a href="logout.php" class="btn btn-secondary">Cerrar Sesión</a>
    </div>
</header>