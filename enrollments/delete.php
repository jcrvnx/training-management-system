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
    $_SESSION['message'] = 'Invalid enrollment ID';
    redirect(url('enrollments/'));
}

$enrollment_id = (int)$_GET['id'];

// Get database connection
$conn = connectDB(false);

if (!$conn || !($conn instanceof mysqli)) {
    redirect(url('install.php'));
}

// Get enrollment details before deletion for the message
$stmt = $conn->prepare("
    SELECT e.id, u.full_name, t.title 
    FROM enrollments e
    JOIN users u ON e.user_id = u.id
    JOIN trainings t ON e.training_id = t.id
    WHERE e.id = ?
");
$stmt->bind_param("i", $enrollment_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    closeDB($conn);
    $_SESSION['message'] = 'Enrollment not found';
    redirect(url('enrollments/'));
}

$enrollment = $result->fetch_assoc();
$stmt->close();

// Delete enrollment
$stmt = $conn->prepare("DELETE FROM enrollments WHERE id = ?");
$stmt->bind_param("i", $enrollment_id);

if ($stmt->execute()) {
    $_SESSION['message'] = 'Enrollment of "' . $enrollment['full_name'] . '" in "' . $enrollment['title'] . '" has been deleted';
} else {
    $_SESSION['message'] = 'Error deleting enrollment: ' . $conn->error;
}

$stmt->close();
closeDB($conn);

// Redirect back to enrollments list
redirect(url('enrollments/'));
?> 