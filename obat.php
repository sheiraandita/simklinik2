<?php
require_once 'config/config.php';
requireLogin();

$db = (new Database())->getConnection();

$message = '';
$message_type = '';

/* Hapus obat */
if (isset($_GET['hapus'])) {
    $id = (int) $_GET['hapus'];

    try {
        $stmt = $db->prepare("DELETE FROM obat WHERE id = ?");
        $stmt->execute([$id]);

        header('Location: obat.php?msg=hapus');
        exit;
    } catch (PDOException $e) {
        $message = 'Obat tidak dapat dihapus karena masih digunakan.';
        $message_type = 'error';
    }
}

/* Notifikasi */
if (isset($_GET['msg']) && $_GET['msg'] === 'hapus') {
    $message = 'Data obat berhasil dihapus.';
    $message_type = 'success';
}

/* Simpan obat */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    $kode_obat = trim($_POST['kode_obat'] ?? '');
    $nama_obat = trim($_POST['nama_obat'] ?? '');
    $kategori_id = (int) ($_POST['kategori_id'] ?? 0);
    $satuan_id = (int) ($_POST['satuan_id'] ?? 0);
    $harga_beli = (float) ($_POST['harga_beli'] ?? 0);
    $harga_jual = (float) ($_POST['harga_jual'] ?? 0);
    $stok = (int) ($_POST['stok'] ?? 0);
    $stok_minimum = (int) ($_POST['stok_minimum'] ?? 0);
    $tanggal_kadaluarsa = $_POST['tanggal_kadaluarsa'] ?: null;
    $keterangan = trim($_POST['keterangan'] ?? '');

    if ($kode_obat === '' || $nama_obat === '') {
        $message = 'Kode dan nama obat wajib diisi.';
        $message_type = 'error';
    } else {
        try {
            if ($id > 0) {
                $sql = "UPDATE obat SET
                        kode_obat = ?, nama_obat = ?, kategori_id = ?,
                        satuan_id = ?, harga_beli = ?, harga_jual = ?,
                        stok = ?, stok_minimum = ?, tanggal_kadaluarsa = ?,
                        keterangan = ?
                        WHERE id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    $kode_obat, $nama_obat,
                    $kategori_id ?: null, $satuan_id ?: null,
                    $harga_beli, $harga_jual, $stok, $stok_minimum,
                    $tanggal_kadaluarsa, $keterangan ?: null, $id
                ]);

                $message = 'Data obat berhasil diperbarui.';
            } else {
                $sql = "INSERT INTO obat
                        (kode_obat, nama_obat, kategori_id, satuan_id,
                         harga_beli, harga_jual, stok, stok_minimum,
                         tanggal_kadaluarsa, keterangan)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    $kode_obat, $nama_obat,
                    $kategori_id ?: null, $satuan_id ?: null,
                    $harga_beli, $harga_jual, $stok, $stok_minimum,
                    $tanggal_kadaluarsa, $keterangan ?: null
                ]);

                $message = 'Data obat berhasil ditambahkan.';
            }

            $message_type = 'success';
        } catch (PDOException $e) {
            $message = 'Gagal menyimpan data. Periksa kode obat dan struktur tabel.';
            $message_type = 'error';
        }
    }
}

/* Data untuk dropdown */
$kategori = $db->query(
    "SELECT id, nama_kategori FROM kategori_obat ORDER BY nama_kategori"
)->fetchAll(PDO::FETCH_ASSOC);

$satuan = $db->query(
    "SELECT id, nama_satuan FROM satuan_obat ORDER BY nama_satuan"
)->fetchAll(PDO::FETCH_ASSOC);

/* Data edit */
$edit = null;

if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM obat WHERE id = ?");
    $stmt->execute([(int) $_GET['edit']]);
    $edit = $stmt->fetch(PDO::FETCH_ASSOC);
}

/* Daftar obat */
$sql = "SELECT o.*, k.nama_kategori, s.nama_satuan
        FROM obat o
        LEFT JOIN kategori_obat k ON o.kategori_id = k.id
        LEFT JOIN satuan_obat s ON o.satuan_id = s.id
        ORDER BY o.id DESC";

