<?php
/**
 * Customer Portal Login
 */

require_once '../includes/auth.php';

// Check if already logged in
if (isCustomerLoggedIn()) {
    redirect('dashboard.php');
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        setFlash('error', 'Sesi tidak valid atau telah kadaluarsa. Silakan coba lagi.');
        redirect('login.php');
    }

    $phone = $_POST['phone'] ?? '';
    $password = $_POST['password'] ?? '';

    if (customerLogin($phone, $password)) {
        setFlash('success', 'Login berhasil! Selamat datang.');
        redirect('dashboard.php');
    } else {
        setFlash('error', 'Nomor HP atau password salah!');
        redirect('login.php');
    }
}

$pageTitle = 'Login Pelanggan';
$content = '';

ob_start();
?>

<div
    style="min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; background: radial-gradient(circle at center, #1a1a2e 0%, #0a0a12 100%);">
    <div
        style="background: #1a1a2e; border: 1px solid #2a2a40; border-radius: 16px; padding: 40px; width: 100%; max-width: 400px; box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3); position: relative; overflow: hidden;">
        <div style="text-align: center; margin-bottom: 30px; position: relative; z-index: 1;">
            <i class="fas fa-network-wired"
                style="font-size: 3rem; background: linear-gradient(135deg, #00f5ff 0%, #bf00ff 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; margin-bottom: 15px; display: inline-block;"></i>
            <h1
                style="font-size: 1.8rem; font-weight: 700; margin-bottom: 5px; background: linear-gradient(135deg, #00f5ff 0%, #bf00ff 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">
                GEMBOK</h1>
            <p style="color: #b0b0c0;">Portal Pelanggan</p>
        </div>

        <?php if (hasFlash('error')): ?>
            <div class="alert alert-error"
                style="margin-bottom: 20px; background: rgba(255, 71, 87, 0.2); border: 1px solid #ff4757; color: #ff4757; padding: 15px; border-radius: 8px; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars(getFlash('error')); ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            <div class="form-group" style="margin-bottom: 20px;">
                <label class="form-label"
                    style="display: block; margin-bottom: 8px; font-weight: 600; color: #ffffff;">Nomor HP</label>
                <input type="text" name="phone" class="form-control" placeholder="08xxxxxxxxxx" required autofocus
                    style="width: 100%; padding: 12px; background: #161628; border: 1px solid #2a2a40; border-radius: 8px; color: #ffffff; font-size: 1rem;">
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label class="form-label"
                    style="display: block; margin-bottom: 8px; font-weight: 600; color: #ffffff;">Password</label>
                <input type="password" name="password" class="form-control" placeholder="Masukkan password" required
                    style="width: 100%; padding: 12px; background: #161628; border: 1px solid #2a2a40; border-radius: 8px; color: #ffffff; font-size: 1rem;">
            </div>

            <button type="submit" class="btn btn-primary"
                style="width: 100%; padding: 12px 20px; border: none; border-radius: 12px; font-size: 1rem; font-weight: 600; cursor: pointer; color: #ffffff; background: linear-gradient(135deg, #00f5ff 0%, #bf00ff 100%); transition: all 0.3s; box-shadow: 0 4px 20px rgba(191, 0, 255, 0.3); border: 1px solid transparent;">
                <i class="fas fa-sign-in-alt"></i> Login
            </button>
        </form>

        <div
            style="margin-top: 20px; padding: 15px; background: rgba(0, 245, 255, 0.1); border: 1px solid var(--neon-cyan); border-radius: 8px; color: #00f5ff; font-size: 0.9rem; text-align: center;">
            <p style="margin: 0; font-size: 0.85rem;">
                <!-- Removed default password display for security --><small>Hubungi admin jika lupa password</small>
            </p>
        </div>

        <div
            style="text-align: center; margin-top: 20px; color: #666680; font-size: 0.9rem; position: relative; z-index: 1;">
            <p>Belum punya akun? Hubungi admin.</p>
            <a href="../index.php"
                style="color: #00f5ff; text-decoration: none; display: inline-block; margin-top: 10px;">← Kembali ke
                Beranda</a>
        </div>
    </div>
</div>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    @media (max-width: 480px) {
        div[style*="min-height: 100vh"] {
            padding: 10px;
        }

        div[style*="max-width: 400px"] {
            padding: 25px;
            margin: 10px;
        }

        h1[style*="font-size: 1.8rem"] {
            font-size: 1.5rem !important;
        }

        .form-group {
            margin-bottom: 15px !important;
        }

        input.form-control {
            padding: 10px !important;
            font-size: 0.9rem !important;
        }
    }
</style>

<?php
$content = ob_get_clean();
echo $content;
