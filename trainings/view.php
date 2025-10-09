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
    redirect(url('trainings/index.php'));
}

$trainingId = (int)$_GET['id'];
$currentUser = getCurrentUser();
$userId = $currentUser['id'];

// Get database connection
$conn = connectDB(false);

if (!$conn) {
    redirect(url('install_schema.php'));
}

// Get training details
$query = "SELECT t.* FROM trainings t WHERE t.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $trainingId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = 'Training not found.';
    redirect(url('trainings/index.php'));
}

$training = $result->fetch_assoc();
$stmt->close();

// Check if user is enrolled
$enrollmentQuery = "SELECT * FROM enrollments WHERE user_id = ? AND training_id = ?";
$enrollStmt = $conn->prepare($enrollmentQuery);
$enrollStmt->bind_param("ii", $userId, $trainingId);
$enrollStmt->execute();
$enrollmentResult = $enrollStmt->get_result();
$isEnrolled = $enrollmentResult->num_rows > 0;
$enrollment = $isEnrolled ? $enrollmentResult->fetch_assoc() : null;
$enrollStmt->close();

// Get enrollment count
$countQuery = "SELECT COUNT(*) as count FROM enrollments WHERE training_id = ?";
$countStmt = $conn->prepare($countQuery);
$countStmt->bind_param("i", $trainingId);
$countStmt->execute();
$countResult = $countStmt->get_result();
$enrollmentCount = $countResult->fetch_assoc()['count'];
$countStmt->close();

// Get course materials if they exist
$materials = [];
$materialsQuery = "SELECT * FROM course_materials WHERE training_id = ? ORDER BY sort_order ASC";
if ($conn->query("SHOW TABLES LIKE 'course_materials'")->num_rows > 0) {
    $materialsStmt = $conn->prepare($materialsQuery);
    $materialsStmt->bind_param("i", $trainingId);
    $materialsStmt->execute();
    $materialsResult = $materialsStmt->get_result();
    while ($material = $materialsResult->fetch_assoc()) {
        $materials[] = $material;
    }
    $materialsStmt->close();
}

// Get quizzes if they exist
$quizzes = [];
$quizzesQuery = "SELECT * FROM quizzes WHERE training_id = ? AND is_active = 1 ORDER BY created_at ASC";
if ($conn->query("SHOW TABLES LIKE 'quizzes'")->num_rows > 0) {
    $quizzesStmt = $conn->prepare($quizzesQuery);
    $quizzesStmt->bind_param("i", $trainingId);
    $quizzesStmt->execute();
    $quizzesResult = $quizzesStmt->get_result();
    while ($quiz = $quizzesResult->fetch_assoc()) {
        $quizzes[] = $quiz;
    }
    $quizzesStmt->close();
}

closeDB($conn);

