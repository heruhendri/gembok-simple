<?php
/**
 * Helper Functions
 */

// Get Mikrotik settings from database (override config.php)
function getMikrotikSettings() {
    static $settings = null;
    if ($settings === null) {
        $settings = [
            'host' => defined('MIKROTIK_HOST') ? MIKROTIK_HOST : '',
            'user' => defined('MIKROTIK_USER') ? MIKROTIK_USER : '',
            'pass' => defined('MIKROTIK_PASS') ? MIKROTIK_PASS : '',
            'port' => defined('MIKROTIK_PORT') ? MIKROTIK_PORT : 8728
        ];
        
        // Try to get from database
        $dbSettings = fetchAll("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('MIKROTIK_HOST', 'MIKROTIK_USER', 'MIKROTIK_PASS', 'MIKROTIK_PORT')");
        foreach ($dbSettings as $s) {
            switch ($s['setting_key']) {
                case 'MIKROTIK_HOST':
                    $settings['host'] = $s['setting_value'];
                    break;
                case 'MIKROTIK_USER':
                    $settings['user'] = $s['setting_value'];
                    break;
                case 'MIKROTIK_PASS':
                    $settings['pass'] = $s['setting_value'];
                    break;
                case 'MIKROTIK_PORT':
                    $settings['port'] = (int)$s['setting_value'];
                    break;
            }
        }
    }
    return $settings;
}

// Format currency
function formatCurrency($amount) {
    return CURRENCY_SYMBOL . ' ' . number_format($amount, 0, ',', '.');
}

// Format date
function formatDate($date, $format = 'd M Y') {
    return date($format, strtotime($date));
}

// Generate invoice number
function generateInvoiceNumber() {
    $prefix = INVOICE_PREFIX;
    $start = INVOICE_START;
    
    $lastInvoice = fetchOne("SELECT invoice_number FROM invoices ORDER BY id DESC LIMIT 1");
    
    if ($lastInvoice) {
        $lastNum = (int)str_replace($prefix, '', $lastInvoice['invoice_number']);
        $newNum = $lastNum + 1;
    } else {
        $newNum = $start;
    }
    
    return $prefix . str_pad($newNum, 6, '0', STR_PAD_LEFT);
}

function sendWhatsApp($phone, $message) {
    require_once 'whatsapp.php';
    
    // Get default WhatsApp gateway from settings
    $defaultGateway = fetchOne("SELECT setting_value FROM settings WHERE setting_key = ?", ['DEFAULT_WHATSAPP_GATEWAY'])['setting_value'] ?? 'fonnte';
    
    // Format phone number (62 format)
    if (substr($phone, 0, 2) === '08') {
        $phone = '62' . substr($phone, 1);
    }
    
    // Send using selected gateway
    $result = sendWhatsAppMessage($phone, $message, $defaultGateway);
    
    return $result['success'] ?? false;
}

function getCustomerDueDate($customer, $baseDate = null) {
    $baseTimestamp = $baseDate ? strtotime($baseDate) : time();
    $year = date('Y', $baseTimestamp);
    $month = date('m', $baseTimestamp);
    $day = isset($customer['isolation_date']) ? (int)$customer['isolation_date'] : 20;
    if ($day < 1) {
        $day = 1;
    }
    if ($day > 28) {
        $day = 28;
    }
    $lastDay = (int)date('t', strtotime($year . '-' . $month . '-01'));
    if ($day > $lastDay) {
        $day = $lastDay;
    }
    return date('Y-m-d', strtotime($year . '-' . $month . '-' . str_pad($day, 2, '0', STR_PAD_LEFT)));
}

