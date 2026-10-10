
<?php
require_once __DIR__ . '/config/config.php';
requireLogin();

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

// PROSES FORM
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);

        $kode = trim($_POST['kode_dokter'] ?? '');
        $nama = trim($_POST['nama_dokter'] ?? '');
        $str = trim($_POST['no_str'] ?? '');
        $sip = trim($_POST['no_sip'] ?? '');
        $spesialisasi = trim($_POST['spesialisasi'] ?? '');
        $jk = $_POST['jenis_kelamin'] ?? '';
        $tempat = trim($_POST['tempat_lahir'] ?? '');
        $tanggal = $_POST['tanggal_lahir'] ?? '';
        $alamat = trim($_POST['alamat'] ?? '');
        $telepon = trim($_POST['telepon'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $tarif = $_POST['tarif_konsultasi'] ?? '0';
        $status = $_POST['status'] ?? 'aktif';

        if ($kode === '' || $nama === '') {
            $error = 'Kode dokter dan nama dokter wajib diisi.';
        } elseif (!in_array($jk, ['', 'L', 'P'], true)) {
            $error = 'Jenis kelamin tidak valid.';
        } elseif (!in_array($status, ['aktif', 'nonaktif'], true)) {
            $error = 'Status tidak valid.';
        } elseif (!is_numeric($tarif) || (float)$tarif < 0) {
            $error = 'Tarif konsultasi tidak valid.';
        } else {
            try {
                $data = [
                    $kode,
                    $nama,
                    $str ?: null,
                    $sip ?: null,
                    $spesialisasi ?: null,
                    $jk ?: null,
                    $tempat ?: null,
                    $tanggal ?: null,
                    $alamat ?: null,
                    $telepon ?: null,
                    $email ?: null,
                    (float)$tarif,
                    $status
                ];

                if ($id > 0) {
                    $sql = "UPDATE dokter SET
                        kode_dokter=?, nama_dokter=?, no_str=?, no_sip=?,
                        spesialisasi=?, jenis_kelamin=?, tempat_lahir=?,
                        tanggal_lahir=?, alamat=?, telepon=?, email=?,
                        tarif_konsultasi=?, status=?
                        WHERE id=?";

                    $data[] = $id;
                    $stmt = $db->prepare($sql);
                    $stmt->execute($data);
                    $success = 'Data dokter berhasil diperbarui.';
                } else {
                    $sql = "INSERT INTO dokter (
                        kode_dokter, nama_dokter, no_str, no_sip,
                        spesialisasi, jenis_kelamin, tempat_lahir,
                        tanggal_lahir, alamat, telepon, email,
                        tarif_konsultasi, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

                    $stmt = $db->prepare($sql);
                    $stmt->execute($data);
                    $success = 'Data dokter berhasil ditambahkan.';
                }
            } catch (PDOException $ex) {
                $error = $ex->getCode() === '23000'
                    ? 'Kode dokter sudah digunakan atau data masih terhubung dengan data lain.'
                    : 'Gagal menyimpan data dokter.';
            }
        }
    }

    if ($action === 'delete') {
        try {
            $stmt = $db->prepare("DELETE FROM dokter WHERE id = ?");
            $stmt->execute([(int)($_POST['id'] ?? 0)]);
            $success = 'Data dokter berhasil dihapus.';
        } catch (PDOException $ex) {
            $error = 'Data dokter tidak dapat dihapus karena masih digunakan.';
        }
    }
}

// DATA EDIT
$editData = null;

if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM dokter WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$editData) {
        $error = 'Data dokter tidak ditemukan.';
    }
}

// PENCARIAN
$search = trim($_GET['search'] ?? '');

