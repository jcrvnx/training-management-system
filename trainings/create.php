<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect(url('auth/login.php'));
}

$error = '';

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
        // Get database connection
        $conn = connectDB();
        
        // Insert new training
        $stmt = $conn->prepare(
            "INSERT INTO trainings (title, description, start_date, end_date, location, max_participants) 
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("sssssi", $title, $description, $start_date, $end_date, $location, $max_participants);
        
        if ($stmt->execute()) {
            // Success
            $_SESSION['message'] = 'Training has been created successfully';
            redirect(url('trainings/'));
        } else {
            // Error
            $error = 'Error creating training: ' . $conn->error;
        }
        
        $stmt->close();
        closeDB($conn);
    }
}

// Include header
include_once '../templates/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h1>Create New Training</h1>
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
                <input type="text" class="form-control" id="title" name="title" required>
            </div>
            
            <div class="mb-3">
                <label for="description" class="form-label">Description</label>
                <textarea class="form-control" id="description" name="description" rows="3"></textarea>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="start_date" class="form-label">Start Date *</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" required>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="end_date" class="form-label">End Date *</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" required>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="location" class="form-label">Location</label>
                    <input type="text" class="form-control" id="location" name="location">
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="max_participants" class="form-label">Maximum Participants</label>
                    <input type="number" class="form-control" id="max_participants" name="max_participants" value="20" min="1">
                </div>
            </div>
            
            <div class="d-flex justify-content-between">
                <a href="<?php echo url('trainings/'); ?>" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Create Training</button>
            </div>
        </form>
    </div>
</div>

<?php include_once '../templates/footer.php'; ?> 