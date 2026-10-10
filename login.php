
<?php
require_once 'config/config.php';

// Jika sudah login, langsung ke dashboard
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username !== '' && $password !== '') {
        try {
            $database = new Database();
            $db = $database->getConnection();

            // Cari akun yang aktif berdasarkan username
            $sql = "SELECT * FROM users
                    WHERE username = :username
                    AND status = 'aktif'
                    LIMIT 1";

            $stmt = $db->prepare($sql);
            $stmt->execute(['username' => $username]);

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Verifikasi password yang tersimpan dalam bentuk hash
            if ($user && password_verify($password, $user['password'])) {
                session_regenerate_id(true);

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['email'] = $user['email'] ?? '';

                header('Location: dashboard.php');
                exit();
            } else {
                $error_message = 'Username atau password salah, atau akun tidak aktif!';
            }
        } catch (PDOException $e) {
            // Detail error tidak ditampilkan kepada pengguna
            error_log($e->getMessage());
            $error_message = 'Terjadi kesalahan saat mengakses database.';
        }
    } else {
        $error_message = 'Username dan password harus diisi!';
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dynamic.php">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <h1><?php echo APP_NAME; ?></h1>
                <p>Silakan masuk dengan akun Anda</p>
            </div>

            <?php if ($error_message): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="login-form">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        required
                        value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
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

                <button type="submit" class="btn btn-primary btn-full">
                    Masuk
                </button>
            </form>

            <div class="login-footer">
                <p><strong>Demo Account:</strong></p>
                <p>Admin: admin / password</p>
                <p>Kasir: kasir1 / password</p>
                <p>Gudang: gudang1 / password</p>

                <p style="margin-top: 15px;">
                    <a href="index.php"
                       style="color: #007bff; text-decoration: none;">
                        ← Kembali ke Beranda
                    </a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>