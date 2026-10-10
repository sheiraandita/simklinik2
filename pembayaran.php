<?php
require_once 'config/config.php';
requireLogin();

$db = (new Database())->getConnection();

$message = '';
$message_type = '';

function e($value) {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/* Notifikasi */
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'hapus') {
        $message = 'Data pembayaran berhasil dihapus.';
        $message_type = 'success';
    } elseif ($_GET['msg'] === 'simpan') {
        $message = 'Data pembayaran berhasil disimpan.';
        $message_type = 'success';
    }
}

/* Hapus pembayaran */
if (isset($_GET['hapus'])) {
    try {
        $stmt = $db->prepare("DELETE FROM pembayaran WHERE id = ?");
        $stmt->execute([(int) $_GET['hapus']]);

        header('Location: pembayaran.php?msg=hapus');
        exit;
    } catch (PDOException $e) {
        $message = 'Pembayaran tidak dapat dihapus karena masih digunakan.';
        $message_type = 'error';
    }
}

/* Simpan pembayaran */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    $kunjungan_id = (int) ($_POST['kunjungan_id'] ?? 0);

    $biaya_pendaftaran = max(0, (float) ($_POST['biaya_pendaftaran'] ?? 0));
    $biaya_konsultasi = max(0, (float) ($_POST['biaya_konsultasi'] ?? 0));
    $biaya_tindakan = max(0, (float) ($_POST['biaya_tindakan'] ?? 0));
    $biaya_obat = max(0, (float) ($_POST['biaya_obat'] ?? 0));
    $diskon = max(0, (float) ($_POST['diskon'] ?? 0));
    $pajak = max(0, (float) ($_POST['pajak'] ?? 0));

    $total_tagihan = max(
        0,
        $biaya_pendaftaran + $biaya_konsultasi +
        $biaya_tindakan + $biaya_obat - $diskon + $pajak
    );

    $total_bayar = max(0, (float) ($_POST['total_bayar'] ?? 0));
    $kembalian = max(0, $total_bayar - $total_tagihan);

    $metode_pembayaran = $_POST['metode_pembayaran'] ?? 'tunai';
    $status = $_POST['status'] ?? 'belum_bayar';
    $catatan = trim($_POST['catatan'] ?? '');

    $metode_valid = ['tunai', 'debit', 'kredit', 'transfer', 'qris'];
    $status_valid = ['belum_bayar', 'sebagian', 'lunas', 'batal'];

    if ($kunjungan_id <= 0) {
        $message = 'Kunjungan pasien wajib dipilih.';
        $message_type = 'error';
    } elseif (!in_array($metode_pembayaran, $metode_valid, true)
        || !in_array($status, $status_valid, true)) {
        $message = 'Metode atau status pembayaran tidak valid.';
        $message_type = 'error';
    } elseif ($diskon > (
        $biaya_pendaftaran + $biaya_konsultasi +
        $biaya_tindakan + $biaya_obat
    )) {
        $message = 'Diskon tidak boleh melebihi total biaya sebelum pajak.';
        $message_type = 'error';
    } elseif ($status === 'lunas' && $total_bayar < $total_tagihan) {
        $message = 'Status lunas membutuhkan jumlah bayar minimal sebesar total tagihan.';
        $message_type = 'error';
    } elseif ($status === 'sebagian' &&
        ($total_bayar <= 0 || $total_bayar >= $total_tagihan)) {
        $message = 'Status sebagian harus memiliki pembayaran di atas Rp0 dan masih kurang dari total tagihan.';
        $message_type = 'error';
    } elseif ($status === 'belum_bayar' && $total_bayar > 0) {
        $message = 'Untuk status belum bayar, jumlah bayar harus Rp0.';
        $message_type = 'error';
    } else {
        try {
            /* Ambil pasien berdasarkan kunjungan */
            $stmt = $db->prepare(
                "SELECT pasien_id FROM kunjungan WHERE id = ?"
            );
            $stmt->execute([$kunjungan_id]);
            $data_kunjungan = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$data_kunjungan) {
                throw new RuntimeException('Kunjungan tidak ditemukan.');
            }

            $pasien_id = (int) $data_kunjungan['pasien_id'];

            /* Nomor pembayaran dibuat otomatis */
            if ($id > 0) {
                $stmt = $db->prepare(
                    "SELECT no_pembayaran FROM pembayaran WHERE id = ?"
                );
                $stmt->execute([$id]);
                $data_lama = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$data_lama) {
                    throw new RuntimeException('Data pembayaran tidak ditemukan.');
                }

                $no_pembayaran = $data_lama['no_pembayaran'];
            } else {
                $no_pembayaran = 'BYR-' . date('YmdHis') . '-' .
                    strtoupper(bin2hex(random_bytes(2)));
            }

            /* Ambil user yang sedang login jika tersedia */
            $user_id = $_SESSION['user_id'] ?? null;

            if ($id > 0) {
                $sql = "UPDATE pembayaran SET
                    no_pembayaran = ?,
                    kunjungan_id = ?,
                    pasien_id = ?,
                    user_id = ?,
                    tanggal_pembayaran = NOW(),
                    biaya_pendaftaran = ?,
                    biaya_konsultasi = ?,
                    biaya_tindakan = ?,
                    biaya_obat = ?,
                    diskon = ?,
                    pajak = ?,
                    total_tagihan = ?,
                    total_bayar = ?,
                    kembalian = ?,
                    metode_pembayaran = ?,
                    status = ?,
                    catatan = ?
                    WHERE id = ?";

                $stmt = $db->prepare($sql);
                $stmt->execute([
                    $no_pembayaran,
                    $kunjungan_id,
                    $pasien_id,
                    $user_id,
                    $biaya_pendaftaran,
                    $biaya_konsultasi,
                    $biaya_tindakan,
                    $biaya_obat,
                    $diskon,
                    $pajak,
                    $total_tagihan,
                    $total_bayar,
                    $kembalian,
                    $metode_pembayaran,
                    $status,
                    $catatan !== '' ? $catatan : null,
                    $id
                ]);
            } else {
                $sql = "INSERT INTO pembayaran (
                    no_pembayaran,
                    kunjungan_id,
                    pasien_id,
                    user_id,
                    tanggal_pembayaran,
                    biaya_pendaftaran,
                    biaya_konsultasi,
                    biaya_tindakan,
                    biaya_obat,
                    diskon,
                    pajak,
                    total_tagihan,
                    total_bayar,
                    kembalian,
                    metode_pembayaran,
                    status,
                    catatan
                ) VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

                $stmt = $db->prepare($sql);
                $stmt->execute([
                    $no_pembayaran,
                    $kunjungan_id,
                    $pasien_id,
                    $user_id,
                    $biaya_pendaftaran,
                    $biaya_konsultasi,
                    $biaya_tindakan,
                    $biaya_obat,
                    $diskon,
                    $pajak,
                    $total_tagihan,
                    $total_bayar,
                    $kembalian,
                    $metode_pembayaran,
                    $status,
                    $catatan !== '' ? $catatan : null
                ]);
            }

            header('Location: pembayaran.php?msg=simpan');
            exit;
        } catch (RuntimeException $e) {
            $message = $e->getMessage();
            $message_type = 'error';
        } catch (PDOException $e) {
            $message = 'Gagal menyimpan pembayaran. Periksa relasi tabel dan data yang dimasukkan.';
            $message_type = 'error';
        }
    }
}

