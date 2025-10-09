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
    redirect(url('users/index.php'));
}

$profileUserId = (int)$_GET['id'];
$currentUser = getCurrentUser();
$currentUserId = $currentUser['id'];

// Check permissions - users can only view their own profile unless they're trainer/admin
if (!hasMinimumRole('trainer') && $profileUserId !== $currentUserId) {
    redirect(url('auth/unauthorized.php'));
}

// Get database connection
$conn = connectDB(false);

if (!$conn) {
    redirect(url('install_schema.php'));
}

// Get user details
$query = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $profileUserId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = 'User not found.';
    redirect(url('users/index.php'));
}

$user = $result->fetch_assoc();
$stmt->close();

// Get user's enrollments
$enrollmentQuery = "SELECT e.*, t.title as training_title FROM enrollments e 
                   JOIN trainings t ON e.training_id = t.id 
                   WHERE e.user_id = ? ORDER BY e.enrolled_at DESC";
$enrollStmt = $conn->prepare($enrollmentQuery);
$enrollStmt->bind_param("i", $profileUserId);
$enrollStmt->execute();
$enrollmentsResult = $enrollStmt->get_result();
$enrollments = [];
while ($enrollment = $enrollmentsResult->fetch_assoc()) {
    $enrollments[] = $enrollment;
}
$enrollStmt->close();

// Get user's certificates if table exists
$certificates = [];
$certificatesQuery = "SELECT c.*, t.title as training_title FROM certificates c 
                     JOIN trainings t ON c.training_id = t.id 
                     WHERE c.user_id = ? ORDER BY c.issue_date DESC";
if ($conn->query("SHOW TABLES LIKE 'certificates'")->num_rows > 0) {
    $certStmt = $conn->prepare($certificatesQuery);
    $certStmt->bind_param("i", $profileUserId);
    $certStmt->execute();
    $certResult = $certStmt->get_result();
    while ($certificate = $certResult->fetch_assoc()) {
        $certificates[] = $certificate;
    }
    $certStmt->close();
}

// Get user's quiz attempts if table exists
$quizAttempts = [];
$quizAttemptsQuery = "SELECT qa.*, q.title as quiz_title, t.title as training_title 
                     FROM quiz_attempts qa 
                     JOIN quizzes q ON qa.quiz_id = q.id 
                     JOIN trainings t ON q.training_id = t.id 
                     WHERE qa.user_id = ? ORDER BY qa.submitted_at DESC LIMIT 10";
if ($conn->query("SHOW TABLES LIKE 'quiz_attempts'")->num_rows > 0) {
    $attemptStmt = $conn->prepare($quizAttemptsQuery);
    $attemptStmt->bind_param("i", $profileUserId);
    $attemptStmt->execute();
    $attemptResult = $attemptStmt->get_result();
    while ($attempt = $attemptResult->fetch_assoc()) {
        $quizAttempts[] = $attempt;
    }
    $attemptStmt->close();
}

closeDB($conn);

