<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect(url('auth/login.php'));
}

// Get database connection
$conn = connectDB(false);

// Check database connection
if (!$conn || !($conn instanceof mysqli)) {
    redirect(url('install.php'));
}

$error = '';
$users = [];
$trainings = [];

// Get all users for dropdown
$usersResult = $conn->query("SELECT id, full_name, email FROM users ORDER BY full_name");
if ($usersResult->num_rows > 0) {
    while ($user = $usersResult->fetch_assoc()) {
        $users[] = $user;
    }
}

// Get all trainings for dropdown
$trainingsResult = $conn->query("SELECT id, title, start_date, end_date FROM trainings ORDER BY start_date DESC");
if ($trainingsResult->num_rows > 0) {
    while ($training = $trainingsResult->fetch_assoc()) {
        $trainings[] = $training;
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $training_id = (int)$_POST['training_id'];
    $user_ids = isset($_POST['user_ids']) ? $_POST['user_ids'] : [];
    
    // Validate input
    if (empty($training_id)) {
        $error = 'Please select a training';
    } elseif (empty($user_ids)) {
        $error = 'Please select at least one user';
    } else {
        // Check if training exists
        $stmt = $conn->prepare("SELECT id FROM trainings WHERE id = ?");
        $stmt->bind_param("i", $training_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $error = 'Selected training does not exist';
        } else {
            // Begin transaction
            $conn->begin_transaction();
            $enrolledCount = 0;
            $errorCount = 0;
            
            try {
                // Prepare statement for enrollment
                $stmt = $conn->prepare("INSERT INTO enrollments (user_id, training_id) VALUES (?, ?)");
                $stmt->bind_param("ii", $user_id, $training_id);
                
                // Insert enrollments for each selected user
                foreach ($user_ids as $user_id) {
                    // Try to insert, ignore if duplicate
                    if ($stmt->execute()) {
                        $enrolledCount++;
                    } elseif ($conn->errno === 1062) { // Duplicate entry error
                        $errorCount++;
                    } else {
                        throw new Exception($conn->error);
                    }
                }
                
                // Commit transaction
                $conn->commit();
                
                // Success message
                $message = "$enrolledCount user(s) enrolled successfully";
                if ($errorCount > 0) {
                    $message .= " ($errorCount already enrolled)";
                }
                
                $_SESSION['message'] = $message;
                redirect(url('enrollments/'));
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollback();
                $error = 'Error enrolling users: ' . $e->getMessage();
            }
            
            $stmt->close();
        }
    }
}

closeDB($conn);

// Include header
include_once '../templates/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h1>Create New Enrollment</h1>
    </div>
</div>

<?php if (!empty($error)): ?>
    <?php echo showAlert($error, 'danger'); ?>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body">
        <?php if (empty($trainings)): ?>
            <div class="alert alert-warning">
                <h4 class="alert-heading">No Trainings Available</h4>
                <p>You need to create trainings before enrolling users.</p>
                <hr>
                <a href="<?php echo url('trainings/create.php'); ?>" class="btn btn-primary">Create Training</a>
            </div>
        <?php elseif (empty($users)): ?>
            <div class="alert alert-warning">
                <h4 class="alert-heading">No Users Available</h4>
                <p>You need to create users before enrolling them in trainings.</p>
                <hr>
                <a href="<?php echo url('users/create.php'); ?>" class="btn btn-primary">Create User</a>
            </div>
        <?php else: ?>
            <form method="POST">
                <div class="mb-4">
                    <label for="training_id" class="form-label">Select Training *</label>
                    <select class="form-select" id="training_id" name="training_id" required>
                        <option value="">-- Select Training --</option>
                        <?php foreach ($trainings as $training): ?>
                            <option value="<?php echo $training['id']; ?>">
                                <?php echo $training['title']; ?> 
                                (<?php echo date('M d, Y', strtotime($training['start_date'])); ?> - 
                                <?php echo date('M d, Y', strtotime($training['end_date'])); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label class="form-label">Select Users to Enroll *</label>
                    <div class="mb-2">
                        <button type="button" class="btn btn-sm btn-outline-primary" id="select-all-users">Select All</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="deselect-all-users">Deselect All</button>
                    </div>
                    <div class="card" style="max-height: 300px; overflow-y: auto;">
                        <div class="list-group list-group-flush">
                            <?php foreach ($users as $user): ?>
                                <label class="list-group-item">
                                    <input class="form-check-input me-1" type="checkbox" name="user_ids[]" value="<?php echo $user['id']; ?>">
                                    <?php echo $user['full_name']; ?> (<?php echo $user['email']; ?>)
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <div class="d-flex justify-content-between">
                    <a href="<?php echo url('enrollments/'); ?>" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Enroll Users</button>
                </div>
            </form>
            
            <!-- JavaScript for select/deselect all users -->
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    // Select all users
                    document.getElementById('select-all-users').addEventListener('click', function() {
                        document.querySelectorAll('input[name="user_ids[]"]').forEach(function(checkbox) {
                            checkbox.checked = true;
                        });
                    });
                    
                    // Deselect all users
                    document.getElementById('deselect-all-users').addEventListener('click', function() {
                        document.querySelectorAll('input[name="user_ids[]"]').forEach(function(checkbox) {
                            checkbox.checked = false;
                        });
                    });
                });
            </script>
        <?php endif; ?>
    </div>
</div>

<?php include_once '../templates/footer.php'; ?> 