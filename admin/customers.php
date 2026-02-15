<?php
/**
 * Customers Management
 */

require_once '../includes/auth.php';
requireAdminLogin();

$pageTitle = 'Pelanggan';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $data = [
                    'name' => sanitize($_POST['name']),
                    'phone' => sanitize($_POST['phone']),
                    'pppoe_username' => sanitize($_POST['pppoe_username']),
                    'package_id' => (int)$_POST['package_id'],
                    'isolation_date' => (int)$_POST['isolation_date'],
                    'address' => sanitize($_POST['address']),
                    'lat' => $_POST['lat'] ?? null,
                    'lng' => $_POST['lng'] ?? null,
                    'portal_password' => password_hash('1234', PASSWORD_DEFAULT),
                    'created_at' => date('Y-m-d H:i:s')
                ];
                
                if (insert('customers', $data)) {
                    setFlash('success', 'Pelanggan berhasil ditambahkan');
                    logActivity('ADD_CUSTOMER', "Name: {$data['name']}");
                } else {
                    setFlash('error', 'Gagal menambahkan pelanggan');
                }
                redirect('customers.php');
                break;
                
            case 'edit':
                $customerId = (int)$_POST['customer_id'];
                $data = [
                    'name' => sanitize($_POST['name']),
                    'phone' => sanitize($_POST['phone']),
                    'package_id' => (int)$_POST['package_id'],
                    'isolation_date' => (int)$_POST['isolation_date'],
                    'address' => sanitize($_POST['address']),
                    'lat' => $_POST['lat'] ?? null,
                    'lng' => $_POST['lng'] ?? null,
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                
                if (update('customers', $data, 'id = ?', [$customerId])) {
                    setFlash('success', 'Pelanggan berhasil diperbarui');
                    logActivity('UPDATE_CUSTOMER', "ID: {$customerId}");
                } else {
                    setFlash('error', 'Gagal memperbarui pelanggan');
                }
                redirect('customers.php');
                break;
                
            case 'delete':
                $customerId = (int)$_POST['customer_id'];
                if (delete('customers', 'id = ?', [$customerId])) {
                    setFlash('success', 'Pelanggan berhasil dihapus');
                    logActivity('DELETE_CUSTOMER', "ID: {$customerId}");
                } else {
                    setFlash('error', 'Gagal menghapus pelanggan');
                }
                redirect('customers.php');
                break;
                
            case 'unisolate':
                $customerId = (int)$_POST['customer_id'];
                if (unisolateCustomer($customerId)) {
                    setFlash('success', 'Pelanggan berhasil di-unisolate');
                } else {
                    setFlash('error', 'Gagal meng-unisolate pelanggan');
                }
                redirect('customers.php');
                break;
        }
    }
}

// Get data with pagination
$page = (int)($_GET['page'] ?? 1);
$perPage = ITEMS_PER_PAGE;
$offset = ($page - 1) * $perPage;

$totalCustomers = fetchOne("SELECT COUNT(*) as total FROM customers")['total'] ?? 0;
$totalPages = ceil($totalCustomers / $perPage);

