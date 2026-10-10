<?php
require_once 'config/config.php';
requireRole(['admin']);

require_once 'models/Manajemen_user.php';

$database = new Database();
$db = $database->getConnection();

$manajemen_user = new Manajemen_user($db);

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {

            case 'create':
                $manajemen_user->username =
                    sanitizeInput($_POST['username']);

                $manajemen_user->password = $_POST['password'];

                $manajemen_user->nama_lengkap =
                    sanitizeInput($_POST['nama_lengkap']);

                $manajemen_user->email =
                    sanitizeInput($_POST['email']);

                $manajemen_user->role =
                    sanitizeInput($_POST['role']);

                if ($manajemen_user->create()) {
                    $message = 'Pengguna berhasil ditambahkan!';
                    $message_type = 'success';
                } else {
                    $message = 'Gagal menambahkan pengguna!';
                    $message_type = 'error';
                }
                break;

            case 'update':
                $manajemen_user->id =
                    sanitizeInput($_POST['id']);

                $manajemen_user->username =
                    sanitizeInput($_POST['username']);

                $manajemen_user->nama_lengkap =
                    sanitizeInput($_POST['nama_lengkap']);

                $manajemen_user->email =
                    sanitizeInput($_POST['email']);

                $manajemen_user->role =
                    sanitizeInput($_POST['role']);

                if ($manajemen_user->update()) {
                    $message = 'Data pengguna berhasil diperbarui!';
                    $message_type = 'success';
                } else {
                    $message = 'Gagal memperbarui data pengguna!';
                    $message_type = 'error';
                }
                break;

            case 'delete':
                $manajemen_user->id =
                    sanitizeInput($_POST['id']);

                if ($manajemen_user->delete()) {
                    $message = 'Pengguna berhasil dihapus!';
                    $message_type = 'success';
                } else {
                    $message = 'Gagal menghapus pengguna!';
                    $message_type = 'error';
                }
                break;

            case 'change_password':
                $manajemen_user->id =
                    sanitizeInput($_POST['id']);

                $new_password = $_POST['new_password'];

                if ($manajemen_user->changePassword($new_password)) {
                    $message = 'Password berhasil diubah!';
                    $message_type = 'success';
                } else {
                    $message = 'Gagal mengubah password!';
                    $message_type = 'error';
                }
                break;
        }
    }
}

$stmt = $manajemen_user->readAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manajemen User - <?php echo APP_NAME; ?></title>

    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>

<div class="main-container">

    <?php
    $role = $_SESSION['user_role'];
    require_once 'sidebar.php';
    ?>

    <main class="main-content">

        <header class="top-nav">
            <h1>Manajemen User</h1>

            <div class="user-info">
                <div class="user-avatar">
                    <?php
                    echo strtoupper(substr($_SESSION['nama_lengkap'], 0, 1));
                    ?>
                </div>

                <div class="user-details">
                    <div class="user-name">
                        <?php
                        echo htmlspecialchars($_SESSION['nama_lengkap']);
                        ?>
                    </div>

                    <div class="user-role">
                        <?php
                        echo ucfirst($_SESSION['user_role']);
                        ?>
                    </div>
                </div>
            </div>
        </header>

        <div class="content">

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Form Tambah Pengguna -->
            <div class="form-container">
                <h2>Tambah User Baru</h2>

                <form method="POST">
                    <input type="hidden" name="action" value="create">

                    <div class="form-row">
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input
                                type="text"
                                id="username"
                                name="username"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="password">Password</label>
                            <input
                                type="password"
                                id="password"
                                name="password"
                                required
                            >
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="nama_lengkap">Nama Lengkap</label>
                            <input
                                type="text"
                                id="nama_lengkap"
                                name="nama_lengkap"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="email">Email</label>
                            <input
                                type="email"
                                id="email"
                                name="email"
                            >
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="role">Role</label>

                            <select id="role" name="role" required>
                                <option value="">Pilih Role</option>
                                <option value="admin">Admin</option>
                                <option value="dokter">Dokter</option>
                                <option value="perawat">Perawat</option>
                                <option value="pendaftaran">Pendaftaran</option>
                                <option value="kasir">Kasir</option>
                                <option value="apoteker">Apoteker</option>
                                <option value="gudang">Gudang</option>
                            </select>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        Tambah Pengguna
                    </button>
                </form>
            </div>

            <!-- Tabel Data Pengguna -->
            <div class="table-container">
                <div class="table-header">
                    <h3 class="table-title">Daftar Pengguna</h3>
                </div>

                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Nama Lengkap</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Tanggal Dibuat</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php while ($row = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
                            <tr>
                                <td>
                                    <?php
                                    echo htmlspecialchars((string) $row['id']);
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    echo htmlspecialchars($row['username']);
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    echo htmlspecialchars($row['nama_lengkap']);
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    echo htmlspecialchars($row['email'] ?? '') ?: '-';
                                    ?>
                                </td>

                                <td>
                                    <span class="badge <?php
                                        echo $row['role'] === 'admin'
                                            ? 'badge-danger'
                                            : ($row['role'] === 'kasir'
                                                ? 'badge-success'
                                                : 'badge-warning');
                                    ?>">
                                        <?php
                                        echo htmlspecialchars(ucfirst($row['role']));
                                        ?>
                                    </span>
                                </td>

                                <td>
                                    <?php
                                    echo !empty($row['created_at'])
                                        ? date(
                                            'd/m/Y',
                                            strtotime($row['created_at'])
                                        )
                                        : '-';
                                    ?>
                                </td>

                                <td>
                                    <button
                                        type="button"
                                        onclick='editManajemenUser(<?php
                                            echo htmlspecialchars(
                                                json_encode($row),
                                                ENT_QUOTES,
                                                "UTF-8"
                                            );
                                        ?>)'
                                        class="btn btn-warning btn-sm">
                                        Edit
                                    </button>

                                    <button
                                        type="button"
                                        onclick='changeManajemenUserPassword(
                                            <?php echo (int) $row['id']; ?>,
                                            <?php
                                            echo htmlspecialchars(
                                                json_encode($row['username']),
                                                ENT_QUOTES,
                                                "UTF-8"
                                            );
                                            ?>
                                        )'
                                        class="btn btn-info btn-sm">
                                        Password
                                    </button>

                                    <?php if ($row['id'] != $_SESSION['user_id']): ?>
                                        <button
                                            type="button"
                                            onclick="deleteManajemenUser(
                                                <?php echo (int) $row['id']; ?>
                                            )"
                                            class="btn btn-danger btn-sm">
                                            Hapus
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </main>
</div>

