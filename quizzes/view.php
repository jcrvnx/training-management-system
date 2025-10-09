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
    redirect(url('quizzes/manage.php'));
}

$quizId = (int)$_GET['id'];
$currentUser = getCurrentUser();

// Get database connection
$conn = connectDB(false);

if (!$conn) {
    redirect(url('install_schema.php'));
}

// Check if quizzes table exists
if ($conn->query("SHOW TABLES LIKE 'quizzes'")->num_rows === 0) {
    $_SESSION['error'] = 'Quiz system not installed.';
    redirect(url('dashboard.php'));
}

// Get quiz details
$quizQuery = "SELECT q.*, t.title as training_title, u.full_name as creator_name
              FROM quizzes q 
              LEFT JOIN trainings t ON q.training_id = t.id 
              LEFT JOIN users u ON q.created_by = u.id 
              WHERE q.id = ?";
$quizStmt = $conn->prepare($quizQuery);
$quizStmt->bind_param("i", $quizId);
$quizStmt->execute();
$quizResult = $quizStmt->get_result();

if ($quizResult->num_rows === 0) {
    $_SESSION['error'] = 'Quiz not found.';
    redirect(url('quizzes/manage.php'));
}

$quiz = $quizResult->fetch_assoc();
$quizStmt->close();

// Get quiz questions with options
$questionsQuery = "SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY sort_order ASC";
$questionsStmt = $conn->prepare($questionsQuery);
$questionsStmt->bind_param("i", $quizId);
$questionsStmt->execute();
$questionsResult = $questionsStmt->get_result();
$questions = [];
while ($question = $questionsResult->fetch_assoc()) {
    // Get options for this question
    $optionsQuery = "SELECT * FROM question_options WHERE question_id = ? ORDER BY sort_order ASC";
    $optionsStmt = $conn->prepare($optionsQuery);
    $optionsStmt->bind_param("i", $question['id']);
    $optionsStmt->execute();
    $optionsResult = $optionsStmt->get_result();
    $question['options'] = [];
    while ($option = $optionsResult->fetch_assoc()) {
        $question['options'][] = $option;
    }
    $optionsStmt->close();
    
    $questions[] = $question;
}
$questionsStmt->close();

// Get quiz statistics
$stats = [
    'total_attempts' => 0,
    'completed_attempts' => 0,
    'average_score' => 0,
    'pass_rate' => 0
];

if ($conn->query("SHOW TABLES LIKE 'quiz_attempts'")->num_rows > 0) {
    // Total attempts
    $attemptsQuery = "SELECT COUNT(*) as count FROM quiz_attempts WHERE quiz_id = ?";
    $attemptsStmt = $conn->prepare($attemptsQuery);
    $attemptsStmt->bind_param("i", $quizId);
    $attemptsStmt->execute();
    $attemptsResult = $attemptsStmt->get_result();
    $stats['total_attempts'] = $attemptsResult->fetch_assoc()['count'];
    $attemptsStmt->close();
    
    // Completed attempts and scores
    $completedQuery = "SELECT COUNT(*) as count, AVG(percentage_score) as avg_score 
                      FROM quiz_attempts 
                      WHERE quiz_id = ? AND status = 'submitted'";
    $completedStmt = $conn->prepare($completedQuery);
    $completedStmt->bind_param("i", $quizId);
    $completedStmt->execute();
    $completedResult = $completedStmt->get_result();
    $completedData = $completedResult->fetch_assoc();
    $stats['completed_attempts'] = $completedData['count'];
    $stats['average_score'] = $completedData['avg_score'] ? round($completedData['avg_score'], 1) : 0;
    $completedStmt->close();
    
    // Pass rate (if passed column exists)
    $passedQuery = "SELECT COUNT(*) as count FROM quiz_attempts 
                   WHERE quiz_id = ? AND status = 'submitted' AND percentage_score >= ?";
    $passedStmt = $conn->prepare($passedQuery);
    $passedStmt->bind_param("id", $quizId, $quiz['passing_score']);
    $passedStmt->execute();
    $passedResult = $passedStmt->get_result();
    $passedCount = $passedResult->fetch_assoc()['count'];
    $passedStmt->close();
    
    $stats['pass_rate'] = $stats['completed_attempts'] > 0 ? 
                         round(($passedCount / $stats['completed_attempts']) * 100, 1) : 0;
}

