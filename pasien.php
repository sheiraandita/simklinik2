<?php
require_once 'config/config.php';
requireLogin();

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    die('Koneksi database gagal.');
}

$role = $_SESSION['user_role'];
$pesan = '';
$error = '';
$editData = null;

// Ambil data untuk diedit
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM pasien WHERE id = ?");
    $stmt->execute([(int) $_GET['edit']]);
    $editData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$editData) {
        $error = 'Data pasien tidak ditemukan.';
    }
}

// Proses tambah, edit, dan hapus
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';

    try {
        if ($aksi === 'simpan' || $aksi === 'ubah') {
            $id = (int) ($_POST['id'] ?? 0);
            $no_rm = trim($_POST['no_rm'] ?? '');
            $nik = trim($_POST['nik'] ?? '');
            $nama = trim($_POST['nama_pasien'] ?? '');
            $jk = $_POST['jenis_kelamin'] ?? '';
            $tanggal = $_POST['tanggal_lahir'] ?: null;
            $alamat = trim($_POST['alamat'] ?? '');
            $telepon = trim($_POST['telepon'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $darah = $_POST['golongan_darah'] ?? 'Tidak Diketahui';
            $status = $_POST['status'] ?? 'aktif';

            if ($no_rm === '' || $nama === '' || !in_array($jk, ['L', 'P'], true)) {
                throw new Exception('Nomor rekam medis, nama pasien, dan jenis kelamin wajib diisi.');
            }

            if (!in_array($darah, ['A', 'B', 'AB', 'O', 'Tidak Diketahui'], true)) {
                throw new Exception('Golongan darah tidak valid.');
            }

            if (!in_array($status, ['aktif', 'nonaktif'], true)) {
                throw new Exception('Status pasien tidak valid.');
            }

            // Cek nomor rekam medis agar tidak duplikat
            $cek = $db->prepare(
                "SELECT id FROM pasien WHERE no_rm = ? AND id != ?"
            );
            $cek->execute([$no_rm, $aksi === 'ubah' ? $id : 0]);

            if ($cek->fetch()) {
                throw new Exception('Nomor rekam medis sudah digunakan.');
            }

            if ($aksi === 'simpan') {
                $sql = "INSERT INTO pasien
                        (no_rm, nik, nama_pasien, jenis_kelamin,
                         tanggal_lahir, alamat, telepon, email,
                         golongan_darah, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

                $stmt = $db->prepare($sql);
                $stmt->execute([
                    $no_rm, $nik ?: null, $nama, $jk,
                    $tanggal, $alamat ?: null, $telepon ?: null,
                    $email ?: null, $darah, $status
                ]);

                $pesan = 'Data pasien berhasil ditambahkan.';
            } else {
                $sql = "UPDATE pasien SET
                        no_rm = ?, nik = ?, nama_pasien = ?,
                        jenis_kelamin = ?, tanggal_lahir = ?,
                        alamat = ?, telepon = ?, email = ?,
                        golongan_darah = ?, status = ?
                        WHERE id = ?";

                $stmt = $db->prepare($sql);
                $stmt->execute([
                    $no_rm, $nik ?: null, $nama, $jk,
                    $tanggal, $alamat ?: null, $telepon ?: null,
                    $email ?: null, $darah, $status, $id
                ]);

                $pesan = 'Data pasien berhasil diperbarui.';
            }

            // Bersihkan parameter edit setelah berhasil
            header('Location: pasien.php?pesan=' . urlencode($pesan));
            exit();
        }

        if ($aksi === 'hapus') {
            $id = (int) ($_POST['id'] ?? 0);

            $stmt = $db->prepare("DELETE FROM pasien WHERE id = ?");
            $stmt->execute([$id]);

            header('Location: pasien.php?pesan=' . urlencode('Data pasien berhasil dihapus.'));
            exit();
        }
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            $error = 'Data tidak dapat diproses. Pastikan nomor rekam medis tidak duplikat dan pasien tidak memiliki data kunjungan terkait.';
        } else {
            error_log($e->getMessage());
            $error = 'Terjadi kesalahan database.';
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

if (isset($_GET['pesan'])) {
    $pesan = $_GET['pesan'];
}

// Ambil seluruh data pasien
$stmt = $db->query("SELECT * FROM pasien ORDER BY id DESC");
$daftarPasien = $stmt->fetchAll(PDO::FETCH_ASSOC);

function h($value)
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function nilaiForm($kolom, $default = '')
{
    global $editData;
    return h($editData[$kolom] ?? $default);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Pasien - <?= h(APP_NAME) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dynamic.php">

    <style>
        .pasien-wrap { padding: 24px; }
        .pasien-card {
            background: #fff;
            padding: 22px;
            border-radius: 12px;
            margin-bottom: 24px;
            box-shadow: 0 2px 10px rgba(0,0,0,.06);
        }
        .pasien-card h2 { margin-top: 0; color: #263746; }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }
        .form-group { display: flex; flex-direction: column; gap: 7px; }
        .form-group label { font-weight: 600; }
        .form-group input, .form-group select, .form-group textarea {
            padding: 10px 12px;
            border: 1px solid #d5dce2;
            border-radius: 7px;
            font: inherit;
            width: 100%;
            box-sizing: border-box;
        }
        .form-group textarea { min-height: 75px; resize: vertical; }
        .full-width { grid-column: 1 / -1; }
        .btn {
            display: inline-block;
            border: none;
            border-radius: 6px;
            padding: 9px 14px;
            text-decoration: none;
            cursor: pointer;
            font: inherit;
            font-size: 14px;
        }
        .btn-primary { background: #2563a6; color: white; }
        .btn-edit { background: #f0ad35; color: #222; }
        .btn-delete { background: #dc3545; color: white; }
        .btn-cancel { background: #e5e7eb; color: #222; }
        .table-wrap { overflow-x: auto; }
        .pasien-table { width: 100%; border-collapse: collapse; }
        .pasien-table th, .pasien-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        .pasien-table th { background: #f3f6f9; }
        .pasien-table td { font-size: 14px; }
        .status-aktif { color: #16803d; font-weight: 600; }
        .status-nonaktif { color: #dc3545; font-weight: 600; }
        .alert-pesan {
            padding: 12px 15px;
            border-radius: 7px;
            margin-bottom: 18px;
            background: #dff5e5;
            color: #176533;
        }
        .alert-error {
            padding: 12px 15px;
            border-radius: 7px;
            margin-bottom: 18px;
            background: #fde2e2;
            color: #991b1b;
        }
        .aksi { display: flex; gap: 7px; align-items: center; }
        @media (max-width: 700px) {
            .form-grid { grid-template-columns: 1fr; }
            .pasien-wrap { padding: 12px; }
        }
    </style>
</head>

<body>
<div class="main-container">

    <?php require 'sidebar.php'; ?>

    <main class="main-content">
        <header class="top-nav">
            <h1>Data Pasien</h1>
            <div class="user-info">
                <div class="user-avatar">
                    <?= h(strtoupper(substr($_SESSION['nama_lengkap'] ?? 'U', 0, 1))) ?>
                </div>
                <div class="user-details">
                    <div class="user-name"><?= h($_SESSION['nama_lengkap'] ?? 'User') ?></div>
                    <div class="user-role"><?= h(ucfirst($role)) ?></div>
                </div>
            </div>
        </header>

        <div class="pasien-wrap">

            <?php if ($pesan !== ''): ?>
                <div class="alert-pesan"><?= h($pesan) ?></div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="alert-error"><?= h($error) ?></div>
            <?php endif; ?>

            <section class="pasien-card">
                <h2><?= $editData ? 'Edit Data Pasien' : 'Tambah Pasien Baru' ?></h2>

                <form method="POST" action="pasien.php<?= $editData ? '?edit=' . (int)$editData['id'] : '' ?>">
                    <input type="hidden" name="aksi" value="<?= $editData ? 'ubah' : 'simpan' ?>">

                    <?php if ($editData): ?>
                        <input type="hidden" name="id" value="<?= (int)$editData['id'] ?>">
                    <?php endif; ?>

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="no_rm">Nomor Rekam Medis *</label>
                            <input id="no_rm" name="no_rm" required
                                   value="<?= nilaiForm('no_rm') ?>"
                                   placeholder="Contoh: RM-0001">
                        </div>

                        <div class="form-group">
                            <label for="nik">NIK</label>
                            <input id="nik" name="nik" value="<?= nilaiForm('nik') ?>">
                        </div>

                        <div class="form-group">
                            <label for="nama_pasien">Nama Pasien *</label>
                            <input id="nama_pasien" name="nama_pasien" required
                                   value="<?= nilaiForm('nama_pasien') ?>">
                        </div>

                        <div class="form-group">
                            <label for="jenis_kelamin">Jenis Kelamin *</label>
                            <select id="jenis_kelamin" name="jenis_kelamin" required>
                                <option value="">-- Pilih --</option>
                                <option value="L" <?= nilaiForm('jenis_kelamin') === 'L' ? 'selected' : '' ?>>Laki-laki</option>
                                <option value="P" <?= nilaiForm('jenis_kelamin') === 'P' ? 'selected' : '' ?>>Perempuan</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="tanggal_lahir">Tanggal Lahir</label>
                            <input type="date" id="tanggal_lahir" name="tanggal_lahir"
                                   value="<?= nilaiForm('tanggal_lahir') ?>">
                        </div>

                        <div class="form-group">
                            <label for="telepon">Nomor Telepon</label>
                            <input id="telepon" name="telepon" value="<?= nilaiForm('telepon') ?>">
                        </div>

                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email"
                                   value="<?= nilaiForm('email') ?>">
                        </div>

                        <div class="form-group">
                            <label for="golongan_darah">Golongan Darah</label>
                            <select id="golongan_darah" name="golongan_darah">
                                <?php foreach (['Tidak Diketahui', 'A', 'B', 'AB', 'O'] as $darah): ?>
                                    <option value="<?= h($darah) ?>"
                                        <?= nilaiForm('golongan_darah', 'Tidak Diketahui') === $darah ? 'selected' : '' ?>>
                                        <?= h($darah) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group full-width">
                            <label for="alamat">Alamat</label>
                            <textarea id="alamat" name="alamat"><?= nilaiForm('alamat') ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="status">Status</label>
                            <select id="status" name="status">
                                <option value="aktif" <?= nilaiForm('status', 'aktif') === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                                <option value="nonaktif" <?= nilaiForm('status', 'aktif') === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                            </select>
                        </div>
                    </div>

                    <div style="margin-top: 20px; display:flex; gap:10px;">
                        <button type="submit" class="btn btn-primary">
                            <?= $editData ? 'Simpan Perubahan' : 'Tambah Pasien' ?>
                        </button>

                        <?php if ($editData): ?>
                            <a href="pasien.php" class="btn btn-cancel">Batal</a>
                        <?php endif; ?>
                    </div>
                </form>
            </section>

            <section class="pasien-card">
                <h2>Daftar Pasien</h2>

                <div class="table-wrap">
                    <table class="pasien-table">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>No. Rekam Medis</th>
                                <th>Nama Pasien</th>
                                <th>Jenis Kelamin</th>
                                <th>Telepon</th>
                                <th>Golongan Darah</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (count($daftarPasien) > 0): ?>
                            <?php foreach ($daftarPasien as $i => $pasien): ?>
                                <tr>
                                    <td><?= $i + 1 ?></td>
                                    <td><?= h($pasien['no_rm']) ?></td>
                                    <td><?= h($pasien['nama_pasien']) ?></td>
                                    <td><?= $pasien['jenis_kelamin'] === 'L' ? 'Laki-laki' : 'Perempuan' ?></td>
                                    <td><?= h($pasien['telepon'] ?: '-') ?></td>
                                    <td><?= h($pasien['golongan_darah']) ?></td>
                                    <td>
                                        <span class="status-<?= h($pasien['status']) ?>">
                                            <?= h(ucfirst($pasien['status'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="aksi">
                                            <a class="btn btn-edit"
                                               href="pasien.php?edit=<?= (int)$pasien['id'] ?>">Edit</a>

                                            <form method="POST" action="pasien.php"
                                                  onsubmit="return confirm('Yakin ingin menghapus data pasien ini?')">
                                                <input type="hidden" name="aksi" value="hapus">
                                                <input type="hidden" name="id" value="<?= (int)$pasien['id'] ?>">
                                                <button type="submit" class="btn btn-delete">Hapus</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align:center;">Belum ada data pasien.</td>
                            </tr>
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