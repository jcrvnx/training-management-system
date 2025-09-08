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
    $_SESSION['message'] = 'Invalid training ID';
    redirect(url('trainings/'));
}

$training_id = (int)$_GET['id'];

// Get database connection
$conn = connectDB();

// Check if training exists
$stmt = $conn->prepare("SELECT id FROM trainings WHERE id = ?");
$stmt->bind_param("i", $training_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    closeDB($conn);
    $_SESSION['message'] = 'Training not found';
    redirect(url('trainings/'));
}

$stmt->close();

// Delete training
$stmt = $conn->prepare("DELETE FROM trainings WHERE id = ?");
$stmt->bind_param("i", $training_id);

if ($stmt->execute()) {
    $_SESSION['message'] = 'Training has been deleted successfully';
} else {
    $_SESSION['message'] = 'Error deleting training: ' . $conn->error;
}

$stmt->close();
closeDB($conn);

// Redirect back to trainings list
redirect(url('trainings/'));
?> 