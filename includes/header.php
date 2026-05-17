<header>
    <div class="header-left">
        <h1>SiCoDiEt</h1>
        <nav class="main-nav">
            <a href="silos.php"> Silos</a>
            <a href="lotes.php"><i class="fa-solid fa-layer-group"></i> Lotes</a>
            <a href="consumos.php"><i class="fa-solid fa-chart-line"></i> Consumos</a>
        </nav>
    </div>
    <div class="header-right">
        <span><?php echo htmlspecialchars($_SESSION['nombre']); ?></span>
        <span class="badge"><?php echo ucfirst($_SESSION['rol']); ?></span>
        <a href="logout.php" class="btn btn-secondary"><i class="fa-solid fa-right-from-bracket"></i> Cerrar Sesión</a>
    </div>
</header>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">