<!-- Modal Edit Pengguna -->
<div
    id="editModal"
    style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000;"
>
    <div
        style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); background:white; padding:30px; border-radius:10px; width:90%; max-width:600px; max-height:90%; overflow-y:auto;"
    >

        <h2>Edit Pengguna</h2>

        <form method="POST" id="editForm">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit_id">

            <div class="form-row">
                <div class="form-group">
                    <label for="edit_username">Username</label>
                    <input
                        type="text"
                        id="edit_username"
                        name="username"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="edit_nama_lengkap">Nama Lengkap</label>
                    <input
                        type="text"
                        id="edit_nama_lengkap"
                        name="nama_lengkap"
                        required
                    >
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="edit_email">Email</label>
                    <input
                        type="email"
                        id="edit_email"
                        name="email"
                    >
                </div>

                <div class="form-group">
                    <label for="edit_role">Role</label>

                    <select id="edit_role" name="role" required>
                        <option value="admin">Admin</option>
                        <option value="dokter">Dokter</option>
                        <option value="perawat">Perawat</option>
                        <option value="pendaftaran">Pendaftaran</option>
                        <option value="kasir">Kasir</option>
                        <option value="apoteker">Apoteker</option>
                        <option value="gudang">Gudang</option>
                    </select>
                </div>
            </div>

            <div style="display:flex; gap:10px;">
                <button type="submit" class="btn btn-primary">
                    Update
                </button>

                <button
                    type="button"
                    onclick="closeEditModal()"
                    class="btn btn-secondary">
                    Batal
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Ubah Password -->
<div
    id="passwordModal"
    style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000;"
>
    <div
        style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); background:white; padding:30px; border-radius:10px; width:90%; max-width:400px;"
    >

        <h2>Ubah Password</h2>

        <form method="POST" id="passwordForm">
            <input type="hidden" name="action" value="change_password">
            <input
                type="hidden"
                name="id"
                id="password_manajemen_user_id"
            >

            <div class="form-group">
                <label for="password_username">Username</label>
                <input
                    type="text"
                    id="password_username"
                    readonly
                    style="background:#f8f9fa;"
                >
            </div>

            <div class="form-group">
                <label for="new_password">Password Baru</label>
                <input
                    type="password"
                    id="new_password"
                    name="new_password"
                    required
                >
            </div>

            <div style="display:flex; gap:10px;">
                <button type="submit" class="btn btn-primary">
                    Ubah Password
                </button>

                <button
                    type="button"
                    onclick="closePasswordModal()"
                    class="btn btn-secondary">
                    Batal
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function editManajemenUser(data) {
        document.getElementById('edit_id').value = data.id;
        document.getElementById('edit_username').value = data.username;
        document.getElementById('edit_nama_lengkap').value =
            data.nama_lengkap;
        document.getElementById('edit_email').value = data.email ?? '';
        document.getElementById('edit_role').value = data.role;

        document.getElementById('editModal').style.display = 'block';
    }

    function closeEditModal() {
        document.getElementById('editModal').style.display = 'none';
    }

    function changeManajemenUserPassword(id, username) {
        document.getElementById('password_manajemen_user_id').value = id;
        document.getElementById('password_username').value = username;
        document.getElementById('new_password').value = '';

        document.getElementById('passwordModal').style.display = 'block';
    }

    function closePasswordModal() {
        document.getElementById('passwordModal').style.display = 'none';
    }

    function deleteManajemenUser(id) {
        if (confirm('Apakah Anda yakin ingin menghapus pengguna ini?')) {
            const form = document.createElement('form');
            form.method = 'POST';

            const action = document.createElement('input');
            action.type = 'hidden';
            action.name = 'action';
            action.value = 'delete';

            const manajemenUserId = document.createElement('input');
            manajemenUserId.type = 'hidden';
            manajemenUserId.name = 'id';
            manajemenUserId.value = id;

            form.appendChild(action);
            form.appendChild(manajemenUserId);
            document.body.appendChild(form);

            form.submit();
        }
    }

    document.getElementById('editModal').onclick = function(e) {
        if (e.target === this) {
            closeEditModal();
        }
    };

    document.getElementById('passwordModal').onclick = function(e) {
        if (e.target === this) {
            closePasswordModal();
        }
    };
</script>

</body>
</html>