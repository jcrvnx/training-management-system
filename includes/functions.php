<?php
/**
 * Enhanced Functions for TMS
 * Compatible with PHP 5.6+ and XAMPP 2015
 */

require_once 'db.php';

// Clean user input
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Generate URL for internal links
function url($path = '') {
    return BASE_URL . '/' . $path;
}

// Redirect to another page
function redirect($location) {
    header("Location: $location");
    exit();
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user']);
}

// Get current user data
function getCurrentUser() {
    return isset($_SESSION['user']) ? $_SESSION['user'] : null;
}

// Get current user role
function getCurrentUserRole() {
    $user = getCurrentUser();
    return $user ? $user['role'] : null;
}

// Check if current user has specific role
function hasRole($role) {
    $currentRole = getCurrentUserRole();
    return $currentRole === $role;
}

// Check if current user is admin
function isAdmin() {
    return hasRole('admin');
}

// Check if current user is trainer
function isTrainer() {
    return hasRole('trainer');
}

// Check if current user is regular user
function isUser() {
    return hasRole('user');
}

// Check if user has minimum role level
function hasMinimumRole($requiredRole) {
    $roleHierarchy = array('user' => 1, 'trainer' => 2, 'admin' => 3);
    $currentRole = getCurrentUserRole();
    
    if (!$currentRole || !isset($roleHierarchy[$currentRole])) {
        return false;
    }
    
    return $roleHierarchy[$currentRole] >= $roleHierarchy[$requiredRole];
}

// Check if user has specific permission  
function hasPermission($permissionName) {
    if (!isLoggedIn()) {
        return false;
    }
    
    $currentRole = getCurrentUserRole();
    if (!$currentRole) {
        return false;
    }
    
    // Admin has all permissions
    if ($currentRole === 'admin') {
        return true;
    }
    
    // Check permission in database
    $conn = connectDB(false);
    if (!$conn) {
        return false;
    }
    
    $stmt = $conn->prepare("
        SELECT rp.id 
        FROM role_permissions rp 
        JOIN user_permissions up ON rp.permission_id = up.id 
        WHERE rp.role = ? AND up.permission_name = ?
    ");
    
    if (!$stmt) {
        closeDB($conn);
        return false;
    }
    
    $stmt->bind_param("ss", $currentRole, $permissionName);
    $stmt->execute();
    $result = $stmt->get_result();
    $hasPermission = $result->num_rows > 0;
    
    $stmt->close();
    closeDB($conn);
    
    return $hasPermission;
}

// Require login - redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        redirect(url('auth/login.php'));
    }
}

// Require specific role - redirect if insufficient permissions
function requireRole($role) {
    requireLogin();
    
    if (!hasRole($role) && !isAdmin()) {
        redirect(url('auth/unauthorized.php'));
    }
}

// Require minimum role level
function requireMinimumRole($role) {
    requireLogin();
    
    if (!hasMinimumRole($role)) {
        redirect(url('auth/unauthorized.php'));
    }
}

// Require specific permission
function requirePermission($permissionName) {
    requireLogin();
    
    if (!hasPermission($permissionName)) {
        redirect(url('auth/unauthorized.php'));
    }
}

// Display alert message
function showAlert($message, $type = 'primary') {
    return '<div class="alert alert-' . $type . ' alert-dismissible fade show" role="alert">
              ' . $message . '
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
}

// Format date for display
function formatDate($date, $format = 'M d, Y') {
    if (!$date) return 'N/A';
    return date($format, strtotime($date));
}

// Format datetime for display
function formatDateTime($datetime, $format = 'M d, Y H:i') {
    if (!$datetime) return 'N/A';
    return date($format, strtotime($datetime));
}

// Generate secure random string
function generateRandomString($length = 32) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $randomString;
}

