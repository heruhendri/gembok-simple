# 🚀 GEMBOK - Modern ISP Management System

<p align="center">
  <img src="https://img.shields.io/badge/version-2.0.0-blue?style=for-the-badge" alt="Version">
  <img src="https://img.shields.io/badge/license-MIT-green?style=for-the-badge" alt="License">
  <img src="https://img.shields.io/badge/php-%3E%3D7.4-8892BF?style=for-the-badge&logo=php" alt="PHP">
  <img src="https://img.shields.io/badge/mysql-%3E%3D5.7-4479A1?style=for-the-badge&logo=mysql" alt="MySQL">
</p>

<p align="center">
  <strong>All-in-one solution for Internet Service Providers with seamless MikroTik and GenieACS integration</strong>
</p>

<p align="center">
  <img src="[https://raw.githubusercontent.com/alijayanet/gembok-simple/main/assets/demo-dashboard.png]" alt="Gembok Dashboard" width="100%">
</p>

---

## ✨ Features Overview

### 🛠️ Core Management
- **Customer Management** - Complete customer lifecycle
- **Package Management** - Flexible internet packages
- **Billing System** - Automated invoicing and payments
- **MikroTik Integration** - PPPoE and Hotspot management
- **GenieACS Integration** - ONU/ONT monitoring and control

### 📊 Advanced Features
- **Real-time Monitoring** - Live status and statistics
- **Interactive Map** - Location-based customer visualization
- **Trouble Ticket System** - Issue tracking and resolution
- **Voucher Generator** - MikroTik hotspot voucher creation
- **Mobile Responsive** - Works on all devices

### 🔗 Integrations
- **WhatsApp API** - Instant notifications
- **Tripay Payment** - Multiple payment gateways
- **Telegram Bot** - Automated alerts
- **GenieACS TR-069** - Device management

---

## 🚀 Quick Installation

### Prerequisites
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)
- MikroTik router (optional)
- GenieACS server (optional)

### Installation Steps

#### 1. Clone or Download
```bash
git clone https://github.com/alijayanet/gembok-simple.git
# Or download ZIP file from GitHub
```

#### 2. Upload to Server
Upload all files to your web directory (public_html/www)

#### 3. Run Web Installer
```bash
http://your-domain.com/install.php
```

#### 4. Follow Installation Wizard
1. **Server Check** - Verify requirements
2. **Database Setup** - Configure database connection
3. **Admin Account** - Create admin credentials
4. **MikroTik Config** - Connect to MikroTik (optional)
5. **Integrations** - Set up WhatsApp, Payment, etc. (optional)

#### 5. Complete Setup
- Access admin panel: `http://your-domain.com/admin/login`
- Access customer portal: `http://your-domain.com/portal/login`

---

## 🎨 Admin Dashboard Preview

<p align="center">
  <img src="https://raw.githubusercontent.com/alijayanet/gembok-simple/main/assets/admin-dashboard-preview.png" alt="Admin Dashboard" width="800">
</p>

### Dashboard Features:
- 📈 Real-time statistics and charts
- 👥 Customer status overview
- 💰 Revenue tracking
- 📋 Active invoices monitoring
- 🌐 MikroTik device status
- 📡 GenieACS ONU monitoring

---

## 🖥️ Customer Portal

<p align="center">
  <img src="https://raw.githubusercontent.com/alijayanet/gembok-simple/main/assets/customer-portal.png" alt="Customer Portal" width="800">
</p>

### Portal Features:
- 🔐 Login with phone number
- 📦 Package information
- 💳 Payment status & history
- 📶 ONU/ONT status and signal
- 🌐 WiFi SSID & Password management
- 🎫 Trouble ticket submission

---

## 🔧 Configuration

### Environment Variables
The system uses a simple configuration file located at `includes/config.php`:

```php
<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'gembok_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// MikroTik Configuration
define('MIKROTIK_HOST', '192.168.1.1');
define('MIKROTIK_USER', 'admin');
define('MIKROTIK_PASS', '');
define('MIKROTIK_PORT', 8728);

// Application Configuration
define('APP_NAME', 'GEMBOK');
define('APP_URL', 'http://localhost/gembok-simple');
define('APP_VERSION', '2.0.0');

// GenieACS Configuration
define('GENIEACS_URL', 'http://localhost:7557');
define('GENIEACS_USERNAME', '');
define('GENIEACS_PASSWORD', '');
?>
```

---

## 📊 API Endpoints