$customers = fetchAll("
    SELECT c.*, p.name as package_name, p.price as package_price 
    FROM customers c 
    LEFT JOIN packages p ON c.package_id = p.id 
    ORDER BY c.created_at DESC
    LIMIT {$perPage} OFFSET {$offset}
");

$packages = fetchAll("SELECT * FROM packages ORDER BY name");

ob_start();
?>

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns: repeat(4, 1fr); margin-bottom: 30px;">
    <div class="stat-card">
        <div class="stat-icon cyan">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo count($customers); ?></h3>
            <p>Total Pelanggan</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon green">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo count(array_filter($customers, fn($c) => $c['status'] === 'active')); ?></h3>
            <p>Aktif</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon orange">
            <i class="fas fa-ban"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo count(array_filter($customers, fn($c) => $c['status'] === 'isolated')); ?></h3>
            <p>Isolir</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon purple">
            <i class="fas fa-wallet"></i>
        </div>
        <div class="stat-info">
            <?php 
            $totalRevenue = 0;
            foreach ($customers as $c) {
                if ($c['status'] === 'active') {
                    $totalRevenue += $c['package_price'] ?? 0;
                }
            }
            ?>
            <h3><?php echo formatCurrency($totalRevenue); ?></h3>
            <p>Estimasi Pendapatan</p>
        </div>
    </div>
</div>

<!-- Add Customer Form -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-user-plus"></i> Tambah Pelanggan</h3>
    </div>
    
    <form method="POST">
        <input type="hidden" name="action" value="add">
        
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
            <div class="form-group">
                <label class="form-label">Nama Pelanggan</label>
                <input type="text" name="name" class="form-control" required placeholder="Nama Lengkap">
            </div>
            
            <div class="form-group">
                <label class="form-label">Nomor HP (WhatsApp)</label>
                <input type="text" name="phone" class="form-control" required placeholder="08xxxxxxxxxx">
            </div>
            
            <div class="form-group">
                <label class="form-label">Username PPPoE</label>
                <div style="display: flex; gap: 10px;">
                    <input type="text" name="pppoe_username" id="pppoe_username_input" class="form-control" required placeholder="Pilih atau ketik username" style="flex: 1;">
                    <button type="button" class="btn btn-secondary" onclick="openPppoeUserModal()">Pilih dari MikroTik</button>
                </div>
                <small style="color: var(--text-muted);">Pilih username PPPoE dari user MikroTik untuk menghindari salah input</small>
            </div>
            
            <div class="form-group">
                <label class="form-label">Paket Langganan</label>
                <select name="package_id" class="form-control" required style="color: var(--text-primary); background: var(--bg-card);">
                    <option value="">Pilih Paket</option>
                    <?php foreach ($packages as $pkg): ?>
                        <option value="<?php echo $pkg['id']; ?>">
                            <?php echo htmlspecialchars($pkg['name']); ?> (<?php echo formatCurrency($pkg['price']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Tanggal Isolir (1-28)</label>
                <input type="number" name="isolation_date" class="form-control" value="20" min="1" max="28" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Alamat</label>
                <textarea name="address" class="form-control" rows="2" placeholder="Alamat rumah"></textarea>
            </div>
        </div>
        
        <div class="form-group">
            <label class="form-label">Lokasi (Latitude, Longitude)</label>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                <input type="text" name="lat" class="form-control" placeholder="Latitude" readonly>
                <input type="text" name="lng" class="form-control" placeholder="Longitude" readonly>
            </div>
            <small style="color: var(--text-muted);">Klik pada peta untuk set lokasi</small>
        </div>
        
        <div style="height: 300px; margin-top: 15px; border-radius: 8px; overflow: hidden;" id="map-picker"></div>
        
        <button type="submit" class="btn btn-primary" style="margin-top: 20px;">
            <i class="fas fa-save"></i> Simpan Pelanggan
        </button>
    </form>
</div>

<!-- Customers Table -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-users"></i> Daftar Pelanggan</h3>
        <div style="display: flex; gap: 10px;">
            <input type="text" id="searchCustomer" class="form-control" placeholder="Cari pelanggan..." style="width: 250px;">
            <a href="export.php" class="btn btn-primary btn-sm">
                <i class="fas fa-file-excel"></i> Export/Import
            </a>
        </div>
    </div>
    
    <table class="data-table" id="customerTable">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nama & Kontak</th>
                <th>Paket & Tagihan</th>
                <th>Status</th>
                <th>PPPoE</th>
                <th>Tgl Isolir</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($customers)): ?>
                <tr>
                    <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 30px;" data-label="Data">
                        Belum ada data pelanggan
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($customers as $c): ?>
                <tr>
                    <td data-label="ID">#<?php echo $c['id']; ?></td>
                    <td data-label="Nama & Kontak">
                        <strong><?php echo htmlspecialchars($c['name']); ?></strong><br>
                        <small><i class="fab fa-whatsapp"></i> <?php echo htmlspecialchars($c['phone']); ?></small>
                    </td>
                    <td data-label="Paket & Tagihan">
                        <?php echo htmlspecialchars($c['package_name'] ?? 'Tanpa Paket'); ?><br>
                        <small style="color: var(--neon-green);">
                            <?php echo formatCurrency($c['package_price'] ?? 0); ?>
                        </small>
                    </td>
                    <td data-label="Status">
                        <?php if ($c['status'] === 'active'): ?>
                            <span class="badge badge-success">Aktif</span>
                        <?php else: ?>
                            <span class="badge badge-warning">Isolir</span>
                        <?php endif; ?>
                    </td>
                    <td data-label="PPPoE">
                        <code style="background: rgba(255,255,255,0.1); padding: 2px 4px; border-radius: 4px;">
                            <?php echo htmlspecialchars($c['pppoe_username']); ?>
                        </code>
                    </td>
                    <td data-label="Tgl Isolir">
                        <span class="badge badge-info">Tgl <?php echo $c['isolation_date']; ?></span>
                    </td>
                    <td data-label="Aksi">
                        <button class="btn btn-secondary btn-sm" onclick="editCustomer(<?php echo $c['id']; ?>)">
                            <i class="fas fa-edit"></i>
                        </button>
                        <?php if ($c['status'] === 'isolated'): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="unisolate">
                                <input type="hidden" name="customer_id" value="<?php echo $c['id']; ?>">
                                <button type="submit" class="btn btn-success btn-sm" title="Buka Isolir">
                                    <i class="fas fa-unlock"></i>
                                </button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    
    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div style="display: flex; justify-content: center; align-items: center; gap: 10px; margin-top: 20px;">
        <a href="?page=1" class="btn btn-secondary btn-sm" <?php echo $page === 1 ? 'disabled style="opacity: 0.5;"' : ''; ?>>
            <i class="fas fa-angle-double-left"></i>
        </a>
        <a href="?page=<?php echo max(1, $page - 1); ?>" class="btn btn-secondary btn-sm" <?php echo $page === 1 ? 'disabled style="opacity: 0.5;"' : ''; ?>>
            <i class="fas fa-angle-left"></i>
        </a>
        
        <span style="color: var(--text-secondary);">
            Halaman <?php echo $page; ?> dari <?php echo $totalPages; ?>
            (Total: <?php echo $totalCustomers; ?> pelanggan)
        </span>
        
        <a href="?page=<?php echo min($totalPages, $page + 1); ?>" class="btn btn-secondary btn-sm" <?php echo $page === $totalPages ? 'disabled style="opacity: 0.5;"' : ''; ?>>
            <i class="fas fa-angle-right"></i>
        </a>
        <a href="?page=<?php echo $totalPages; ?>" class="btn btn-secondary btn-sm" <?php echo $page === $totalPages ? 'disabled style="opacity: 0.5;"' : ''; ?>>
            <i class="fas fa-angle-double-right"></i>
        </a>
    </div>
    <?php endif; ?>
</div>
        
<!-- PPPoE User Modal -->
<div id="pppoeUserModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); z-index: 2000; align-items: center; justify-content: center;">
    <div class="card" style="width: 700px; max-width: 90%; margin: 2rem; max-height: 80vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <h3 style="margin: 0; color: var(--neon-cyan);">
                <i class="fas fa-network-wired"></i> Pilih Username PPPoE
            </h3>
            <button type="button" onclick="closePppoeUserModal()" style="background: none; border: none; color: var(--text-secondary); cursor: pointer; font-size: 1.25rem;">&times;</button>
        </div>
        <div class="form-group" style="margin-bottom: 15px;">
            <input type="text" id="pppoeUserSearch" class="form-control" placeholder="Cari username PPPoE...">
        </div>
        <div id="pppoeUserList" style="max-height: 60vh; overflow-y: auto;"></div>
    </div>
</div>
        
<!-- Edit Customer Modal -->
<div id="editCustomerModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); z-index: 2000; align-items: center; justify-content: center;">
    <div class="card" style="width: 800px; max-width: 90%; margin: 2rem; max-height: 90vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin: 0; color: var(--neon-cyan);">
                <i class="fas fa-edit"></i> Edit Pelanggan
            </h3>
            <button onclick="closeEditModal()" style="background: none; border: none; color: var(--text-secondary); cursor: pointer; font-size: 1.25rem;">&times;</button>
        </div>
        
        <form method="POST" id="editCustomerForm">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="customer_id" id="edit_customer_id">
            
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
                <div class="form-group">
                    <label class="form-label">Nama Pelanggan</label>
                    <input type="text" name="name" id="edit_name" class="form-control" required placeholder="Nama Lengkap">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Nomor HP (WhatsApp)</label>
                    <input type="text" name="phone" id="edit_phone" class="form-control" required placeholder="08xxxxxxxxxx">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Username PPPoE</label>
                    <input type="text" name="pppoe_username" id="edit_pppoe_username" class="form-control" required placeholder="Username di MikroTik" readonly style="background: rgba(255,255,255,0.05); cursor: not-allowed;">
                    <small style="color: var(--text-muted);">Username PPPoE tidak dapat diubah</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Paket Langganan</label>
                    <select name="package_id" id="edit_package_id" class="form-control" required style="color: var(--text-primary); background: var(--bg-card);">
                        <option value="">Pilih Paket</option>
                        <?php foreach ($packages as $pkg): ?>
                            <option value="<?php echo $pkg['id']; ?>">
                                <?php echo htmlspecialchars($pkg['name']); ?> (<?php echo formatCurrency($pkg['price']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Tanggal Isolir (1-28)</label>
                    <input type="number" name="isolation_date" id="edit_isolation_date" class="form-control" min="1" max="28" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Alamat</label>
                    <textarea name="address" id="edit_address" class="form-control" rows="2" placeholder="Alamat rumah"></textarea>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Lokasi (Latitude, Longitude)</label>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <input type="text" name="lat" id="edit_lat" class="form-control" placeholder="Latitude" readonly>
                    <input type="text" name="lng" id="edit_lng" class="form-control" placeholder="Longitude" readonly>
                </div>
                <small style="color: var(--text-muted);">Klik pada peta untuk set lokasi</small>
            </div>
            
            <div style="height: 300px; margin-top: 15px; border-radius: 8px; overflow: hidden;" id="edit-map-picker"></div>
            
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" class="btn btn-primary" style="flex: 1;">
                    <i class="fas fa-save"></i> Simpan Perubahan
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()" style="flex: 1;">
                    Batal
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<script>
let map, marker;
let editMap, editMarker;
let pppoeUsers = [];

function openPppoeUserModal() {
    const modal = document.getElementById('pppoeUserModal');
    if (!modal) {
        return;
    }
    modal.style.display = 'flex';
    
    const list = document.getElementById('pppoeUserList');
    if (list) {
        list.innerHTML = '<div style="padding: 10px; color: var(--text-secondary);">Memuat data dari MikroTik...</div>';
    }
    
    fetch('../api/mikrotik.php?action=users')
        .then(response => response.text())
        .then(text => {
            let data = null;
            try {
                const start = text.indexOf('{');
                if (start !== -1) {
                    data = JSON.parse(text.slice(start));
                }
            } catch (e) {
                console.error('Respon MikroTik tidak valid:', text, e);
            }
            
            if (data && data.success && data.data && Array.isArray(data.data.users)) {
                pppoeUsers = data.data.users;
                renderPppoeUserList(pppoeUsers);
            } else if (list) {
                list.innerHTML = '<div style="padding: 10px; color: var(--text-secondary);">Gagal mengambil data dari MikroTik</div>';
            }
        })
        .catch(error => {
            console.error('Fetch MikroTik error:', error);
            if (list) {
                list.innerHTML = '<div style="padding: 10px; color: var(--text-secondary);">Gagal mengambil data dari MikroTik</div>';
            }
        });
}

function renderPppoeUserList(users) {
    const list = document.getElementById('pppoeUserList');
    if (!list) {
        return;
    }
    
    if (!users || users.length === 0) {
        list.innerHTML = '<div style="padding: 10px; color: var(--text-secondary);">Tidak ada user PPPoE ditemukan</div>';
        return;
    }
    
    list.innerHTML = '';
    
    users.forEach(user => {
        const username = user.name || user['name'];
        if (!username) {
            return;
        }
        
        const item = document.createElement('button');
        item.type = 'button';
        item.className = 'btn btn-secondary';
        item.style.display = 'block';
        item.style.width = '100%';
        item.style.textAlign = 'left';
        item.style.marginBottom = '8px';
        item.textContent = username;
        item.onclick = function() {
            const input = document.getElementById('pppoe_username_input') || document.querySelector('input[name="pppoe_username"]');
            if (input) {
                input.value = username;
            }
            closePppoeUserModal();
        };
        
        list.appendChild(item);
    });
}

function closePppoeUserModal() {
    const modal = document.getElementById('pppoeUserModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('pppoeUserSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            const term = e.target.value.toLowerCase();
            const filtered = (pppoeUsers || []).filter(user => {
                const username = user.name || user['name'] || '';
                return username.toLowerCase().includes(term);
            });
            renderPppoeUserList(filtered);
        });
    }
    
    const modal = document.getElementById('pppoeUserModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closePppoeUserModal();
            }
        });
    }
});

