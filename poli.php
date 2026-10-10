
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

// SIMPAN / EDIT POLI
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'save') {

    $id = (int)($_POST['id'] ?? 0);
    $kode = trim($_POST['kode_poli'] ?? '');
    $nama = trim($_POST['nama_poli'] ?? '');
    $lokasi = trim($_POST['lokasi'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $status = $_POST['status'] ?? 'aktif';

    if ($kode === '' || $nama === '') {
        $error = 'Kode poli dan nama poli wajib diisi.';
    } elseif (!in_array($status, ['aktif', 'nonaktif'], true)) {
        $error = 'Status poli tidak valid.';
    } else {
        try {
            if ($id > 0) {
                $stmt = $db->prepare("
                    UPDATE poli
                    SET kode_poli = ?, nama_poli = ?, lokasi = ?,
                        deskripsi = ?, status = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $kode, $nama, $lokasi ?: null,
                    $deskripsi ?: null, $status, $id
                ]);

                $success = 'Data poli berhasil diperbarui.';
            } else {
                $stmt = $db->prepare("
                    INSERT INTO poli
                    (kode_poli, nama_poli, lokasi, deskripsi, status)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $kode, $nama, $lokasi ?: null,
                    $deskripsi ?: null, $status
                ]);

                $success = 'Data poli berhasil ditambahkan.';
            }
        } catch (PDOException $ex) {
            $error = $ex->getCode() === '23000'
                ? 'Kode poli sudah digunakan atau data masih terhubung dengan data lain.'
                : 'Gagal menyimpan data poli. Periksa struktur tabel poli.';
        }
    }
}

// HAPUS POLI
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'delete') {

    try {
        $stmt = $db->prepare("DELETE FROM poli WHERE id = ?");
        $stmt->execute([(int)($_POST['id'] ?? 0)]);
        $success = 'Data poli berhasil dihapus.';
    } catch (PDOException $ex) {
        $error = 'Poli tidak dapat dihapus karena masih digunakan pada data lain.';
    }
}

// DATA EDIT
$editData = null;

if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM poli WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$editData) {
        $error = 'Data poli tidak ditemukan.';
    }
}

// PENCARIAN
$search = trim($_GET['search'] ?? '');

if ($search !== '') {
    $stmt = $db->prepare("
        SELECT * FROM poli
        WHERE kode_poli LIKE ?
           OR nama_poli LIKE ?
        ORDER BY id DESC
    ");
    $keyword = "%$search%";
    $stmt->execute([$keyword, $keyword]);
} else {
    $stmt = $db->query("SELECT * FROM poli ORDER BY id DESC");
}

$poliList = $stmt->fetchAll(PDO::FETCH_ASSOC);

// SIDEBAR
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
<title>Data Poli - SIM Klinik</title>

<style>
* { box-sizing: border-box; }

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f3f6fb;
    color: #25364a;
}

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

.sidebar li { margin: 6px 0; }

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

.page-heading { margin-bottom: 24px; }

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
    box-shadow: 0 4px 16px rgba(25,55,80,.06);
}

.panel h2 {
    font-size: 19px;
    margin: 0 0 22px;
    color: #173b57;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 17px;
}

.field {
    display: flex;
    flex-direction: column;
    gap: 7px;
    min-width: 0;
}

.field.full { grid-column: 1 / -1; }

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
    background: white;
}

