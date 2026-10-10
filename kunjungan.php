
<?php
require_once __DIR__ . '/config/config.php';
requireLogin();

$db = (new Database())->getConnection();

$error = '';
$success = '';

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

// Ambil data pilihan form
$pasienList = $db->query("
    SELECT id, no_rm, nama_pasien
    FROM pasien
    ORDER BY nama_pasien
")->fetchAll(PDO::FETCH_ASSOC);

$dokterList = $db->query("
    SELECT id, kode_dokter, nama_dokter
    FROM dokter
    WHERE status = 'aktif'
    ORDER BY nama_dokter
")->fetchAll(PDO::FETCH_ASSOC);

$poliList = $db->query("
    SELECT id, kode_poli, nama_poli
    FROM poli
    WHERE status = 'aktif'
    ORDER BY nama_poli
")->fetchAll(PDO::FETCH_ASSOC);

// Tambah / edit kunjungan
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'save') {

    $id = (int)($_POST['id'] ?? 0);
    $pasienId = (int)($_POST['pasien_id'] ?? 0);
    $dokterId = (int)($_POST['dokter_id'] ?? 0);
    $poliId = (int)($_POST['poli_id'] ?? 0);
    $tanggal = $_POST['tanggal_kunjungan'] ?? '';
    $jenis = trim($_POST['jenis_kunjungan'] ?? 'Umum');
    $keluhan = trim($_POST['keluhan'] ?? '');
    $status = $_POST['status'] ?? 'menunggu';

    $allowedStatus = ['menunggu', 'diperiksa', 'selesai', 'batal'];

    if (!$pasienId || !$dokterId || !$poliId || !$tanggal) {
        $error = 'Pasien, dokter, poli, dan tanggal kunjungan wajib diisi.';
    } elseif (!in_array($status, $allowedStatus, true)) {
        $error = 'Status kunjungan tidak valid.';
    } else {
        try {
            if ($id > 0) {
                $stmt = $db->prepare("
                    UPDATE kunjungan
                    SET pasien_id = ?, dokter_id = ?, poli_id = ?,
                        tanggal_kunjungan = ?, jenis_kunjungan = ?,
                        keluhan = ?, status = ?
                    WHERE id = ?
                ");

                $stmt->execute([
                    $pasienId, $dokterId, $poliId,
                    $tanggal, $jenis, $keluhan ?: null,
                    $status, $id
                ]);

                $success = 'Data kunjungan berhasil diperbarui.';
            } else {
                $stmt = $db->prepare("
                    INSERT INTO kunjungan
                    (pasien_id, dokter_id, poli_id, tanggal_kunjungan,
                     jenis_kunjungan, keluhan, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $pasienId, $dokterId, $poliId,
                    $tanggal, $jenis, $keluhan ?: null, $status
                ]);

                $success = 'Pendaftaran kunjungan berhasil ditambahkan.';
            }
        } catch (PDOException $ex) {
            $error = 'Gagal menyimpan kunjungan. Periksa struktur tabel kunjungan.';
        }
    }
}

// Hapus kunjungan
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'delete') {
    try {
        $stmt = $db->prepare("DELETE FROM kunjungan WHERE id = ?");
        $stmt->execute([(int)($_POST['id'] ?? 0)]);
        $success = 'Data kunjungan berhasil dihapus.';
    } catch (PDOException $ex) {
        $error = 'Kunjungan tidak dapat dihapus karena sudah terhubung dengan data lain.';
    }
}

// Data yang diedit
$editData = null;

if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM kunjungan WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editData = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

// Pencarian daftar kunjungan
$search = trim($_GET['search'] ?? '');

$sql = "
    SELECT k.*,
           p.no_rm,
           p.nama_pasien,
           d.nama_dokter,
           po.nama_poli
    FROM kunjungan k
    LEFT JOIN pasien p ON p.id = k.pasien_id
    LEFT JOIN dokter d ON d.id = k.dokter_id
    LEFT JOIN poli po ON po.id = k.poli_id
";

if ($search !== '') {
    $sql .= "
        WHERE p.nama_pasien LIKE ?
           OR d.nama_dokter LIKE ?
           OR po.nama_poli LIKE ?
    ";
}

$sql .= " ORDER BY k.id DESC";

$stmt = $db->prepare($sql);

if ($search !== '') {
    $keyword = "%$search%";
    $stmt->execute([$keyword, $keyword, $keyword]);
} else {
    $stmt->execute();
}

$kunjunganList = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Sidebar
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
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Pendaftaran / Kunjungan - SIM Klinik</title>

<style>
* { box-sizing: border-box; }

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f3f6fb;
    color: #25364a;
}

.main-container { display: flex; min-height: 100vh; }

.sidebar {
    flex: 0 0 250px;
    width: 250px;
    min-height: 100vh;
    padding: 24px 16px;
    background: #173b57;
    color: white;
}

.sidebar h2 { margin: 0 0 8px; }
.sidebar p { color: #c8d8e6; font-size: 13px; margin-bottom: 28px; }
.sidebar ul { list-style: none; padding: 0; margin: 0; }
.sidebar li { margin: 6px 0; }

.sidebar a {
    display: block;
    padding: 11px 12px;
    border-radius: 7px;
    color: #e7eff7;
    text-decoration: none;
    font-size: 14px;
}

.sidebar a:hover,
.sidebar a.active { background: #285a7d; color: white; }

.main-content { flex: 1; min-width: 0; padding: 28px; }
.page-heading { margin-bottom: 24px; }
.page-heading h1 { margin: 0 0 8px; color: #173b57; }
.page-heading p { margin: 0; color: #718096; font-size: 14px; }

.panel {
    background: white;
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 24px;
    box-shadow: 0 4px 16px rgba(25,55,80,.06);
}

.panel h2 { margin: 0 0 22px; font-size: 19px; color: #173b57; }

.form-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 17px;
}

.field { display: flex; flex-direction: column; gap: 7px; min-width: 0; }
.field.full { grid-column: 1 / -1; }
.field label { font-size: 13px; font-weight: 600; color: #46576a; }

.field input,
.field select,
.field textarea,
.search-form input {
    width: 100%;
    padding: 11px 12px;
    border: 1px solid #d7e0e9;
    border-radius: 7px;
    font: inherit;
    font-size: 14px;
    background: white;
}

.field textarea { min-height: 80px; resize: vertical; }

.actions, .table-actions, .search-form {
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
}

.btn-primary { background: #1877b9; color: white; }
.btn-secondary { background: #e9eef4; color: #334155; }
.btn-edit { background: #e0f2fe; color: #075985; }
.btn-delete { background: #fee4e2; color: #b42318; }

.alert { padding: 13px 16px; border-radius: 8px; margin-bottom: 18px; }
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

.search-form input { min-width: 240px; }
.table-wrapper { overflow-x: auto; }

table { width: 100%; border-collapse: collapse; white-space: nowrap; }
th, td { padding: 13px 12px; text-align: left; border-bottom: 1px solid #edf0f4; font-size: 13px; }
th { background: #f7f9fc; color: #526579; }

.status {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
}

.status-menunggu { background: #fef3c7; color: #92400e; }
.status-diperiksa { background: #dbeafe; color: #1d4ed8; }
.status-selesai { background: #dcfce7; color: #166534; }
.status-batal { background: #fee2e2; color: #991b1b; }
.empty { padding: 28px; text-align: center; color: #718096; }

@media(max-width: 900px) {
    .sidebar { flex-basis: 210px; width: 210px; }
    .main-content { padding: 18px; }
    .form-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}

@media(max-width: 600px) {
    .main-container { display: block; }
    .sidebar { width: 100%; min-height: auto; }
    .form-grid { grid-template-columns: 1fr; }
    .field.full { grid-column: auto; }
    .search-form, .search-form input { width: 100%; min-width: 0; }
}
</style>
</head>

<body>
<div class="main-container">

<?php if ($sidebarFile): ?>
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
            <li><a href="poli.php">🏥 Data Poli</a></li>
            <li><a href="kunjungan.php" class="active">📋 Pendaftaran / Kunjungan</a></li>
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
        <h1>Pendaftaran / Kunjungan</h1>
        <p>Kelola pendaftaran pasien dan jadwal kunjungan klinik.</p>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <section class="panel">
        <h2><?= $editData ? 'Edit Kunjungan' : 'Pendaftaran Kunjungan' ?></h2>

        <form method="POST" action="kunjungan.php">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= e($editData['id'] ?? '') ?>">

            <div class="form-grid">
                <div class="field">
                    <label for="pasien_id">Pasien *</label>
                    <select id="pasien_id" name="pasien_id" required>
                        <option value="">-- Pilih Pasien --</option>
                        <?php foreach ($pasienList as $p): ?>
                            <option value="<?= (int)$p['id'] ?>"
                                <?= (int)($editData['pasien_id'] ?? 0) === (int)$p['id'] ? 'selected' : '' ?>>
                                <?= e($p['no_rm']) ?> - <?= e($p['nama_pasien']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="dokter_id">Dokter *</label>
                    <select id="dokter_id" name="dokter_id" required>
                        <option value="">-- Pilih Dokter --</option>
                        <?php foreach ($dokterList as $d): ?>
                            <option value="<?= (int)$d['id'] ?>"
                                <?= (int)($editData['dokter_id'] ?? 0) === (int)$d['id'] ? 'selected' : '' ?>>
                                <?= e($d['nama_dokter']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="poli_id">Poli *</label>
                    <select id="poli_id" name="poli_id" required>
                        <option value="">-- Pilih Poli --</option>
                        <?php foreach ($poliList as $poli): ?>
                            <option value="<?= (int)$poli['id'] ?>"
                                <?= (int)($editData['poli_id'] ?? 0) === (int)$poli['id'] ? 'selected' : '' ?>>
                                <?= e($poli['nama_poli']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="tanggal_kunjungan">Tanggal Kunjungan *</label>
                    <input type="date" id="tanggal_kunjungan"
                           name="tanggal_kunjungan" required
                           value="<?= e($editData['tanggal_kunjungan'] ?? date('Y-m-d')) ?>">
                </div>

                <div class="field">
                    <label for="jenis_kunjungan">Jenis Kunjungan</label>
                    <select id="jenis_kunjungan" name="jenis_kunjungan">
                        <?php
                        $jenisSaatIni = $editData['jenis_kunjungan'] ?? 'Umum';
                        foreach (['Umum', 'Kontrol', 'Darurat'] as $jenis):
                        ?>
                            <option value="<?= e($jenis) ?>"
                                <?= $jenisSaatIni === $jenis ? 'selected' : '' ?>>
                                <?= e($jenis) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <?php
                        $statusSaatIni = $editData['status'] ?? 'menunggu';
                        foreach (['menunggu', 'diperiksa', 'selesai', 'batal'] as $st):
                        ?>
                            <option value="<?= e($st) ?>"
                                <?= $statusSaatIni === $st ? 'selected' : '' ?>>
                                <?= e(ucfirst($st)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field full">
                    <label for="keluhan">Keluhan Pasien</label>
                    <textarea id="keluhan" name="keluhan"><?= e($editData['keluhan'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="actions">
                <button type="submit" class="btn btn-primary">
                    <?= $editData ? 'Simpan Perubahan' : 'Daftarkan Kunjungan' ?>
                </button>
                <?php if ($editData): ?>
                    <a href="kunjungan.php" class="btn btn-secondary">Batal</a>
                <?php else: ?>
                    <button type="reset" class="btn btn-secondary">Reset</button>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <section class="panel">
        <h2>Daftar Kunjungan</h2>

        <div class="table-toolbar">
            <span>Total data: <strong><?= count($kunjunganList) ?></strong> kunjungan</span>

            <form method="GET" action="kunjungan.php" class="search-form">
                <input type="text" name="search"
                       placeholder="Cari pasien, dokter, atau poli..."
                       value="<?= e($search) ?>">
                <button class="btn btn-primary" type="submit">Cari</button>
                <?php if ($search !== ''): ?>
                    <a href="kunjungan.php" class="btn btn-secondary">Reset</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Tanggal</th>
                        <th>No. RM</th>
                        <th>Nama Pasien</th>
                        <th>Dokter</th>
                        <th>Poli</th>
                        <th>Jenis</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($kunjunganList): ?>
                    <?php foreach ($kunjunganList as $i => $k): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><?= e($k['tanggal_kunjungan']) ?></td>
                            <td><?= e($k['no_rm'] ?? '-') ?></td>
                            <td><?= e($k['nama_pasien'] ?? '-') ?></td>
                            <td><?= e($k['nama_dokter'] ?? '-') ?></td>
                            <td><?= e($k['nama_poli'] ?? '-') ?></td>
                            <td><?= e($k['jenis_kunjungan'] ?? '-') ?></td>
                            <td>
                                <span class="status status-<?= e($k['status']) ?>">
                                    <?= e(ucfirst($k['status'])) ?>
                                </span>
                            </td>
                            <td>
                                <div class="table-actions">
                                    <a href="kunjungan.php?edit=<?= (int)$k['id'] ?>"
                                       class="btn btn-edit">Edit</a>
                                    <form method="POST" action="kunjungan.php"
                                          onsubmit="return confirm('Yakin ingin menghapus kunjungan ini?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int)$k['id'] ?>">
                                        <button type="submit" class="btn btn-delete">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="9" class="empty">Belum ada data kunjungan.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</div>
</body>
</html>