// Include header
include_once '../templates/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1><i class="fas fa-user text-primary me-2"></i><?php echo htmlspecialchars($user['full_name']); ?></h1>
                <p class="text-muted mb-0">User Profile</p>
            </div>
            <div>
                <a href="<?php echo url('users/index.php'); ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i>Back to Users
                </a>
                <?php if (hasMinimumRole('admin') || $profileUserId === $currentUserId): ?>
                <a href="<?php echo url('users/edit.php?id=' . $user['id']); ?>" class="btn btn-primary">
                    <i class="fas fa-edit me-1"></i>Edit Profile
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- User Info -->
    <div class="col-md-4">
        <div class="card shadow mb-4">
            <div class="card-body text-center">
                <div class="avatar-circle mx-auto mb-3" style="width: 80px; height: 80px; font-size: 2rem;">
                    <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                </div>
                <h4><?php echo htmlspecialchars($user['full_name']); ?></h4>
                <p class="text-muted"><?php echo htmlspecialchars($user['email']); ?></p>
                
                <span class="badge bg-<?php
                switch($user['role']) {
                    case 'admin': echo 'danger'; break;
                    case 'trainer': echo 'warning'; break;
                    default: echo 'primary';
                } 
                ?> fs-6">
                    <i class="fas fa-<?php
                    switch($user['role']) {
                        case 'admin': echo 'user-shield'; break;
                        case 'trainer': echo 'chalkboard-teacher'; break;
                        default: echo 'user';
                    } 
                    ?> me-1"></i>
                    <?php echo ucfirst($user['role']); ?>
                </span>
            </div>
        </div>
        
        <div class="card shadow">
            <div class="card-body">
                <h5 class="card-title">Contact Information</h5>
                
                <div class="mb-2">
                    <strong>Email:</strong><br>
                    <a href="mailto:<?php echo htmlspecialchars($user['email']); ?>">
                        <?php echo htmlspecialchars($user['email']); ?>
                    </a>
                </div>
                
                <?php if (isset($user['phone']) && $user['phone']): ?>
                <div class="mb-2">
                    <strong>Phone:</strong><br>
                    <?php echo htmlspecialchars($user['phone']); ?>
                </div>
                <?php endif; ?>
                
                <?php if (isset($user['department']) && $user['department']): ?>
                <div class="mb-2">
                    <strong>Department:</strong><br>
                    <?php echo htmlspecialchars($user['department']); ?>
                </div>
                <?php endif; ?>
                
                <?php if (isset($user['position']) && $user['position']): ?>
                <div class="mb-2">
                    <strong>Position:</strong><br>
                    <?php echo htmlspecialchars($user['position']); ?>
                </div>
                <?php endif; ?>
                
                <hr>
                
                <div class="mb-2">
                    <strong>Status:</strong><br>
                    <span class="badge bg-<?php echo $user['is_active'] ? 'success' : 'danger'; ?>">
                        <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                    </span>
                </div>
                
                <div class="mb-2">
                    <strong>Member Since:</strong><br>
                    <small><?php echo date('M d, Y', strtotime($user['created_at'])); ?></small>
                </div>
                
                <?php if (isset($user['last_login']) && $user['last_login']): ?>
                <div class="mb-2">
                    <strong>Last Login:</strong><br>
                    <small><?php echo date('M d, Y g:i A', strtotime($user['last_login'])); ?></small>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Activity Tabs -->
    <div class="col-md-8">
        <div class="card shadow">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs" id="profileTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="enrollments-tab" data-bs-toggle="tab" data-bs-target="#enrollments" type="button">
                            <i class="fas fa-book me-1"></i>Enrollments (<?php echo count($enrollments); ?>)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="certificates-tab" data-bs-toggle="tab" data-bs-target="#certificates" type="button">
                            <i class="fas fa-certificate me-1"></i>Certificates (<?php echo count($certificates); ?>)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="quizzes-tab" data-bs-toggle="tab" data-bs-target="#quizzes" type="button">
                            <i class="fas fa-question-circle me-1"></i>Quiz History (<?php echo count($quizAttempts); ?>)
                        </button>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content" id="profileTabsContent">
                    <!-- Enrollments Tab -->
                    <div class="tab-pane fade show active" id="enrollments">
                        <?php if (!empty($enrollments)): ?>
                        <div class="list-group">
                            <?php foreach ($enrollments as $enrollment): ?>
                            <div class="list-group-item">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($enrollment['training_title']); ?></h6>
                                    <small><?php echo date('M d, Y', strtotime($enrollment['enrolled_at'])); ?></small>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <?php if (isset($enrollment['status'])): ?>
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
                                        <?php endif; ?>
                                    </div>
                                    <?php if (isset($enrollment['progress_percentage'])): ?>
                                    <div class="progress" style="width: 100px;">
                                        <div class="progress-bar" style="width: <?php echo $enrollment['progress_percentage']; ?>%">
                                            <?php echo round($enrollment['progress_percentage']); ?>%
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="text-center text-muted">
                            <i class="fas fa-book fa-3x mb-3"></i>
                            <p>No enrollments yet.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Certificates Tab -->
                    <div class="tab-pane fade" id="certificates">
                        <?php if (!empty($certificates)): ?>
                        <div class="row">
                            <?php foreach ($certificates as $certificate): ?>
                            <div class="col-md-6 mb-3">
                                <div class="card border-warning">
                                    <div class="card-body text-center">
                                        <i class="fas fa-certificate text-warning fa-2x mb-2"></i>
                                        <h6><?php echo htmlspecialchars($certificate['training_title']); ?></h6>
                                        <small class="text-muted">
                                            Issued: <?php echo date('M d, Y', strtotime($certificate['issue_date'])); ?>
                                        </small>
                                        <br>
                                        <small class="text-muted">
                                            ID: <?php echo htmlspecialchars($certificate['certificate_number']); ?>
                                        </small>
                                        <div class="mt-2">
                                            <a href="<?php echo url('certificates/view.php?id=' . $certificate['id']); ?>" 
                                               class="btn btn-sm btn-outline-warning">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="text-center text-muted">
                            <i class="fas fa-certificate fa-3x mb-3"></i>
                            <p>No certificates earned yet.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Quiz History Tab -->
                    <div class="tab-pane fade" id="quizzes">
                        <?php if (!empty($quizAttempts)): ?>
                        <div class="list-group">
                            <?php foreach ($quizAttempts as $attempt): ?>
                            <div class="list-group-item">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($attempt['quiz_title']); ?></h6>
                                    <small><?php echo $attempt['submitted_at'] ? date('M d, Y', strtotime($attempt['submitted_at'])) : 'In Progress'; ?></small>
                                </div>
                                <p class="mb-1">
                                    <small class="text-muted">Training: <?php echo htmlspecialchars($attempt['training_title']); ?></small>
                                </p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <?php if (isset($attempt['percentage_score'])): ?>
                                        <span class="badge bg-<?php 
                                        if ($attempt['percentage_score'] >= 70) echo 'success';
                                        elseif ($attempt['percentage_score'] >= 50) echo 'warning';
                                        else echo 'danger';
                                        ?>">
                                            <?php echo round($attempt['percentage_score']); ?>%
                                        </span>
                                        <?php endif; ?>
                                        
                                        <?php if (isset($attempt['passed'])): ?>
                                        <span class="badge bg-<?php echo $attempt['passed'] ? 'success' : 'danger'; ?>">
                                            <?php echo $attempt['passed'] ? 'Passed' : 'Failed'; ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($attempt['submitted_at']): ?>
                                    <small class="text-muted">
                                        Attempt #<?php echo $attempt['attempt_number']; ?>
                                        <?php if ($attempt['time_taken_minutes']): ?>
                                        - <?php echo $attempt['time_taken_minutes']; ?> min
                                        <?php endif; ?>
                                    </small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="text-center text-muted">
                            <i class="fas fa-question-circle fa-3x mb-3"></i>
                            <p>No quiz attempts yet.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.avatar-circle {
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}
</style>

<?php include_once '../templates/footer.php'; ?>