function initMap() {
    // Add map
    map = L.map('map-picker').setView([-6.200000, 106.816666], 13);
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap'
    }).addTo(map);
    
    map.on('click', function(e) {
        if (marker) {
            map.removeLayer(marker);
        }
        
        marker = L.marker(e.latlng).addTo(map);
        
        document.querySelector('input[name="lat"]').value = e.latlng.lat.toFixed(6);
        document.querySelector('input[name="lng"]').value = e.latlng.lng.toFixed(6);
    });
}

function initEditMap() {
    if (editMap) return;
    
    editMap = L.map('edit-map-picker').setView([-6.200000, 106.816666], 13);
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap'
    }).addTo(editMap);
    
    editMap.on('click', function(e) {
        if (editMarker) {
            editMap.removeLayer(editMarker);
        }
        
        editMarker = L.marker(e.latlng).addTo(editMap);
        
        document.getElementById('edit_lat').value = e.latlng.lat.toFixed(6);
        document.getElementById('edit_lng').value = e.latlng.lng.toFixed(6);
    });
}

// Search functionality
document.getElementById('searchCustomer').addEventListener('input', function(e) {
    const search = e.target.value.toLowerCase();
    const rows = document.querySelectorAll('#customerTable tbody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(search) ? '' : 'none';
    });
});

