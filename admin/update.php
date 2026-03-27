<?php
require_once '../includes/auth.php';
requireAdminLogin();

$pageTitle = 'Update Aplikasi';

// Get local version primarily from version.txt
$localVersion = '1.0.0'; // Fallback
$localVersionFile = dirname(__DIR__) . '/version.txt';
if (file_exists($localVersionFile)) {
    $fileVersion = trim(file_get_contents($localVersionFile));
    if ($fileVersion !== '') {
        $localVersion = $fileVersion;
    }
} elseif (defined('APP_VERSION')) {
    $localVersion = APP_VERSION;
}

$remoteVersion = null;
$statusMessage = '';
$statusType = 'info';
$projectRoot = realpath(dirname(__DIR__));
$gitDir = $projectRoot ? $projectRoot . DIRECTORY_SEPARATOR . '.git' : '';
$isGitRepo = $gitDir !== '' && is_dir($gitDir);
$gitBranch = null;
$gitCommit = null;
$gitRemote = null;
if ($isGitRepo) {
    $tmp = [];
    $rv = 0;
    exec('cd ' . escapeshellarg($projectRoot) . ' && git rev-parse --abbrev-ref HEAD 2>&1', $tmp, $rv);
    if ($rv === 0 && !empty($tmp[0])) {
        $gitBranch = trim((string) $tmp[0]);
    }
    $tmp = [];
    $rv = 0;
    exec('cd ' . escapeshellarg($projectRoot) . ' && git rev-parse --short HEAD 2>&1', $tmp, $rv);
    if ($rv === 0 && !empty($tmp[0])) {
        $gitCommit = trim((string) $tmp[0]);
    }
    $tmp = [];
    $rv = 0;
    exec('cd ' . escapeshellarg($projectRoot) . ' && git remote get-url origin 2>&1', $tmp, $rv);
    if ($rv === 0 && !empty($tmp[0])) {
        $gitRemote = trim((string) $tmp[0]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken((string) $_POST['csrf_token'])) {
        $statusMessage = 'Sesi tidak valid atau telah kadaluarsa. Silakan refresh halaman dan coba lagi.';
        $statusType = 'error';
        $action = '';
    }
    
    if ($action === 'check') {
        $configuredUrl = defined('GEMBOK_UPDATE_VERSION_URL') ? (string) GEMBOK_UPDATE_VERSION_URL : '';
        $configuredUrl = trim($configuredUrl, " \t\n\r\0\x0B`'\"");
        $configuredUrl = str_replace('refs/heads/main', 'main', $configuredUrl);
        $fallbackUrls = [
            'https://raw.githubusercontent.com/alijayanet/gembok-simple/main/version.txt',
            'https://raw.githubusercontent.com/alijayanet/gembok-simple/refs/heads/main/version.txt'
        ];
        $urlsToTry = [];
        if ($configuredUrl !== '') {
            $urlsToTry[] = $configuredUrl;
        }
        foreach ($fallbackUrls as $url) {
            if (!in_array($url, $urlsToTry, true)) {
                $urlsToTry[] = $url;
            }
        }
        if (empty($urlsToTry)) {
            $statusMessage = 'URL versi update belum dikonfigurasi.';
            $statusType = 'error';
        } else {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'header' => "User-Agent: GEMBOK-Updater\r\n"
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false
                ]
            ]);
            $lastErrorMessage = 'Unknown';
            foreach ($urlsToTry as $url) {
                $remoteContent = @file_get_contents($url, false, $context);
                if ($remoteContent !== false) {
                    $remoteVersion = trim($remoteContent);
                    if ($remoteVersion === '') {
                        $lastErrorMessage = 'File versi kosong dari ' . $url;
                        continue;
                    }
                    if (version_compare($localVersion, $remoteVersion, '>=')) {
                        $statusMessage = 'Versi aplikasi sudah terbaru (' . htmlspecialchars($localVersion) . ').';
                        $statusType = 'success';
                    } else {
                        $statusMessage = 'Tersedia versi baru: <strong>' . htmlspecialchars($remoteVersion) . '</strong> (saat ini: ' . htmlspecialchars($localVersion) . ').';
                        $statusType = 'info';
                    }
                    break;
                }
                $error = error_get_last();
                $lastErrorMessage = $error['message'] ?? 'Unknown';
            }
            if ($statusMessage === '') {
                $statusMessage = 'Gagal mengambil versi dari server update. Error terakhir: ' . $lastErrorMessage;
                $statusType = 'error';
            }
        }
    } elseif ($action === 'update') {
        $output = [];
        $returnVar = 0;

        if (!$isGitRepo) {
            $output[] = 'Gagal update otomatis: folder aplikasi ini bukan repository Git (.git tidak ditemukan).';
            $output[] = 'Solusi: deploy ulang dari source Git atau update manual dengan upload file rilis terbaru.';
            $returnVar = 1;
        } else {
            $statusOut = [];
            $statusRv = 0;
            exec('cd ' . escapeshellarg($projectRoot) . ' && git status --porcelain 2>&1', $statusOut, $statusRv);
            if ($statusRv !== 0) {
                $output[] = 'Gagal cek status git.';
                $output = array_merge($output, $statusOut);
                $returnVar = 1;
            } elseif (!empty($statusOut)) {
                $output[] = 'Update dibatalkan karena ada perubahan lokal di server.';
                $output[] = 'Solusi: commit/stash dulu, atau deploy ulang dari Git.';
                $output = array_merge($output, $statusOut);
                $returnVar = 1;
            } else {
                exec('cd ' . escapeshellarg($projectRoot) . ' && git pull --ff-only 2>&1', $output, $returnVar);
            }
        }
        
        if ($returnVar === 0) {
            require_once '../includes/db.php';
            try {
                $pdo = getDB();
                
                // --- Sales Portal Migration ---
                
                // Add bill_discount to sales_users
                try {
                    $pdo->query("SELECT bill_discount FROM sales_users LIMIT 1");
                } catch (Exception $e) {
                    $pdo->exec("ALTER TABLE sales_users ADD COLUMN bill_discount DECIMAL(15,2) DEFAULT 2000 AFTER status");
                    $output[] = "Added column: bill_discount to sales_users";
                }
                
                // Fix sales_transactions type
                try {
                    $stmt = $pdo->query("SHOW COLUMNS FROM sales_transactions LIKE 'type'");
                    $col = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (strpos($col['Type'], 'enum') !== false) {
                        $pdo->exec("ALTER TABLE sales_transactions MODIFY type VARCHAR(50) NOT NULL");
                        $output[] = "Updated column type: sales_transactions.type to VARCHAR";
                    }
                } catch (Exception $e) {
                    // Table might not exist yet, create it
                    $pdo->exec("CREATE TABLE IF NOT EXISTS sales_transactions (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        sales_user_id INT NOT NULL,
                        type VARCHAR(50) NOT NULL,
                        amount DECIMAL(15,2) NOT NULL,
                        description TEXT,
                        related_username VARCHAR(100),
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        FOREIGN KEY (sales_user_id) REFERENCES sales_users(id) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                    $output[] = "Created table: sales_transactions";
                }
                
                // Ensure updated_at exists
                $tables = ['sales_transactions', 'hotspot_sales', 'sales_users'];
                foreach($tables as $tbl) {
                    try {
                        $pdo->query("SELECT updated_at FROM $tbl LIMIT 1");
                    } catch (Exception $e) {
                        $pdo->exec("ALTER TABLE $tbl ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
                        $output[] = "Added column: updated_at to $tbl";
                    }
                }
                
                // Ensure voucher columns exist on sales_users
                try {
                    $pdo->query("SELECT voucher_mode FROM sales_users LIMIT 1");
                } catch (Exception $e) {
                    $pdo->exec("ALTER TABLE sales_users ADD COLUMN voucher_mode VARCHAR(20) DEFAULT 'mix' AFTER status");
                    $output[] = "Added column: voucher_mode to sales_users";
                }
                try {
                    $pdo->query("SELECT voucher_length FROM sales_users LIMIT 1");
                } catch (Exception $e) {
                    $pdo->exec("ALTER TABLE sales_users ADD COLUMN voucher_length INT DEFAULT 6 AFTER voucher_mode");
                    $output[] = "Added column: voucher_length to sales_users";
                }
                try {
                    $pdo->query("SELECT voucher_type FROM sales_users LIMIT 1");
                } catch (Exception $e) {
                    $pdo->exec("ALTER TABLE sales_users ADD COLUMN voucher_type VARCHAR(20) DEFAULT 'upp' AFTER voucher_length");
                    $output[] = "Added column: voucher_type to sales_users";
                }
                
                try {
                    $pdo->query("SELECT id FROM site_settings LIMIT 1");
                } catch (Exception $e) {
                    $pdo->exec("CREATE TABLE IF NOT EXISTS site_settings (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        setting_key VARCHAR(50) UNIQUE NOT NULL,
                        setting_value TEXT,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                    $output[] = "Created table: site_settings";
                    
                    // Insert default data for site_settings
                    $siteSettings = [
                        ['hero_title', 'Internet Cepat <br>Tanpa Batas'],
                        ['hero_description', 'Nikmati koneksi internet fiber optic super cepat, stabil, dan unlimited untuk kebutuhan rumah maupun bisnis Anda. Gabung sekarang!'],
                        ['contact_phone', '+62 812-3456-7890'],
                        ['contact_email', 'info@gembok.net'],
                        ['contact_address', 'Jakarta, Indonesia'],
                        ['footer_about', 'Penyedia layanan internet terpercaya dengan jaringan fiber optic berkualitas untuk menunjang aktivitas digital Anda.']
                    ];
                    
                    foreach ($siteSettings as $ss) {
                        $stmt = $pdo->prepare("INSERT IGNORE INTO site_settings (setting_key, setting_value) VALUES (?, ?)");
                        $stmt->execute($ss);
                    }
                }

                try {
                    $pdo->query("SELECT id FROM hotspot_voucher_orders LIMIT 1");
                } catch (Exception $e) {
                    $pdo->exec("CREATE TABLE IF NOT EXISTS hotspot_voucher_orders (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        order_number VARCHAR(50) UNIQUE NOT NULL,
                        customer_name VARCHAR(100) NOT NULL,
                        customer_phone VARCHAR(20) NOT NULL,
                        profile_name VARCHAR(100) NOT NULL,
                        amount DECIMAL(15,2) NOT NULL,
                        payment_gateway VARCHAR(20) NOT NULL DEFAULT 'tripay',
                        payment_method VARCHAR(100) DEFAULT NULL,
                        payment_link TEXT,
                        payment_reference VARCHAR(100) DEFAULT NULL,
                        payment_payload LONGTEXT,
                        status ENUM('pending','paid','failed','expired') DEFAULT 'pending',
                        paid_at DATETIME DEFAULT NULL,
                        voucher_username VARCHAR(100) DEFAULT NULL,
                        voucher_password VARCHAR(100) DEFAULT NULL,
                        voucher_generated_at DATETIME DEFAULT NULL,
                        fulfillment_status ENUM('pending','success','failed') DEFAULT 'pending',
                        fulfillment_error TEXT,
                        whatsapp_status ENUM('pending','sent','failed') DEFAULT 'pending',
                        whatsapp_sent_at DATETIME DEFAULT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                    $output[] = "Created table: hotspot_voucher_orders";
                }

                $stmt = $pdo->prepare("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)");
                $stmt->execute(['PUBLIC_VOUCHER_PREFIX', 'VCH-']);
                $stmt->execute(['PUBLIC_VOUCHER_LENGTH', '6']);
                
                $output[] = "Database migration completed.";
                
            } catch (Exception $e) {
                $output[] = "Database migration failed: " . $e->getMessage();
            }
        }
        
        $statusMessage = implode("\n", $output);
        $statusType = $returnVar === 0 ? 'success' : 'error';
    }
}

ob_start();
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-sync-alt"></i> Update Aplikasi</h3>
    </div>
    <div class="card-body">
        <p>Versi Terpasang: <strong><?php echo htmlspecialchars($localVersion); ?></strong></p>
        <p style="color: var(--text-muted); margin-top: 6px;">
            Repo: <strong><?php echo $isGitRepo ? 'Git' : 'Non-Git'; ?></strong>
            <?php if ($isGitRepo): ?>
                <?php if ($gitBranch): ?> · Branch: <strong><?php echo htmlspecialchars($gitBranch); ?></strong><?php endif; ?>
                <?php if ($gitCommit): ?> · Commit: <strong><?php echo htmlspecialchars($gitCommit); ?></strong><?php endif; ?>
                <?php if ($gitRemote): ?> · Origin: <strong><?php echo htmlspecialchars($gitRemote); ?></strong><?php endif; ?>
            <?php endif; ?>
        </p>
        
        <?php if ($statusMessage): ?>
            <div class="alert alert-<?php echo $statusType === 'success' ? 'success' : ($statusType === 'error' ? 'error' : 'info'); ?>" style="white-space: pre-line;">
                <?php echo htmlspecialchars($statusMessage); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" style="margin-bottom: 15px;">
            <input type="hidden" name="action" value="check">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            <button type="submit" class="btn btn-secondary">
                <i class="fas fa-search"></i> Cek Versi di Server Update
            </button>
        </form>
        
        <form method="POST" onsubmit="return confirm('Jalankan git pull untuk update aplikasi?\nPastikan sudah backup terlebih dahulu.');">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-download"></i> Jalankan Update (git pull)
            </button>
        </form>
        
        <p style="margin-top: 15px; color: var(--text-muted); font-size: 0.9rem;">
            Catatan:
            <br>- Update akan menjalankan perintah <code>git pull</code> di folder aplikasi.
            <br>- Pastikan server memiliki akses git dan izin file yang benar.
            <br>- Untuk cek versi terbaru, aplikasi otomatis menggunakan <code>GEMBOK_UPDATE_VERSION_URL</code> dari config.php yang mengarah ke file <code>version.txt</code> di GitHub.
            <br>- Setelah instalasi awal, hapus file <code>install.sh</code> dari server jika pernah digunakan, agar tidak dijalankan ulang dan mengganggu data yang sudah ada.
        </p>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