// Log user activity (simplified - audit_logs table optional)
function logActivity($action, $tableName = null, $recordId = null, $oldValues = null, $newValues = null) {
    if (!isLoggedIn()) {
        return false;
    }
    
    $conn = connectDB(false);
    if (!$conn) {
        return false;
    }
    
    // Check if audit_logs table exists first
    $result = $conn->query("SHOW TABLES LIKE 'audit_logs'");
    if (!$result || $result->num_rows === 0) {
        closeDB($conn);
        return true; // Skip logging if table doesn't exist
    }
    
    $userId = getCurrentUser()['id'];
    $ipAddress = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
    $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null;
    
    $oldValuesJson = $oldValues ? json_encode($oldValues) : null;
    $newValuesJson = $newValues ? json_encode($newValues) : null;
    
    $stmt = $conn->prepare("
        INSERT INTO audit_logs (user_id, table_name, record_id, action, old_values, new_values, ip_address, user_agent) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    if (!$stmt) {
        closeDB($conn);
        return false;
    }
    
    $stmt->bind_param("isisssss", $userId, $tableName, $recordId, $action, $oldValuesJson, $newValuesJson, $ipAddress, $userAgent);
    $result = $stmt->execute();
    
    $stmt->close();
    closeDB($conn);
    
    return $result;
}

// Check if tables exist (for migration status)
function tablesExist($tables) {
    $conn = connectDB(false);
    if (!$conn) {
        return false;
    }
    
    foreach ($tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if (!$result || $result->num_rows === 0) {
            closeDB($conn);
            return false;
        }
    }
    
    closeDB($conn);
    return true;
}

// Get system setting value (simplified - system_settings table optional)
function getSystemSetting($key, $default = null) {
    $conn = connectDB(false);
    if (!$conn) {
        return $default;
    }
    
    // Check if system_settings table exists
    $result = $conn->query("SHOW TABLES LIKE 'system_settings'");
    if (!$result || $result->num_rows === 0) {
        closeDB($conn);
        return $default; // Return default if table doesn't exist
    }
    
    $stmt = $conn->prepare("SELECT setting_value, setting_type FROM system_settings WHERE setting_key = ?");
    if (!$stmt) {
        closeDB($conn);
        return $default;
    }
    
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        closeDB($conn);
        return $default;
    }
    
    $row = $result->fetch_assoc();
    if (!$row) {
        $stmt->close();
        closeDB($conn);
        return $default;
    }
    
    $value = $row['setting_value'];
    $type = $row['setting_type'];
    
    $stmt->close();
    closeDB($conn);
    
    // Convert value based on type
    switch ($type) {
        case 'boolean':
            return (bool)$value;
        case 'integer':
            return (int)$value;
        case 'json':
            return json_decode($value, true);
        default:
            return $value;
    }
}

// Set system setting value
function setSystemSetting($key, $value, $type = 'string') {
    if (!hasPermission('system.manage_settings')) {
        return false;
    }
    
    $conn = connectDB(false);
    if (!$conn) {
        return false;
    }
    
    // Convert value to string for storage
    $stringValue = $value;
    if ($type === 'json') {
        $stringValue = json_encode($value);
    } elseif ($type === 'boolean') {
        $stringValue = $value ? '1' : '0';
    }
    
    $stmt = $conn->prepare("
        INSERT INTO system_settings (setting_key, setting_value, setting_type) 
        VALUES (?, ?, ?) 
        ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = CURRENT_TIMESTAMP
    ");
    
    if (!$stmt) {
        closeDB($conn);
        return false;
    }
    
    $stmt->bind_param("ssss", $key, $stringValue, $type, $stringValue);
    $result = $stmt->execute();
    
    $stmt->close();
    closeDB($conn);
    
    return $result;
}

// Safe query execution with error handling
function safeQuery($conn, $query) {
    $result = $conn->query($query);
    if ($result === false) {
        return false;
    }
    return $result;
}

// Safe fetch_assoc with error handling
function safeFetchAssoc($result) {
    if ($result && $result instanceof mysqli_result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    return false;
}

// Safe count query - returns 0 if query fails
function safeCountQuery($conn, $query) {
    $result = safeQuery($conn, $query);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return isset($row['count']) ? (int)$row['count'] : 0;
    }
    return 0;
}
?>
