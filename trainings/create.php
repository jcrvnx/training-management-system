<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

// Check permissions
requirePermission('trainings.create');

$error = '';

// Get categories and instructors for form
$conn = connectDB(false);
$categories = array();
$instructors = array();

if ($conn) {
    // Get categories
    $result = $conn->query("SELECT * FROM course_categories WHERE is_active = 1 ORDER BY name");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
    }
    
    // Get instructors (trainers and admins)
    $result = $conn->query("SELECT id, full_name FROM users WHERE role IN ('trainer', 'admin') AND is_active = 1 ORDER BY full_name");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $instructors[] = $row;
        }
    }
    
    closeDB($conn);
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitize($_POST['title']);
    $description = sanitize($_POST['description']);
    $start_date = sanitize($_POST['start_date']);
    $end_date = sanitize($_POST['end_date']);
    $location = sanitize($_POST['location']);
    $max_participants = (int)sanitize($_POST['max_participants']);
    $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $instructor_id = !empty($_POST['instructor_id']) ? (int)$_POST['instructor_id'] : getCurrentUser()['id'];
    $difficulty_level = sanitize($_POST['difficulty_level']);
    $estimated_duration_hours = !empty($_POST['estimated_duration_hours']) ? (float)$_POST['estimated_duration_hours'] : null;
    $is_mandatory = isset($_POST['is_mandatory']) ? 1 : 0;
    $enrollment_deadline = !empty($_POST['enrollment_deadline']) ? sanitize($_POST['enrollment_deadline']) : null;
    $completion_deadline = !empty($_POST['completion_deadline']) ? sanitize($_POST['completion_deadline']) : null;
    $passing_score = !empty($_POST['passing_score']) ? (int)$_POST['passing_score'] : 70;
    
    // Validate input
    if (empty($title) || empty($start_date) || empty($end_date)) {
        $error = 'Please fill all required fields';
    } else if (strtotime($end_date) < strtotime($start_date)) {
        $error = 'End date cannot be before start date';
    } else if ($enrollment_deadline && strtotime($enrollment_deadline) > strtotime($start_date)) {
        $error = 'Enrollment deadline cannot be after start date';
    } else if ($completion_deadline && strtotime($completion_deadline) < strtotime($end_date)) {
        $error = 'Completion deadline cannot be before end date';
    } else if (!in_array($difficulty_level, ['beginner', 'intermediate', 'advanced'])) {
        $error = 'Invalid difficulty level selected';
    } else {
        // Get database connection
        $conn = connectDB();
        
        // Insert new training with enhanced fields
        $stmt = $conn->prepare(
            "INSERT INTO trainings (title, description, start_date, end_date, location, max_participants, category_id, instructor_id, difficulty_level, estimated_duration_hours, is_mandatory, enrollment_deadline, completion_deadline, passing_score) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("sssssiissdssi", $title, $description, $start_date, $end_date, $location, $max_participants, $category_id, $instructor_id, $difficulty_level, $estimated_duration_hours, $is_mandatory, $enrollment_deadline, $completion_deadline, $passing_score);
        
        if ($stmt->execute()) {
            $trainingId = $conn->insert_id;
            
            // Log activity
            logActivity('CREATE', 'trainings', $trainingId, null, array(
                'title' => $title,
                'instructor_id' => $instructor_id,
                'category_id' => $category_id
            ));
            
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
                    <label for="category_id" class="form-label">Category</label>
                    <select class="form-select" id="category_id" name="category_id">
                        <option value="">Select category...</option>
                        <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="instructor_id" class="form-label">Instructor</label>
                    <select class="form-select" id="instructor_id" name="instructor_id">
                        <?php foreach ($instructors as $instructor): ?>
                        <option value="<?php echo $instructor['id']; ?>" <?php echo ($instructor['id'] == getCurrentUser()['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($instructor['full_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
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
            
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="difficulty_level" class="form-label">Difficulty Level *</label>
                    <select class="form-select" id="difficulty_level" name="difficulty_level" required>
                        <option value="beginner" selected>Beginner</option>
                        <option value="intermediate">Intermediate</option>
                        <option value="advanced">Advanced</option>
                    </select>
                </div>
                
                <div class="col-md-4 mb-3">
                    <label for="estimated_duration_hours" class="form-label">Duration (Hours)</label>
                    <input type="number" class="form-control" id="estimated_duration_hours" name="estimated_duration_hours" step="0.5" min="0.5">
                </div>
                
                <div class="col-md-4 mb-3">
                    <label for="passing_score" class="form-label">Passing Score (%)</label>
                    <input type="number" class="form-control" id="passing_score" name="passing_score" value="70" min="0" max="100">
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="enrollment_deadline" class="form-label">Enrollment Deadline</label>
                    <input type="date" class="form-control" id="enrollment_deadline" name="enrollment_deadline">
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="completion_deadline" class="form-label">Completion Deadline</label>
                    <input type="date" class="form-control" id="completion_deadline" name="completion_deadline">
                </div>
            </div>
            
            <div class="mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="is_mandatory" name="is_mandatory">
                    <label class="form-check-label" for="is_mandatory">
                        This is a mandatory training
                    </label>
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