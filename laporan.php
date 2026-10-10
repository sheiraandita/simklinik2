<?php
require_once 'config/config.php';

requireLogin();

$db = (new Database())->getConnection();

$tanggal_awal = $_GET['tanggal_awal'] ?? date('Y-m-01');
$tanggal_akhir = $_GET['tanggal_akhir'] ?? date('Y-m-d');
$jenis_laporan = $_GET['jenis_laporan'] ?? 'kunjungan';

$jenis_valid = ['kunjungan', 'pembayaran'];

if (!in_array($jenis_laporan, $jenis_valid, true)) {
    $jenis_laporan = 'kunjungan';
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal_awal)) {
    $tanggal_awal = date('Y-m-01');
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal_akhir)) {
    $tanggal_akhir = date('Y-m-d');
}

$laporan = [];
$total_data = 0;
$total_pendapatan = 0;
$error = '';

try {
    if ($tanggal_awal <= $tanggal_akhir) {

        if ($jenis_laporan === 'kunjungan') {
            $sql = "
                SELECT
                    k.id,
                    k.tanggal_kunjungan,
                    p.no_rm,
                    p.nama_pasien,
                    d.nama_dokter,
                    po.nama_poli,
                    k.status
                FROM kunjungan k
                LEFT JOIN pasien p ON p.id = k.pasien_id
                LEFT JOIN dokter d ON d.id = k.dokter_id
                LEFT JOIN poli po ON po.id = k.poli_id
                WHERE k.tanggal_kunjungan >= ?
                  AND k.tanggal_kunjungan < DATE_ADD(?, INTERVAL 1 DAY)
                ORDER BY k.tanggal_kunjungan DESC
            ";

            $stmt = $db->prepare($sql);
            $stmt->execute([$tanggal_awal, $tanggal_akhir]);
            $laporan = $stmt->fetchAll(PDO::FETCH_ASSOC);

        } else {
            $sql = "
                SELECT
                    b.id,
                    b.no_pembayaran,
                    b.tanggal_pembayaran,
                    p.no_rm,
                    p.nama_pasien,
                    b.metode_pembayaran,
                    b.total_tagihan,
                    b.total_bayar,
                    b.status
                FROM pembayaran b
                LEFT JOIN pasien p ON p.id = b.pasien_id
                WHERE b.tanggal_pembayaran >= ?
                  AND b.tanggal_pembayaran < DATE_ADD(?, INTERVAL 1 DAY)
                ORDER BY b.tanggal_pembayaran DESC
            ";

            $stmt = $db->prepare($sql);
            $stmt->execute([$tanggal_awal, $tanggal_akhir]);
            $laporan = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($laporan as $item) {
                if (($item['status'] ?? '') === 'lunas') {
                    $total_pendapatan += (float) ($item['total_bayar'] ?? 0);
                }
            }
        }

        $total_data = count($laporan);

    } else {
        $tanggal_awal = date('Y-m-01');
        $tanggal_akhir = date('Y-m-d');
    }

} catch (PDOException $e) {
    $error = 'Data laporan gagal dimuat. Periksa kembali struktur tabel dan nama kolom database.';
}

