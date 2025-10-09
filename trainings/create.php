<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/validator.php';

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
    // Sanitize input data
    $data = array(
        'title' => sanitizeString($_POST['title']),
        'description' => sanitizeString($_POST['description']),
        'start_date' => sanitizeString($_POST['start_date']),
        'end_date' => sanitizeString($_POST['end_date']),
        'location' => sanitizeString($_POST['location']),
        'max_participants' => sanitizeInt($_POST['max_participants']),
        'category_id' => !empty($_POST['category_id']) ? sanitizeInt($_POST['category_id']) : null,
        'instructor_id' => !empty($_POST['instructor_id']) ? sanitizeInt($_POST['instructor_id']) : getCurrentUser()['id'],
        'difficulty_level' => sanitizeString($_POST['difficulty_level']),
        'estimated_duration_hours' => !empty($_POST['estimated_duration_hours']) ? sanitizeFloat($_POST['estimated_duration_hours']) : null,
        'is_mandatory' => isset($_POST['is_mandatory']) ? 1 : 0,
        'enrollment_deadline' => !empty($_POST['enrollment_deadline']) ? sanitizeString($_POST['enrollment_deadline']) : null,
        'completion_deadline' => !empty($_POST['completion_deadline']) ? sanitizeString($_POST['completion_deadline']) : null,
        'passing_score' => !empty($_POST['passing_score']) ? sanitizeInt($_POST['passing_score']) : 70
    );
    
    // Define validation rules
    $rules = array(
        'title' => 'required|min_length:3|max_length:200',
        'description' => 'max_length:1000',
        'start_date' => 'required|date|date_after:yesterday',
        'end_date' => 'required|date',
        'location' => 'max_length:200',
        'max_participants' => 'integer|min:1|max:1000',
        'difficulty_level' => 'required|in:beginner,intermediate,advanced',
        'estimated_duration_hours' => 'numeric|min:0.5|max:1000',
        'passing_score' => 'integer|min:0|max:100'
    );
    
    // Add conditional validation for deadlines
    if (!empty($data['enrollment_deadline'])) {
        $rules['enrollment_deadline'] = 'date|date_before:' . $data['start_date'];
    }
    if (!empty($data['completion_deadline'])) {
        $rules['completion_deadline'] = 'date|date_after:' . $data['end_date'];
    }
    
    // Custom validation messages
    $messages = array(
        'title.min_length' => 'Training title must be at least 3 characters.',
        'start_date.date_after' => 'Start date must be in the future.',
        'max_participants.min' => 'Maximum participants must be at least 1.',
        'estimated_duration_hours.min' => 'Duration must be at least 0.5 hours.',
        'passing_score.min' => 'Passing score must be between 0 and 100.',
        'passing_score.max' => 'Passing score must be between 0 and 100.'
    );
    
    // Perform validation
    $validator = validate($data, $rules, $messages);
    
    // Custom validation for end date after start date
    if (!empty($data['start_date']) && !empty($data['end_date'])) {
        if (strtotime($data['end_date']) < strtotime($data['start_date'])) {
            $validator->errors['end_date'] = 'End date must be after start date.';
        }
    }
    
    if ($validator->validate() && empty($validator->errors['end_date'])) {
        // Get database connection
        $conn = connectDB();
        
        // Insert new training with enhanced fields
        $stmt = $conn->prepare(
            "INSERT INTO trainings (title, description, start_date, end_date, location, max_participants, category_id, instructor_id, difficulty_level, estimated_duration_hours, is_mandatory, enrollment_deadline, completion_deadline, passing_score) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        
        if (!$stmt) {
            $error = 'Error preparing statement: ' . $conn->error;
        } else {
            $stmt->bind_param("sssssiissdissi", 
                $data['title'], 
                $data['description'], 
                $data['start_date'], 
                $data['end_date'], 
                $data['location'], 
                $data['max_participants'], 
                $data['category_id'], 
                $data['instructor_id'], 
                $data['difficulty_level'], 
                $data['estimated_duration_hours'], 
                $data['is_mandatory'], 
                $data['enrollment_deadline'], 
                $data['completion_deadline'], 
                $data['passing_score']
            );
        
            if ($stmt->execute()) {
                $trainingId = $conn->insert_id;
                
                // Log activity
                logActivity('CREATE', 'trainings', $trainingId, null, array(
                    'title' => $data['title'],
                    'instructor_id' => $data['instructor_id'],
                    'category_id' => $data['category_id']
                ));
                
                // Success
                $_SESSION['message'] = 'Training has been created successfully';
                redirect(url('trainings/'));
            } else {
                // Error
                $error = 'Error creating training: ' . $conn->error;
            }
            
            $stmt->close();
        }
        closeDB($conn);
    } else {
        // Validation failed - get first error
        $errors = $validator->errors();
        $error = reset($errors); // Get first error message
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
        <form method="POST" id="createTrainingForm" novalidate>
            <div class="mb-3">
                <label for="title" class="form-label">Title *</label>
                <input type="text" class="form-control" id="title" name="title" value="<?php echo isset($data['title']) ? $data['title'] : ''; ?>" required>
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

<!-- Include validation library -->
<script src="<?php echo url('assets/js/validation.js'); ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const validator = new TMSValidator('#createTrainingForm');
    
    // Add validation rules
    validator.addRules({
        title: ['required', 'minLength:3', 'maxLength:200'],
        description: ['maxLength:1000'],
        start_date: ['required', 'date', 'dateAfter:today'],
        end_date: ['required', 'date'],
        location: ['maxLength:200'],
        max_participants: ['integer', 'min:1', 'max:1000'],
        difficulty_level: ['required'],
        estimated_duration_hours: ['numeric', 'min:0.5', 'max:1000'],
        passing_score: ['integer', 'min:0', 'max:100']
    });
    
    // Custom validation for dates
    const startDate = document.getElementById('start_date');
    const endDate = document.getElementById('end_date');
    const enrollmentDeadline = document.getElementById('enrollment_deadline');
    const completionDeadline = document.getElementById('completion_deadline');
    
    function validateDateSequence() {
        const start = new Date(startDate.value);
        const end = new Date(endDate.value);
        
        // Validate end date is after start date
        if (startDate.value && endDate.value && end <= start) {
            endDate.classList.add('is-invalid');
            let feedback = endDate.parentNode.querySelector('.invalid-feedback');
            if (!feedback) {
                feedback = document.createElement('div');
                feedback.className = 'invalid-feedback';
                endDate.parentNode.appendChild(feedback);
            }
            feedback.textContent = 'End date must be after start date.';
        } else {
            endDate.classList.remove('is-invalid');
            const feedback = endDate.parentNode.querySelector('.invalid-feedback');
            if (feedback) feedback.remove();
        }
        
        // Validate enrollment deadline is before start date
        if (startDate.value && enrollmentDeadline.value) {
            const enrollment = new Date(enrollmentDeadline.value);
            if (enrollment >= start) {
                enrollmentDeadline.classList.add('is-invalid');
                let feedback = enrollmentDeadline.parentNode.querySelector('.invalid-feedback');
                if (!feedback) {
                    feedback = document.createElement('div');
                    feedback.className = 'invalid-feedback';
                    enrollmentDeadline.parentNode.appendChild(feedback);
                }
                feedback.textContent = 'Enrollment deadline must be before start date.';
            } else {
                enrollmentDeadline.classList.remove('is-invalid');
                const feedback = enrollmentDeadline.parentNode.querySelector('.invalid-feedback');
                if (feedback) feedback.remove();
            }
        }
        
        // Validate completion deadline is after end date
        if (endDate.value && completionDeadline.value) {
            const completion = new Date(completionDeadline.value);
            if (completion <= end) {
                completionDeadline.classList.add('is-invalid');
                let feedback = completionDeadline.parentNode.querySelector('.invalid-feedback');
                if (!feedback) {
                    feedback = document.createElement('div');
                    feedback.className = 'invalid-feedback';
                    completionDeadline.parentNode.appendChild(feedback);
                }
                feedback.textContent = 'Completion deadline must be after end date.';
            } else {
                completionDeadline.classList.remove('is-invalid');
                const feedback = completionDeadline.parentNode.querySelector('.invalid-feedback');
                if (feedback) feedback.remove();
            }
        }
    }
    
    // Add event listeners for date validation
    [startDate, endDate, enrollmentDeadline, completionDeadline].forEach(field => {
        field.addEventListener('change', validateDateSequence);
    });
    
    // Set minimum date to today for start date
    const today = new Date().toISOString().split('T')[0];
    startDate.setAttribute('min', today);
    
    // Dynamic minimum date setting
    startDate.addEventListener('change', function() {
        if (this.value) {
            endDate.setAttribute('min', this.value);
            if (enrollmentDeadline.value) {
                enrollmentDeadline.setAttribute('max', this.value);
            }
        }
    });
    
    endDate.addEventListener('change', function() {
        if (this.value && completionDeadline.value) {
            completionDeadline.setAttribute('min', this.value);
        }
    });
});
</script>

<?php include_once '../templates/footer.php'; ?>
