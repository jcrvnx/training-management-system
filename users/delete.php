<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect(url('auth/login.php'));
}

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['message'] = 'Invalid user ID';
    redirect(url('users/'));
}

$user_id = (int)$_GET['id'];

// Prevent users from deleting themselves
if ($user_id == $_SESSION['user']['id']) {
    $_SESSION['message'] = 'You cannot delete your own account';
    redirect(url('users/'));
}

// Get database connection
$conn = connectDB(false);

if (!$conn || !($conn instanceof mysqli)) {
    redirect(url('install.php'));
}

// Check if user exists
$stmt = $conn->prepare("SELECT id, full_name FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    closeDB($conn);
    $_SESSION['message'] = 'User not found';
    redirect(url('users/'));
}

$user = $result->fetch_assoc();
$stmt->close();

// Delete user
$stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);

if ($stmt->execute()) {
    $_SESSION['message'] = 'User "' . $user['full_name'] . '" has been deleted successfully';
} else {
    $_SESSION['message'] = 'Error deleting user: ' . $conn->error;
}

$stmt->close();
closeDB($conn);

// Redirect back to users list
redirect(url('users/'));
?> 