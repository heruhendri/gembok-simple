<?php
/**
 * Authentication Functions
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Admin Authentication
function adminLogin($username, $password) {
    $admin = fetchOne("SELECT * FROM admin_users WHERE username = ?", [$username]);
    
    if (!$admin) {
        return false;
    }
    
    if (password_verify($password, $admin['password'])) {
        $_SESSION['admin'] = [
            'id' => $admin['id'],
            'username' => $admin['username'],
            'email' => $admin['email'],
            'logged_in' => true,
            'login_time' => time()
        ];
        
        logActivity('ADMIN_LOGIN', "Username: {$username}");
        return true;
    }
    
    return false;
}

function adminLogout() {
    logActivity('ADMIN_LOGOUT', "Username: " . ($_SESSION['admin']['username'] ?? 'unknown'));
    
    unset($_SESSION['admin']);
    session_destroy();
    
    redirect(APP_URL . '/login.php');
}

function requireAdminLogin() {
    if (!isAdminLoggedIn()) {
        setFlash('error', 'Silakan login terlebih dahulu');
        redirect(APP_URL . '/login.php');
    }
}

// Customer Authentication
function customerLogin($phone, $password) {
    $customer = fetchOne("SELECT * FROM customers WHERE phone = ?", [$phone]);
    
    if (!$customer) {
        return false;
    }
    
    // Check portal password
    if (!password_verify($password, $customer['portal_password'])) {
        return false;
    }
    
    $_SESSION['customer'] = [
        'id' => $customer['id'],
        'name' => $customer['name'],
        'phone' => $customer['phone'],
        'pppoe_username' => $customer['pppoe_username'],
        'logged_in' => true,
        'login_time' => time()
    ];
    
    logActivity('CUSTOMER_LOGIN', "Phone: {$phone}");
    return true;
}

function customerLogout() {
    logActivity('CUSTOMER_LOGOUT', "Phone: " . ($_SESSION['customer']['phone'] ?? 'unknown'));
    
    unset($_SESSION['customer']);
    session_destroy();
    
    redirect(APP_URL . '/login.php');
}

function requireCustomerLogin() {
    if (!isCustomerLoggedIn()) {
        setFlash('error', 'Silakan login terlebih dahulu');
        redirect(APP_URL . '/login.php');
    }
}

// Check if admin user exists
function adminUserExists($username) {
    $admin = fetchOne("SELECT id FROM admin_users WHERE username = ?", [$username]);
    return $admin !== null;
}

// Create admin user
function createAdminUser($username, $password, $email = null) {
    $data = [
        'username' => $username,
        'password' => password_hash($password, PASSWORD_DEFAULT),
        'email' => $email,
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    return insert('admin_users', $data);
}

// Update admin password
function updateAdminPassword($userId, $newPassword) {
    $data = [
        'password' => password_hash($newPassword, PASSWORD_DEFAULT),
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    return update('admin_users', $data, 'id = ?', [$userId]);
}

// Get admin by ID
function getAdmin($id) {
    return fetchOne("SELECT * FROM admin_users WHERE id = ?", [$id]);
}

// Get admin by username
function getAdminByUsername($username) {
    return fetchOne("SELECT * FROM admin_users WHERE username = ?", [$username]);
}

// Update admin profile
function updateAdminProfile($userId, $data) {
    $updateData = [];
    
    if (isset($data['email'])) {
        $updateData['email'] = $data['email'];
    }
    
    if (isset($data['name'])) {
        $updateData['name'] = $data['name'];
    }
    
    $updateData['updated_at'] = date('Y-m-d H:i:s');
    
    return update('admin_users', $updateData, 'id = ?', [$userId]);
}

// Customer portal password
function setCustomerPortalPassword($customerId, $password) {
    $data = [
        'portal_password' => password_hash($password, PASSWORD_DEFAULT),
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    return update('customers', $data, 'id = ?', [$customerId]);
}

// Check if customer has portal password
function customerHasPortalPassword($customerId) {
    $customer = fetchOne("SELECT portal_password FROM customers WHERE id = ?", [$customerId]);
    return $customer && !empty($customer['portal_password']);
}

// Generate portal password for customer
function generateCustomerPortalPassword($customerId) {
    $password = generateRandomString(8);
    setCustomerPortalPassword($customerId, $password);
    return $password;
}