| Endpoint | Purpose | Methods |
|----------|---------|---------|
| `/api/dashboard.php` | Dashboard statistics | GET |
| `/api/customers.php` | Customer management | GET, POST, PUT, DELETE |
| `/api/invoices.php` | Invoice operations | GET, POST, PUT, DELETE |
| `/api/mikrotik.php` | MikroTik operations | GET, POST |
| `/api/genieacs.php` | GenieACS operations | GET, POST |
| `/api/onu_locations.php` | ONU location management | GET, POST |
| `/api/onu_wifi.php` | WiFi settings control | POST |
| `/api/portal_password.php` | Portal password management | POST |

---

## 🤖 Cron Jobs Setup

To enable automated features, set up cron jobs on your server:

### Linux/CPanel
```bash
# Run scheduler every 5 minutes
*/5 * * * * /usr/bin/php /path/to/your/gembok-simple/cron/scheduler.php
```

### Windows (Task Scheduler)
- Create scheduled task
- Run `php.exe` with path to `cron\scheduler.php`
- Schedule every 5 minutes

### Automated Tasks:
- 🔄 Auto invoice generation
- 🔒 Auto isolation for unpaid bills
- 📬 Payment reminder notifications
- 📊 Daily activity reports
- 💾 Automatic backups

---

## 🔐 Security Features

- 🔑 Strong password hashing (bcrypt)
- 🛡️ SQL injection prevention (prepared statements)
- 🚫 XSS protection (output encoding)
- 🏷️ CSRF tokens for forms
- 🕵️ Session management with timeout
- 📝 Activity logging
- 🔍 Input validation and sanitization

---

## 📱 Mobile Responsive

<p align="center">
  <img src="https://raw.githubusercontent.com/alijayanet/gembok-simple/main/assets/mobile-preview.png" alt="Mobile Responsive" width="300">
</p>

Fully responsive design works on:
- 📱 Smartphones (iOS & Android)
- 📱 Tablets
- 💻 Desktop computers
- 🖥️ Large monitors

---

## 📚 Database Schema

### Main Tables:
- `admin_users` - Administrator accounts
- `customers` - Customer information
- `packages` - Internet packages
- `invoices` - Billing records
- `onu_locations` - ONU location mapping
- `trouble_tickets` - Support tickets
- `cron_schedules` - Automated task scheduling
- `settings` - System configuration

---

## 🤝 Contributing

We welcome contributions! Here's how you can help:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

---

## 📞 Support & Contact

