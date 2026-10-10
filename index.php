<?php
require_once 'config/config.php';

// Redirect to dashboard if already logged in
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - SIM Klinik</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            overflow-x: hidden;
        }

        /* Navigation */
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 1rem 0;
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: bold;
            color: white;
            text-decoration: none;
        }

        .nav-links {
            display: flex;
            gap: 2rem;
            align-items: center;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            transition: opacity 0.3s;
        }

        .nav-links a:hover {
            opacity: 0.8;
        }

        .btn-login {
            background: black;
            color: #667eea;
            padding: 0.5rem 1.5rem;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 255, 255, 0.3);
        }

        /* Hero Section */
        .hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 150px 2rem 100px;
            text-align: center;
            margin-top: 60px;
        }

        .hero-content {
            max-width: 800px;
            margin: 0 auto;
        }

        .hero h1 {
            font-size: 3.5rem;
            margin-bottom: 1rem;
            animation: fadeInUp 1s ease;
        }

        .hero p {
            font-size: 1.3rem;
            margin-bottom: 2rem;
            opacity: 0.9;
            animation: fadeInUp 1s ease 0.2s both;
        }

        .hero-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
            animation: fadeInUp 1s ease 0.4s both;
        }

        .btn-primary {
            background: white;
            color: #667eea;
            padding: 1rem 2.5rem;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
            font-size: 1.1rem;
            transition: transform 0.3s, box-shadow 0.3s;
            display: inline-block;
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }

        .btn-secondary {
            background: transparent;
            color: white;
            padding: 1rem 2.5rem;
            border: 2px solid white;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s;
            display: inline-block;
        }

        .btn-secondary:hover {
            background: white;
            color: #667eea;
            transform: translateY(-3px);
        }

        /* Features Section */
        .features {
            padding: 80px 2rem;
            background: rgb(18, 22, 27);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .section-title {
            text-align: center;
            font-size: 2.5rem;
            margin-bottom: 3rem;
            color: #2c3e50;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }

        .feature-card {
            background: white;
            padding: 2.5rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s, box-shadow 0.3s;
            text-align: center;
        }

        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        }

        .feature-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .feature-card h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: #2c3e50;
        }

        .feature-card p {
            color: #666;
            line-height: 1.8;
        }

        /* Stats Section */
        .stats {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 60px 2rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
            text-align: center;
        }

        .stat-item h3 {
            font-size: 3rem;
            margin-bottom: 0.5rem;
        }

        .stat-item p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        /* CTA Section */
        .cta {
            padding: 80px 2rem;
            background: white;
            text-align: center;
        }

        .cta h2 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: #2c3e50;
        }

        .cta p {
            font-size: 1.2rem;
            color: #666;
            margin-bottom: 2rem;
        }

        /* Footer */
        .footer {
            background: #2c3e50;
            color: white;
            padding: 40px 2rem;
            text-align: center;
        }

        .footer p {
            opacity: 0.8;
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .hero h1 {
                font-size: 2.5rem;
            }

            .hero p {
                font-size: 1.1rem;
            }

            .nav-links {
                gap: 1rem;
            }

            .features-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="#" class="logo"><?php echo APP_NAME; ?></a>
            <div class="nav-links">
                <a href="#features">Fitur</a>
                <a href="#about">Tentang</a>
                <a href="login.php" class="btn-login">Masuk</a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <h1>Selamat Datang di Sistem Informasi Klinik</h1>
            <p>Kelola data pasien, dokter, kunjungan, rekam medis, dan administrasi klinik dengan lebih mudah dan
                terstruktur.</p>
            <div class="hero-buttons">
                <a href="login.php" class="btn-primary">Mulai Sekarang</a>
                <a href="#features" class="btn-secondary">Pelajari Lebih Lanjut</a>
            </div>
        </div>
    </section>


    <!-- Features Section -->
    <section class="features" id="features">
        <div class="container">
            <h2 class="section-title" style="color: white;">Fitur Unggulan</h2>

            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">🧑‍🤝‍🧑</div>
                    <h3>Manajemen Pasien</h3>
                    <p>
                        Mengelola data pasien, nomor rekam medis,
                        identitas, dan informasi kontak secara terstruktur.
                    </p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">🩺</div>
                    <h3>Manajemen Dokter</h3>
                    <p>
                        Mengelola informasi dokter, spesialisasi,
                        tarif konsultasi, serta data poli klinik.
                    </p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">📋</div>
                    <h3>Pendaftaran & Kunjungan</h3>
                    <p>
                        Mencatat pendaftaran pasien, keluhan awal,
                        poli tujuan, dan status kunjungan.
                    </p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">📝</div>
                    <h3>Rekam Medis</h3>
                    <p>
                        Mendukung pencatatan hasil pemeriksaan,
                        diagnosis, tindakan, dan catatan dokter.
                    </p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">💊</div>
                    <h3>Manajemen Obat</h3>
                    <p>
                        Mengelola data obat, kategori, satuan,
                        stok, dan tanggal kedaluwarsa.
                    </p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">💳</div>
                    <h3>Pembayaran Klinik</h3>
                    <p>
                        Mencatat tagihan pasien, biaya pelayanan,
                        metode pembayaran, dan status pembayaran.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats">
        <div class="container">
            <div class="stats-grid">
                <div class="stat-item">
                    <h3>100%</h3>
                    <p>Akurat</p>
                </div>
                <div class="stat-item">
                    <h3>24/7</h3>
                    <p>Tersedia</p>
                </div>
                <div class="stat-item">
                    <h3>3</h3>
                    <p>Level Akses</p>
                </div>
                <div class="stat-item">
                    <h3>∞</h3>
                    <p>Transaksi</p>
                </div>
            </div>
        </div>
    </section>


    <!-- CTA Section -->
    <section class="cta" id="about">
        <div class="container">
            <h2>Pelayanan Klinik Lebih Terstruktur</h2>
            <p>
                SIM Klinik membantu pengelolaan data pasien,
                kunjungan, rekam medis, obat, dan administrasi
                klinik dalam satu sistem.
            </p>
            <a href="login.php" class="btn-primary">Masuk ke Sistem</a>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p>
            <p style="margin-top: 10px; font-size: 0.9rem;">Dibuat dengan ❤️ untuk kemudahan bisnis Anda</p>
        </div>
    </footer>

    <script>
        // Smooth scroll
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    </script>
</body>

</html>