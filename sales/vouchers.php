<?php
/**
 * Sales Voucher Module
 */

require_once '../includes/auth.php';
requireSalesLogin();

$pageTitle = 'Buat Voucher';

$salesId = $_SESSION['sales']['id'];
$salesUser = getSalesUser($salesId);

// Get Assigned Profiles
$profiles = fetchAll("SELECT * FROM sales_profile_prices WHERE sales_user_id = ? AND is_active = 1", [$salesId]);

// Handle Voucher Generation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        setFlash('error', 'Sesi tidak valid atau telah kadaluarsa. Silakan coba lagi.');
        redirect('vouchers.php');
    }

    $profileId = (int) $_POST['profile_id'];
    $qty = (int) $_POST['qty'];
    $prefix = sanitize($_POST['prefix'] ?? '');
    
    // Validate
    if ($qty < 1 || $qty > 50) {
        setFlash('error', 'Jumlah voucher harus antara 1 - 50.');
        redirect('vouchers.php');
    }
    
    // Get Profile Data
    $selectedProfile = fetchOne("SELECT * FROM sales_profile_prices WHERE id = ? AND sales_user_id = ? AND is_active = 1", [$profileId, $salesId]);
    
    if (!$selectedProfile) {
        setFlash('error', 'Profile tidak valid.');
        redirect('vouchers.php');
    }
    
    // Calculate Total Cost
    $totalCost = $selectedProfile['base_price'] * $qty;
    
    // Check Balance
    if ($salesUser['deposit_balance'] < $totalCost) {
        setFlash('error', 'Saldo deposit tidak mencukupi. Total: ' . formatCurrency($totalCost) . ', Saldo: ' . formatCurrency($salesUser['deposit_balance']));
        redirect('vouchers.php');
    }
    
    // Process Transaction
    $successCount = 0;
    $generatedVouchers = [];
    $pdo = getDB();
    
    try {
        $pdo->beginTransaction();
        
        // Deduct Balance
        $newBalance = $salesUser['deposit_balance'] - $totalCost;
        update('sales_users', ['deposit_balance' => $newBalance], 'id = ?', [$salesId]);
        
        // Record Transaction
        insert('sales_transactions', [
            'sales_user_id' => $salesId,
            'type' => 'voucher_sale',
            'amount' => -$totalCost,
            'description' => "Pembelian $qty Voucher {$selectedProfile['profile_name']}",
            'created_at' => date('Y-m-d H:i:s')
        ]);

        $profileValidity = '-';
        $hotspotProfiles = mikrotikGetHotspotProfiles();
        if (is_array($hotspotProfiles)) {
            foreach ($hotspotProfiles as $hp) {
                if (($hp['name'] ?? '') === ($selectedProfile['profile_name'] ?? '')) {
                    $parsedOnLogin = parseMikhmonOnLogin($hp['on-login'] ?? '');
                    $profileValidity = $parsedOnLogin['validity'] ?? '-';
                    break;
                }
            }
        }
        
        // Generate Vouchers
        for ($i = 0; $i < $qty; $i++) {
            // Use Sales User Voucher Settings
            $length = $salesUser['voucher_length'] ?: 6;
            $mode = $salesUser['voucher_mode'] ?: 'mix'; // mix, num, alp
            $type = $salesUser['voucher_type'] ?: 'upp'; // upp, up

            $charSet = 'alphanumeric'; // default
            if ($mode === 'num') $charSet = 'numeric';
            if ($mode === 'alp') $charSet = 'low'; // use lowercase for alpha

            $user = $prefix . generateRandomString($length, $charSet);
            
            if ($type === 'up') {
                $pass = generateRandomString($length, $charSet);
            } else {
                $pass = $user; // Default u=p
            }
            
            // Add to Mikrotik
            // Note: We need mikrotik_api.php functions. auth.php includes functions.php which includes mikrotik_api.php
            
            // Extra data for Mikrotik comment
            // Format: vc-namasales-tanggal (e.g., vc-jhon-26/02/26)
            $comment = "vc-" . strtolower($salesUser['username']) . "-" . date('d/m/y');
            
            if (mikrotikAddHotspotUser($user, $pass, $selectedProfile['profile_name'], ['comment' => $comment])) {
                // Record Sale
                recordHotspotSale(
                    $user, 
                    $selectedProfile['profile_name'], 
                    $selectedProfile['base_price'], 
                    $selectedProfile['selling_price'], 
                    $prefix, 
                    $salesId
                );
                
                $generatedVouchers[] = [
                    'username' => $user,
                    'password' => $pass,
                    'profile' => $selectedProfile['profile_name'],
                    'price' => formatCurrency($selectedProfile['selling_price']),
                    'validity' => $profileValidity
                ];
                $successCount++;
            }
        }
        
        if ($successCount > 0) {
            $pdo->commit();
            $_SESSION['generated_vouchers'] = $generatedVouchers;
            $_SESSION['generated_voucher_meta'] = [
                'sales_name' => $salesUser['name'] ?? '-',
                'sales_username' => $salesUser['username'] ?? '-',
                'sales_phone' => $salesUser['phone'] ?? '',
                'profile_name' => $selectedProfile['profile_name'] ?? '-',
                'qty' => $qty,
                'voucher_type' => $salesUser['voucher_type'] ?? 'upp',
                'validity' => $profileValidity,
                'total_cost' => (float) $totalCost,
                'remaining_balance' => (float) $newBalance,
                'generated_at' => date('Y-m-d H:i:s')
            ];
            
            // Send Notification (Optional)
            if (function_exists('sendWhatsApp')) {
                // Notif to Sales
                $message = "Voucher Created!\n\n";
                $message .= "Profile: {$selectedProfile['profile_name']}\n";
                $message .= "Qty: {$qty}\n";
                $message .= "Total Modal: " . formatCurrency($totalCost) . "\n";
                $message .= "Sisa Saldo: " . formatCurrency($newBalance) . "\n\n";
                $message .= "Terima kasih.";
                
                // Assuming sales user has phone number in sales_users table
                if (!empty($salesUser['phone'])) {
                    sendWhatsApp($salesUser['phone'], $message);
                }
            }

            setFlash('success', "Berhasil membuat $successCount voucher.");
            redirect('vouchers.php');
        } else {
            $pdo->rollBack();
            setFlash('error', "Gagal membuat voucher (Error Mikrotik). Saldo tidak terpotong.");
        }
        
    } catch (Exception $e) {
        $pdo->rollBack();
        logError("Sales Transaction Error: " . $e->getMessage());
        setFlash('error', "Terjadi kesalahan sistem.");
    }
    
    redirect('vouchers.php');
}

