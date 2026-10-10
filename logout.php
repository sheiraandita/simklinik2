<?php
require_once 'config/config.php';

// Hapus semua data session
$_SESSION = [];

// Hapus session di server
session_destroy();

// Kembali ke halaman login
header('Location: login.php');
exit;
?>