/* Pilihan kunjungan dan pasien */
$kunjungan = $db->query("
    SELECT
        k.id,
        k.no_kunjungan,
        k.tanggal_kunjungan,
        k.pasien_id,
        p.nama_pasien
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

    if (!$edit) {
        $message = 'Data pembayaran tidak ditemukan.';
        $message_type = 'error';
    }
}

/* Daftar pembayaran */
$pembayaran = $db->query("
    SELECT
        b.*,
        p.nama_pasien,
        k.no_kunjungan
    FROM pembayaran b
    LEFT JOIN pasien p ON p.id = b.pasien_id
    LEFT JOIN kunjungan k ON k.id = b.kunjungan_id
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
        .pay-page .total-box {
            background: #eff6ff;
            padding: 12px;
            border-radius: 8px;
            margin-top: 12px;
        }
        @media (max-width: 768px) {
            .pay-page .form-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 480px) {
            .pay-page .form-grid { grid-template-columns: 1fr; }
            .pay-page .full { grid-column: auto; }
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
                <?= e($message) ?>
            </div>
        <?php endif; ?>

        <section class="card">
            <h3><?= $edit ? 'Edit Pembayaran' : 'Tambah Pembayaran' ?></h3>

            <form method="POST" action="pembayaran.php">
                <input type="hidden" name="id"
                       value="<?= (int) ($edit['id'] ?? 0) ?>">

                <div class="form-grid">
                    <div class="form-group full">
                        <label>Kunjungan / Pasien *</label>
                        <select name="kunjungan_id" required>
                            <option value="">Pilih kunjungan</option>
                            <?php foreach ($kunjungan as $k): ?>
                                <option value="<?= (int) $k['id'] ?>"
                                    <?= (string) ($edit['kunjungan_id'] ?? '') === (string) $k['id'] ? 'selected' : '' ?>>
                                    <?= e(
                                        ($k['no_kunjungan'] ?? 'Kunjungan #' . $k['id']) .
                                        ' - ' . ($k['nama_pasien'] ?? 'Pasien') .
                                        ' (' . ($k['tanggal_kunjungan'] ?? '-') . ')'
                                    ) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php
                    $biaya_fields = [
                        'biaya_pendaftaran' => 'Biaya Pendaftaran',
                        'biaya_konsultasi' => 'Biaya Konsultasi',
                        'biaya_tindakan' => 'Biaya Tindakan',
                        'biaya_obat' => 'Biaya Obat',
                        'diskon' => 'Diskon',
                        'pajak' => 'Pajak'
                    ];
                    foreach ($biaya_fields as $field => $label):
                    ?>
                        <div class="form-group">
                            <label><?= e($label) ?> (Rp)</label>
                            <input type="number"
                                   name="<?= e($field) ?>"
                                   class="biaya"
                                   min="0"
                                   step="0.01"
                                   value="<?= e($edit[$field] ?? '0') ?>">
                        </div>
                    <?php endforeach; ?>

                    <div class="form-group">
                        <label>Total Tagihan (Rp)</label>
                        <input type="number" id="total_tagihan" name="total_tagihan_tampilan"
                               min="0" step="0.01"
                               value="<?= e($edit['total_tagihan'] ?? '0') ?>"
                               readonly>
                    </div>

                    <div class="form-group">
                        <label>Jumlah Dibayar (Rp) *</label>
                        <input type="number" name="total_bayar" id="total_bayar"
                               min="0" step="0.01" required
                               value="<?= e($edit['total_bayar'] ?? '0') ?>">
                    </div>

                    <div class="form-group">
                        <label>Kembalian (Rp)</label>
                        <input type="number" id="kembalian" readonly
                               value="<?= e($edit['kembalian'] ?? '0') ?>">
                    </div>

                    <div class="form-group">
                        <label>Metode Pembayaran *</label>
                        <select name="metode_pembayaran" required>
                            <?php
                            $metode = [
                                'tunai' => 'Tunai',
                                'debit' => 'Debit',
                                'kredit' => 'Kredit',
                                'transfer' => 'Transfer',
                                'qris' => 'QRIS'
                            ];
                            ?>
                            <?php foreach ($metode as $value => $label): ?>
                                <option value="<?= e($value) ?>"
                                    <?= ($edit['metode_pembayaran'] ?? 'tunai') === $value ? 'selected' : '' ?>>
                                    <?= e($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Status *</label>
                        <select name="status" required>
                            <?php
                            $status_list = [
                                'belum_bayar' => 'Belum Bayar',
                                'sebagian' => 'Sebagian',
                                'lunas' => 'Lunas',
                                'batal' => 'Batal'
                            ];
                            ?>
                            <?php foreach ($status_list as $value => $label): ?>
                                <option value="<?= e($value) ?>"
                                    <?= ($edit['status'] ?? 'belum_bayar') === $value ? 'selected' : '' ?>>
                                    <?= e($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group full">
                        <label>Catatan</label>
                        <textarea name="catatan"><?= e($edit['catatan'] ?? '') ?></textarea>
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
                            <th>No. Pembayaran</th>
                            <th>No. Kunjungan</th>
                            <th>Pasien</th>
                            <th>Tanggal</th>
                            <th>Total Tagihan</th>
                            <th>Total Bayar</th>
                            <th>Kembalian</th>
                            <th>Metode</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$pembayaran): ?>
                        <tr><td colspan="11">Belum ada data pembayaran.</td></tr>
                    <?php else: ?>
                        <?php foreach ($pembayaran as $i => $p): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><?= e($p['no_pembayaran']) ?></td>
                                <td><?= e($p['no_kunjungan'] ?? '-') ?></td>
                                <td><?= e($p['nama_pasien'] ?? '-') ?></td>
                                <td><?= e($p['tanggal_pembayaran'] ?? '-') ?></td>
                                <td>Rp <?= number_format((float) ($p['total_tagihan'] ?? 0), 0, ',', '.') ?></td>
                                <td>Rp <?= number_format((float) ($p['total_bayar'] ?? 0), 0, ',', '.') ?></td>
                                <td>Rp <?= number_format((float) ($p['kembalian'] ?? 0), 0, ',', '.') ?></td>
                                <td><?= e(strtoupper($p['metode_pembayaran'] ?? '-')) ?></td>
                                <td><?= e(ucwords(str_replace('_', ' ', $p['status'] ?? '-'))) ?></td>
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

<script>
function hitungTotal() {
    const biaya = [
        'biaya_pendaftaran',
        'biaya_konsultasi',
        'biaya_tindakan',
        'biaya_obat'
    ];

    let subtotal = 0;

    biaya.forEach(function(nama) {
        const input = document.querySelector('[name="' + nama + '"]');
        subtotal += Number(input.value) || 0;
    });

    const diskon = Number(document.querySelector('[name="diskon"]').value) || 0;
    const pajak = Number(document.querySelector('[name="pajak"]').value) || 0;
    const total = Math.max(0, subtotal - diskon + pajak);

    document.getElementById('total_tagihan').value = total.toFixed(2);

    const bayar = Number(document.getElementById('total_bayar').value) || 0;
    document.getElementById('kembalian').value =
        Math.max(0, bayar - total).toFixed(2);
}

document.querySelectorAll('.biaya').forEach(function(input) {
    input.addEventListener('input', hitungTotal);
});

document.getElementById('total_bayar').addEventListener('input', hitungTotal);

hitungTotal();
</script>

</body>
</html>