// Edit customer
function editCustomer(id) {
    // Show loading or something if needed
    fetch(`../api/customers.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const customer = data.data;
                
                document.getElementById('edit_customer_id').value = customer.id;
                document.getElementById('edit_name').value = customer.name;
                document.getElementById('edit_phone').value = customer.phone;
                document.getElementById('edit_pppoe_username').value = customer.pppoe_username;
                document.getElementById('edit_package_id').value = customer.package_id;
                document.getElementById('edit_isolation_date').value = customer.isolation_date;
                document.getElementById('edit_address').value = customer.address || '';
                document.getElementById('edit_lat').value = customer.lat || '';
                document.getElementById('edit_lng').value = customer.lng || '';
                
                // Show modal
                document.getElementById('editCustomerModal').style.display = 'flex';
                
                // Initialize map if needed and set view
                setTimeout(() => {
                    initEditMap();
                    editMap.invalidateSize();
                    
                    if (customer.lat && customer.lng) {
                        const latlng = [customer.lat, customer.lng];
                        editMap.setView(latlng, 15);
                        
                        if (editMarker) editMap.removeLayer(editMarker);
                        editMarker = L.marker(latlng).addTo(editMap);
                    }
                }, 100);
            } else {
                alert('Gagal mengambil data pelanggan: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Terjadi kesalahan saat mengambil data pelanggan');
        });
}

function closeEditModal() {
    document.getElementById('editCustomerModal').style.display = 'none';
}

// Close modal when clicking outside
document.getElementById('editCustomerModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeEditModal();
    }
});

// Initialize map when page loads
setTimeout(initMap, 500);
</script>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
