<?php
/**
 * GEMBOK ISP - Modern Landing Page
 */

// Check for installation
if (!file_exists(__DIR__ . '/includes/installed.lock')) {
    header("Location: install.php");
    exit;
}

require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Fetch Packages
$packages = [];
try {
    $pdo = getDB();
    $packages = $pdo->query("SELECT * FROM packages ORDER BY price ASC LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Fail silently
}

// App settings
$appName = getSetting('app_name', 'GEMBOK');

// Landing settings
$heroTitle = getSiteSetting('hero_title', 'Internet Cepat <br>Tanpa Batas');
$heroDesc = getSiteSetting('hero_description', 'Nikmati koneksi internet fiber optic super cepat, stabil, dan unlimited untuk kebutuhan rumah maupun bisnis Anda. Gabung sekarang!');
$contactPhone = getSiteSetting('contact_phone', '+62 812-3456-7890');
$contactEmail = getSiteSetting('contact_email', 'info@gembok.net');
$contactAddress = getSiteSetting('contact_address', 'Jakarta, Indonesia');
$footerAbout = getSiteSetting('footer_about', 'Penyedia layanan internet terpercaya dengan jaringan fiber optic berkualitas untuk menunjang aktivitas digital Anda.');

// Feature settings
$f1_title = getSiteSetting('feature_1_title', 'Kecepatan Tinggi');
$f1_desc = getSiteSetting('feature_1_desc', 'Koneksi fiber optic dengan kecepatan simetris upload dan download.');

$f2_title = getSiteSetting('feature_2_title', 'Unlimited Quota');
$f2_desc = getSiteSetting('feature_2_desc', 'Akses internet sepuasnya tanpa batasan kuota (FUP).');

$f3_title = getSiteSetting('feature_3_title', 'Support 24/7');
$f3_desc = getSiteSetting('feature_3_desc', 'Tim teknis kami siap membantu Anda kapanpun jika terjadi gangguan.');

// Social settings
$s_fb = getSiteSetting('social_facebook', '#');
$s_ig = getSiteSetting('social_instagram', '#');
$s_tw = getSiteSetting('social_twitter', '#');
$s_yt = getSiteSetting('social_youtube', '#');

// Theme settings
$themeColor = getSiteSetting('theme_color', 'neon');
?>
<!DOCTYPE html>
<html lang="id" <?php echo $themeColor !== 'neon' ? 'data-theme="' . htmlspecialchars($themeColor) . '"' : ''; ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $appName; ?> - Internet Service Provider</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            /* Default Theme (Neon) */
            --primary: #00f5ff;
            --secondary: #bf00ff;
            --dark: #0a0a12;
            --light: #ffffff;
            --gray: #b0b0c0;
            --bg-dark: #0f0f1a;
            --bg-card: #1a1a2e;
        }

        /* Theme Variations */
        [data-theme="ocean"] {
            --primary: #0099ff;
            --secondary: #00d4ff;
            --bg-card: #121e2d;
        }

        [data-theme="nature"] {
            --primary: #00ff88;
            --secondary: #00cc6a;
            --bg-card: #122d1e;
        }

        [data-theme="sunset"] {
            --primary: #ff6b35;
            --secondary: #ff9f43;
            --bg-card: #2d1a12;
        }

        [data-theme="royal"] {
            --primary: #a742f5;
            --secondary: #d633ff;
            --bg-card: #22122d;
        }

        [data-theme="crimson"] {
            --primary: #ff2e63;
            --secondary: #ff0055;
            --bg-card: #2d1218;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--dark);
            color: var(--light);
            overflow-x: hidden;
        }

        /* Navbar */
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 50px;
            background: rgba(10, 10, 18, 0.95);
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1000;
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .nav-links {
            display: flex;
            gap: 30px;
        }

        .nav-links a {
            color: var(--light);
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 500;
            transition: 0.3s;
        }

        .nav-links a:hover {
            color: var(--primary);
        }

        .login-btn {
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            padding: 10px 25px;
            border-radius: 50px;
            color: #fff !important;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(0, 245, 255, 0.3);
            border: none;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 245, 255, 0.4);
        }

        /* Mobile Menu */
        .menu-toggle {
            display: none;
            font-size: 1.5rem;
            cursor: pointer;
        }

        /* Hero Section */
        .hero {
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 50px;
            position: relative;
            background: radial-gradient(circle at top right, rgba(191, 0, 255, 0.1), transparent 40%),
                        radial-gradient(circle at bottom left, rgba(0, 245, 255, 0.1), transparent 40%);
            margin-top: 60px;
        }

        .hero-content {
            max-width: 600px;
            z-index: 1;
        }

        .hero h1 {
            font-size: 3.5rem;
            line-height: 1.2;
            margin-bottom: 20px;
            background: linear-gradient(to right, #fff, #b0b0c0);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero p {
            font-size: 1.1rem;
            color: var(--gray);
            margin-bottom: 30px;
            line-height: 1.6;
        }

        .cta-buttons {
            display: flex;
            gap: 20px;
        }

        .btn {
            padding: 12px 30px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .btn-primary {
            background: var(--primary);
            color: #000;
        }
        
        .btn-primary:hover {
            background: #00dcec;
        }

        .btn-outline {
            border: 2px solid var(--border-color, #333);
            color: var(--light);
        }
        
        .btn-outline:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        .hero-image {
            flex: 1;
            display: flex;
            justify-content: center;
            position: relative;
        }

        .hero-image img {
            max-width: 100%;
            height: auto;
            animation: float 6s ease-in-out infinite;
            filter: drop-shadow(0 0 30px rgba(0, 245, 255, 0.2));
        }

        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
            100% { transform: translateY(0px); }
        }

        /* Features */
        .features {
            padding: 80px 50px;
            background: var(--bg-dark);
            text-align: center;
        }

        .section-title {
            font-size: 2.5rem;
            margin-bottom: 50px;
        }

        .section-title span {
            color: var(--primary);
        }

        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
        }

        .feature-card {
            background: var(--bg-card);
            padding: 30px;
            border-radius: 15px;
            transition: 0.3s;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .feature-card:hover {
            transform: translateY(-10px);
            border-color: var(--primary);
            box-shadow: 0 10px 30px rgba(0, 245, 255, 0.1);
        }

        .feature-icon {
            font-size: 2.5rem;
            color: var(--primary);
            margin-bottom: 20px;
        }

        .feature-card h3 {
            margin-bottom: 15px;
            font-size: 1.2rem;
        }

        .feature-card p {
            color: var(--gray);
            font-size: 0.9rem;
            line-height: 1.5;
        }

        /* Packages */
        .pricing {
            padding: 80px 50px;
            background: var(--dark);
            text-align: center;
        }

        .pricing-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .pricing-card {
            background: var(--bg-card);
            padding: 40px 30px;
            border-radius: 20px;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.05);
            transition: 0.3s;
        }

        .pricing-card.popular {
            border-color: var(--secondary);
            box-shadow: 0 0 30px rgba(191, 0, 255, 0.15);
        }
        
        .pricing-card:hover {
            transform: scale(1.02);
        }

        .popular-badge {
            position: absolute;
            top: 20px;
            right: -30px;
            background: var(--secondary);
            padding: 5px 40px;
            transform: rotate(45deg);
            font-size: 0.8rem;
            font-weight: 600;
        }

        .price {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--light);
            margin: 20px 0;
        }

        .price span {
            font-size: 1rem;
            color: var(--gray);
            font-weight: 400;
        }

        .package-features {
            list-style: none;
            margin: 30px 0;
            text-align: left;
        }

        .package-features li {
            margin-bottom: 15px;
            color: var(--gray);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .package-features li i {
            color: var(--primary);
        }

        /* Footer */
        footer {
            background: #080810;
            padding: 80px 50px 30px;
            border-top: 1px solid rgba(0, 245, 255, 0.1);
            position: relative;
        }

        .footer-content {
            display: grid;
            grid-template-columns: 2fr 1fr 1.5fr;
            gap: 60px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .footer-col h4 {
            margin-bottom: 25px;
            color: var(--light);
            font-size: 1.2rem;
            font-weight: 600;
            position: relative;
            padding-bottom: 10px;
        }

        .footer-col h4::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 30px;
            height: 2px;
            background: var(--primary);
        }

        .footer-links a {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--gray);
            text-decoration: none;
            margin-bottom: 15px;
            transition: 0.3s;
            font-size: 0.95rem;
        }

        .footer-links a i {
            font-size: 0.8rem;
            color: var(--primary);
            opacity: 0.5;
            transition: 0.3s;
        }

        .footer-links a:hover {
            color: var(--primary);
            padding-left: 5px;
        }

        .footer-links a:hover i {
            opacity: 1;
            transform: translateX(3px);
        }

        .contact-item {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            color: var(--gray);
            font-size: 0.95rem;
        }

        .contact-item i {
            color: var(--primary);
            font-size: 1.1rem;
            margin-top: 3px;
        }

        .contact-item div p {
            margin-bottom: 5px;
            color: var(--light);
            font-weight: 500;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.6;
        }

        .social-links {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 25px;
        }

        .social-links a {
            padding: 10px 18px;
            background: rgba(255, 255, 255, 0.03);
            display: flex;
            align-items: center;
            gap: 10px;
            border-radius: 12px;
            color: var(--light);
            text-decoration: none;
            transition: 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            font-size: 0.85rem;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .social-links a:hover {
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            color: #fff;
            border-color: transparent;
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 245, 255, 0.2);
        }

        .copyright {
            text-align: center;
            margin-top: 60px;
            padding-top: 30px;
            color: var(--gray);
            font-size: 0.85rem;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
        }

        /* Responsive */
        @media (max-width: 992px) {
            .footer-content {
                grid-template-columns: 1fr 1fr;
            }
            .footer-col:first-child {
                grid-column: span 2;
            }
        }

        @media (max-width: 768px) {
            .footer-content {
                grid-template-columns: 1fr;
                gap: 40px;
            }
            .footer-col:first-child {
                grid-column: span 1;
            }
            footer {
                padding: 60px 20px 30px;
            }
            .navbar {
                padding: 20px;
            }
            
            .nav-links {
                display: none;
                position: absolute;
                top: 70px;
                left: 0;
                width: 100%;
                background: var(--bg-dark);
                flex-direction: column;
                padding: 20px;
                text-align: center;
                box-shadow: 0 10px 20px rgba(0,0,0,0.5);
            }
            
            .nav-links.active {
                display: flex;
            }
            
            .menu-toggle {
                display: block;
            }
            
            .hero {
                flex-direction: column-reverse;
                text-align: center;
                height: auto;
                padding: 100px 20px 50px;
            }
            
            .hero h1 {
                font-size: 2.5rem;
            }
            
            .cta-buttons {
                justify-content: center;
            }
            
            .hero-image {
                margin-bottom: 40px;
            }
            
            .pricing-grid {
                grid-template-columns: 1fr;
            }
            
            .features, .pricing {
                padding: 50px 20px;
            }
        }
        
        /* Dropdown Menu */
        .dropdown {
            position: relative;
            display: inline-block;
        }
        
        .dropdown-content {
            display: none;
            position: absolute;
            background-color: var(--bg-card);
            min-width: 160px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.2);
            z-index: 1;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            top: 100%;
            right: 0;
            overflow: hidden;
        }
        
        .dropdown-content a {
            color: var(--text-primary);
            padding: 12px 16px;
            text-decoration: none;
            display: block;
            text-align: left;
        }
        
        .dropdown-content a:hover {
            background-color: rgba(255,255,255,0.05);
            color: var(--primary);
        }
        
        .dropdown:hover .dropdown-content {
            display: block;
        }
    </style>
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar">
        <a href="#" class="logo">
            <i class="fas fa-bolt"></i> <?php echo $appName; ?>
        </a>
        <div class="menu-toggle" onclick="toggleMenu()">
            <i class="fas fa-bars"></i>
        </div>
        <div class="nav-links" id="navLinks">
            <a href="#home">Beranda</a>
            <a href="#services">Layanan</a>
            <a href="#pricing">Paket</a>
            <a href="#contact">Hubungi Kami</a>
            
            <!-- Login Dropdown -->
            <div class="dropdown">
                <a href="#" class="login-btn">Member Area <i class="fas fa-chevron-down"></i></a>
                <div class="dropdown-content">
                    <a href="portal/login.php"><i class="fas fa-user"></i> Pelanggan</a>
                    <a href="sales/login.php"><i class="fas fa-user-tie"></i> Sales / Agen</a>
                    <a href="admin/login.php"><i class="fas fa-user-shield"></i> Admin</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero" id="home">
        <div class="hero-content">
            <h1><?php echo $heroTitle; ?></h1>
            <p><?php echo $heroDesc; ?></p>
            <div class="cta-buttons">
                <a href="#pricing" class="btn btn-primary">Lihat Paket</a>
                <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $contactPhone); ?>?text=Halo%20saya%20ingin%20berlangganan" class="btn btn-outline">Hubungi Sales</a>
            </div>
        </div>
        <div class="hero-image">
            <!-- Simple SVG Illustration placeholder -->
            <svg width="400" height="400" viewBox="0 0 400 400" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="200" cy="200" r="150" fill="url(#paint0_radial)" fill-opacity="0.2"/>
                <path d="M100 200L150 250L300 100" stroke="#00f5ff" stroke-width="20" stroke-linecap="round" stroke-linejoin="round"/>
                <defs>
                    <radialGradient id="paint0_radial" cx="0" cy="0" r="1" gradientUnits="userSpaceOnUse" gradientTransform="translate(200 200) rotate(90) scale(150)">
                        <stop stop-color="#00F5FF"/>
                        <stop offset="1" stop-color="#00F5FF" stop-opacity="0"/>
                    </radialGradient>
                </defs>
            </svg>
        </div>
    </section>

    <!-- Services Section -->
    <section class="features" id="services">
        <div class="section-title">
            Kenapa Memilih <span><?php echo $appName; ?></span>?
        </div>
        <div class="feature-grid">
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-rocket"></i>
                </div>
                <h3><?php echo htmlspecialchars($f1_title); ?></h3>
                <p><?php echo htmlspecialchars($f1_desc); ?></p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-infinity"></i>
                </div>
                <h3><?php echo htmlspecialchars($f2_title); ?></h3>
                <p><?php echo htmlspecialchars($f2_desc); ?></p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-headset"></i>
                </div>
                <h3><?php echo htmlspecialchars($f3_title); ?></h3>
                <p><?php echo htmlspecialchars($f3_desc); ?></p>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section class="pricing" id="pricing">
        <div class="section-title">
            Pilihan <span>Paket Internet</span>
        </div>
        <div class="pricing-grid">
            <?php if (empty($packages)): ?>
                <!-- Default Static Packages if DB Empty -->
                <div class="pricing-card">
                    <h3>Home Basic</h3>
                    <div class="price">150k<span>/bulan</span></div>
                    <ul class="package-features">
                        <li><i class="fas fa-check-circle"></i> Speed up to 10 Mbps</li>
                        <li><i class="fas fa-check-circle"></i> Unlimited Quota</li>
                        <li><i class="fas fa-check-circle"></i> Support 24 Jam</li>
                    </ul>
                    <a href="https://wa.me/?text=Halo%20saya%20tertarik%20paket%20Basic" class="btn btn-outline" style="width: 100%; justify-content: center;">Pilih Paket</a>
                </div>
                <div class="pricing-card popular">
                    <div class="popular-badge">Best Seller</div>
                    <h3>Home Super</h3>
                    <div class="price">250k<span>/bulan</span></div>
                    <ul class="package-features">
                        <li><i class="fas fa-check-circle"></i> Speed up to 20 Mbps</li>
                        <li><i class="fas fa-check-circle"></i> Unlimited Quota</li>
                        <li><i class="fas fa-check-circle"></i> Prioritas Support</li>
                    </ul>
                    <a href="https://wa.me/?text=Halo%20saya%20tertarik%20paket%20Super" class="btn btn-primary" style="width: 100%; justify-content: center;">Pilih Paket</a>
                </div>
            <?php else: ?>
                <!-- Dynamic Packages from DB -->
                <?php foreach($packages as $index => $pkg): ?>
                    <div class="pricing-card <?php echo $index == 1 ? 'popular' : ''; ?>">
                        <?php if($index == 1): ?><div class="popular-badge">Best Seller</div><?php endif; ?>
                        <h3><?php echo htmlspecialchars($pkg['name']); ?></h3>
                        <div class="price"><?php echo number_format($pkg['price'] / 1000, 0); ?>k<span>/bulan</span></div>
                        <ul class="package-features">
                            <li><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($pkg['description'] ?: 'Unlimited Access'); ?></li>
                            <li><i class="fas fa-check-circle"></i> Fiber Optic</li>
                            <li><i class="fas fa-check-circle"></i> Support 24/7</li>
                        </ul>
                        <a href="https://wa.me/?text=Halo%20saya%20tertarik%20paket%20<?php echo urlencode($pkg['name']); ?>" class="btn <?php echo $index == 1 ? 'btn-primary' : 'btn-outline'; ?>" style="width: 100%; justify-content: center;">Pilih Paket</a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <!-- Footer -->
    <footer id="contact">
        <div class="footer-content">
            <div class="footer-col">
                <a href="#" class="logo" style="margin-bottom: 25px;">
                    <i class="fas fa-bolt"></i> <?php echo $appName; ?>
                </a>
                <p style="color: var(--gray); line-height: 1.8; margin-bottom: 20px;">
                    <?php echo $footerAbout; ?>
                </p>
                <div class="social-links">
                    <?php if($s_fb !== '#'): ?><a href="<?php echo $s_fb; ?>"><i class="fab fa-facebook-f"></i> Facebook</a><?php endif; ?>
                    <?php if($s_ig !== '#'): ?><a href="<?php echo $s_ig; ?>"><i class="fab fa-instagram"></i> Instagram</a><?php endif; ?>
                    <?php if($s_tw !== '#'): ?><a href="<?php echo $s_tw; ?>"><i class="fab fa-twitter"></i> Twitter</a><?php endif; ?>
                    <?php if($s_yt !== '#'): ?><a href="<?php echo $s_yt; ?>"><i class="fab fa-youtube"></i> YouTube</a><?php endif; ?>
                </div>
            </div>
            
            <div class="footer-col">
                <h4>Navigasi</h4>
                <div class="footer-links">
                    <a href="#home"><i class="fas fa-chevron-right"></i> Beranda</a>
                    <a href="#services"><i class="fas fa-chevron-right"></i> Layanan</a>
                    <a href="#pricing"><i class="fas fa-chevron-right"></i> Paket Internet</a>
                    <a href="portal/login.php"><i class="fas fa-chevron-right"></i> Portal Pelanggan</a>
                </div>
            </div>
            
            <div class="footer-col">
                <h4>Hubungi Kami</h4>
                <div class="contact-item">
                    <i class="fas fa-phone-alt"></i>
                    <div>
                        <p>Telepon / WA</p>
                        <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $contactPhone); ?>" style="color: inherit; text-decoration: none;"><?php echo $contactPhone; ?></a>
                    </div>
                </div>
                <div class="contact-item">
                    <i class="fas fa-envelope"></i>
                    <div>
                        <p>Email Support</p>
                        <a href="mailto:<?php echo $contactEmail; ?>" style="color: inherit; text-decoration: none;"><?php echo $contactEmail; ?></a>
                    </div>
                </div>
                <div class="contact-item">
                    <i class="fas fa-map-marker-alt"></i>
                    <div>
                        <p>Alamat Kantor</p>
                        <span><?php echo $contactAddress; ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="copyright">
            &copy; <?php echo date('Y'); ?> <strong><?php echo $appName; ?></strong>. All rights reserved. 
            <br><span style="opacity: 0.5; font-size: 0.75rem; margin-top: 10px; display: inline-block;">Designed for speed and reliability.</span>
        </div>
    </footer>

    <script>
        function toggleMenu() {
            const navLinks = document.getElementById('navLinks');
            navLinks.classList.toggle('active');
        }

        // Smooth Scrolling
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });
    </script>
</body>
</html>
