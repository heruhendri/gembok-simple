<?php
require_once '../includes/auth.php';
requireAdminLogin();

$pageTitle = 'Update Aplikasi';

$currentVersion = APP_VERSION;
$localVersion = $currentVersion;
$localVersionFile = dirname(__DIR__) . '/version.txt';
if (file_exists($localVersionFile)) {
    $fileVersion = trim(file_get_contents($localVersionFile));
    if ($fileVersion !== '') {
        $localVersion = $fileVersion;
    }
}

$remoteVersion = null;
$statusMessage = '';
$statusType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'check') {
        $remoteUrl = defined('GEMBOK_UPDATE_VERSION_URL') ? GEMBOK_UPDATE_VERSION_URL : '';
        if ($remoteUrl === '') {
            $statusMessage = 'URL versi update belum dikonfigurasi.';
            $statusType = 'error';
        } else {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10
                ]
            ]);
            $remoteContent = @file_get_contents($remoteUrl, false, $context);
            if ($remoteContent === false) {
                $statusMessage = 'Gagal mengambil versi dari server update.';
                $statusType = 'error';
            } else {
                $remoteVersion = trim($remoteContent);
                if ($remoteVersion === '') {
                    $statusMessage = 'File versi di server update kosong.';
                    $statusType = 'error';
                } else {
                    if ($remoteVersion === $localVersion) {
                        $statusMessage = 'Versi aplikasi sudah terbaru (' . htmlspecialchars($localVersion) . ').';
                        $statusType = 'success';
                    } else {
                        $statusMessage = 'Tersedia versi baru: ' . htmlspecialchars($remoteVersion) . ' (saat ini: ' . htmlspecialchars($localVersion) . ').';
                        $statusType = 'info';
                    }
                }
            }
        }
    } elseif ($action === 'update') {
        $output = [];
        $returnVar = 0;
        $projectRoot = realpath(dirname(__DIR__));
        $cmd = 'cd ' . escapeshellarg($projectRoot) . ' && git pull 2>&1';
        exec($cmd, $output, $returnVar);
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
        <p>APP_VERSION (config): <strong><?php echo htmlspecialchars($currentVersion); ?></strong></p>
        <p>Version.txt (local): <strong><?php echo htmlspecialchars($localVersion); ?></strong></p>
        
        <?php if ($statusMessage): ?>
            <div class="alert alert-<?php echo $statusType === 'success' ? 'success' : ($statusType === 'error' ? 'error' : 'info'); ?>" style="white-space: pre-line;">
                <?php echo htmlspecialchars($statusMessage); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" style="margin-bottom: 15px;">
            <input type="hidden" name="action" value="check">
            <button type="submit" class="btn btn-secondary">
                <i class="fas fa-search"></i> Cek Versi di Server Update
            </button>
        </form>
        
        <form method="POST" onsubmit="return confirm('Jalankan git pull untuk update aplikasi?\nPastikan sudah backup terlebih dahulu.');">
            <input type="hidden" name="action" value="update">
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
