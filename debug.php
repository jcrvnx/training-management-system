<?php
/**
 * Debug and Testing Script for TMS
 * Use this to test database connectivity and core functionality
 */

// Start session and include core files
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>TMS Debug & Test</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css' rel='stylesheet'>
</head>
<body>
<div class='container mt-4'>
    <h1 class='text-primary'>🔧 TMS Debug & Test Console</h1>
    <hr>
";

// Test 1: Database Connection
echo "<div class='card mb-3'>
    <div class='card-header'><h5>📡 Database Connection Test</h5></div>
    <div class='card-body'>";

$conn = connectDB(false);
if ($conn && $conn instanceof mysqli) {
    echo "<div class='alert alert-success'>✅ Database connection successful</div>";
    
    // Test 2: Check Tables
    echo "<h6>📋 Tables Check:</h6>";
    $requiredTables = [
        'users', 'trainings', 'enrollments', 'quizzes', 'certificates', 
        'course_categories', 'notifications', 'file_uploads'
    ];
    
    $missingTables = [];
    foreach ($requiredTables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result && $result->num_rows > 0) {
            echo "<span class='badge bg-success me-2'>$table ✅</span>";
        } else {
            echo "<span class='badge bg-danger me-2'>$table ❌</span>";
            $missingTables[] = $table;
        }
    }
    
    if (empty($missingTables)) {
        echo "<div class='alert alert-success mt-2'>All required tables exist</div>";
    } else {
        echo "<div class='alert alert-warning mt-2'>Missing tables: " . implode(', ', $missingTables) . "</div>";
    }
    
    // Test 3: Sample Data Check
    echo "<h6 class='mt-3'>👥 Sample Data Check:</h6>";
    $userCount = safeCountQuery($conn, "SELECT COUNT(*) as count FROM users");
    $categoryCount = safeCountQuery($conn, "SELECT COUNT(*) as count FROM course_categories");
    
    echo "<p>Users: <strong>$userCount</strong> | Categories: <strong>$categoryCount</strong></p>";
    
    if ($userCount > 0) {
        echo "<h6>Default Users:</h6>";
        $users = safeQuery($conn, "SELECT full_name, email, role FROM users LIMIT 5");
        if ($users && $users->num_rows > 0) {
            echo "<table class='table table-sm'><tr><th>Name</th><th>Email</th><th>Role</th></tr>";
            while ($user = $users->fetch_assoc()) {
                echo "<tr><td>{$user['full_name']}</td><td>{$user['email']}</td><td><span class='badge bg-info'>{$user['role']}</span></td></tr>";
            }
            echo "</table>";
        } else {
            echo "<p>No users found or query failed.</p>";
        }
    }
    
} else {
    echo "<div class='alert alert-danger'>❌ Database connection failed</div>";
}

echo "</div></div>";

// Test 4: Authentication Test
echo "<div class='card mb-3'>
    <div class='card-header'><h5>🔐 Authentication Test</h5></div>
    <div class='card-body'>";

if (isLoggedIn()) {
    $user = getCurrentUser();
    echo "<div class='alert alert-info'>✅ User is logged in: <strong>{$user['name']}</strong> ({$user['role']})</div>";
    echo "<a href='auth/logout.php' class='btn btn-warning'>Logout</a>";
} else {
    echo "<div class='alert alert-warning'>⚠️ User not logged in</div>";
    echo "<a href='auth/login.php' class='btn btn-primary'>Go to Login</a>";
}

echo "</div></div>";

// Test 5: File Structure Check
echo "<div class='card mb-3'>
    <div class='card-header'><h5>📁 File Structure Test</h5></div>
    <div class='card-body'>";

$criticalFiles = [
    'includes/config.php' => 'Configuration',
    'includes/db.php' => 'Database Functions',
    'includes/functions.php' => 'Core Functions',
    'auth/login.php' => 'Login Page',
    'dashboard.php' => 'Dashboard',
    'install_complete.flag' => 'Installation Flag'
];

foreach ($criticalFiles as $file => $desc) {
    if (file_exists($file)) {
        echo "<span class='badge bg-success me-2'>$desc ✅</span>";
    } else {
        echo "<span class='badge bg-danger me-2'>$desc ❌</span>";
    }
}

echo "</div></div>";

// Test 6: PHP Configuration
echo "<div class='card mb-3'>
    <div class='card-header'><h5>🐘 PHP Configuration</h5></div>
    <div class='card-body'>";

echo "<p><strong>PHP Version:</strong> " . PHP_VERSION . "</p>";
echo "<p><strong>Extensions:</strong> ";
$required_extensions = ['mysqli', 'json', 'session'];
foreach ($required_extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "<span class='badge bg-success me-1'>$ext ✅</span>";
    } else {
        echo "<span class='badge bg-danger me-1'>$ext ❌</span>";
    }
}
echo "</p>";

echo "</div></div>";

// Navigation
echo "<div class='card'>
    <div class='card-header'><h5>🧭 Navigation</h5></div>
    <div class='card-body'>
        <a href='index.php' class='btn btn-primary me-2'>🏠 Home</a>
        <a href='dashboard.php' class='btn btn-success me-2'>📊 Dashboard</a>
        <a href='auth/login.php' class='btn btn-info me-2'>🔑 Login</a>
        <a href='install_schema.php' class='btn btn-warning me-2'>⚙️ Installer</a>
    </div>
</div>";

if ($conn) closeDB($conn);

echo "</div>
</body>
</html>";
?>
