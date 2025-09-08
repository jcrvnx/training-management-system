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
$error = '';
$training = null;

// Get database connection
$conn = connectDB();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitize($_POST['title']);
    $description = sanitize($_POST['description']);
    $start_date = sanitize($_POST['start_date']);
    $end_date = sanitize($_POST['end_date']);
    $location = sanitize($_POST['location']);
    $max_participants = (int)sanitize($_POST['max_participants']);
    
    // Validate input
    if (empty($title) || empty($start_date) || empty($end_date)) {
        $error = 'Please fill all required fields';
    } else if (strtotime($end_date) < strtotime($start_date)) {
        $error = 'End date cannot be before start date';
    } else {
        // Update training
        $stmt = $conn->prepare(
            "UPDATE trainings 
             SET title = ?, description = ?, start_date = ?, end_date = ?, location = ?, max_participants = ? 
             WHERE id = ?"
        );
        $stmt->bind_param("sssssii", $title, $description, $start_date, $end_date, $location, $max_participants, $training_id);
        
        if ($stmt->execute()) {
            // Success
            $_SESSION['message'] = 'Training has been updated successfully';
            redirect(url('trainings/'));
        } else {
            // Error
            $error = 'Error updating training: ' . $conn->error;
        }
        
        $stmt->close();
    }
} else {
    // Get training data
    $stmt = $conn->prepare("SELECT * FROM trainings WHERE id = ?");
    $stmt->bind_param("i", $training_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        closeDB($conn);
        $_SESSION['message'] = 'Training not found';
        redirect(url('trainings/'));
    }
    
    $training = $result->fetch_assoc();
    $stmt->close();
}

closeDB($conn);

// Include header
include_once '../templates/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h1>Edit Training</h1>
    </div>
</div>

<?php if (!empty($error)): ?>
    <?php echo showAlert($error, 'danger'); ?>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body">
        <form method="POST">
            <div class="mb-3">
                <label for="title" class="form-label">Title *</label>
                <input type="text" class="form-control" id="title" name="title" value="<?php echo $training['title']; ?>" required>
            </div>
            
            <div class="mb-3">
                <label for="description" class="form-label">Description</label>
                <textarea class="form-control" id="description" name="description" rows="3"><?php echo $training['description']; ?></textarea>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="start_date" class="form-label">Start Date *</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $training['start_date']; ?>" required>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="end_date" class="form-label">End Date *</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $training['end_date']; ?>" required>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="location" class="form-label">Location</label>
                    <input type="text" class="form-control" id="location" name="location" value="<?php echo $training['location']; ?>">
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="max_participants" class="form-label">Maximum Participants</label>
                    <input type="number" class="form-control" id="max_participants" name="max_participants" value="<?php echo $training['max_participants']; ?>" min="1">
                </div>
            </div>
            
            <div class="d-flex justify-content-between">
                <a href="<?php echo url('trainings/'); ?>" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Update Training</button>
            </div>
        </form>
    </div>
</div>

<?php include_once '../templates/footer.php'; ?> 