// Include header
include_once '../templates/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1><i class="fas fa-book text-primary me-2"></i><?php echo htmlspecialchars($training['title']); ?></h1>
                <p class="text-muted mb-0">Training Details</p>
            </div>
            <div>
                <a href="<?php echo url('trainings/index.php'); ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i>Back to Trainings
                </a>
                <?php if (hasMinimumRole('trainer')): ?>
                <a href="<?php echo url('trainings/edit.php?id=' . $training['id']); ?>" class="btn btn-primary">
                    <i class="fas fa-edit me-1"></i>Edit Training
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Training Info -->
<div class="row">
    <div class="col-md-8">
        <div class="card shadow mb-4">
            <div class="card-body">
                <h5 class="card-title">Description</h5>
                <p class="card-text">
                    <?php echo $training['description'] ? nl2br(htmlspecialchars($training['description'])) : 'No description available.'; ?>
                </p>
                
                <?php if (!empty($materials)): ?>
                <hr>
                <h5>Course Materials</h5>
                <div class="list-group">
                    <?php foreach ($materials as $material): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-file me-2"></i>
                            <strong><?php echo htmlspecialchars($material['title']); ?></strong>
                            <?php if ($material['description']): ?>
                            <br><small class="text-muted"><?php echo htmlspecialchars($material['description']); ?></small>
                            <?php endif; ?>
                        </div>
                        <?php if ($isEnrolled && $material['is_downloadable']): ?>
                        <a href="<?php echo url('materials/download.php?id=' . $material['id']); ?>" class="btn btn-sm btn-primary">
                            <i class="fas fa-download"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($quizzes)): ?>
                <hr>
                <h5>Quizzes & Assessments</h5>
                <div class="list-group">
                    <?php foreach ($quizzes as $quiz): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-question-circle me-2"></i>
                            <strong><?php echo htmlspecialchars($quiz['title']); ?></strong>
                            <?php if ($quiz['description']): ?>
                            <br><small class="text-muted"><?php echo htmlspecialchars($quiz['description']); ?></small>
                            <?php endif; ?>
                            <?php if ($quiz['time_limit_minutes']): ?>
                            <br><small class="text-info"><i class="fas fa-clock me-1"></i><?php echo $quiz['time_limit_minutes']; ?> minutes</small>
                            <?php endif; ?>
                        </div>
                        <?php if ($isEnrolled): ?>
                        <a href="<?php echo url('quizzes/take.php?id=' . $quiz['id']); ?>" class="btn btn-sm btn-success">
                            <i class="fas fa-play"></i> Take Quiz
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <!-- Enrollment Status -->
        <div class="card shadow mb-4">
            <div class="card-body">
                <h5 class="card-title">Enrollment Status</h5>
                
                <?php if ($isEnrolled): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    You are enrolled in this training!
                </div>
                
                <?php if ($enrollment && isset($enrollment['status'])): ?>
                <div class="mb-3">
                    <strong>Status:</strong> 
                    <span class="badge bg-<?php 
                    switch($enrollment['status']) {
                        case 'completed': echo 'success'; break;
                        case 'in_progress': echo 'warning'; break;
                        case 'failed': echo 'danger'; break;
                        default: echo 'primary';
                    } 
                    ?>">
                        <?php echo ucfirst($enrollment['status']); ?>
                    </span>
                </div>
                <?php endif; ?>
                
                <?php if ($enrollment && isset($enrollment['progress_percentage'])): ?>
                <div class="mb-3">
                    <strong>Progress:</strong>
                    <div class="progress mt-1">
                        <div class="progress-bar" style="width: <?php echo $enrollment['progress_percentage']; ?>%">
                            <?php echo round($enrollment['progress_percentage']); ?>%
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <small class="text-muted">
                    Enrolled on: <?php echo date('M d, Y', strtotime($enrollment['enrolled_at'])); ?>
                </small>
                
                <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    You are not enrolled in this training.
                </div>
                
                <?php if (!hasMinimumRole('admin')): ?>
                <form method="POST" action="<?php echo url('enrollments/create.php'); ?>">
                    <input type="hidden" name="training_id" value="<?php echo $training['id']; ?>">
                    <button type="submit" class="btn btn-success w-100">
                        <i class="fas fa-plus me-1"></i>Enroll Now
                    </button>
                </form>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Training Details -->
        <div class="card shadow">
            <div class="card-body">
                <h5 class="card-title">Training Details</h5>
                
                <div class="mb-2">
                    <strong>Enrollments:</strong> 
                    <span class="text-primary"><?php echo $enrollmentCount; ?></span>
                </div>
                
                <?php if (isset($training['max_participants'])): ?>
                <div class="mb-2">
                    <strong>Max Participants:</strong> 
                    <?php echo $training['max_participants'] ?: 'Unlimited'; ?>
                </div>
                <?php endif; ?>
                
                <?php if (isset($training['difficulty_level'])): ?>
                <div class="mb-2">
                    <strong>Difficulty:</strong> 
                    <span class="badge bg-<?php 
                    switch($training['difficulty_level']) {
                        case 'beginner': echo 'success'; break;
                        case 'intermediate': echo 'warning'; break;
                        case 'advanced': echo 'danger'; break;
                        default: echo 'secondary';
                    } 
                    ?>">
                        <?php echo ucfirst($training['difficulty_level'] ?? 'Not specified'); ?>
                    </span>
                </div>
                <?php endif; ?>
                
                <?php if (isset($training['estimated_duration_hours'])): ?>
                <div class="mb-2">
                    <strong>Duration:</strong> 
                    <?php echo $training['estimated_duration_hours']; ?> hours
                </div>
                <?php endif; ?>
                
                <?php if (isset($training['location'])): ?>
                <div class="mb-2">
                    <strong>Location:</strong> 
                    <?php echo $training['location'] ?: 'Online/TBD'; ?>
                </div>
                <?php endif; ?>
                
                <div class="mb-2">
                    <strong>Created:</strong> 
                    <small><?php echo date('M d, Y', strtotime($training['created_at'])); ?></small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../templates/footer.php'; ?>