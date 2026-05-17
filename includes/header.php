<header>
    <div class="header-left">
        <h1>SiCoDiEt</h1>
        <nav class="main-nav">
            <a href="silos.php"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L4 6v12h16V6l-8-4z"/><line x1="4" y1="10" x2="20" y2="10"/><line x1="4" y1="14" x2="20" y2="14"/><path d="M8 18v4h8v-4"/><path d="M8 22h8"/></svg> Silos</a>
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