ob_start();
$showVoucherPopup = false;
$voucherMeta = [];
$hidePasswordInPopup = false;
?>

<div class="row" style="display: flex; flex-wrap: wrap; gap: 20px;">
    <!-- Form Card -->
    <div class="col-md-6" style="flex: 1; min-width: 300px;">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-plus-circle"></i> Buat Voucher Baru</h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <div class="form-group">
                        <label class="form-label">Pilih Paket / Profile</label>
                        <?php if (empty($profiles)): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i> Belum ada profile aktif. Hubungi admin untuk mengaktifkan profile sales.
                            </div>
                        <?php else: ?>
                            <div class="sales-profile-grid">
                                <?php foreach ($profiles as $idx => $p): ?>
                                    <label class="sales-profile-card">
                                        <input type="radio" name="profile_id" value="<?php echo (int) $p['id']; ?>" <?php echo $idx === 0 ? 'required' : ''; ?>>
                                        <div class="sales-profile-title"><?php echo htmlspecialchars($p['profile_name']); ?></div>
                                        <div class="sales-profile-modal">Modal: <?php echo formatCurrency($p['base_price']); ?></div>
                                        <div class="sales-profile-price">Jual: <?php echo formatCurrency($p['selling_price']); ?></div>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Jumlah (Qty)</label>
                        <input type="number" name="qty" class="form-control" value="1" min="1" max="50" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Prefix (Opsional)</label>
                        <input type="text" name="prefix" class="form-control" placeholder="Contoh: VC-">
                        <small style="color: var(--text-muted);">Awalan untuk username voucher</small>
                    </div>

                    <div class="alert alert-info" style="margin-top: 20px;">
                        <i class="fas fa-info-circle"></i> Saldo akan otomatis terpotong sesuai harga modal.
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px;">
                        <i class="fas fa-check"></i> Proses Transaksi
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Result Card -->
    <?php if (isset($_SESSION['generated_vouchers'])): 
        $vouchers = $_SESSION['generated_vouchers'];
        $voucherMeta = $_SESSION['generated_voucher_meta'] ?? [];
        unset($_SESSION['generated_vouchers']);
        unset($_SESSION['generated_voucher_meta']);
        $showVoucherPopup = !empty($vouchers);
        if (($voucherMeta['voucher_type'] ?? '') !== 'up') {
            $hidePasswordInPopup = true;
        } else {
            $hidePasswordInPopup = true;
            foreach ($vouchers as $v) {
                if (($v['password'] ?? '') !== ($v['username'] ?? '')) {
                    $hidePasswordInPopup = false;
                    break;
                }
            }
        }
    ?>
    <div class="col-md-6" style="flex: 1; min-width: 300px;">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-receipt"></i> Voucher Berhasil Dibuat</h3>
                <button onclick="printVoucherDetailed()" class="btn btn-sm btn-secondary" style="margin-left: auto;">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>
            <div class="card-body">
                <div id="voucher-list" style="max-height: 500px; overflow-y: auto;">
                    <?php foreach ($vouchers as $v): ?>
                    <div style="border: 1px dashed var(--border-color); padding: 15px; margin-bottom: 10px; border-radius: 8px; background: rgba(0,0,0,0.2);">
                        <div style="font-weight: bold; font-size: 1.2rem; color: var(--neon-cyan); text-align: center; letter-spacing: 2px;">
                            <?php echo $v['username']; ?>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-top: 5px; font-size: 0.9rem; color: var(--text-secondary);">
                            <span><?php echo $v['profile']; ?></span>
                            <span>Masa Aktif: <?php echo htmlspecialchars($v['validity'] ?? '-'); ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
