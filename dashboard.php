<?php
require_once 'config/config.php';
requireLogin();

$database = new Database();
$db = $database->getConnection();

// Menghitung data dashboard
$jumlahPasien = $db->query(
    "SELECT COUNT(*) FROM pasien"
)->fetchColumn();

$jumlahDokter = $db->query(
    "SELECT COUNT(*) FROM dokter"
)->fetchColumn();

$jumlahPoli = $db->query(
    "SELECT COUNT(*) FROM poli"
)->fetchColumn();

$stmtKunjungan = $db->prepare(
    "SELECT COUNT(*) FROM kunjungan
     WHERE tanggal_kunjungan = CURDATE()"
);
$stmtKunjungan->execute();
$kunjunganHariIni = $stmtKunjungan->fetchColumn();

// Kunjungan terbaru
$stmt = $db->query(
    "SELECT k.no_kunjungan, k.tanggal_kunjungan,
            k.status, p.nama_pasien, po.nama_poli
     FROM kunjungan k
     JOIN pasien p ON k.pasien_id = p.id
     LEFT JOIN poli po ON k.poli_id = po.id
     ORDER BY k.id DESC
     LIMIT 5"
);
$kunjunganTerbaru = $stmt->fetchAll(PDO::FETCH_ASSOC);

$nama = $_SESSION['nama_lengkap'] ?? 'Pengguna';
$role = $_SESSION['user_role'] ?? '-';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard SimKlinik</title>
    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        .dashboard-welcome {
            background: #eaf3ff;
            padding: 22px;
            border-radius: 12px;
            margin-bottom: 24px;
        }

        .dashboard-welcome h2 {
            margin-top: 0;
            color: #174ea6;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 28px;
        }

        .stat-card {
            background: #fff;
            border-radius: 12px;
            padding: 22px;
            box-shadow: 0 2px 10px rgba(0,0,0,.06);
            border-left: 4px solid #2878d0;
        }

        .stat-card p {
            color: #64748b;
            margin-bottom: 10px;
        }

        .stat-card h2 {
            font-size: 28px;
            margin: 0;
            color: #1e293b;
        }

        .dashboard-table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
        }

        .dashboard-table th,
        .dashboard-table td {
            padding: 13px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }

        .dashboard-table th {
            background: #f1f5f9;
        }

        .table-responsive {
            overflow-x: auto;
        }

        @media (max-width: 900px) {
            .stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
<div class="main-container">

    <?php require 'sidebar.php'; ?>

    <main class="main-content">
        <header class="top-nav">
            <h1>Dashboard Klinik</h1>

            <div class="user-info">
                <div class="user-details">
                    <div class="user-name">
                        <?= htmlspecialchars($nama) ?>
                    </div>
                    <div class="user-role">
                        <?= htmlspecialchars(ucfirst($role)) ?>
                    </div>
                </div>
            </div>
        </header>

        <div class="content">

            <section class="dashboard-welcome">
                <h2>Selamat Datang di SimKlinik</h2>
                <p>
                    Halo, <?= htmlspecialchars($nama) ?>!
                    Berikut ringkasan informasi operasional klinik.
                </p>
            </section>

            <section class="stats-grid">
                <div class="stat-card">
                    <p>Total Pasien</p>
                    <h2><?= number_format($jumlahPasien) ?></h2>
                </div>

                <div class="stat-card">
                    <p>Total Dokter</p>
                    <h2><?= number_format($jumlahDokter) ?></h2>
                </div>

                <div class="stat-card">
                    <p>Total Poli</p>
                    <h2><?= number_format($jumlahPoli) ?></h2>
                </div>

                <div class="stat-card">
                    <p>Kunjungan Hari Ini</p>
                    <h2><?= number_format($kunjunganHariIni) ?></h2>
                </div>
            </section>

            <section class="table-container">
                <h3>Kunjungan Terbaru</h3>

                <div class="table-responsive">
                    <table class="dashboard-table">
                        <thead>
                            <tr>
                                <th>No. Kunjungan</th>
                                <th>Nama Pasien</th>
                                <th>Poli</th>
                                <th>Tanggal</th>
                                <th>Status</th>
                            </tr>
                        </thead>

                        <tbody>
                        <?php if (empty($kunjunganTerbaru)): ?>
                            <tr>
                                <td colspan="5">
                                    Belum ada data kunjungan.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($kunjunganTerbaru as $k): ?>
                                <tr>
                                    <td>
                                        <?= htmlspecialchars($k['no_kunjungan']) ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($k['nama_pasien']) ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($k['nama_poli'] ?? '-') ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($k['tanggal_kunjungan']) ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars(ucfirst($k['status'])) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

        </div>
    </main>
</div>
</body>
</html>