function logError($message) {
    $logFile = __DIR__ . '/../logs/error.log';
    $logDir = dirname($logFile);
    
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] ERROR: {$message}\n";
    
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// Log activity
function logActivity($action, $details = '') {
    $logFile = __DIR__ . '/../logs/activity.log';
    $logDir = dirname($logFile);
    
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $user = $_SESSION['admin']['username'] ?? 'guest';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    $logMessage = "[{$timestamp}] [{$user}] [{$ip}] {$action} - {$details}\n";
    
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// Redirect
function redirect($url) {
    header("Location: {$url}");
    exit;
}

// Flash message
function setFlash($type, $message) {
    $_SESSION['flash'][$type] = $message;
}

function getFlash($type) {
    $message = $_SESSION['flash'][$type] ?? null;
    unset($_SESSION['flash'][$type]);
    return $message;
}

function hasFlash($type) {
    return isset($_SESSION['flash'][$type]);
}

// Sanitize input
function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Validate email
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Generate random string
function generateRandomString($length = 10) {
    return substr(str_shuffle(str_repeat($x='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length/strlen($x)) )), 1, $length);
}

// Check if customer is isolated
function isCustomerIsolated($customerId) {
    $customer = fetchOne("SELECT status FROM customers WHERE id = ?", [$customerId]);
    return $customer && $customer['status'] === 'isolated';
}

// Isolate customer
function isolateCustomer($customerId) {
    $customer = fetchOne("SELECT * FROM customers WHERE id = ?", [$customerId]);
    if (!$customer) {
        return false;
    }
    
    // Update status
    update('customers', ['status' => 'isolated'], 'id = ?', [$customerId]);
    
    // Update MikroTik profile
    $package = fetchOne("SELECT * FROM packages WHERE id = ?", [$customer['package_id']]);
    if ($package && !empty($customer['pppoe_username'])) {
        // Call MikroTik API to change profile
        $result = mikrotikSetProfile($customer['pppoe_username'], $package['profile_isolir']);
        
        // Send WhatsApp notification
        $message = "Halo {$customer['name']},\n\nPembayaran internet Anda sudah melewati tanggal jatuh tempo.\n\nMohon segera lakukan pembayaran untuk mengaktifkan kembali koneksi internet Anda.\n\nTerima kasih.";
        sendWhatsApp($customer['phone'], $message);
    }
    
    logActivity('ISOLATE_CUSTOMER', "Customer ID: {$customerId}");
    
    return true;
}

// Unisolate customer
function unisolateCustomer($customerId) {
    $customer = fetchOne("SELECT * FROM customers WHERE id = ?", [$customerId]);
    if (!$customer) {
        return false;
    }
    
    // Update status
    update('customers', ['status' => 'active'], 'id = ?', [$customerId]);
    
    // Update MikroTik profile
    $package = fetchOne("SELECT * FROM packages WHERE id = ?", [$customer['package_id']]);
    if ($package && !empty($customer['pppoe_username'])) {
        // Call MikroTik API to change profile
        $result = mikrotikSetProfile($customer['pppoe_username'], $package['profile_normal']);
    }
    
    logActivity('UNISOLATE_CUSTOMER', "Customer ID: {$customerId}");
    
    return true;
}

// MikroTik API functions - Binary Protocol Implementation
function mikrotikConnect() {
    $mikrotik = getMikrotikSettings();
    
    if (empty($mikrotik['host']) || empty($mikrotik['user'])) {
        logError("MikroTik config incomplete: host or user is empty");
        return false;
    }
    
    $socket = @fsockopen($mikrotik['host'], $mikrotik['port'], $errno, $errstr, 5);
    
    if (!$socket) {
        logError("MikroTik connection failed: $errstr ($errno)");
        return false;
    }
    
    stream_set_timeout($socket, 5);
    stream_set_blocking($socket, true);
    
    return $socket;
}

function mikrotikLogin($socket) {
    $mikrotik = getMikrotikSettings();
    $username = $mikrotik['user'];
    $password = $mikrotik['pass'];
    
    // Method 1: Plain text password (RouterOS 6.43+)
    // This is the preferred method for modern RouterOS
    mikrotikWrite($socket, '/login');
    mikrotikWrite($socket, '=name=' . $username);
    mikrotikWrite($socket, '=password=' . $password);
    mikrotikWrite($socket, ''); // End sentence
    
    $response = mikrotikReadSentence($socket);
    
    // Check if login succeeded
    foreach ($response as $word) {
        if ($word === '!done') {
            return true;
        }
    }
    
    // If plain text method failed, try MD5 challenge-response (older RouterOS)
    // Reconnect is needed, but we'll try a different approach
    
    // Method 2: MD5 Challenge-Response (RouterOS pre-6.43)
    mikrotikWrite($socket, '/login');
    mikrotikWrite($socket, ''); // End sentence
    
    $response = mikrotikReadSentence($socket);
    
    if (empty($response)) {
        return false;
    }
    
    // Extract challenge from response
    $challenge = null;
    foreach ($response as $word) {
        if (strpos($word, '=ret=') === 0) {
            $challenge = substr($word, 5);
            break;
        }
    }
    
    if (!$challenge) {
        return false;
    }
    
    // Calculate MD5 hash
    $hash = md5(chr(0) . $password . pack('H*', $challenge), true);
    
    // Send login with hash
    mikrotikWrite($socket, '/login');
    mikrotikWrite($socket, '=name=' . $username);
    mikrotikWrite($socket, '=response=' . bin2hex($hash));
    mikrotikWrite($socket, ''); // End sentence
    
    // Read response
    $response = mikrotikReadSentence($socket);
    
    // Check if login succeeded
    foreach ($response as $word) {
        if ($word === '!done') {
            return true;
        }
    }
    
    return false;
}

function mikrotikWrite($socket, $word) {
    if ($word === '') {
        fwrite($socket, chr(0));
        return;
    }
    
    $len = strlen($word);
    $encodedLen = '';
    
    if ($len < 0x80) {
        $encodedLen = chr($len);
    } elseif ($len < 0x4000) {
        $encodedLen = chr(($len >> 8) | 0x80) . chr($len & 0xFF);
    } elseif ($len < 0x200000) {
        $encodedLen = chr(($len >> 16) | 0xC0) . chr(($len >> 8) & 0xFF) . chr($len & 0xFF);
    } elseif ($len < 0x10000000) {
        $encodedLen = chr(($len >> 24) | 0xE0) . chr(($len >> 16) & 0xFF) . chr(($len >> 8) & 0xFF) . chr($len & 0xFF);
    } else {
        $encodedLen = chr(0xF0) . chr(($len >> 24) & 0xFF) . chr(($len >> 16) & 0xFF) . chr(($len >> 8) & 0xFF) . chr($len & 0xFF);
    }
    
    fwrite($socket, $encodedLen . $word);
}

function mikrotikWriteCommand($socket, $command) {
    mikrotikWrite($socket, $command);
}

function mikrotikWriteWord($socket, $word) {
    mikrotikWrite($socket, $word);
}

function mikrotikReadSentence($socket) {
    $words = [];
    while (true) {
        $byte = fread($socket, 1);
        if ($byte === false || $byte === '') break;
        
        $byte = ord($byte);
        $len = 0;
        
        if (($byte & 0x80) == 0x00) {
            $len = $byte;
        } elseif (($byte & 0xC0) == 0x80) {
            $len = (($byte & 0x3F) << 8) + ord(fread($socket, 1));
        } elseif (($byte & 0xE0) == 0xC0) {
            $len = (($byte & 0x1F) << 16) + (ord(fread($socket, 1)) << 8) + ord(fread($socket, 1));
        } elseif (($byte & 0xF0) == 0xE0) {
            $len = (($byte & 0x0F) << 24) + (ord(fread($socket, 1)) << 16) + (ord(fread($socket, 1)) << 8) + ord(fread($socket, 1));
        } elseif (($byte & 0xF8) == 0xF0) {
            $len = (ord(fread($socket, 1)) << 24) + (ord(fread($socket, 1)) << 16) + (ord(fread($socket, 1)) << 8) + ord(fread($socket, 1));
        }
        
        if ($len == 0) {
            break;
        }
        
        $word = '';
        $remaining = $len;
        while ($remaining > 0) {
            $chunk = fread($socket, $remaining);
            if ($chunk === false || $chunk === '') break;
            $word .= $chunk;
            $remaining -= strlen($chunk);
        }
        
        $words[] = $word;
    }
    
    return $words;
}

function mikrotikRead($socket) {
    return mikrotikReadSentence($socket);
}

function mikrotikQuery($command, $params = []) {
    $socket = mikrotikConnect();
    if (!$socket) {
        return false;
    }
    
    mikrotikLogin($socket);
    
    // Send command
    mikrotikWrite($socket, $command);
    foreach ($params as $key => $value) {
        mikrotikWrite($socket, '=' . $key . '=' . $value);
    }
    
    // Read response
    $response = mikrotikRead($socket);
    
    fclose($socket);
    
    return mikrotikParseResponse($response);
}

function mikrotikParseResponse($response) {
    $lines = explode("\n", $response);
    $result = [];
    
    foreach ($lines as $line) {
        if (strpos($line, '!done') !== false || strpos($line, '!trap') !== false) {
            break;
        }
        
        if (preg_match('/^=([a-zA-Z0-9_-]+)=(.*)$/', $line, $matches)) {
            $result[$matches[1]] = $matches[2];
        }
    }
    
    return $result;
}

function mikrotikSetProfile($username, $profile) {
    $socket = mikrotikConnect();
    if (!$socket) {
        return false;
    }
    
    mikrotikLogin($socket);
    
    // Find user and get their secret ID
    mikrotikWrite($socket, '/ppp/secret/print');
    mikrotikWrite($socket, '?name=' . $username);
    
    $response = mikrotikRead($socket);
    $parsed = mikrotikParseUsers($response);
    
    if (empty($parsed)) {
        fclose($socket);
        return false;
    }
    
    // Get the secret ID from first user
    $secretId = $parsed[0]['.id'] ?? null;
    if (!$secretId) {
        fclose($socket);
        return false;
    }
    
    // Update profile using secret ID
    mikrotikWrite($socket, '/ppp/secret/set');
    mikrotikWrite($socket, '.id=' . $secretId);
    mikrotikWrite($socket, '=profile=' . $profile);
    
    // Read response to confirm
    mikrotikRead($socket);
    
    fclose($socket);
    
    return true;
}

function mikrotikGetPppoeUsers() {
    $socket = mikrotikConnect();
    if (!$socket) {
        return [];
    }
    
    if (!mikrotikLogin($socket)) {
        fclose($socket);
        return [];
    }
    
    mikrotikWrite($socket, '/ppp/secret/print');
    mikrotikWrite($socket, ''); // End sentence
    
    // Read ALL sentences until !done
    $allWords = [];
    $done = false;
    $timeout = time() + 30; // 30 second timeout for large user lists
    
    while (!$done && time() < $timeout) {
        $words = mikrotikReadSentence($socket);
        if (empty($words)) {
            break;
        }
        
        foreach ($words as $word) {
            $allWords[] = $word;
            if ($word === '!done') {
                $done = true;
                break;
            }
        }
    }
    
    fclose($socket);
    
    return mikrotikParseUsers($allWords);
}

function mikrotikParseUsers($response) {
    // $response is now an array of words from binary protocol
    // Format: =key=value (e.g., =name=user1)
    $users = [];
    $currentUser = [];
    
    foreach ($response as $word) {
        if ($word === '!done') {
            if (!empty($currentUser)) {
                $users[] = $currentUser;
                $currentUser = [];
            }
            break;
        }
        
        if ($word === '!re') {
            if (!empty($currentUser)) {
                $users[] = $currentUser;
                $currentUser = [];
            }
        } elseif (strpos($word, '=') === 0) {
            // Format: =key=value, so remove first '=' then split
            $word = substr($word, 1); // Remove leading '='
            $parts = explode('=', $word, 2);
            if (count($parts) === 2) {
                $currentUser[$parts[0]] = $parts[1];
            }
        }
    }
    
    return $users;
}

// Add PPPoE Secret
function mikrotikAddSecret($name, $password, $profile = 'default', $service = 'pppoe') {
    $socket = mikrotikConnect();
    if (!$socket) {
        return ['success' => false, 'message' => 'Cannot connect to MikroTik'];
    }
    
    if (!mikrotikLogin($socket)) {
        fclose($socket);
        return ['success' => false, 'message' => 'Authentication failed'];
    }
    
    mikrotikWrite($socket, '/ppp/secret/add');
    mikrotikWrite($socket, '=name=' . $name);
    mikrotikWrite($socket, '=password=' . $password);
    mikrotikWrite($socket, '=profile=' . $profile);
    mikrotikWrite($socket, '=service=' . $service);
    mikrotikWrite($socket, ''); // End sentence
    
    $response = mikrotikReadSentence($socket);
    fclose($socket);
    
    foreach ($response as $word) {
        if ($word === '!done') {
            return ['success' => true, 'message' => 'User added successfully'];
        }
        if (strpos($word, '!trap') === 0) {
            $message = 'Unknown error';
            foreach ($response as $w) {
                if (strpos($w, '=message=') === 0) {
                    $message = substr($w, 9);
                    break;
                }
            }
            return ['success' => false, 'message' => $message];
        }
    }
    
    return ['success' => false, 'message' => 'Unknown response'];
}

// Update PPPoE Secret
function mikrotikUpdateSecret($id, $data) {
    $socket = mikrotikConnect();
    if (!$socket) {
        return ['success' => false, 'message' => 'Cannot connect to MikroTik'];
    }
    
    if (!mikrotikLogin($socket)) {
        fclose($socket);
        return ['success' => false, 'message' => 'Authentication failed'];
    }
    
    mikrotikWrite($socket, '/ppp/secret/set');
    mikrotikWrite($socket, '=.id=' . $id);
    
    if (isset($data['name'])) mikrotikWrite($socket, '=name=' . $data['name']);
    if (isset($data['password'])) mikrotikWrite($socket, '=password=' . $data['password']);
    if (isset($data['profile'])) mikrotikWrite($socket, '=profile=' . $data['profile']);
    if (isset($data['service'])) mikrotikWrite($socket, '=service=' . $data['service']);
    if (isset($data['disabled'])) mikrotikWrite($socket, '=disabled=' . $data['disabled']);
    
    mikrotikWrite($socket, ''); // End sentence
    
    $response = mikrotikReadSentence($socket);
    fclose($socket);
    
    foreach ($response as $word) {
        if ($word === '!done') {
            return ['success' => true, 'message' => 'User updated successfully'];
        }
        if (strpos($word, '!trap') === 0) {
            $message = 'Unknown error';
            foreach ($response as $w) {
                if (strpos($w, '=message=') === 0) {
                    $message = substr($w, 9);
                    break;
                }
            }
            return ['success' => false, 'message' => $message];
        }
    }
    
    return ['success' => false, 'message' => 'Unknown response'];
}

// Delete PPPoE Secret
function mikrotikDeleteSecret($id) {
    $socket = mikrotikConnect();
    if (!$socket) {
        return ['success' => false, 'message' => 'Cannot connect to MikroTik'];
    }
    
    if (!mikrotikLogin($socket)) {
        fclose($socket);
        return ['success' => false, 'message' => 'Authentication failed'];
    }
    
    mikrotikWrite($socket, '/ppp/secret/remove');
    mikrotikWrite($socket, '=.id=' . $id);
    mikrotikWrite($socket, ''); // End sentence
    
    $response = mikrotikReadSentence($socket);
    fclose($socket);
    
    foreach ($response as $word) {
        if ($word === '!done') {
            return ['success' => true, 'message' => 'User deleted successfully'];
        }
        if (strpos($word, '!trap') === 0) {
            $message = 'Unknown error';
            foreach ($response as $w) {
                if (strpos($w, '=message=') === 0) {
                    $message = substr($w, 9);
                    break;
                }
            }
            return ['success' => false, 'message' => $message];
        }
    }
    
    return ['success' => false, 'message' => 'Unknown response'];
}

// Get Active PPPoE Sessions (users currently connected)
function mikrotikGetActiveSessions() {
    $socket = mikrotikConnect();
    if (!$socket) {
        return [];
    }
    
    if (!mikrotikLogin($socket)) {
        fclose($socket);
        return [];
    }
    
    mikrotikWrite($socket, '/ppp/active/print');
    mikrotikWrite($socket, ''); // End sentence
    
    // Read ALL sentences until !done
    $allWords = [];
    $done = false;
    $timeout = time() + 30;
    
    while (!$done && time() < $timeout) {
        $words = mikrotikReadSentence($socket);
        if (empty($words)) {
            break;
        }
        
        foreach ($words as $word) {
            $allWords[] = $word;
            if ($word === '!done') {
                $done = true;
                break;
            }
        }
    }
    
    fclose($socket);
    
    // Parse active sessions
    $sessions = [];
    $currentSession = [];
    
    foreach ($allWords as $word) {
        if ($word === '!done') {
            if (!empty($currentSession)) {
                $sessions[] = $currentSession;
            }
            break;
        }
        
        if ($word === '!re') {
            if (!empty($currentSession)) {
                $sessions[] = $currentSession;
                $currentSession = [];
            }
        } elseif (strpos($word, '=') === 0) {
            $word = substr($word, 1);
            $parts = explode('=', $word, 2);
            if (count($parts) === 2) {
                $currentSession[$parts[0]] = $parts[1];
            }
        }
    }
    
    return $sessions;
}

function mikrotikGetProfiles() {
    $socket = mikrotikConnect();
    $mikrotik = getMikrotikSettings();
    if (!$socket) {
        logError("MikroTik connection failed: Could not connect to " . $mikrotik['host'] . ":" . $mikrotik['port']);
        return [];
    }
    
    if (!mikrotikLogin($socket)) {
        logError("MikroTik login failed: Authentication failed");
        fclose($socket);
        return [];
    }
    
    logActivity('MIKROTI_API', "Fetching PPPoE profiles");
    
    // Send print command
    mikrotikWrite($socket, '/ppp/profile/print');
    
    // End sentence
    mikrotikWrite($socket, '');
    
    // Read ALL sentences until !done (MikroTik sends multiple sentences)
    $allWords = [];
    $done = false;
    $timeout = time() + 10; // 10 second timeout
    
    while (!$done && time() < $timeout) {
        $words = mikrotikReadSentence($socket);
        if (empty($words)) {
            break;
        }
        
        foreach ($words as $word) {
            $allWords[] = $word;
            if ($word === '!done') {
                $done = true;
                break;
            }
        }
    }
    
    fclose($socket);
    
    $profiles = mikrotikParseProfiles($allWords);
    
    return $profiles;
}

function mikrotikParseProfiles($response) {
    // $response is now an array of words from binary protocol
    // Format: =key=value (e.g., =name=default)
    $profiles = [];
    $currentProfile = [];
    
    foreach ($response as $word) {
        if ($word === '!done') {
            if (!empty($currentProfile)) {
                $profiles[] = $currentProfile;
                $currentProfile = [];
            }
            break;
        }
        
        if ($word === '!re') {
            if (!empty($currentProfile)) {
                $profiles[] = $currentProfile;
                $currentProfile = [];
            }
        } elseif (strpos($word, '=') === 0) {
            // Format: =key=value, so remove first '=' then split
            $word = substr($word, 1); // Remove leading '='
            $parts = explode('=', $word, 2);
            if (count($parts) === 2) {
                $currentProfile[$parts[0]] = $parts[1];
            }
        }
    }
    
    return $profiles;
}

// Get MikroTik Hotspot User Profiles
function mikrotikGetHotspotProfiles() {
    $socket = mikrotikConnect();
    if (!$socket) {
        return [];
    }
    
    if (!mikrotikLogin($socket)) {
        fclose($socket);
        return [];
    }
    
    // Get hotspot user profiles
    mikrotikWrite($socket, '/ip/hotspot/user/profile/print');
    mikrotikWrite($socket, '');
    
    $allWords = [];
    $done = false;
    $timeout = time() + 10;
    
    while (!$done && time() < $timeout) {
        $words = mikrotikReadSentence($socket);
        if (empty($words)) break;
        foreach ($words as $word) {
            $allWords[] = $word;
            if ($word === '!done') {
                $done = true;
                break;
            }
        }
    }
    
    fclose($socket);
    
    $profiles = [];
    $currentProfile = [];
    
    foreach ($allWords as $word) {
        if ($word === '!re') {
            if (!empty($currentProfile)) {
                $profiles[] = $currentProfile;
                $currentProfile = [];
            }
        } elseif (strpos($word, '=') === 0) {
            $word = substr($word, 1);
            $parts = explode('=', $word, 2);
            if (count($parts) === 2) {
                $currentProfile[$parts[0]] = $parts[1];
            }
        }
    }
    
    return $profiles;
}

// Add MikroTik Hotspot User
function mikrotikAddHotspotUser($username, $password, $profile = 'default') {
    $socket = mikrotikConnect();
    if (!$socket) {
        return false;
    }
    
    if (!mikrotikLogin($socket)) {
        fclose($socket);
        return false;
    }
    
    // Add hotspot user
    mikrotikWrite($socket, '/ip/hotspot/user/add');
    mikrotikWrite($socket, '=name=' . $username);
    mikrotikWrite($socket, '=password=' . $password);
    mikrotikWrite($socket, '=profile=' . $profile);
    mikrotikWrite($socket, '');
    
    $response = mikrotikReadSentence($socket);
    fclose($socket);
    
    // Check for success (no !trap error)
    foreach ($response as $word) {
        if (strpos($word, '!trap') === 0) {
            return false;
        }
    }
    
    return true;
}

// Delete MikroTik Hotspot User
function mikrotikDeleteHotspotUser($username) {
    $socket = mikrotikConnect();
    if (!$socket) {
        return false;
    }
    
    if (!mikrotikLogin($socket)) {
        fclose($socket);
        return false;
    }
    
    // Find user first
    mikrotikWrite($socket, '/ip/hotspot/user/print');
    mikrotikWrite($socket, '?name=' . $username);
    mikrotikWrite($socket, '');
    
    $allWords = [];
    $done = false;
    $timeout = time() + 10;
    
    while (!$done && time() < $timeout) {
        $words = mikrotikReadSentence($socket);
        if (empty($words)) break;
        foreach ($words as $word) {
            $allWords[] = $word;
            if ($word === '!done') {
                $done = true;
                break;
            }
        }
    }
    
    // Find the .id
    $userId = null;
    foreach ($allWords as $word) {
        if (strpos($word, '=.id=') === 0) {
            $userId = substr($word, 5);
            break;
        }
    }
    
    if (!$userId) {
        fclose($socket);
        return false; // User not found
    }
    
    // Remove user
    mikrotikWrite($socket, '/ip/hotspot/user/remove');
    mikrotikWrite($socket, '=.id=' . $userId);
    mikrotikWrite($socket, '');
    
    $response = mikrotikReadSentence($socket);
    fclose($socket);
    
    foreach ($response as $word) {
        if (strpos($word, '!trap') === 0) {
            return false;
        }
    }
    
    return true;
}

// Get MikroTik Hotspot Users
function mikrotikGetHotspotUsers() {
    $socket = mikrotikConnect();
    if (!$socket) {
        return [];
    }
    
    if (!mikrotikLogin($socket)) {
        fclose($socket);
        return [];
    }
    
    // Get hotspot users
    mikrotikWrite($socket, '/ip/hotspot/user/print');
    mikrotikWrite($socket, '');
    
    $allWords = [];
    $done = false;
    $timeout = time() + 10;
    
    while (!$done && time() < $timeout) {
        $words = mikrotikReadSentence($socket);
        if (empty($words)) break;
        foreach ($words as $word) {
            $allWords[] = $word;
            if ($word === '!done') {
                $done = true;
                break;
            }
        }
    }
    
    fclose($socket);
    
    $users = [];
    $currentUser = [];
    
    foreach ($allWords as $word) {
        if ($word === '!re') {
            if (!empty($currentUser)) {
                $users[] = $currentUser;
                $currentUser = [];
            }
        } elseif (strpos($word, '=') === 0) {
            $word = substr($word, 1);
            $parts = explode('=', $word, 2);
            if (count($parts) === 2) {
                $currentUser[$parts[0]] = $parts[1];
            }
        }
    }
    
    return $users;
}

// Get GenieACS settings from database (override config.php)
function getGenieacsSettings() {
    static $settings = null;
    if ($settings === null) {
        $settings = [
            'url' => defined('GENIEACS_URL') ? GENIEACS_URL : '',
            'username' => defined('GENIEACS_USERNAME') ? GENIEACS_USERNAME : '',
            'password' => defined('GENIEACS_PASSWORD') ? GENIEACS_PASSWORD : ''
        ];
        
        // Try to get from database
        $dbSettings = fetchAll("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('GENIEACS_URL', 'GENIEACS_USERNAME', 'GENIEACS_PASSWORD')");
        foreach ($dbSettings as $s) {
            switch ($s['setting_key']) {
                case 'GENIEACS_URL':
                    $settings['url'] = $s['setting_value'];
                    break;
                case 'GENIEACS_USERNAME':
                    $settings['username'] = $s['setting_value'];
                    break;
                case 'GENIEACS_PASSWORD':
                    $settings['password'] = $s['setting_value'];
                    break;
            }
        }
    }
    return $settings;
}

// GenieACS functions
function genieacsGetDevices() {
    $genieacs = getGenieacsSettings();
    if (empty($genieacs['url'])) {
        return [];
    }
    
    $url = rtrim($genieacs['url'], '/') . '/devices/';
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    // Add authentication if credentials are set
    if (!empty($genieacs['username']) && !empty($genieacs['password'])) {
        curl_setopt($ch, CURLOPT_USERPWD, $genieacs['username'] . ':' . $genieacs['password']);
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    // curl_close() is deprecated in PHP 8.0+ - CurlHandle auto-destroys
    
    if ($httpCode === 200) {
        $devices = json_decode($response, true);
        return is_array($devices) ? $devices : [];
    }
    
    return [];
}

function genieacsGetDevice($serial) {
    $genieacs = getGenieacsSettings();
    if (empty($genieacs['url'])) {
        return null;
    }
    
    // Search device by serial number using query
    // GenieACS uses query parameter to filter devices
    $query = json_encode(['DeviceID.SerialNumber' => $serial]);
    $url = rtrim($genieacs['url'], '/') . '/devices/?query=' . urlencode($query);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    // Add authentication if credentials are set
    if (!empty($genieacs['username']) && !empty($genieacs['password'])) {
        curl_setopt($ch, CURLOPT_USERPWD, $genieacs['username'] . ':' . $genieacs['password']);
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    // curl_close() is deprecated in PHP 8.0+ - CurlHandle auto-destroys
    
    if ($httpCode === 200) {
        $devices = json_decode($response, true);
        if (is_array($devices) && count($devices) > 0) {
            return $devices[0]; // Return first matching device
        }
    }
    
    // Alternative: Try direct lookup by device ID (Serial Number might be the Device ID)
    $url2 = rtrim($genieacs['url'], '/') . '/devices/' . urlencode($serial);
    $ch2 = curl_init($url2);
    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch2, CURLOPT_TIMEOUT, 10);
    
    if (!empty($genieacs['username']) && !empty($genieacs['password'])) {
        curl_setopt($ch2, CURLOPT_USERPWD, $genieacs['username'] . ':' . $genieacs['password']);
    }
    
    $response2 = curl_exec($ch2);
    $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
    
    if ($httpCode2 === 200) {
        $device = json_decode($response2, true);
        if (is_array($device) && !isset($device['message'])) {
            return $device;
        }
    }
    
    return null;
}

// Helper function to extract value from GenieACS parameter structure
function genieacsGetValue($device, $path) {
    // Navigate through nested structure
    $keys = explode('.', $path);
    $current = $device;
    
    foreach ($keys as $key) {
        if (!is_array($current)) {
            return null;
        }
        
        // Try direct key access
        if (isset($current[$key])) {
            $current = $current[$key];
        } else {
            // Try numeric index pattern (e.g., LANDevice.1 -> LANDevice["1"])
            $found = false;
            foreach ($current as $k => $v) {
                if (strpos($k, $key) === 0) {
                    $current = $v;
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                return null;
            }
        }
    }
    
    // Extract value - GenieACS stores values in different formats
    if (is_array($current)) {
        // Try common value keys
        if (isset($current['_value'])) {
            return $current['_value'];
        }
        if (isset($current['value'])) {
            return $current['value'];
        }
        if (isset($current[0]) && is_string($current[0])) {
            return $current[0];
        }
    }
    
    return is_string($current) ? $current : null;
}

// Get device info summary from GenieACS
function genieacsGetDeviceInfo($serial) {
    $device = genieacsGetDevice($serial);
    
    if (!$device) {
        return null;
    }
    
    $info = [
        'id' => $device['_id'] ?? $serial,
        'serial_number' => $serial,
        'last_inform' => $device['_lastInform'] ?? null,
        'status' => 'unknown',
        'uptime' => null,
        'manufacturer' => null,
        'model' => null,
        'software_version' => null,
        'rx_power' => null,
        'tx_power' => null,
        'ssid' => null,
        'wifi_password' => null,
        'ip_address' => null,
        'mac_address' => null
    ];
    
    // Determine online status (last inform within 5 minutes)
    if ($info['last_inform']) {
        $lastInform = strtotime($info['last_inform']);
        $info['status'] = (time() - $lastInform) < 300 ? 'online' : 'offline';
    }
    
    // Extract common parameters using different possible paths
    // Device Manufacturer
    $info['manufacturer'] = 
        genieacsGetValue($device, 'InternetGatewayDevice.DeviceInfo.Manufacturer') ??
        genieacsGetValue($device, 'Device.DeviceInfo.Manufacturer') ??
        genieacsGetValue($device, 'DeviceID.Manufacturer');
    
    // Device Model
    $info['model'] = 
        genieacsGetValue($device, 'InternetGatewayDevice.DeviceInfo.ModelName') ??
        genieacsGetValue($device, 'Device.DeviceInfo.ModelName') ??
        genieacsGetValue($device, 'DeviceID.ProductClass');
    
    // Software Version
    $info['software_version'] = 
        genieacsGetValue($device, 'InternetGatewayDevice.DeviceInfo.SoftwareVersion') ??
        genieacsGetValue($device, 'Device.DeviceInfo.SoftwareVersion');
    
    // Uptime
    $info['uptime'] = 
        genieacsGetValue($device, 'InternetGatewayDevice.DeviceInfo.UpTime') ??
        genieacsGetValue($device, 'Device.DeviceInfo.UpTime');
    
    // WAN IP Address
    $info['ip_address'] = 
        genieacsGetValue($device, 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.ExternalIPAddress') ??
        genieacsGetValue($device, 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.ExternalIPAddress');
    
    // MAC Address
    $info['mac_address'] = 
        genieacsGetValue($device, 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.MACAddress') ??
        genieacsGetValue($device, 'Device.Ethernet.Interface.1.MACAddress');
    
    // WiFi SSID - try multiple paths
    $info['ssid'] = 
        genieacsGetValue($device, 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID') ??
        genieacsGetValue($device, 'InternetGatewayDevice.LANDevice.1.WiFi.Radio.1.SSID') ??
        genieacsGetValue($device, 'Device.WiFi.SSID.1.SSID');
    
    // WiFi Password
    $info['wifi_password'] = 
        genieacsGetValue($device, 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.KeyPassphrase') ??
        genieacsGetValue($device, 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.KeyPassphrase') ??
        genieacsGetValue($device, 'Device.WiFi.AccessPoint.1.Security.KeyPassphrase');
    
    // PON Optical Power (for GPON/EPON ONUs)
    $info['rx_power'] = 
        genieacsGetValue($device, 'InternetGatewayDevice.WANDevice.1.X_GponInterafceConfig.RxPower') ??
        genieacsGetValue($device, 'Device.Optical.Interface.1.RXPower');
    
    $info['tx_power'] = 
        genieacsGetValue($device, 'InternetGatewayDevice.WANDevice.1.X_GponInterafceConfig.TxPower') ??
        genieacsGetValue($device, 'Device.Optical.Interface.1.TXPower');
    
    return $info;
}

function genieacsSetParameter($serial, $parameter, $value) {
    $genieacs = getGenieacsSettings();
    if (empty($genieacs['url'])) {
        return false;
    }
    
    // Get device first to find the actual device ID
    $device = genieacsGetDevice($serial);
    if (!$device) {
        return false;
    }
    
    $deviceId = $device['_id'] ?? $serial;
    $url = rtrim($genieacs['url'], '/') . '/devices/' . urlencode($deviceId) . '/tasks?connection_request';
    
    $data = [
        'name' => 'setParameter',
        'parameterValues' => [
            [$parameter, $value, 'xsd:string']
        ]
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    // Add authentication if credentials are set
    if (!empty($genieacs['username']) && !empty($genieacs['password'])) {
        curl_setopt($ch, CURLOPT_USERPWD, $genieacs['username'] . ':' . $genieacs['password']);
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    // curl_close() is deprecated in PHP 8.0+ - CurlHandle auto-destroys
    
    return $httpCode === 200 || $httpCode === 201;
}

// Find device by PPPoE username in GenieACS
function genieacsFindDeviceByPppoe($pppoeUsername) {
    $genieacs = getGenieacsSettings();
    if (empty($genieacs['url'])) {
        return null;
    }
    
    // First, try to find device using VirtualParameters.pppoeUsername which is the most reliable approach
    $query = json_encode(['VirtualParameters.pppoeUsername' => $pppoeUsername]);
    $url = rtrim($genieacs['url'], '/') . '/devices/?query=' . urlencode($query);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    // Add authentication if credentials are set
    if (!empty($genieacs['username']) && !empty($genieacs['password'])) {
        curl_setopt($ch, CURLOPT_USERPWD, $genieacs['username'] . ':' . $genieacs['password']);
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    // curl_close() is deprecated in PHP 8.0+ - CurlHandle auto-destroys
    
    if ($httpCode === 200) {
        $devices = json_decode($response, true);
        if (is_array($devices) && count($devices) > 0) {
            return $devices[0]; // Return first matching device
        }
    }
    
    // If not found via VirtualParameters, try alternative approaches
    // Try searching for devices with PPPoE username in various possible locations
    $possibleQueries = [
        // Alternative VirtualParameters that might contain the username
        ['VirtualParameters.pppoeUsername2' => $pppoeUsername],
        // Common paths where username might be stored in standard parameters
        ['InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.Username' => $pppoeUsername],
        ['InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.Username' => $pppoeUsername],
        ['Device.PPP.Interface.1.Credentials.Username' => $pppoeUsername],
        ['InternetGatewayDevice.PPPPEngine.PPPoE.UnicastDiscovery.Username' => $pppoeUsername],
        // If PPPoE username is stored as part of device name or description
        ['Device.DeviceInfo.Description' => $pppoeUsername],
        ['Device.DeviceInfo.FriendlyName' => $pppoeUsername]
    ];
    
    foreach ($possibleQueries as $query) {
        $encodedQuery = json_encode($query);
        $url = rtrim($genieacs['url'], '/') . '/devices/?query=' . urlencode($encodedQuery);
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        // Add authentication if credentials are set
        if (!empty($genieacs['username']) && !empty($genieacs['password'])) {
            curl_setopt($ch, CURLOPT_USERPWD, $genieacs['username'] . ':' . $genieacs['password']);
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        // curl_close() is deprecated in PHP 8.0+ - CurlHandle auto-destroys
        
        if ($httpCode === 200) {
            $devices = json_decode($response, true);
            if (is_array($devices) && count($devices) > 0) {
                return $devices[0]; // Return first matching device
            }
        }
    }
    
    // If no device found by searching parameters, try a more general search
    // Sometimes the PPPoE username might be stored in custom fields
    $generalQuery = urlencode('"' . $pppoeUsername . '"');
    $url = rtrim($genieacs['url'], '/') . '/devices/?query=' . $generalQuery;
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    // Add authentication if credentials are set
    if (!empty($genieacs['username']) && !empty($genieacs['password'])) {
        curl_setopt($ch, CURLOPT_USERPWD, $genieacs['username'] . ':' . $genieacs['password']);
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    // curl_close() is deprecated in PHP 8.0+ - CurlHandle auto-destroys
    
    if ($httpCode === 200) {
        $devices = json_decode($response, true);
        if (is_array($devices) && count($devices) > 0) {
            return $devices[0]; // Return first matching device
        }
    }
    
    return null;
}

// Reboot device via GenieACS
function genieacsReboot($serial) {
    $genieacs = getGenieacsSettings();
    if (empty($genieacs['url'])) {
        return false;
    }
    
    // Get device first to find the actual device ID
    $device = genieacsGetDevice($serial);
    if (!$device) {
        return false;
    }
    
    $deviceId = $device['_id'] ?? $serial;
    $url = rtrim($genieacs['url'], '/') . '/devices/' . urlencode($deviceId) . '/tasks?connection_request';
    
    $data = [
        'name' => 'reboot'
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    // Add authentication if credentials are set
    if (!empty($genieacs['username']) && !empty($genieacs['password'])) {
        curl_setopt($ch, CURLOPT_USERPWD, $genieacs['username'] . ':' . $genieacs['password']);
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    // curl_close() is deprecated in PHP 8.0+ - CurlHandle auto-destroys
    
    return $httpCode === 200 || $httpCode === 201;
}

// Pagination
function paginate($table, $page = 1, $perPage = ITEMS_PER_PAGE, $where = '', $params = []) {
    $offset = ($page - 1) * $perPage;
    
    // Get total
    $countSql = "SELECT COUNT(*) as total FROM {$table}";
    if ($where) {
        $countSql .= " WHERE {$where}";
    }
    $totalResult = fetchOne($countSql, $params);
    $total = $totalResult['total'] ?? 0;
    
    // Get data
    $dataSql = "SELECT * FROM {$table}";
    if ($where) {
        $dataSql .= " WHERE {$where}";
    }
    $dataSql .= " ORDER BY id DESC LIMIT {$perPage} OFFSET {$offset}";
    
    $data = fetchAll($dataSql, $params);
    
    return [
        'data' => $data,
        'total' => $total,
        'page' => $page,
        'perPage' => $perPage,
        'totalPages' => ceil($total / $perPage)
    ];
}

// Generate CSRF token
function generateCsrfToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verify CSRF token
function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Check if admin is logged in
function isAdminLoggedIn() {
    return isset($_SESSION['admin']['logged_in']) && $_SESSION['admin']['logged_in'] === true;
}

// Check if customer is logged in
function isCustomerLoggedIn() {
    return isset($_SESSION['customer']['logged_in']) && $_SESSION['customer']['logged_in'] === true;
}

// Get current admin
function getCurrentAdmin() {
    return $_SESSION['admin'] ?? null;
}

// Get current customer
function getCurrentCustomer() {
    return $_SESSION['customer'] ?? null;
}

// JSON response
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Check if request is AJAX
function isAjax() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

// Get current URL
function getCurrentUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    return $protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
}