.sales-profile-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
}

.sales-profile-card {
    position: relative;
    display: block;
    border: 1px solid var(--border-color);
    border-radius: 10px;
    padding: 12px;
    cursor: pointer;
    background: rgba(0, 0, 0, 0.2);
    transition: all 0.2s ease;
}

.sales-profile-card:hover {
    border-color: var(--neon-cyan);
    transform: translateY(-1px);
}

.sales-profile-card input {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.sales-profile-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 6px;
}

.sales-profile-modal {
    font-size: 0.82rem;
    color: var(--text-secondary);
    margin-bottom: 2px;
}

.sales-profile-price {
    font-size: 0.82rem;
    color: var(--neon-cyan);
    font-weight: 600;
}

.sales-profile-card.selected {
    border-color: var(--neon-cyan);
    box-shadow: 0 0 0 1px rgba(0, 245, 255, 0.35) inset;
}

.voucher-popup-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.7);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    padding: 14px;
}

.voucher-popup-card {
    width: 100%;
    max-width: 680px;
    max-height: 85vh;
    overflow: auto;
    border: 1px solid var(--border-color);
    border-radius: 12px;
    background: var(--bg-card);
    padding: 16px;
}

.voucher-popup-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 12px;
}

.voucher-popup-title {
    font-size: 1rem;
    font-weight: 700;
    color: var(--neon-cyan);
}

.voucher-popup-list {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
}

.voucher-popup-info {
    border: 1px solid var(--border-color);
    border-radius: 10px;
    padding: 10px;
    margin-bottom: 10px;
    background: rgba(0, 0, 0, 0.2);
}

.voucher-popup-info-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px 12px;
}

.voucher-popup-info-item {
    font-size: 0.82rem;
    color: var(--text-secondary);
}

