```php
<?php
require_once 'config/config.php';
requireLogin();

$db = (new Database())->getConnection();

$message = '';
$message_type = '';

function h($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

// Simpan dan update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'simpan' || $aksi === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $kunjungan_id = (int)($_POST['kunjungan_id'] ?? 0);
        $diagnosis = trim($_POST['diagnosis'] ?? '');

        if ($kunjungan_id <= 0 || $diagnosis === '') {
            $message = 'Kunjungan dan diagnosis wajib diisi.';
            $message_type = 'error';
        } else {
            try {
                $stmt = $db->prepare("
                    SELECT pasien_id, dokter_id
                    FROM kunjungan
                    WHERE id = ?
                ");
                $stmt->execute([$kunjungan_id]);
                $kunjungan = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$kunjungan) {
                    throw new Exception('Kunjungan tidak ditemukan.');
                }

                $fields = [
                    'kunjungan_id' => $kunjungan_id,
                    'pasien_id' => $kunjungan['pasien_id'],
                    'dokter_id' => $kunjungan['dokter_id'],
                    'keluhan_utama' => trim($_POST['keluhan_utama'] ?? ''),
                    'riwayat_penyakit' => trim($_POST['riwayat_penyakit'] ?? ''),
                    'riwayat_alergi' => trim($_POST['riwayat_alergi'] ?? ''),
                    'tekanan_darah' => trim($_POST['tekanan_darah'] ?? ''),
                    'suhu' => ($_POST['suhu'] ?? '') !== '' ? $_POST['suhu'] : null,
                    'berat_badan' => ($_POST['berat_badan'] ?? '') !== '' ? $_POST['berat_badan'] : null,
                    'tinggi_badan' => ($_POST['tinggi_badan'] ?? '') !== '' ? $_POST['tinggi_badan'] : null,
                    'denyut_nadi' => ($_POST['denyut_nadi'] ?? '') !== '' ? $_POST['denyut_nadi'] : null,
                    'frekuensi_nafas' => ($_POST['frekuensi_nafas'] ?? '') !== '' ? $_POST['frekuensi_nafas'] : null,
                    'pemeriksaan_fisik' => trim($_POST['pemeriksaan_fisik'] ?? ''),
                    'hasil_pemeriksaan' => trim($_POST['hasil_pemeriksaan'] ?? ''),
                    'diagnosis' => $diagnosis,
                    'tindakan' => trim($_POST['tindakan'] ?? ''),
                    'catatan_dokter' => trim($_POST['catatan_dokter'] ?? ''),
                    'status' => in_array($_POST['status'] ?? '', ['draft', 'selesai'], true)
                        ? $_POST['status'] : 'draft'
                ];

                if ($aksi === 'simpan') {
                    $columns = implode(', ', array_keys($fields));
                    $placeholders = ':' . implode(', :', array_keys($fields));

                    $sql = "INSERT INTO rekam_medis ($columns) VALUES ($placeholders)";
                    $stmt = $db->prepare($sql);

                    $params = [];
                    foreach ($fields as $key => $value) {
                        $params[":$key"] = $value;
                    }
                    $stmt->execute($params);

                    $message = 'Rekam medis berhasil ditambahkan.';
                } else {
                    $set = [];
                    foreach (array_keys($fields) as $key) {
                        $set[] = "$key = :$key";
                    }

                    $sql = "UPDATE rekam_medis SET " . implode(', ', $set) . " WHERE id = :id";
                    $stmt = $db->prepare($sql);

                    $params = [];
                    foreach ($fields as $key => $value) {
                        $params[":$key"] = $value;
                    }
                    $params[':id'] = $id;
                    $stmt->execute($params);

                    $message = 'Rekam medis berhasil diperbarui.';
                }

                $message_type = 'success';
            } catch (Exception $e) {
                $message = 'Gagal menyimpan rekam medis. Periksa data kunjungan dan struktur database.';
                $message_type = 'error';
            } catch (PDOException $e) {
                $message = 'Gagal menyimpan rekam medis. Pastikan data yang dimasukkan valid.';
                $message_type = 'error';
            }
        }
    }

    if ($aksi === 'hapus') {
        try {
            $stmt = $db->prepare("DELETE FROM rekam_medis WHERE id = ?");
            $stmt->execute([(int)($_POST['id'] ?? 0)]);
            $message = 'Rekam medis berhasil dihapus.';
            $message_type = 'success';
        } catch (PDOException $e) {
            $message = 'Rekam medis gagal dihapus.';
            $message_type = 'error';
        }
    }
}

// Data untuk edit
$data_edit = null;

if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM rekam_medis WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $data_edit = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Dropdown kunjungan
$stmt = $db->query("
    SELECT k.id, k.no_kunjungan, k.tanggal_kunjungan,
           p.nama_pasien, p.no_rm, d.nama_dokter
    FROM kunjungan k
    JOIN pasien p ON p.id = k.pasien_id
    LEFT JOIN dokter d ON d.id = k.dokter_id
    ORDER BY k.tanggal_kunjungan DESC
");
$kunjungan_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Pencarian
$cari = trim($_GET['cari'] ?? '');

$sql = "
    SELECT rm.*, k.no_kunjungan, p.nama_pasien, p.no_rm,
           d.nama_dokter
    FROM rekam_medis rm
    LEFT JOIN kunjungan k ON k.id = rm.kunjungan_id
    LEFT JOIN pasien p ON p.id = rm.pasien_id
    LEFT JOIN dokter d ON d.id = rm.dokter_id
";

if ($cari !== '') {
    $sql .= "
        WHERE p.nama_pasien LIKE :cari
           OR p.no_rm LIKE :cari
           OR k.no_kunjungan LIKE :cari
           OR rm.diagnosis LIKE :cari
    ";
}

$sql .= " ORDER BY rm.tanggal_pemeriksaan DESC";

$stmt = $db->prepare($sql);

if ($cari !== '') {
    $stmt->execute([':cari' => "%$cari%"]);
} else {
    $stmt->execute();
}

$list = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekam Medis - <?= h(APP_NAME) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dynamic.php">

    <style>
        .rm-page {
            padding: 20px 24px;
            min-width: 0;
        }

        .rm-page .page-heading {
            margin-bottom: 18px;
        }

        .rm-page .page-heading h1 {
            font-size: 22px;
            margin: 0 0 5px;
            color: #1e293b;
        }

        .rm-page .page-heading p {
            font-size: 13px;
            color: #64748b;
            margin: 0;
        }

        .rm-box {
            background: #fff;
            border-radius: 10px;
            padding: 18px 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,.04);
        }

        .rm-box-title {
            font-size: 16px;
            font-weight: 700;
            margin: 0 0 16px;
            padding-bottom: 11px;
            border-bottom: 1px solid #e8edf3;
            color: #1e293b;
        }

        .rm-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px 16px;
        }

        .rm-field {
            min-width: 0;
        }

        .rm-field.full {
            grid-column: 1 / -1;
        }

        .rm-field label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #475569;
            margin-bottom: 5px;
        }

        .rm-field input,
        .rm-field select,
        .rm-field textarea {
            display: block;
            width: 100%;
            min-width: 0;
            padding: 8px 10px;
            border: 1px solid #d8e0e9;
            border-radius: 6px;
            font: inherit;
            font-size: 13px;
            color: #334155;
            background: #fff;
        }

        .rm-field textarea {
            height: 58px;
            resize: vertical;
        }

        .rm-field textarea.short {
            height: 44px;
        }

        .rm-buttons {
            display: flex;
            gap: 8px;
            margin-top: 15px;
            flex-wrap: wrap;
        }

        .rm-btn {
            display: inline-block;
            border: 0;
            border-radius: 6px;
            padding: 8px 12px;
            font-size: 12px;
            text-decoration: none;
            cursor: pointer;
        }

        .rm-btn-primary {
            color: #fff;
            background: #287f91;
        }

        .rm-btn-edit {
            color: #765700;
            background: #fff0c2;
        }

        .rm-btn-delete {
            color: #a32121;
            background: #ffe1e1;
        }

        .rm-btn-light {
            color: #334155;
            background: #e9eef4;
        }

        .rm-alert {
            padding: 10px 13px;
            border-radius: 6px;
            font-size: 13px;
            margin-bottom: 16px;
        }

        .rm-alert.success {
            background: #dcfce7;
            color: #166534;
        }

        .rm-alert.error {
            background: #fee2e2;
            color: #991b1b;
        }

        .rm-search {
            display: flex;
            gap: 8px;
            margin-bottom: 14px;
        }

        .rm-search input {
            width: 280px;
            max-width: 100%;
            padding: 8px 10px;
            border: 1px solid #d8e0e9;
            border-radius: 6px;
            font-size: 13px;
        }

        .rm-table-wrap {
            overflow-x: auto;
        }

        .rm-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 780px;
        }

        .rm-table th,
        .rm-table td {
            padding: 10px;
            border-bottom: 1px solid #edf0f4;
            text-align: left;
            vertical-align: top;
            font-size: 12px;
        }

        .rm-table th {
            background: #f6f8fb;
            color: #475569;
            font-weight: 700;
        }

        .rm-status {
            display: inline-block;
            border-radius: 15px;
            padding: 4px 8px;
            background: #fff0c2;
            color: #765700;
            font-size: 11px;
        }

        .rm-status.selesai {
            background: #dcfce7;
            color: #166534;
        }

        .rm-action-row {
            display: flex;
            gap: 5px;
        }

        .rm-action-row form {
            margin: 0;
        }

        @media (max-width: 650px) {
            .rm-page {
                padding: 14px;
            }

            .rm-form-grid {
                grid-template-columns: 1fr;
            }

            .rm-field.full {
                grid-column: auto;
            }

            .rm-search input {
                flex: 1;
                width: 100%;
            }
        }
    </style>
</head>

<body>
<div class="main-container">

    <?php require_once 'sidebar.php'; ?>

    <main class="main-content">
        <header class="top-nav">
            <div>
                <h1 style="font-size:22px;font-weight:700;color:#1e293b;">
                    Rekam Medis
                </h1>
                <small style="color:#64748b;">
                    Kelola data pemeriksaan dan catatan medis pasien
                </small>
            </div>
        </header>

        <div class="content rm-page">

            <?php if ($message): ?>
                <div class="rm-alert <?= h($message_type) ?>">
                    <?= h($message) ?>
                </div>
            <?php endif; ?>

            <section class="rm-box">
                <h2 class="rm-box-title">
                    <?= $data_edit ? 'Edit Rekam Medis' : 'Tambah Rekam Medis' ?>
                </h2>

                <form method="POST">
                    <input type="hidden" name="aksi"
                           value="<?= $data_edit ? 'update' : 'simpan' ?>">

                    <?php if ($data_edit): ?>
                        <input type="hidden" name="id" value="<?= h($data_edit['id']) ?>">
                    <?php endif; ?>

                    <div class="rm-form-grid">
                        <div class="rm-field full">
                            <label>Kunjungan Pasien *</label>
                            <select name="kunjungan_id" required>
                                <option value="">-- Pilih kunjungan --</option>
                                <?php foreach ($kunjungan_list as $k): ?>
                                    <option value="<?= h($k['id']) ?>"
                                        <?= (string)($data_edit['kunjungan_id'] ?? '') === (string)$k['id'] ? 'selected' : '' ?>>
                                        <?= h($k['no_kunjungan']) ?> -
                                        <?= h($k['nama_pasien']) ?> -
                                        <?= h($k['tanggal_kunjungan']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="rm-field">
                            <label>Keluhan Utama</label>
                            <textarea class="short" name="keluhan_utama"><?= h($data_edit['keluhan_utama'] ?? '') ?></textarea>
                        </div>

                        <div class="rm-field">
                            <label>Riwayat Penyakit</label>
                            <textarea class="short" name="riwayat_penyakit"><?= h($data_edit['riwayat_penyakit'] ?? '') ?></textarea>
                        </div>

                        <div class="rm-field">
                            <label>Riwayat Alergi</label>
                            <input type="text" name="riwayat_alergi"
                                   value="<?= h($data_edit['riwayat_alergi'] ?? '') ?>">
                        </div>

                        <div class="rm-field">
                            <label>Tekanan Darah</label>
                            <input type="text" name="tekanan_darah"
                                   placeholder="120/80"
                                   value="<?= h($data_edit['tekanan_darah'] ?? '') ?>">
                        </div>

                        <div class="rm-field">
                            <label>Suhu Tubuh (°C)</label>
                            <input type="number" step="0.1" name="suhu"
                                   value="<?= h($data_edit['suhu'] ?? '') ?>">
                        </div>

                        <div class="rm-field">
                            <label>Berat Badan (kg)</label>
                            <input type="number" step="0.01" name="berat_badan"
                                   value="<?= h($data_edit['berat_badan'] ?? '') ?>">
                        </div>

                        <div class="rm-field">
                            <label>Tinggi Badan (cm)</label>
                            <input type="number" step="0.01" name="tinggi_badan"
                                   value="<?= h($data_edit['tinggi_badan'] ?? '') ?>">
                        </div>

                        <div class="rm-field">
                            <label>Denyut Nadi</label>
                            <input type="number" name="denyut_nadi"
                                   value="<?= h($data_edit['denyut_nadi'] ?? '') ?>">
                        </div>

                        <div class="rm-field">
                            <label>Frekuensi Napas</label>
                            <input type="number" name="frekuensi_nafas"
                                   value="<?= h($data_edit['frekuensi_nafas'] ?? '') ?>">
                        </div>

                        <div class="rm-field">
                            <label>Pemeriksaan Fisik</label>
                            <textarea class="short" name="pemeriksaan_fisik"><?= h($data_edit['pemeriksaan_fisik'] ?? '') ?></textarea>
                        </div>

                        <div class="rm-field">
                            <label>Hasil Pemeriksaan</label>
                            <textarea class="short" name="hasil_pemeriksaan"><?= h($data_edit['hasil_pemeriksaan'] ?? '') ?></textarea>
                        </div>

                        <div class="rm-field full">
                            <label>Diagnosis *</label>
                            <textarea class="short" name="diagnosis" required><?= h($data_edit['diagnosis'] ?? '') ?></textarea>
                        </div>

                        <div class="rm-field">
                            <label>Tindakan</label>
                            <textarea class="short" name="tindakan"><?= h($data_edit['tindakan'] ?? '') ?></textarea>
                        </div>

                        <div class="rm-field">
                            <label>Catatan Dokter</label>
                            <textarea class="short" name="catatan_dokter"><?= h($data_edit['catatan_dokter'] ?? '') ?></textarea>
                        </div>

                        <div class="rm-field">
                            <label>Status Rekam Medis</label>
                            <select name="status">
                                <option value="draft"
                                    <?= ($data_edit['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>
                                    Draft
                                </option>
                                <option value="selesai"
                                    <?= ($data_edit['status'] ?? '') === 'selesai' ? 'selected' : '' ?>>
                                    Selesai
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="rm-buttons">
                        <button type="submit" class="rm-btn rm-btn-primary">
                            <?= $data_edit ? 'Simpan Perubahan' : 'Simpan Rekam Medis' ?>
                        </button>

                        <?php if ($data_edit): ?>
                            <a href="rekam_medis.php" class="rm-btn rm-btn-light">Batal</a>
                        <?php endif; ?>
                    </div>
                </form>
            </section>

            <section class="rm-box">
                <h2 class="rm-box-title">Data Rekam Medis</h2>

                <form method="GET" class="rm-search">
                    <input type="text" name="cari"
                           placeholder="Cari pasien atau diagnosis..."
                           value="<?= h($cari) ?>">
                    <button class="rm-btn rm-btn-primary" type="submit">Cari</button>
                    <a class="rm-btn rm-btn-light" href="rekam_medis.php">Reset</a>
                </form>

                <div class="rm-table-wrap">
                    <table class="rm-table">
                        <thead>
                        <tr>
                            <th>No.</th>
                            <th>Tanggal</th>
                            <th>No. RM</th>
                            <th>Nama Pasien</th>
                            <th>Dokter</th>
                            <th>Diagnosis</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if ($list): ?>
                            <?php foreach ($list as $i => $rm): ?>
                                <tr>
                                    <td><?= $i + 1 ?></td>
                                    <td><?= h($rm['tanggal_pemeriksaan']) ?></td>
                                    <td><?= h($rm['no_rm'] ?? '-') ?></td>
                                    <td><?= h($rm['nama_pasien'] ?? '-') ?></td>
                                    <td><?= h($rm['nama_dokter'] ?? '-') ?></td>
                                    <td><?= nl2br(h($rm['diagnosis'])) ?></td>
                                    <td>
                                        <span class="rm-status <?= h($rm['status']) ?>">
                                            <?= h(ucfirst($rm['status'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="rm-action-row">
                                            <a class="rm-btn rm-btn-edit"
                                               href="rekam_medis.php?edit=<?= h($rm['id']) ?>">
                                                Edit
                                            </a>

                                            <form method="POST"
                                                  onsubmit="return confirm('Hapus rekam medis ini?')">
                                                <input type="hidden" name="aksi" value="hapus">
                                                <input type="hidden" name="id" value="<?= h($rm['id']) ?>">
                                                <button type="submit" class="rm-btn rm-btn-delete">
                                                    Hapus
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align:center;padding:22px;color:#64748b;">
                                    Belum ada data rekam medis.
                                </td>
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
```
