<?php
require_once 'config/config.php';
requireLogin();

$db = (new Database())->getConnection();

$message = '';
$message_type = '';

/* Hapus pembayaran */
if (isset($_GET['hapus'])) {
    try {
        $stmt = $db->prepare("DELETE FROM pembayaran WHERE id = ?");
        $stmt->execute([(int) $_GET['hapus']]);

        header('Location: pembayaran.php?msg=hapus');
        exit;
    } catch (PDOException $e) {
        $message = 'Pembayaran tidak dapat dihapus.';
        $message_type = 'error';
    }
}

if (isset($_GET['msg']) && $_GET['msg'] === 'hapus') {
    $message = 'Data pembayaran berhasil dihapus.';
    $message_type = 'success';
}

/* Simpan pembayaran */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    $kunjungan_id = (int) ($_POST['kunjungan_id'] ?? 0);
    $tanggal_pembayaran = $_POST['tanggal_pembayaran'] ?? date('Y-m-d');
    $metode_pembayaran = trim($_POST['metode_pembayaran'] ?? '');
    $total_tagihan = (float) ($_POST['total_tagihan'] ?? 0);
    $jumlah_bayar = (float) ($_POST['jumlah_bayar'] ?? 0);
    $status = $_POST['status'] ?? 'belum_bayar';
    $keterangan = trim($_POST['keterangan'] ?? '');

    $status_valid = ['belum_bayar', 'sebagian', 'lunas'];
    $metode_valid = ['tunai', 'transfer', 'debit', 'qris'];

    if ($kunjungan_id <= 0 || $total_tagihan < 0 || $jumlah_bayar < 0) {
        $message = 'Kunjungan dan jumlah pembayaran harus valid.';
        $message_type = 'error';
    } elseif (!in_array($status, $status_valid, true)
        || !in_array($metode_pembayaran, $metode_valid, true)) {
        $message = 'Metode atau status pembayaran tidak valid.';
        $message_type = 'error';
    } else {
        try {
            if ($id > 0) {
                $sql = "UPDATE pembayaran SET
                        kunjungan_id = ?, tanggal_pembayaran = ?,
                        metode_pembayaran = ?, total_tagihan = ?,
                        jumlah_bayar = ?, status = ?, keterangan = ?
                        WHERE id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    $kunjungan_id, $tanggal_pembayaran,
                    $metode_pembayaran, $total_tagihan,
                    $jumlah_bayar, $status, $keterangan ?: null, $id
                ]);
            } else {
                $sql = "INSERT INTO pembayaran
                        (kunjungan_id, tanggal_pembayaran,
                         metode_pembayaran, total_tagihan,
                         jumlah_bayar, status, keterangan)
                        VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    $kunjungan_id, $tanggal_pembayaran,
                    $metode_pembayaran, $total_tagihan,
                    $jumlah_bayar, $status, $keterangan ?: null
                ]);
            }

            header('Location: pembayaran.php?msg=simpan');
            exit;
        } catch (PDOException $e) {
            $message = 'Gagal menyimpan pembayaran. Periksa struktur tabel database.';
            $message_type = 'error';
        }
    }
}

if (isset($_GET['msg']) && $_GET['msg'] === 'simpan') {
    $message = 'Data pembayaran berhasil disimpan.';
    $message_type = 'success';
}

/* Pilihan kunjungan */
$kunjungan = $db->query("
    SELECT k.id, k.tanggal_kunjungan, p.nama_pasien
    FROM kunjungan k
    LEFT JOIN pasien p ON p.id = k.pasien_id
    ORDER BY k.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

/* Data edit */
$edit = null;

if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM pembayaran WHERE id = ?");
    $stmt->execute([(int) $_GET['edit']]);
    $edit = $stmt->fetch(PDO::FETCH_ASSOC);
}