.field textarea {
    min-height: 90px;
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

.actions { margin-top: 20px; }

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

.table-wrapper { overflow-x: auto; }

table {
    width: 100%;
    border-collapse: collapse;
    white-space: nowrap;
}

th, td {
    padding: 13px 12px;
    text-align: left;
    border-bottom: 1px solid #edf0f4;
    font-size: 13px;
}

th { background: #f7f9fc; color: #526579; }

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

    .main-content { padding: 18px; }
}

@media(max-width: 600px) {
    .main-container { display: block; }
    .sidebar { width: 100%; min-height: auto; }
    .form-grid { grid-template-columns: 1fr; }
    .field.full { grid-column: auto; }
    .search-form, .search-form input {
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
            <li><a href="dokter.php">🩺 Data Dokter</a></li>
            <li><a href="poli.php" class="active">🏥 Data Poli</a></li>
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
        <h1>Data Poli</h1>
        <p>Kelola data poli dan layanan yang tersedia di klinik.</p>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <section class="panel">
        <h2><?= $editData ? 'Edit Data Poli' : 'Tambah Poli' ?></h2>

        <form method="POST" action="poli.php">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= e($editData['id'] ?? '') ?>">

            <div class="form-grid">
                <div class="field">
                    <label for="kode_poli">Kode Poli *</label>
                    <input id="kode_poli" name="kode_poli" maxlength="30"
                           required value="<?= fieldValue($editData ?? [], 'kode_poli') ?>">
                </div>

                <div class="field">
                    <label for="nama_poli">Nama Poli *</label>
                    <input id="nama_poli" name="nama_poli" maxlength="100"
                           required value="<?= fieldValue($editData ?? [], 'nama_poli') ?>">
                </div>

                <div class="field">
                    <label for="lokasi">Lokasi Poli</label>
                    <input id="lokasi" name="lokasi"
                           value="<?= fieldValue($editData ?? [], 'lokasi') ?>">
                </div>

                <div class="field">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="aktif" <?= (($editData['status'] ?? 'aktif') === 'aktif') ? 'selected' : '' ?>>Aktif</option>
                        <option value="nonaktif" <?= (($editData['status'] ?? '') === 'nonaktif') ? 'selected' : '' ?>>Nonaktif</option>
                    </select>
                </div>

                <div class="field full">
                    <label for="deskripsi">Deskripsi</label>
                    <textarea id="deskripsi" name="deskripsi"><?= fieldValue($editData ?? [], 'deskripsi') ?></textarea>
                </div>
            </div>

            <div class="actions">
                <button type="submit" class="btn btn-primary">
                    <?= $editData ? 'Simpan Perubahan' : 'Tambah Poli' ?>
                </button>

                <?php if ($editData): ?>
                    <a href="poli.php" class="btn btn-secondary">Batal</a>
                <?php else: ?>
                    <button type="reset" class="btn btn-secondary">Reset</button>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <section class="panel">
        <h2>Daftar Poli</h2>

        <div class="table-toolbar">
            <span>Total data: <strong><?= count($poliList) ?></strong> poli</span>

            <form method="GET" action="poli.php" class="search-form">
                <input type="text" name="search"
                       placeholder="Cari kode atau nama poli..."
                       value="<?= e($search) ?>">
                <button class="btn btn-primary" type="submit">Cari</button>

                <?php if ($search !== ''): ?>
                    <a href="poli.php" class="btn btn-secondary">Reset</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Kode Poli</th>
                        <th>Nama Poli</th>
                        <th>Lokasi</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($poliList): ?>
                    <?php foreach ($poliList as $i => $poli): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><?= e($poli['kode_poli']) ?></td>
                            <td><?= e($poli['nama_poli']) ?></td>
                            <td><?= e($poli['lokasi'] ?? '-') ?></td>
                            <td>
                                <span class="status status-<?= e($poli['status']) ?>">
                                    <?= e(ucfirst($poli['status'])) ?>
                                </span>
                            </td>
                            <td>
                                <div class="table-actions">
                                    <a href="poli.php?edit=<?= (int)$poli['id'] ?>"
                                       class="btn btn-edit">Edit</a>

                                    <form method="POST" action="poli.php"
                                          onsubmit="return confirm('Yakin ingin menghapus poli ini?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int)$poli['id'] ?>">
                                        <button type="submit" class="btn btn-delete">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="empty">Belum ada data poli.</td>
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