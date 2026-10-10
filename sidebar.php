
<?php
$current_page = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['user_role'] ?? '';
?>

<nav class="sidebar">
    <div class="sidebar-header">
        <h2>SimKlinik</h2>
        <p>Sistem Informasi Klinik</p>
    </div>

    <ul class="sidebar-nav">

        <!-- Dashboard -->
        <li class="nav-item">
            <a href="dashboard.php"
               class="nav-link <?= $current_page === 'dashboard.php' ? 'active' : '' ?>">
                📊 Dashboard
            </a>
        </li>

        <!-- Manajemen User: khusus admin -->
        <?php if ($role === 'admin'): ?>
        <li class="nav-item">
            <a href="manajemen_user.php"
               class="nav-link <?= $current_page === 'manajemen_user.php' ? 'active' : '' ?>">
                👤 Manajemen User
            </a>
        </li>
        <?php endif; ?>

        <!-- Data Klinik -->
        <li class="nav-item">
            <a href="pasien.php"
               class="nav-link <?= $current_page === 'pasien.php' ? 'active' : '' ?>">
                🧑‍🤝‍🧑 Data Pasien
            </a>
        </li>

        <li class="nav-item">
            <a href="dokter.php"
               class="nav-link <?= $current_page === 'dokter.php' ? 'active' : '' ?>">
                🩺 Data Dokter
            </a>
        </li>

        <li class="nav-item">
            <a href="poli.php"
               class="nav-link <?= $current_page === 'poli.php' ? 'active' : '' ?>">
                🏥 Data Poli
            </a>
        </li>

        <!-- Pelayanan -->
        <li class="nav-item">
            <a href="kunjungan.php"
               class="nav-link <?= $current_page === 'kunjungan.php' ? 'active' : '' ?>">
                📋 Pendaftaran / Kunjungan
            </a>
        </li>

        <li class="nav-item">
            <a href="rekam_medis.php"
               class="nav-link <?= $current_page === 'rekam_medis.php' ? 'active' : '' ?>">
                📝 Rekam Medis
            </a>
        </li>

        <!-- Farmasi -->
        <li class="nav-item">
            <a href="obat.php"
               class="nav-link <?= $current_page === 'obat.php' ? 'active' : '' ?>">
                💊 Data Obat
            </a>
        </li>

        <!-- Administrasi -->
        <li class="nav-item">
            <a href="pembayaran.php"
               class="nav-link <?= $current_page === 'pembayaran.php' ? 'active' : '' ?>">
                💳 Pembayaran
            </a>
        </li>

        <li class="nav-item">
            <a href="laporan.php"
               class="nav-link <?= $current_page === 'laporan.php' ? 'active' : '' ?>">
                📈 Laporan Klinik
            </a>
        </li>

        <!-- Logout -->
        <li class="nav-item">
            <a href="logout.php" class="nav-link">
                🚪 Logout
            </a>
        </li>

    </ul>
</nav>