.voucher-popup-info-item strong {
    color: var(--text-primary);
}

.voucher-popup-item {
    border: 1px dashed var(--border-color);
    border-radius: 8px;
    padding: 10px;
    background: rgba(0, 0, 0, 0.2);
}

.voucher-popup-user {
    font-size: 1rem;
    font-weight: 700;
    color: var(--neon-cyan);
    margin-bottom: 4px;
    word-break: break-all;
}

.voucher-popup-pass {
    font-size: 0.9rem;
    color: var(--text-primary);
    margin-bottom: 4px;
    word-break: break-all;
}

.voucher-popup-meta {
    font-size: 0.8rem;
    color: var(--text-secondary);
}

.voucher-popup-actions {
    margin-top: 12px;
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.voucher-wa-wrap {
    margin-top: 10px;
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.voucher-wa-input {
    flex: 1;
    min-width: 220px;
}

@media (max-width: 720px) {
    .sales-profile-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .sales-profile-card {
        padding: 10px;
    }

    .voucher-popup-list {
        grid-template-columns: 1fr;
    }

    .voucher-popup-info-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<?php if (!empty($vouchers ?? [])): ?>
<div id="voucherPopup" class="voucher-popup-overlay">
    <div class="voucher-popup-card">
        <div class="voucher-popup-header">
            <div class="voucher-popup-title">Kode Voucher Berhasil Dibuat</div>
            <button type="button" class="btn btn-sm btn-secondary" onclick="closeVoucherPopup()">
                <i class="fas fa-times"></i> Tutup
            </button>
        </div>
        <div class="voucher-popup-info">
            <div class="voucher-popup-info-grid">
                <div class="voucher-popup-info-item">Sales: <strong><?php echo htmlspecialchars($voucherMeta['sales_name'] ?? '-'); ?></strong></div>
                <div class="voucher-popup-info-item">Username Sales: <strong><?php echo htmlspecialchars($voucherMeta['sales_username'] ?? '-'); ?></strong></div>
                <div class="voucher-popup-info-item">No WA Sales: <strong><?php echo htmlspecialchars(($voucherMeta['sales_phone'] ?? '') !== '' ? $voucherMeta['sales_phone'] : '-'); ?></strong></div>
                <div class="voucher-popup-info-item">Profile: <strong><?php echo htmlspecialchars($voucherMeta['profile_name'] ?? '-'); ?></strong></div>
                <div class="voucher-popup-info-item">Masa Aktif: <strong><?php echo htmlspecialchars($voucherMeta['validity'] ?? '-'); ?></strong></div>
                <div class="voucher-popup-info-item">Qty: <strong><?php echo (int) ($voucherMeta['qty'] ?? 0); ?></strong></div>
                <div class="voucher-popup-info-item">Total Modal: <strong><?php echo formatCurrency($voucherMeta['total_cost'] ?? 0); ?></strong></div>
                <div class="voucher-popup-info-item">Sisa Saldo: <strong><?php echo formatCurrency($voucherMeta['remaining_balance'] ?? 0); ?></strong></div>
                <div class="voucher-popup-info-item">Waktu: <strong><?php echo htmlspecialchars($voucherMeta['generated_at'] ?? '-'); ?></strong></div>
            </div>
            <?php if ($hidePasswordInPopup): ?>
                <div style="font-size: 0.8rem; color: var(--neon-cyan); margin-top: 8px;">Format voucher U=P aktif, password mengikuti username.</div>
            <?php endif; ?>
        </div>
        <div class="voucher-popup-list" id="voucher-popup-list">
            <?php foreach ($vouchers as $v): ?>
            <div class="voucher-popup-item">
                <div class="voucher-popup-user"><?php echo htmlspecialchars($v['username']); ?></div>
                <?php if (!$hidePasswordInPopup): ?>
                    <div class="voucher-popup-pass">Password: <?php echo htmlspecialchars($v['password']); ?></div>
                <?php endif; ?>
                <div class="voucher-popup-meta"><?php echo htmlspecialchars($v['profile']); ?> • Masa Aktif: <?php echo htmlspecialchars($v['validity'] ?? '-'); ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="voucher-popup-actions">
            <button type="button" class="btn btn-success btn-sm" onclick="copyAllVouchers()">
                <i class="fas fa-copy"></i> Copy Semua Kode
            </button>
            <button type="button" class="btn btn-primary btn-sm" onclick="printVoucherDetailed()">
                <i class="fas fa-print"></i> Print dari Popup
            </button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="closeVoucherPopup()">
                <i class="fas fa-check"></i> Lanjut
            </button>
        </div>
        <div class="voucher-wa-wrap">
            <input type="text" id="buyerWhatsapp" class="form-control voucher-wa-input" placeholder="Nomor WA pembeli, contoh: 08123456789">
            <button type="button" class="btn btn-success btn-sm" onclick="sendVoucherToWhatsApp()">
                <i class="fab fa-whatsapp"></i> Kirim ke WhatsApp
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
document.querySelectorAll('.sales-profile-card input[name="profile_id"]').forEach(function (input) {
    input.addEventListener('change', function () {
        document.querySelectorAll('.sales-profile-card').forEach(function (card) {
            card.classList.remove('selected');
        });
        if (input.checked) {
            var card = input.closest('.sales-profile-card');
            if (card) {
                card.classList.add('selected');
            }
        }
    });
});
var firstSalesProfile = document.querySelector('.sales-profile-card input[name="profile_id"]');
if (firstSalesProfile) {
    firstSalesProfile.checked = true;
    firstSalesProfile.dispatchEvent(new Event('change'));
}

function closeVoucherPopup() {
    var popup = document.getElementById('voucherPopup');
    if (popup) {
        popup.style.display = 'none';
    }
}

<?php if ($showVoucherPopup): ?>
var voucherPopup = document.getElementById('voucherPopup');
if (voucherPopup) {
    voucherPopup.style.display = 'flex';
}
<?php endif; ?>

var popupVoucherData = <?php echo json_encode($vouchers ?? [], JSON_UNESCAPED_UNICODE); ?>;
var popupVoucherMeta = <?php echo json_encode($voucherMeta ?? [], JSON_UNESCAPED_UNICODE); ?>;
var popupHidePassword = <?php echo $hidePasswordInPopup ? 'true' : 'false'; ?>;

function normalizeWaNumber(phone) {
    var digits = String(phone || '').replace(/\D+/g, '');
    if (digits.startsWith('0')) {
        return '62' + digits.slice(1);
    }
    if (digits.startsWith('62')) {
        return digits;
    }
    return digits;
}

function buildVoucherText() {
    if (!Array.isArray(popupVoucherData) || popupVoucherData.length === 0) {
        return '';
    }
    var lines = [];
    lines.push('Voucher Hotspot');
    lines.push('');
    lines.push('Sales: ' + (popupVoucherMeta.sales_name || '-'));
    lines.push('Profile: ' + (popupVoucherMeta.profile_name || '-'));
    lines.push('Qty: ' + (popupVoucherMeta.qty || popupVoucherData.length));
    lines.push('Waktu: ' + (popupVoucherMeta.generated_at || '-'));
    lines.push('');
    popupVoucherData.forEach(function (v, idx) {
        lines.push((idx + 1) + '. Username: ' + (v.username || '-'));
        if (!popupHidePassword) {
            lines.push('   Password: ' + (v.password || '-'));
        }
    });
    if (popupHidePassword) {
        lines.push('');
        lines.push('Keterangan: Password mengikuti Username (format U=P).');
    }
    return lines.join('\n');
}

function copyAllVouchers() {
    var text = buildVoucherText();
    if (!text) {
        return;
    }
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function () {
            alert('Kode voucher berhasil disalin.');
        }).catch(function () {
            alert('Gagal menyalin kode voucher.');
        });
        return;
    }
    var area = document.createElement('textarea');
    area.value = text;
    document.body.appendChild(area);
    area.select();
    document.execCommand('copy');
    document.body.removeChild(area);
    alert('Kode voucher berhasil disalin.');
}

function sendVoucherToWhatsApp() {
    var input = document.getElementById('buyerWhatsapp');
    var normalized = normalizeWaNumber(input ? input.value : '');
    if (!normalized) {
        alert('Isi nomor WhatsApp pembeli terlebih dahulu.');
        return;
    }
    var text = buildVoucherText();
    if (!text) {
        alert('Data voucher belum tersedia.');
        return;
    }
    var url = 'https://wa.me/' + encodeURIComponent(normalized) + '?text=' + encodeURIComponent(text);
    window.open(url, '_blank', 'noopener');
}

function printVoucherDetailed() {
    if (!Array.isArray(popupVoucherData) || popupVoucherData.length === 0) {
        alert('Data voucher belum tersedia.');
        return;
    }
    var salesName = popupVoucherMeta.sales_name || '-';
    var salesUsername = popupVoucherMeta.sales_username || '-';
    var salesPhone = popupVoucherMeta.sales_phone || '-';
    var profileName = popupVoucherMeta.profile_name || '-';
    var validity = popupVoucherMeta.validity || '-';
    var qty = popupVoucherMeta.qty || popupVoucherData.length;
    var generatedAt = popupVoucherMeta.generated_at || '-';

    var listHtml = '';
    popupVoucherData.forEach(function (v, idx) {
        listHtml += "<div class='ticket-item'>";
        listHtml += "<div class='ticket-user'>#" + (idx + 1) + " " + (v.username || '-') + "</div>";
        if (!popupHidePassword) {
            listHtml += "<div class='ticket-pass'>Password: " + (v.password || '-') + "</div>";
        }
        listHtml += "<div class='ticket-validity'>Masa Aktif: " + (v.validity || validity || '-') + "</div>";
        listHtml += "</div>";
    });

    var printContents = "<style>"
        + "@page{size:58mm auto;margin:2mm;}"
        + "html,body{width:58mm;margin:0;padding:0;font-family:'Courier New',monospace;color:#000;background:#fff;}"
        + ".thermal-wrap{width:54mm;margin:0 auto;padding:1mm 0;font-size:11px;line-height:1.3;}"
        + ".thermal-title{text-align:center;font-size:13px;font-weight:700;margin:0 0 4px;}"
        + ".thermal-sep{border-top:1px dashed #000;margin:4px 0;}"
        + ".thermal-meta div{margin:1px 0;word-break:break-word;}"
        + ".ticket-item{border-top:1px dashed #000;padding-top:4px;margin-top:4px;}"
        + ".ticket-user{font-size:13px;font-weight:700;word-break:break-all;}"
        + ".ticket-pass{margin-top:2px;word-break:break-all;}"
        + ".ticket-validity{margin-top:2px;}"
        + ".thermal-footer{text-align:center;margin-top:6px;font-size:10px;}"
        + "</style>"
        + "<div class='thermal-wrap'>"
        + "<div class='thermal-title'>VOUCHER HOTSPOT</div>"
        + "<div class='thermal-sep'></div>"
        + "<div class='thermal-meta'>"
        + "<div>Sales : " + salesName + " (" + salesUsername + ")</div>"
        + "<div>No WA : " + salesPhone + "</div>"
        + "<div>Profile: " + profileName + "</div>"
        + "<div>Aktif  : " + validity + "</div>"
        + "<div>Qty    : " + qty + "</div>"
        + "<div>Waktu  : " + generatedAt + "</div>"
        + "</div>"
        + "<div class='thermal-sep'></div>"
        + listHtml
        + "<div class='thermal-footer'>Terima kasih</div>"
        + "</div>";

    var originalContents = document.body.innerHTML;
    document.body.innerHTML = printContents;
    window.print();
    document.body.innerHTML = originalContents;
    location.reload(); // Reload to restore event listeners
}
</script>

<?php
$content = ob_get_clean();
require_once '../includes/sales_layout.php';
?>
