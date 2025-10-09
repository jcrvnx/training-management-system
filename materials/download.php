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
    $_SESSION['error'] = 'Invalid material ID.';
    redirect(url('trainings/index.php'));
}

$materialId = (int)$_GET['id'];
$currentUser = getCurrentUser();
$userId = $currentUser['id'];

// Get database connection
$conn = connectDB(false);

if (!$conn) {
    redirect(url('install_schema.php'));
}

// Check if course_materials table exists
if ($conn->query("SHOW TABLES LIKE 'course_materials'")->num_rows === 0) {
    $_SESSION['error'] = 'Course materials system not available.';
    redirect(url('trainings/index.php'));
}

// Get material details and check permissions
$materialQuery = "SELECT cm.*, t.title as training_title 
                 FROM course_materials cm 
                 LEFT JOIN trainings t ON cm.training_id = t.id 
                 WHERE cm.id = ?";
$materialStmt = $conn->prepare($materialQuery);
$materialStmt->bind_param("i", $materialId);
$materialStmt->execute();
$materialResult = $materialStmt->get_result();

if ($materialResult->num_rows === 0) {
    $_SESSION['error'] = 'Material not found.';
    redirect(url('trainings/index.php'));
}

$material = $materialResult->fetch_assoc();
$materialStmt->close();

// Check if user is enrolled in the training or has appropriate permissions
$hasAccess = false;

if (hasMinimumRole('trainer')) {
    $hasAccess = true; // Trainers and admins can access all materials
} else {
    // Check if user is enrolled in the training
    $enrollmentQuery = "SELECT id FROM enrollments WHERE user_id = ? AND training_id = ?";
    $enrollmentStmt = $conn->prepare($enrollmentQuery);
    $enrollmentStmt->bind_param("ii", $userId, $material['training_id']);
    $enrollmentStmt->execute();
    $enrollmentResult = $enrollmentStmt->get_result();
    $hasAccess = $enrollmentResult->num_rows > 0;
    $enrollmentStmt->close();
}

if (!$hasAccess) {
    $_SESSION['error'] = 'You do not have access to this material.';
    redirect(url('auth/unauthorized.php'));
}

// Check if material is downloadable
if (!$material['is_downloadable']) {
    $_SESSION['error'] = 'This material is not available for download.';
    redirect(url('trainings/view.php?id=' . $material['training_id']));
}

closeDB($conn);

// Check if file exists
$filePath = $material['file_path'];
if (!file_exists($filePath)) {
    $_SESSION['error'] = 'File not found on server.';
    redirect(url('trainings/view.php?id=' . $material['training_id']));
}

// Log download (optional - you could add a downloads table)
error_log("Material downloaded: {$material['title']} by user {$userId}");

// Set appropriate headers for download
$fileName = $material['file_name'];
$mimeType = $material['mime_type'] ?: 'application/octet-stream';
$fileSize = $material['file_size'];

// Security: Clean filename to prevent path traversal
$fileName = basename($fileName);

header('Content-Type: ' . $mimeType);
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Content-Length: ' . $fileSize);
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('Expires: 0');

// Clear any output buffering
if (ob_get_level()) {
    ob_end_clean();
}

// Output file
readfile($filePath);
exit();
?>