/* Daftar pembayaran */
$pembayaran = $db->query("
    SELECT b.*, p.nama_pasien
    FROM pembayaran b
    LEFT JOIN kunjungan k ON k.id = b.kunjungan_id
    LEFT JOIN pasien p ON p.id = k.pasien_id
    ORDER BY b.id DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran - SIM Klinik</title>
    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        .pay-page { padding: 20px; }
        .pay-page .card {
            background: #fff;
            padding: 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px #0000000d;
        }
        .pay-page .form-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
        }
        .pay-page .form-group { min-width: 0; }
        .pay-page label {
            display: block;
            font-size: 13px;
            margin-bottom: 5px;
        }
        .pay-page input,
        .pay-page select,
        .pay-page textarea {
            box-sizing: border-box;
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #d5d9df;
            border-radius: 6px;
            font-size: 13px;
        }
        .pay-page textarea { resize: vertical; min-height: 60px; }
        .pay-page .full { grid-column: 1 / -1; }
        .pay-page .actions { display: flex; gap: 8px; margin-top: 14px; }
        .pay-page .btn {
            display: inline-block;
            padding: 8px 12px;
            border: 0;
            border-radius: 6px;
            text-decoration: none;
            font-size: 13px;
            cursor: pointer;
        }
        .pay-page .primary { background: #2563eb; color: #fff; }
        .pay-page .secondary { background: #e5e7eb; color: #222; }
        .pay-page .edit { background: #fef3c7; color: #92400e; }
        .pay-page .delete { background: #fee2e2; color: #991b1b; }
        .pay-page .table-wrap { overflow-x: auto; }
        .pay-page table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .pay-page th, .pay-page td {
            padding: 10px;
            border-bottom: 1px solid #eee;
            text-align: left;
            white-space: nowrap;
        }
        .pay-page th { background: #f8fafc; }
        .pay-page .alert {
            padding: 10px 12px;
            border-radius: 6px;
            margin-bottom: 15px;
            font-size: 13px;
        }
        .pay-page .success { background: #dcfce7; color: #166534; }
        .pay-page .error { background: #fee2e2; color: #991b1b; }

        @media (max-width: 768px) {
            .pay-page .form-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 480px) {
            .pay-page .form-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<?php require_once 'sidebar.php'; ?>

<main class="main-content">
    <header class="top-nav">
        <h2>Manajemen Pembayaran</h2>
    </header>

    <div class="content pay-page">
        <h2>Data Pembayaran</h2>
        <p>Kelola pembayaran kunjungan pasien.</p>

        <?php if ($message): ?>
            <div class="alert <?= $message_type === 'success' ? 'success' : 'error' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <section class="card">
            <h3><?= $edit ? 'Edit Pembayaran' : 'Tambah Pembayaran' ?></h3>

            <form method="POST" action="pembayaran.php">
                <input type="hidden" name="id"
                       value="<?= (int) ($edit['id'] ?? 0) ?>">

                <div class="form-grid">
                    <div class="form-group">
                        <label>Kunjungan / Pasien *</label>
                        <select name="kunjungan_id" required>
                            <option value="">Pilih kunjungan</option>
                            <?php foreach ($kunjungan as $k): ?>
                                <option value="<?= (int) $k['id'] ?>"
                                    <?= (string) ($edit['kunjungan_id'] ?? '') === (string) $k['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars(
                                        'Kunjungan #' . $k['id'] . ' - ' .
                                        ($k['nama_pasien'] ?? 'Pasien') . ' (' .
                                        ($k['tanggal_kunjungan'] ?? '-') . ')'
                                    ) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Tanggal Pembayaran *</label>
                        <input type="date" name="tanggal_pembayaran" required
                               value="<?= htmlspecialchars($edit['tanggal_pembayaran'] ?? date('Y-m-d')) ?>">
                    </div>

                    <div class="form-group">
                        <label>Metode Pembayaran *</label>
                        <select name="metode_pembayaran" required>
                            <?php
                            $metode = [
                                'tunai' => 'Tunai',
                                'transfer' => 'Transfer',
                                'debit' => 'Debit',
                                'qris' => 'QRIS'
                            ];
                            ?>
                            <option value="">Pilih metode</option>
                            <?php foreach ($metode as $value => $label): ?>
                                <option value="<?= $value ?>"
                                    <?= ($edit['metode_pembayaran'] ?? '') === $value ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Total Tagihan (Rp) *</label>
                        <input type="number" name="total_tagihan" min="0" step="0.01"
                               required value="<?= htmlspecialchars((string) ($edit['total_tagihan'] ?? '0')) ?>">
                    </div>

                    <div class="form-group">
                        <label>Jumlah Dibayar (Rp) *</label>
                        <input type="number" name="jumlah_bayar" min="0" step="0.01"
                               required value="<?= htmlspecialchars((string) ($edit['jumlah_bayar'] ?? '0')) ?>">
                    </div>

                    <div class="form-group">
                        <label>Status *</label>
                        <select name="status" required>
                            <?php
                            $status_list = [
                                'belum_bayar' => 'Belum Bayar',
                                'sebagian' => 'Sebagian',
                                'lunas' => 'Lunas'
                            ];
                            ?>
                            <?php foreach ($status_list as $value => $label): ?>
                                <option value="<?= $value ?>"
                                    <?= ($edit['status'] ?? 'belum_bayar') === $value ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group full">
                        <label>Keterangan</label>
                        <textarea name="keterangan"><?= htmlspecialchars($edit['keterangan'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="actions">
                    <button type="submit" class="btn primary">
                        <?= $edit ? 'Simpan Perubahan' : 'Tambah Pembayaran' ?>
                    </button>

                    <?php if ($edit): ?>
                        <a href="pembayaran.php" class="btn secondary">Batal</a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <section class="card">
            <h3>Daftar Pembayaran</h3>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Pasien</th>
                            <th>Tanggal</th>
                            <th>Metode</th>
                            <th>Total Tagihan</th>
                            <th>Jumlah Dibayar</th>
                            <th>Sisa</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$pembayaran): ?>
                        <tr><td colspan="9">Belum ada data pembayaran.</td></tr>
                    <?php else: ?>
                        <?php foreach ($pembayaran as $i => $p): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><?= htmlspecialchars($p['nama_pasien'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($p['tanggal_pembayaran'] ?? '-') ?></td>
                                <td><?= htmlspecialchars(strtoupper($p['metode_pembayaran'] ?? '-')) ?></td>
                                <td>Rp <?= number_format((float) ($p['total_tagihan'] ?? 0), 0, ',', '.') ?></td>
                                <td>Rp <?= number_format((float) ($p['jumlah_bayar'] ?? 0), 0, ',', '.') ?></td>
                                <td>Rp <?= number_format(max(0, (float) ($p['total_tagihan'] ?? 0) - (float) ($p['jumlah_bayar'] ?? 0)), 0, ',', '.') ?></td>
                                <td><?= htmlspecialchars(ucwords(str_replace('_', ' ', $p['status'] ?? '-'))) ?></td>
                                <td>
                                    <a class="btn edit"
                                       href="pembayaran.php?edit=<?= (int) $p['id'] ?>">Edit</a>
                                    <a class="btn delete"
                                       href="pembayaran.php?hapus=<?= (int) $p['id'] ?>"
                                       onclick="return confirm('Yakin ingin menghapus pembayaran ini?')">Hapus</a>
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

</body>
</html>