// Get recent attempts (for trainers/admins)
$recentAttempts = [];
if (hasMinimumRole('trainer') && $conn->query("SHOW TABLES LIKE 'quiz_attempts'")->num_rows > 0) {
    $recentQuery = "SELECT qa.*, u.full_name as student_name 
                   FROM quiz_attempts qa 
                   LEFT JOIN users u ON qa.user_id = u.id 
                   WHERE qa.quiz_id = ? AND qa.status = 'submitted' 
                   ORDER BY qa.submitted_at DESC LIMIT 10";
    $recentStmt = $conn->prepare($recentQuery);
    $recentStmt->bind_param("i", $quizId);
    $recentStmt->execute();
    $recentResult = $recentStmt->get_result();
    while ($attempt = $recentResult->fetch_assoc()) {
        $recentAttempts[] = $attempt;
    }
    $recentStmt->close();
}

closeDB($conn);

// Include header
include_once '../templates/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1><i class="fas fa-question-circle text-primary me-2"></i><?php echo htmlspecialchars($quiz['title']); ?></h1>
                <p class="text-muted mb-0">Quiz Details & Preview</p>
            </div>
            <div>
                <a href="<?php echo url('quizzes/manage.php'); ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i>Back to Quizzes
                </a>
                <?php if (hasMinimumRole('trainer')): ?>
                <a href="<?php echo url('quizzes/edit.php?id=' . $quiz['id']); ?>" class="btn btn-primary">
                    <i class="fas fa-edit me-1"></i>Edit Quiz
                </a>
                <?php endif; ?>
                <?php if (!hasMinimumRole('admin')): ?>
                <a href="<?php echo url('quizzes/take.php?id=' . $quiz['id']); ?>" class="btn btn-success">
                    <i class="fas fa-play me-1"></i>Take Quiz
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Quiz Information -->
    <div class="col-md-8">
        <div class="card shadow mb-4">
            <div class="card-body">
                <h5 class="card-title">Quiz Information</h5>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Training:</strong> 
                        <?php echo htmlspecialchars($quiz['training_title'] ?: 'Not assigned'); ?>
                    </div>
                    <div class="col-md-6">
                        <strong>Created by:</strong> 
                        <?php echo htmlspecialchars($quiz['creator_name'] ?: 'Unknown'); ?>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Time Limit:</strong> 
                        <?php echo $quiz['time_limit_minutes'] ? $quiz['time_limit_minutes'] . ' minutes' : 'No limit'; ?>
                    </div>
                    <div class="col-md-6">
                        <strong>Max Attempts:</strong> 
                        <?php echo $quiz['max_attempts']; ?>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Passing Score:</strong> 
                        <?php echo $quiz['passing_score']; ?>%
                    </div>
                    <div class="col-md-6">
                        <strong>Status:</strong> 
                        <span class="badge bg-<?php echo $quiz['is_active'] ? 'success' : 'danger'; ?>">
                            <?php echo $quiz['is_active'] ? 'Active' : 'Inactive'; ?>
                        </span>
                    </div>
                </div>
                
                <?php if ($quiz['description']): ?>
                <div class="mb-3">
                    <strong>Description:</strong><br>
                    <?php echo nl2br(htmlspecialchars($quiz['description'])); ?>
                </div>
                <?php endif; ?>
                
                <?php if ($quiz['instructions']): ?>
                <div class="mb-3">
                    <strong>Instructions:</strong><br>
                    <div class="alert alert-info">
                        <?php echo nl2br(htmlspecialchars($quiz['instructions'])); ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" disabled 
                                   <?php echo $quiz['shuffle_questions'] ? 'checked' : ''; ?>>
                            <label class="form-check-label">Shuffle Questions</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" disabled 
                                   <?php echo $quiz['show_results_immediately'] ? 'checked' : ''; ?>>
                            <label class="form-check-label">Show Results Immediately</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Questions Preview -->
        <div class="card shadow">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>Questions Preview (<?php echo count($questions); ?>)</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($questions)): ?>
                <?php foreach ($questions as $index => $question): ?>
                <div class="question-preview mb-4 p-3 border rounded">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h6 class="text-primary">Question <?php echo $index + 1; ?></h6>
                        <div>
                            <span class="badge bg-secondary"><?php echo ucfirst(str_replace('_', ' ', $question['question_type'])); ?></span>
                            <span class="badge bg-info"><?php echo $question['points']; ?> pts</span>
                        </div>
                    </div>
                    
                    <p class="mb-3"><?php echo nl2br(htmlspecialchars($question['question_text'])); ?></p>
                    
                    <?php if (!empty($question['options'])): ?>
                    <div class="options-list">
                        <?php foreach ($question['options'] as $optIndex => $option): ?>
                        <div class="form-check mb-1">
                            <input class="form-check-input" type="<?php echo $question['question_type'] === 'multiple_choice' ? 'checkbox' : 'radio'; ?>" 
                                   disabled <?php echo $option['is_correct'] ? 'checked' : ''; ?>>
                            <label class="form-check-label <?php echo $option['is_correct'] ? 'text-success fw-bold' : ''; ?>">
                                <?php echo htmlspecialchars($option['option_text']); ?>
                                <?php if ($option['is_correct']): ?>
                                <i class="fas fa-check-circle text-success ms-1"></i>
                                <?php endif; ?>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($question['explanation']): ?>
                    <div class="mt-3 p-2 bg-light rounded">
                        <small class="text-muted">
                            <strong>Explanation:</strong> <?php echo nl2br(htmlspecialchars($question['explanation'])); ?>
                        </small>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                <?php else: ?>
                <div class="text-center text-muted py-5">
                    <i class="fas fa-question-circle fa-3x mb-3"></i>
                    <p>No questions have been added to this quiz yet.</p>
                    <?php if (hasMinimumRole('trainer')): ?>
                    <a href="<?php echo url('quizzes/edit.php?id=' . $quiz['id']); ?>" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i>Add Questions
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Statistics & Recent Attempts -->
    <div class="col-md-4">
        <!-- Quiz Statistics -->
        <div class="card shadow mb-4">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-chart-bar me-2"></i>Statistics</h5>
                
                <div class="row text-center">
                    <div class="col-6 mb-3">
                        <div class="stat-box">
                            <div class="stat-number text-primary"><?php echo $stats['total_attempts']; ?></div>
                            <div class="stat-label">Total Attempts</div>
                        </div>
                    </div>
                    <div class="col-6 mb-3">
                        <div class="stat-box">
                            <div class="stat-number text-success"><?php echo $stats['completed_attempts']; ?></div>
                            <div class="stat-label">Completed</div>
                        </div>
                    </div>
                    <div class="col-6 mb-3">
                        <div class="stat-box">
                            <div class="stat-number text-info"><?php echo $stats['average_score']; ?>%</div>
                            <div class="stat-label">Average Score</div>
                        </div>
                    </div>
                    <div class="col-6 mb-3">
                        <div class="stat-box">
                            <div class="stat-number text-warning"><?php echo $stats['pass_rate']; ?>%</div>
                            <div class="stat-label">Pass Rate</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if (hasMinimumRole('trainer') && !empty($recentAttempts)): ?>
        <!-- Recent Attempts -->
        <div class="card shadow">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-history me-2"></i>Recent Attempts</h6>
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    <?php foreach ($recentAttempts as $attempt): ?>
                    <div class="list-group-item px-0">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="mb-1"><?php echo htmlspecialchars($attempt['student_name'] ?: 'Unknown'); ?></h6>
                                <small class="text-muted">
                                    <?php echo date('M d, Y g:i A', strtotime($attempt['submitted_at'])); ?>
                                </small>
                            </div>
                            <div class="text-end">
                                <?php if ($attempt['percentage_score'] !== null): ?>
                                <span class="badge bg-<?php 
                                if ($attempt['percentage_score'] >= $quiz['passing_score']) echo 'success';
                                elseif ($attempt['percentage_score'] >= 50) echo 'warning';
                                else echo 'danger';
                                ?>">
                                    <?php echo round($attempt['percentage_score']); ?>%
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.question-preview {
    background-color: #f8f9fa;
}

.stat-box {
    padding: 15px;
    border-radius: 8px;
    background: #f8f9fa;
    margin-bottom: 10px;
}

.stat-number {
    font-size: 2rem;
    font-weight: bold;
    line-height: 1;
}

.stat-label {
    font-size: 0.875rem;
    color: #6c757d;
    margin-top: 5px;
}
</style>

<?php include_once '../templates/footer.php'; ?>