if ($search !== '') {
    $stmt = $db->prepare("
        SELECT * FROM dokter
        WHERE kode_dokter LIKE ?
        OR nama_dokter LIKE ?
        OR spesialisasi LIKE ?
        ORDER BY id DESC
    ");
    $keyword = "%$search%";
    $stmt->execute([$keyword, $keyword, $keyword]);
} else {
    $stmt = $db->query("SELECT * FROM dokter ORDER BY id DESC");
}

$dokterList = $stmt->fetchAll(PDO::FETCH_ASSOC);

// LOKASI SIDEBAR
$sidebarCandidates = [
    __DIR__ . '/sidebar.php',
    __DIR__ . '/includes/sidebar.php',
    __DIR__ . '/layouts/sidebar.php',
    __DIR__ . '/components/sidebar.php'
];

$sidebarFile = null;

foreach ($sidebarCandidates as $path) {
    if (is_file($path)) {
        $sidebarFile = $path;
        break;
    }
}

function fieldValue($data, $key) {
    return e($data[$key] ?? '');
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Data Dokter - SIM Klinik</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f3f6fb;
    color: #25364a;
}

/* Layout utama */
.main-container {
    display: flex;
    min-height: 100vh;
}

.sidebar {
    flex: 0 0 250px;
    width: 250px;
    min-height: 100vh;
    background: #173b57;
    color: white;
    padding: 24px 16px;
}

.sidebar h2 {
    margin: 0 0 8px;
    font-size: 22px;
}

.sidebar p {
    color: #c8d8e6;
    font-size: 13px;
    margin-bottom: 28px;
}

.sidebar ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.sidebar li {
    margin: 6px 0;
}

.sidebar a {
    display: block;
    padding: 11px 12px;
    border-radius: 7px;
    text-decoration: none;
    color: #e7eff7;
    font-size: 14px;
}

.sidebar a:hover,
.sidebar a.active {
    background: #285a7d;
    color: #fff;
}

.main-content {
    flex: 1;
    min-width: 0;
    padding: 28px;
}

.page-heading {
    margin-bottom: 24px;
}

.page-heading h1 {
    margin: 0 0 8px;
    color: #173b57;
    font-size: 28px;
}

.page-heading p {
    margin: 0;
    color: #718096;
    font-size: 14px;
}

.panel {
    background: white;
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 24px;
    box-shadow: 0 4px 16px rgba(25, 55, 80, .06);
}

.panel h2 {
    font-size: 19px;
    margin: 0 0 22px;
    color: #173b57;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 17px;
}

.field {
    display: flex;
    flex-direction: column;
    gap: 7px;
    min-width: 0;
}

.field.full {
    grid-column: 1 / -1;
}

.field label {
    font-size: 13px;
    font-weight: 600;
    color: #46576a;
}

.field input,
.field select,
.field textarea {
    width: 100%;
    padding: 11px 12px;
    border: 1px solid #d7e0e9;
    border-radius: 7px;
    font: inherit;
    font-size: 14px;
    background: #fff;
}

.field textarea {
    min-height: 80px;
    resize: vertical;
}

.actions,
.table-actions,
.search-form {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.actions {
    margin-top: 20px;
}

.btn {
    display: inline-block;
    padding: 10px 15px;
    border: 0;
    border-radius: 7px;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    text-align: center;
}

.btn-primary { background: #1877b9; color: white; }
.btn-secondary { background: #e9eef4; color: #334155; }
.btn-edit { background: #e0f2fe; color: #075985; }
.btn-delete { background: #fee4e2; color: #b42318; }

.alert {
    padding: 13px 16px;
    border-radius: 8px;
    margin-bottom: 18px;
    font-size: 14px;
}

.alert-success { background: #dcfce7; color: #166534; }
.alert-error { background: #fee2e2; color: #991b1b; }

.table-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
    margin-bottom: 18px;
}

.search-form input {
    min-width: 240px;
    padding: 10px 12px;
    border: 1px solid #d7e0e9;
    border-radius: 7px;
    font-size: 14px;
}

.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    white-space: nowrap;
}

th,
td {
    padding: 13px 12px;
    text-align: left;
    border-bottom: 1px solid #edf0f4;
    font-size: 13px;
}

th {
    background: #f7f9fc;
    color: #526579;
}

.status {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
}

.status-aktif { background: #dcfce7; color: #166534; }
.status-nonaktif { background: #fee2e2; color: #991b1b; }

.empty {
    padding: 28px;
    text-align: center;
    color: #718096;
}

@media(max-width: 900px) {
    .sidebar {
        flex-basis: 210px;
        width: 210px;
    }

    .main-content {
        padding: 18px;
    }

    .form-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media(max-width: 600px) {
    .main-container {
        display: block;
    }

    .sidebar {
        width: 100%;
        min-height: auto;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }

    .field.full {
        grid-column: auto;
    }

    .search-form,
    .search-form input {
        width: 100%;
        min-width: 0;
    }
}
</style>
</head>

<body>

<div class="main-container">

    <?php if ($sidebarFile !== null): ?>
        <?php require $sidebarFile; ?>
    <?php else: ?>
        <aside class="sidebar">
            <h2>SimKlinik</h2>
            <p>Sistem Informasi Klinik</p>
            <ul>
                <li><a href="dashboard.php">📊 Dashboard</a></li>
                <li><a href="manajemen_user.php">👤 Manajemen User</a></li>
                <li><a href="pasien.php">🧑 Data Pasien</a></li>
                <li><a href="dokter.php" class="active">🩺 Data Dokter</a></li>
                <li><a href="poli.php">🏥 Data Poli</a></li>
                <li><a href="kunjungan.php">📋 Pendaftaran / Kunjungan</a></li>
                <li><a href="rekam_medis.php">📝 Rekam Medis</a></li>
                <li><a href="obat.php">💊 Data Obat</a></li>
                <li><a href="pembayaran.php">💳 Pembayaran</a></li>
                <li><a href="laporan.php">📈 Laporan Klinik</a></li>
                <li><a href="logout.php">🚪 Logout</a></li>
            </ul>
        </aside>
    <?php endif; ?>

    <main class="main-content">

        <div class="page-heading">
            <h1>Data Dokter</h1>
            <p>Kelola informasi dokter yang terdaftar di klinik.</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= e($success) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <section class="panel">
            <h2><?= $editData ? 'Edit Data Dokter' : 'Tambah Dokter' ?></h2>

            <form method="POST" action="dokter.php">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= e($editData['id'] ?? '') ?>">

                <div class="form-grid">
                    <div class="field">
                        <label for="kode_dokter">Kode Dokter *</label>
                        <input id="kode_dokter" name="kode_dokter" maxlength="30"
                               required value="<?= fieldValue($editData ?? [], 'kode_dokter') ?>">
                    </div>

                    <div class="field">
                        <label for="nama_dokter">Nama Dokter *</label>
                        <input id="nama_dokter" name="nama_dokter" maxlength="150"
                               required value="<?= fieldValue($editData ?? [], 'nama_dokter') ?>">
                    </div>

                    <div class="field">
                        <label for="spesialisasi">Spesialisasi</label>
                        <input id="spesialisasi" name="spesialisasi" maxlength="100"
                               value="<?= fieldValue($editData ?? [], 'spesialisasi') ?>">
                    </div>

                    <div class="field">
                        <label for="no_str">Nomor STR</label>
                        <input id="no_str" name="no_str" maxlength="100"
                               value="<?= fieldValue($editData ?? [], 'no_str') ?>">
                    </div>

                    <div class="field">
                        <label for="no_sip">Nomor SIP</label>
                        <input id="no_sip" name="no_sip" maxlength="100"
                               value="<?= fieldValue($editData ?? [], 'no_sip') ?>">
                    </div>

                    <div class="field">
                        <label for="jenis_kelamin">Jenis Kelamin</label>
                        <select id="jenis_kelamin" name="jenis_kelamin">
                            <option value="">-- Pilih --</option>
                            <option value="L" <?= (($editData['jenis_kelamin'] ?? '') === 'L') ? 'selected' : '' ?>>Laki-laki</option>
                            <option value="P" <?= (($editData['jenis_kelamin'] ?? '') === 'P') ? 'selected' : '' ?>>Perempuan</option>
                        </select>
                    </div>

                    <div class="field">
                        <label for="tempat_lahir">Tempat Lahir</label>
                        <input id="tempat_lahir" name="tempat_lahir"
                               value="<?= fieldValue($editData ?? [], 'tempat_lahir') ?>">
                    </div>

                    <div class="field">
                        <label for="tanggal_lahir">Tanggal Lahir</label>
                        <input type="date" id="tanggal_lahir" name="tanggal_lahir"
                               value="<?= fieldValue($editData ?? [], 'tanggal_lahir') ?>">
                    </div>

                    <div class="field">
                        <label for="telepon">Nomor Telepon</label>
                        <input id="telepon" name="telepon" maxlength="20"
                               value="<?= fieldValue($editData ?? [], 'telepon') ?>">
                    </div>

                    <div class="field">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" maxlength="100"
                               value="<?= fieldValue($editData ?? [], 'email') ?>">
                    </div>

                    <div class="field">
                        <label for="tarif_konsultasi">Tarif Konsultasi (Rp)</label>
                        <input type="number" id="tarif_konsultasi"
                               name="tarif_konsultasi" min="0" step="0.01"
                               value="<?= fieldValue($editData ?? [], 'tarif_konsultasi') !== '' ? fieldValue($editData ?? [], 'tarif_konsultasi') : '0' ?>">
                    </div>

                    <div class="field">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="aktif" <?= (($editData['status'] ?? 'aktif') === 'aktif') ? 'selected' : '' ?>>Aktif</option>
                            <option value="nonaktif" <?= (($editData['status'] ?? '') === 'nonaktif') ? 'selected' : '' ?>>Nonaktif</option>
                        </select>
                    </div>

                    <div class="field full">
                        <label for="alamat">Alamat</label>
                        <textarea id="alamat" name="alamat"><?= fieldValue($editData ?? [], 'alamat') ?></textarea>
                    </div>
                </div>

                <div class="actions">
                    <button type="submit" class="btn btn-primary">
                        <?= $editData ? 'Simpan Perubahan' : 'Tambah Dokter' ?>
                    </button>

                    <?php if ($editData): ?>
                        <a href="dokter.php" class="btn btn-secondary">Batal</a>
                    <?php else: ?>
                        <button type="reset" class="btn btn-secondary">Reset</button>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <section class="panel">
            <h2>Daftar Dokter</h2>

            <div class="table-toolbar">
                <span>Total data: <strong><?= count($dokterList) ?></strong> dokter</span>

                <form method="GET" action="dokter.php" class="search-form">
                    <input type="text" name="search"
                           placeholder="Cari kode, nama, spesialisasi..."
                           value="<?= e($search) ?>">
                    <button type="submit" class="btn btn-primary">Cari</button>

                    <?php if ($search !== ''): ?>
                        <a href="dokter.php" class="btn btn-secondary">Reset</a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Kode Dokter</th>
                            <th>Nama Dokter</th>
                            <th>Spesialisasi</th>
                            <th>Jenis Kelamin</th>
                            <th>Telepon</th>
                            <th>Tarif</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($dokterList): ?>
                        <?php foreach ($dokterList as $i => $d): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><?= e($d['kode_dokter']) ?></td>
                                <td><?= e($d['nama_dokter']) ?></td>
                                <td><?= e($d['spesialisasi'] ?: '-') ?></td>
                                <td>
                                    <?= ($d['jenis_kelamin'] ?? '') === 'L'
                                        ? 'Laki-laki'
                                        : (($d['jenis_kelamin'] ?? '') === 'P' ? 'Perempuan' : '-') ?>
                                </td>
                                <td><?= e($d['telepon'] ?: '-') ?></td>
                                <td>Rp <?= number_format((float)$d['tarif_konsultasi'], 0, ',', '.') ?></td>
                                <td>
                                    <span class="status status-<?= e($d['status']) ?>">
                                        <?= e(ucfirst($d['status'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="table-actions">
                                        <a href="dokter.php?edit=<?= (int)$d['id'] ?>"
                                           class="btn btn-edit">Edit</a>

                                        <form method="POST" action="dokter.php"
                                              onsubmit="return confirm('Yakin ingin menghapus dokter ini?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                                            <button type="submit" class="btn btn-delete">Hapus</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="empty">Data dokter belum tersedia.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

    </main>
</div>

</body>
</html>