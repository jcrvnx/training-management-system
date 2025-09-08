<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';

// Check if installation is complete
if (!file_exists('install_complete.flag')) {
    // Installation needed
    redirect(url('install.php'));
}

// Try to connect to the database but don't create it if it doesn't exist
$conn = connectDB(false);

// If connection failed, redirect to installation
if (!$conn || !($conn instanceof mysqli)) {
    redirect(url('install.php'));
}

// Check if users table exists and has data
$result = $conn->query("SHOW TABLES LIKE 'users'");

if ($result->num_rows === 0) {
    // Table doesn't exist, redirect to installation
    $conn->close();
    redirect(url('install.php'));
}

// Check if admin user exists
$result = $conn->query("SELECT id FROM users WHERE email = 'admin@example.com'");

if ($result->num_rows === 0) {
    // No admin user, redirect to installation
    $conn->close();
    redirect(url('install.php'));
}

$conn->close();

// If all checks passed, redirect to login page
redirect(url('auth/login.php'));
?> 