function e($value)
{
    return htmlspecialchars((string) ($value ?? '-'), ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan - SIM Klinik</title>
    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        .report-page { padding: 20px; }

        .report-page .report-card,
        .report-page .summary-box {
            background: #fff;
            padding: 18px;
            border-radius: 10px;
            margin-bottom: 18px;
            box-shadow: 0 2px 8px #0000000d;
        }

        .report-page .filter-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            align-items: end;
        }

        .report-page label {
            display: block;
            font-size: 13px;
            margin-bottom: 5px;
        }

        .report-page input,
        .report-page select {
            box-sizing: border-box;
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #d5d9df;
            border-radius: 6px;
            font-size: 13px;
        }

        .report-page .btn {
            display: inline-block;
            border: none;
            border-radius: 6px;
            padding: 9px 13px;
            cursor: pointer;
            font-size: 13px;
            text-decoration: none;
        }

        .report-page .primary {
            background: #2563eb;
            color: #fff;
        }

        .report-page .secondary {
            background: #e5e7eb;
            color: #222;
        }

        .report-page .summary-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 18px;
        }

        .report-page .summary-box {
            margin-bottom: 0;
        }

        .report-page .summary-box p {
            margin: 0 0 7px;
            color: #666;
            font-size: 13px;
        }

        .report-page .summary-box h3 {
            margin: 0;
            font-size: 22px;
        }

        .report-page .table-wrap { overflow-x: auto; }

        .report-page table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .report-page th,
        .report-page td {
            padding: 10px;
            border-bottom: 1px solid #eee;
            text-align: left;
            white-space: nowrap;
        }

        .report-page th { background: #f8fafc; }

        .report-page .empty {
            padding: 18px;
            text-align: center;
            color: #777;
        }

        .report-page .error {
            padding: 12px;
            margin-bottom: 16px;
            background: #fee2e2;
            color: #991b1b;
            border-radius: 8px;
        }

        @media (max-width: 800px) {
            .report-page .filter-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 480px) {
            .report-page .filter-grid,
            .report-page .summary-grid {
                grid-template-columns: 1fr;
            }
        }

        @media print {
            .sidebar,
            .top-nav,
            .filter-card,
            .no-print {
                display: none !important;
            }

            .report-page { padding: 0; }

            .report-page .report-card,
            .report-page .summary-box {
                box-shadow: none;
                border: 1px solid #ddd;
            }
        }
    </style>
</head>

<body>
<?php require_once 'sidebar.php'; ?>

<main class="main-content">
    <header class="top-nav">
        <h2>Laporan Klinik</h2>
    </header>

    <div class="content report-page">
        <h2>Laporan Klinik</h2>
        <p>Lihat laporan kunjungan dan pembayaran berdasarkan periode.</p>

        <?php if ($error !== ''): ?>
            <div class="error"><?= e($error) ?></div>
        <?php endif; ?>

        <section class="report-card filter-card">
            <form method="GET" action="laporan.php">
                <div class="filter-grid">
                    <div>
                        <label>Jenis Laporan</label>
                        <select name="jenis_laporan">
                            <option value="kunjungan"
                                <?= $jenis_laporan === 'kunjungan' ? 'selected' : '' ?>>
                                Kunjungan Pasien
                            </option>
                            <option value="pembayaran"
                                <?= $jenis_laporan === 'pembayaran' ? 'selected' : '' ?>>
                                Pembayaran
                            </option>
                        </select>
                    </div>

                    <div>
                        <label>Tanggal Awal</label>
                        <input type="date" name="tanggal_awal" required
                            value="<?= e($tanggal_awal) ?>">
                    </div>

                    <div>
                        <label>Tanggal Akhir</label>
                        <input type="date" name="tanggal_akhir" required
                            value="<?= e($tanggal_akhir) ?>">
                    </div>

                    <div class="no-print">
                        <button type="submit" class="btn primary">Tampilkan</button>
                        <button type="button" class="btn secondary"
                            onclick="window.print()">Cetak</button>
                    </div>
                </div>
            </form>
        </section>

        <div class="summary-grid">
            <div class="summary-box">
                <p>
                    <?= $jenis_laporan === 'kunjungan'
                        ? 'Total Kunjungan'
                        : 'Total Transaksi' ?>
                </p>
                <h3><?= number_format($total_data, 0, ',', '.') ?></h3>
            </div>

            <?php if ($jenis_laporan === 'pembayaran'): ?>
                <div class="summary-box">
                    <p>Pendapatan dari Pembayaran Lunas</p>
                    <h3>Rp <?= number_format($total_pendapatan, 0, ',', '.') ?></h3>
                </div>
            <?php endif; ?>
        </div>

        <section class="report-card">
            <h3>
                <?= $jenis_laporan === 'kunjungan'
                    ? 'Laporan Kunjungan Pasien'
                    : 'Laporan Pembayaran' ?>
            </h3>

            <p>
                Periode: <?= e($tanggal_awal) ?>
                sampai <?= e($tanggal_akhir) ?>
            </p>

            <div class="table-wrap">
                <table>
                    <thead>
                    <?php if ($jenis_laporan === 'kunjungan'): ?>
                        <tr>
                            <th>No.</th>
                            <th>Tanggal</th>
                            <th>No. RM</th>
                            <th>Nama Pasien</th>
                            <th>Dokter</th>
                            <th>Poli</th>
                            <th>Status</th>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <th>No.</th>
                            <th>No. Pembayaran</th>
                            <th>Tanggal</th>
                            <th>No. RM</th>
                            <th>Nama Pasien</th>
                            <th>Metode</th>
                            <th>Total Tagihan</th>
                            <th>Total Dibayar</th>
                            <th>Status</th>
                        </tr>
                    <?php endif; ?>
                    </thead>

                    <tbody>
                    <?php if (empty($laporan)): ?>
                        <tr>
                            <td colspan="<?= $jenis_laporan === 'kunjungan' ? 7 : 9 ?>"
                                class="empty">
                                <?= $error !== ''
                                    ? 'Laporan belum dapat ditampilkan.'
                                    : 'Tidak ada data pada periode ini.' ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($laporan as $i => $row): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>

                                <?php if ($jenis_laporan === 'kunjungan'): ?>
                                    <td><?= e($row['tanggal_kunjungan']) ?></td>
                                    <td><?= e($row['no_rm']) ?></td>
                                    <td><?= e($row['nama_pasien']) ?></td>
                                    <td><?= e($row['nama_dokter']) ?></td>
                                    <td><?= e($row['nama_poli']) ?></td>
                                    <td><?= e($row['status']) ?></td>
                                <?php else: ?>
                                    <td><?= e($row['no_pembayaran']) ?></td>
                                    <td><?= e($row['tanggal_pembayaran']) ?></td>
                                    <td><?= e($row['no_rm']) ?></td>
                                    <td><?= e($row['nama_pasien']) ?></td>
                                    <td><?= e(strtoupper($row['metode_pembayaran'])) ?></td>
                                    <td>Rp <?= number_format((float) $row['total_tagihan'], 0, ',', '.') ?></td>
                                    <td>Rp <?= number_format((float) $row['total_bayar'], 0, ',', '.') ?></td>
                                    <td><?= e(ucwords(str_replace('_', ' ', $row['status']))) ?></td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</main>
</body>
</html>