$obat = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Obat - SIM Klinik</title>
    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        .obat-page {
            padding: 20px;
        }

        .obat-page .page-heading {
            margin-bottom: 18px;
        }

        .obat-page .form-card,
        .obat-page .table-card {
            background: #fff;
            padding: 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,.06);
        }

        .obat-page .form-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
        }

        .obat-page .form-group {
            min-width: 0;
        }

        .obat-page label {
            display: block;
            font-size: 13px;
            margin-bottom: 5px;
        }

        .obat-page input,
        .obat-page select,
        .obat-page textarea {
            box-sizing: border-box;
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #d5d9df;
            border-radius: 6px;
            font-size: 13px;
        }

        .obat-page textarea {
            resize: vertical;
            min-height: 65px;
        }

        .obat-page .full-width {
            grid-column: 1 / -1;
        }

        .obat-page .form-actions {
            display: flex;
            gap: 8px;
            margin-top: 14px;
        }

        .obat-page .btn {
            display: inline-block;
            border: none;
            border-radius: 6px;
            padding: 9px 14px;
            text-decoration: none;
            cursor: pointer;
            font-size: 13px;
        }

        .obat-page .btn-primary {
            background: #2563eb;
            color: white;
        }

        .obat-page .btn-secondary {
            background: #e5e7eb;
            color: #222;
        }

        .obat-page .btn-edit {
            background: #fef3c7;
            color: #92400e;
        }

        .obat-page .btn-delete {
            background: #fee2e2;
            color: #991b1b;
        }

        .obat-page .table-wrap {
            overflow-x: auto;
        }

        .obat-page table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .obat-page th,
        .obat-page td {
            padding: 10px;
            border-bottom: 1px solid #eee;
            text-align: left;
            white-space: nowrap;
        }

        .obat-page th {
            background: #f8fafc;
        }

        .obat-page .alert {
            padding: 10px 12px;
            border-radius: 6px;
            margin-bottom: 15px;
            font-size: 13px;
        }

        .obat-page .success {
            background: #dcfce7;
            color: #166534;
        }

        .obat-page .error {
            background: #fee2e2;
            color: #991b1b;
        }

        @media (max-width: 768px) {
            .obat-page .form-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 480px) {
            .obat-page .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<?php require_once 'sidebar.php'; ?>

<main class="main-content">
    <header class="top-nav">
        <h2>Manajemen Obat</h2>
    </header>

    <div class="content obat-page">
        <div class="page-heading">
            <h2>Data Obat</h2>
            <p>Kelola data obat dan persediaan klinik.</p>
        </div>

        <?php if ($message): ?>
            <div class="alert <?= $message_type === 'success' ? 'success' : 'error' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <section class="form-card">
            <h3><?= $edit ? 'Edit Obat' : 'Tambah Obat' ?></h3>

            <form method="POST" action="obat.php">
                <input type="hidden" name="id"
                       value="<?= (int) ($edit['id'] ?? 0) ?>">

                <div class="form-grid">
                    <div class="form-group">
                        <label>Kode Obat *</label>
                        <input type="text" name="kode_obat" required
                               value="<?= htmlspecialchars($edit['kode_obat'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label>Nama Obat *</label>
                        <input type="text" name="nama_obat" required
                               value="<?= htmlspecialchars($edit['nama_obat'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label>Kategori</label>
                        <select name="kategori_id">
                            <option value="">Pilih kategori</option>
                            <?php foreach ($kategori as $k): ?>
                                <option value="<?= (int) $k['id'] ?>"
                                    <?= (string) ($edit['kategori_id'] ?? '') === (string) $k['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($k['nama_kategori']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Satuan</label>
                        <select name="satuan_id">
                            <option value="">Pilih satuan</option>
                            <?php foreach ($satuan as $s): ?>
                                <option value="<?= (int) $s['id'] ?>"
                                    <?= (string) ($edit['satuan_id'] ?? '') === (string) $s['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($s['nama_satuan']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Harga Beli</label>
                        <input type="number" name="harga_beli" min="0" step="0.01"
                               value="<?= htmlspecialchars((string) ($edit['harga_beli'] ?? '0')) ?>">
                    </div>

                    <div class="form-group">
                        <label>Harga Jual</label>
                        <input type="number" name="harga_jual" min="0" step="0.01"
                               value="<?= htmlspecialchars((string) ($edit['harga_jual'] ?? '0')) ?>">
                    </div>

                    <div class="form-group">
                        <label>Stok</label>
                        <input type="number" name="stok" min="0"
                               value="<?= htmlspecialchars((string) ($edit['stok'] ?? '0')) ?>">
                    </div>

                    <div class="form-group">
                        <label>Stok Minimum</label>
                        <input type="number" name="stok_minimum" min="0"
                               value="<?= htmlspecialchars((string) ($edit['stok_minimum'] ?? '0')) ?>">
                    </div>

                    <div class="form-group">
                        <label>Tanggal Kedaluwarsa</label>
                        <input type="date" name="tanggal_kadaluarsa"
                               value="<?= htmlspecialchars($edit['tanggal_kadaluarsa'] ?? '') ?>">
                    </div>

                    <div class="form-group full-width">
                        <label>Keterangan</label>
                        <textarea name="keterangan"><?= htmlspecialchars($edit['keterangan'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <?= $edit ? 'Simpan Perubahan' : 'Tambah Obat' ?>
                    </button>

                    <?php if ($edit): ?>
                        <a href="obat.php" class="btn btn-secondary">Batal</a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <section class="table-card">
            <h3>Daftar Obat</h3>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Kode</th>
                            <th>Nama Obat</th>
                            <th>Kategori</th>
                            <th>Satuan</th>
                            <th>Harga Beli</th>
                            <th>Harga Jual</th>
                            <th>Stok</th>
                            <th>Stok Minimum</th>
                            <th>Kedaluwarsa</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$obat): ?>
                        <tr>
                            <td colspan="11">Belum ada data obat.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($obat as $i => $o): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><?= htmlspecialchars($o['kode_obat'] ?? '') ?></td>
                                <td><?= htmlspecialchars($o['nama_obat'] ?? '') ?></td>
                                <td><?= htmlspecialchars($o['nama_kategori'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($o['nama_satuan'] ?? '-') ?></td>
                                <td>Rp <?= number_format((float) ($o['harga_beli'] ?? 0), 0, ',', '.') ?></td>
                                <td>Rp <?= number_format((float) ($o['harga_jual'] ?? 0), 0, ',', '.') ?></td>
                                <td><?= (int) ($o['stok'] ?? 0) ?></td>
                                <td><?= (int) ($o['stok_minimum'] ?? 0) ?></td>
                                <td><?= htmlspecialchars($o['tanggal_kadaluarsa'] ?? '-') ?></td>
                                <td>
                                    <a class="btn btn-edit"
                                       href="obat.php?edit=<?= (int) $o['id'] ?>">Edit</a>

                                    <a class="btn btn-delete"
                                       href="obat.php?hapus=<?= (int) $o['id'] ?>"
                                       onclick="return confirm('Yakin ingin menghapus obat ini?')">
                                        Hapus
                                    </a>
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