- 🐛 **Issues:** [GitHub Issues](https://github.com/alijayanet/gembok-simple/issues)
- 🌐 **Repository:** [GitHub](https://github.com/alijayanet/gembok-simple)
- 💬 **Contact:** alijayanet@gmail.com

### Professional Support
For professional installation, customization, or support services:
- 📞 WhatsApp: [Contact Developer](https://wa.me/6281947215703)
- 📧 Email: alijayanet@gmail.com

---

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

---

## 🙏 Acknowledgments

- Built with ❤️ for the ISP community
- Special thanks to the MikroTik and GenieACS communities
- Inspired by the need for accessible ISP management tools

---

## 📢 Note

This is the simplified version of GEMBOK - designed for easy installation and minimal dependencies while maintaining all essential features.

---

<p align="center">
  <strong>⭐ Star this repo if you find it helpful!</strong>
</p>

<p align="center">
  <sub>Built with passion for the ISP community | Made with ❤️ in Indonesia</sub>
</p>

---

## 📁 Struktur Folder (Sangat Sederhana)

```
gembok-simple/
├── admin/               # Halaman admin
│   ├── login.php
│   ├── dashboard.php
│   ├── customers.php
│   ├── packages.php
│   ├── invoices.php
│   ├── mikrotik.php
│   ├── genieacs.php
│   ├── map.php
│   ├── settings.php
│   ├── trouble.php
│   ├── voucher.php
│   ├── export.php
│   ├── import.php
│   ├── forgot_password.php
│   └── logout.php
├── portal/              # Halaman pelanggan
│   ├── login.php
│   ├── dashboard.php
│   └── logout.php
├── api/                 # API endpoints
│   ├── dashboard.php
│   ├── customers.php
│   ├── invoices.php
│   ├── mikrotik.php
│   ├── genieacs.php
│   ├── onu_locations.php
│   ├── onu_wifi.php
│   └── portal_password.php
├── install_steps/       # Installer wizard steps
│   ├── step1_server.php
│   ├── step2_database.php
│   ├── step3_admin.php
│   ├── step4_mikrotik.php
│   ├── step5_integrations.php
└── ...
TIDAK ADA:
❌ composer.json
❌ vendor/ folder
❌ .env file
❌ app/ folder
❌ migrations/
```

---

## ✅ Keunggulan Versi Simple vs Original

| Fitur | Original (CodeIgniter) | Simple (Native PHP) |
|-------|----------------------|---------------------|
| Dependencies | Composer + vendor | Tidak ada |
| Configuration | .env + Database | config.php (satu file) |
| Installation | Manual + CLI | Installer wizard |
| Framework | CodeIgniter 4 | Native PHP |
| Size | ~50MB | ~5MB |
| Setup Time | 30-60 menit | 5-10 menit |
| Features | Sama | Sama |
| Performance | Berat | Ringan |

---

## ✅ Perbaikan yang Sudah Dilakukan

### 🔴 Perbaikan Kritis (Sudah Diperbaiki)
1. **Tabel admin_users** - Ditambahkan di installer
2. **Folder logs/** - Dibuat otomatis di db.php
3. **File .htaccess** - Dibuat untuk security
4. **CSF Protection** - Ditambahkan di functions.php
5. **Session Timeout** - Ditambahkan di config.php
6. **Password Reset** - Fitur lupa password dibuat
7. **Cron Scheduler** - Script scheduler dibuat
8. **Error Handling** - Diperbaiki di semua API endpoints
9. **Export/Import** - Fitur export/import pelanggan dibuat

### � Perbaikan Tambahan
- Security headers di .htaccess
- Rate limiting untuk API
- Audit logging untuk semua actions
- Validasi yang lebih ketat
- Error handling yang konsisten

---

## 🎯 Cara Install (Detail)

### Step 1: Upload
1. Download file ZIP
2. Extract di komputer
3. Upload SEMUA file ke hosting (via File Manager atau FTP)
4. Pastikan semua file di folder public_html atau root folder

### Step 2: Buka Installer
Buka browser dan akses: `http://namadomain.com/install.php`

### Step 3: Ikuti Wizard

**Step 1: Server Check**
- Cek PHP version (min 7.4)
- Cek MySQL extension
- Cek CURL extension
- Cek JSON extension
- Cek Session support
- Cek File permissions

**Step 2: Database Setup**
- Host: localhost (default)
- Database Name: nama database yang sudah dibuat
- Username: username database
- Password: password database
- Klik "Test Connection" untuk cek

**Step 3: Admin Setup**
- Username: admin (default)
- Password: password admin
- Email: email admin (opsional)

**Step 4: MikroTik Setup (Opsional)**
- MikroTik IP Address
- Username MikroTik
- Password MikroTik
- API Port (default: 8728)

**Step 5: Integrations Setup (Opsional)**
- WhatsApp API URL & Token
- Tripay API Key, Private Key, Merchant Code
- Telegram Bot Token

**Step 6: Finish**
- Klik "Install Now"
- Tunggu proses selesai
- Klik "Go to Login"

### Step 4: Login
- Username: admin
- Password: password yang Anda set

---

## 🔧 Konfigurasi Manual (Opsional)

Jika ingin edit config manual, buka file `includes/config.php`:

```php
<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'gembok_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// MikroTik Configuration
define('MIKROTIK_HOST', '192.168.1.1');
define('MIKROTIK_USER', 'admin');
define('MIKROTIK_PASS', '');
define('MIKROTIK_PORT', 8728);

// Application Configuration
define('APP_NAME', 'GEMBOK');
define('APP_URL', 'http://localhost/gembok-simple');
define('APP_VERSION', '2.0.0');

// Security
define('ENCRYPTION_KEY', 'your-secret-key-here');

// WhatsApp Configuration
define('WHATSAPP_API_URL', '');
define('WHATSAPP_TOKEN', '');

// Tripay Configuration
define('TRIPAY_API_KEY', '');
define('TRIPAY_PRIVATE_KEY', '');
define('TRIPAY_MERCHANT_CODE', '');

// Telegram Configuration
define('TELEGRAM_BOT_TOKEN', '');

// GenieACS Configuration
define('GENIEACS_URL', 'http://localhost:7557');
define('GENIEACS_USERNAME', '');
define('GENIEACS_PASSWORD', '');
```

---

## 🎨 Fitur Tersedia

### Admin Panel
- ✅ Dashboard dengan statistik real-time
- ✅ Manajemen Pelanggan (tambah, edit, delete, isolir, peta lokasi)
- ✅ Manajemen Paket Internet
- ✅ Manajemen Invoice (generate, bayar, unisolate)
- ✅ MikroTik PPPoE & Hotspot Management
- ✅ Voucher Generator
- ✅ GenieACS Device Management
- ✅ Peta Lokasi ONU (Leaflet.js)
- ✅ Trouble Ticket Management
- ✅ Settings (Admin, MikroTik, Integrations)
- ✅ Cron Job Scheduler
- ✅ **Export/Import Pelanggan** - Fitur baru!

### Customer Portal
- ✅ Login dengan nomor HP
- ✅ Dashboard dengan info paket & status
- ✅ Status pembayaran & riwayat tagihan
- ✅ Informasi ONU (serial, model, status, signal)
- ✅ Pengaturan WiFi (SSID & Password) real-time
- ✅ Ganti password portal
- ✅ **Lupa Password** - Fitur baru!

### Integrasi
- ✅ MikroTik API (PPPoE & Hotspot)
- ✅ GenieACS (ONU/ONT monitoring & control)
- ✅ WhatsApp (Notifications)
- ✅ Tripay (Payment Gateway)
- ✅ Telegram (Bot notifications)

### API Endpoints
- ✅ `/api/dashboard.php` - Dashboard stats
- ✅ `/api/customers.php` - Customers list
- ✅ `/api/invoices.php` - Invoices list
- ✅ `/api/mikrotik.php` - MikroTik operations
- ✅ `/api/genieacs.php` - GenieACS operations
- ✅ `/api/onu_locations.php` - ONU locations
- ✅ `/api/onu_wifi.php` - WiFi settings
- ✅ `/api/portal_password.php` - Portal password

---

## 📊 Database Structure

### Tables
- `settings` - Konfigurasi sistem
- `admin_users` - User admin
- `packages` - Paket internet
- `customers` - Data pelanggan
- `invoices` - Tagihan/invoice
- `onu_locations` - Lokasi ONU
- `trouble_tickets` - Tiket gangguan
- `cron_schedules` - Jadwal cron job
- `cron_logs` - Log eksekusi cron
- `webhook_logs` - Log webhook

---

## 🔒 Security Features

- ✅ Password hashing (bcrypt)
- ✅ Input validation & sanitization
- ✅ SQL injection prevention (PDO prepared statements)
- ✅ XSS prevention (output escaping)
- ✅ CSRF protection
- ✅ Session timeout (1 hour)
- ✅ .htaccess security headers
- ✅ Error logging
- Webhook signature validation
- File upload validation
- Directory protection

---

## 📞 Support

- **WhatsApp:** 0819-4721-5703
- **GitHub:** https://github.com/alijayanet/gembok-php
- **Issues:** https://github.com/alijayanet/gembok-php/issues

---

## 🆚 Perbandingan: Original vs Simple

| Fitur | Original (CodeIgniter) | Simple (Native PHP) |
|-------|----------------------|---------------------|
| Dependencies | Composer + vendor | Tidak ada |
| Configuration | .env + Database | config.php (satu file) |
| Installation | Manual + CLI | Installer wizard |
| Framework | CodeIgniter 4 | Native PHP |
| Size | ~50MB | ~5MB |
| Setup Time | 30-60 menit | 5-10 menit |
| Features | Sama | Sama |
| Performance | Sedikit lebih berat | Lebih ringan |

---

## 💡 Tips & Tricks

### Upload via FTP
1. Gunakan FileZilla atau FTP client lainnya
2. Upload semua file ke public_html
3. Set permission folder: 755 (files), 777 (logs/)

### Upload via cPanel
1. Buka File Manager di cPanel
2. Upload ZIP file
3. Extract di public_html
4. Buka install.php

### Troubleshooting
1. Cek file `logs/error.log` jika ada error
2. Pastikan folder permissions: 755 (auto), 777 (logs/)
3. Cek PHP version minimal 7.4
4. Cek MySQL extension aktif
5. Pastikan database sudah dibuat di cPanel/phpMyAdmin

### Reinstall
Jika perlu reinstall:
1. Hapus file `includes/installed.lock`
2. Buka install.php lagi
3. Ikuti wizard lagi

---

## ⚙️ Setup Cron Job di Hosting

### Cara Setup Cron Job

**Penting:** Cron job diperlukan untuk:
- Auto-isolir pelanggan yang belum bayar
- Auto-send pengingat pembayaran
- Backup database otomatis

### 1. cPanel (paling umum)

**Langkah:**
1. Login ke cPanel
2. Cari menu **"Cron Jobs"**
3. Klik **"Add New Cron Job"**
4. Isi form:
   - **Command:** `/usr/bin/php /home/username/public_html/cron/scheduler.php`
   - **Interval:** `* * * * *` (setiap menit)
   - Atau lebih hemat: `*/5 * * *` (setiap 5 menit)
5. Klik **"Add New Cron Job"**

**Catatan:** Ganti `/home/username/public_html` dengan path yang sesuai dengan hosting Anda.

### 2. Plesk

**Langkah:**
1. Login ke Plesk
2. Cari menu **"Scheduled Tasks"**
3. Klik **"Add Task"**
4. Pilih **"Run a command"**
5. Isi form:
   - **Command:** `/usr/bin/php /var/www/vhosts/username/httpdocs/cron/scheduler.php`
   - **Run:** `*/5 * * * *` (setiap 5 menit)
6. Klik **"OK"**

### 3. DirectAdmin

**Langkah:**
1. Login ke DirectAdmin
2. Cari menu **"Cron Jobs"**
3. Klik **"Create Cron Job"**
4. Isi form:
   - **Command:** `/usr/bin/php /home/username/public_html/cron/scheduler.php`
   - **Minute:** `*/5`
   - **Hour:** `*`
   - **Day of Month:** `*`
   - **Month:** `*`
   - **Day of Week:** `*`
5. Klik **"Create"**

### 4. CyberPanel

**Langkah:**
1. Login ke CyberPanel
2. Cari menu **"Cron Jobs"**
3. Klik **"Add Cron Job"**
4. Isi form:
   - **Command:** `/usr/bin/php /home/yourdomain/public_html/cron/scheduler.php`
   - **Schedule:** `*/5 * * * *`
5. Klik **"Create"**

### 5. Shared Hosting Lainnya

Untuk hosting lain, cari menu seperti:
- **Cron Jobs**
- **Scheduled Tasks**
- **Task Scheduler**
- **Cron Manager**

**Command yang digunakan:**
```bash
/usr/bin/php /path/ke/gembok-simple/cron/scheduler.php
```

**Ganti `/path/ke/gembok-simple/` dengan path absolut ke folder aplikasi Anda.**

### 6. Cara Cek Path Absolut

**Cara cek path:**
1. Buat file `checkpath.php` di root folder:
```php
<?php
echo __DIR__;
?>
```

2. Upload ke hosting
3. Buka di browser: `http://namadomain.com/checkpath.php`
4. Hasilnya adalah path absolut yang benar

**Contoh output:**
```
/home/username/public_html/gembok-simple
```

### 7. Test Cron Job

**Cara test:**
1. Jalankan manual di terminal:
```bash
php /path/ke/cron/scheduler.php
```

2. Cek log di `cron/logs/`:
   - `activity.log` - Log aktivitas
   - `error.log` - Log error

3. Cek di halaman admin:
   - Dashboard → Cron Manager
   - Lihat log eksekusi

### 8. Cron Job yang Akan Dijalankan

**Scheduler akan menjalankan:**
1. **Auto-isolir** - Pelanggan yang belum bayar otomatis diisolir
2. **Backup database** - Backup database otomatis (setiap hari)
3. **Send reminders** - Kirim pengingat pembayaran (3 hari sebelum jatuh tempo)
4. **Custom scripts** - Script custom yang Anda tambahkan

**Waktu eksekusi:** Setiap 5 menit (hemat resource server)

### 9. Troubleshooting Cron Job

**Masalah umum:**
- **Cron job tidak jalan:**
  - Cek path absolut benar atau tidak
  - Pastikan permission file `scheduler.php` = 755
  - Cek error di `logs/error.log`
  
- **Cron job error:**
  - Cek log error di `logs/error.log`
  - Pastikan PHP CLI diaktifkan di hosting
  - Cek path ke file `includes/config.php`
  
- **Cron job jalan tapi tidak ada efek:**
  - Cek database connection
  - Cek MikroTik connection
  - Cek log aktivitas di `logs/activity.log`

### 10. Cron Job Manager di Admin Panel

**Cara setup:**
1. Login ke admin panel
2. Buka menu **"Cron Manager"**
3. Klik **"Add Schedule"**
4. Isi form:
   - **Name:** Auto-isolir
   - **Task Type:** auto_isolir
   - **Schedule Time:** 00:00
   - **Schedule Days:** daily
   - **Is Active:** Yes
5. Klik **"Save"**

**Cron job akan berjalan otomatis sesuai jadwal!**

---

## 🎉 Selesai!

Aplikasi GEMBOK Simple Version siap digunakan!

**Versi ini sangat cocok untuk:**
- Pemula yang baru belajar
- Hosting dengan resource terbatas
- Deployment cepat
- Maintenance mudah

**Semua fitur sama dengan versi original, tapi jauh lebih mudah diinstall!**

---

*Dokumentasi ini dibuat untuk versi 2.0.0 - Simple Version*
*Terakhir diperbarui: 2026-02-15*
