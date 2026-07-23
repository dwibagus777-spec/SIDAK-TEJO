<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?= site_url('dashboard') ?>">SIDAK TEJO</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="<?= site_url('dashboard') ?>">Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= site_url('temuan') ?>">Input Temuan</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= site_url('ulps') ?>">Master ULP</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= site_url('penyulang') ?>">Master Penyulang</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= site_url('sections') ?>">Master Section</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= site_url('users') ?>">Master User</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= site_url('laporan') ?>">Pusat Laporan</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= site_url('identifikasi') ?>">Identifikasi Gangguan</a></li>
            </ul>
            <a class="btn btn-outline-light btn-sm" href="<?= site_url('logout') ?>">Logout</a>
        </div>
    </div>
</nav>
