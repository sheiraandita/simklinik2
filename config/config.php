
<?php
// Konfigurasi umum aplikasi
define('BASE_URL', 'http://localhost/simklinik2/');
define('APP_NAME', 'SIM Klinik');

// Konfigurasi session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Autoload classes
spl_autoload_register(function ($class_name) {
    $directories = [
        __DIR__ . '/../models/',
        __DIR__ . '/../controllers/',
        __DIR__ . '/../classes/'
    ];

    foreach ($directories as $directory) {
        $file = $directory . $class_name . '.php';

        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// Koneksi database
require_once __DIR__ . '/database.php';

// Mengecek status login
function isLoggedIn() {
    return isset($_SESSION['user_id'])
        && isset($_SESSION['user_role']);
}

// Memastikan pengguna sudah login
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

// Membatasi akses berdasarkan role
function requireRole($allowedRoles) {
    requireLogin();

    if (!in_array($_SESSION['user_role'], $allowedRoles, true)) {
        header('Location: unauthorized.php');
        exit();
    }
}

// Membersihkan input
function sanitizeInput($data) {
    return htmlspecialchars(
        stripslashes(trim($data)),
        ENT_QUOTES,
        'UTF-8'
    );
}

// Format mata uang
function formatCurrency($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}
?>