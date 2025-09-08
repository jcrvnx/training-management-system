<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/quiz-manager.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('auth/login.php');
}

$conn = connectDB();
$errors = array();
$success = '';

// Get quiz ID from URL
$quizId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get quiz information
$manager = new QuizManager();
$quiz = $manager->getQuizInfo($quizId);

if (!$quiz || !$quiz['is_active']) {
    redirect('dashboard.php');
}

$userId = getCurrentUser()['id'];

// Check if user is enrolled in the training
$enrollmentStmt = $conn->prepare("SELECT * FROM enrollments WHERE training_id = ? AND user_id = ?");
$enrollmentStmt->bind_param("ii", $quiz['training_id'], $userId);
$enrollmentStmt->execute();
$enrollmentResult = $enrollmentStmt->get_result();

if ($enrollmentResult->num_rows === 0) {
    redirect('auth/unauthorized.php');
}

$enrollment = $enrollmentResult->fetch_assoc();
$enrollmentStmt->close();

// Get user's previous attempts
$attemptsStmt = $conn->prepare("
    SELECT * FROM quiz_attempts 
    WHERE quiz_id = ? AND user_id = ? 
    ORDER BY attempt_number DESC
");
$attemptsStmt->bind_param("ii", $quizId, $userId);
$attemptsStmt->execute();
$attemptsResult = $attemptsStmt->get_result();
$attempts = array();
while ($row = $attemptsResult->fetch_assoc()) {
    $attempts[] = $row;
}
$attemptsStmt->close();

// Check if user can take another attempt
$canTakeQuiz = count($attempts) < $quiz['max_attempts'];
$hasInProgressAttempt = false;
$inProgressAttemptId = null;

foreach ($attempts as $attempt) {
    if ($attempt['status'] === 'in_progress') {
        $hasInProgressAttempt = true;
        $inProgressAttemptId = $attempt['id'];
        break;
    }
}

// Handle starting new attempt
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'start_quiz') {
    if (!$canTakeQuiz && !$hasInProgressAttempt) {
        $errors[] = 'You have reached the maximum number of attempts for this quiz.';
    } else {
        $result = $manager->startQuizAttempt($quizId, $userId);
        if ($result['success']) {
            redirect("quizzes/attempt.php?id={$result['attempt_id']}");
        } else {
            $errors[] = $result['message'];
        }
    }
}

// Handle continuing existing attempt
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'continue_quiz') {
    redirect("quizzes/attempt.php?id={$inProgressAttemptId}");
}

include '../templates/header.php';
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <!-- Quiz Header -->
            <div class="card mb-4">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h3 class="mb-0">
                            <i class="fas fa-question-circle me-2"></i><?php echo htmlspecialchars($quiz['title']); ?>
                        </h3>
                        <a href="<?php echo BASE_URL; ?>dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <p class="text-muted mb-2"><strong>Training:</strong> <?php echo htmlspecialchars($quiz['training_title']); ?></p>
                            <p><?php echo nl2br(htmlspecialchars($quiz['description'])); ?></p>
                            
                            <?php if ($quiz['instructions']): ?>
                                <div class="alert alert-info">
                                    <h6><i class="fas fa-info-circle me-1"></i>Instructions:</h6>
                                    <?php echo nl2br(htmlspecialchars($quiz['instructions'])); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6 class="card-title">Quiz Information</h6>
                                    
                                    <?php if ($quiz['time_limit_minutes']): ?>
                                        <p class="mb-2"><i class="fas fa-clock me-1"></i><strong>Time Limit:</strong> <?php echo $quiz['time_limit_minutes']; ?> minutes</p>
                                    <?php endif; ?>
                                    
                                    <p class="mb-2"><i class="fas fa-redo me-1"></i><strong>Max Attempts:</strong> <?php echo $quiz['max_attempts']; ?></p>
                                    <p class="mb-2"><i class="fas fa-chart-line me-1"></i><strong>Passing Score:</strong> <?php echo $quiz['passing_score']; ?>%</p>
                                    <p class="mb-2"><i class="fas fa-question me-1"></i><strong>Questions:</strong> 
                                        <?php 
                                        $questionCount = count($manager->getQuizQuestions($quizId));
                                        echo $questionCount;
                                        ?>
                                    </p>
                                    
                                    <?php if ($quiz['shuffle_questions']): ?>
                                        <p class="mb-2"><i class="fas fa-random me-1"></i>Questions will be shuffled</p>
                                    <?php endif; ?>
                                    
                                    <?php if ($quiz['show_results_immediately']): ?>
                                        <p class="mb-0"><i class="fas fa-eye me-1"></i>Results shown immediately</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <!-- Previous Attempts -->
            <?php if (!empty($attempts)): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Your Attempts (<?php echo count($attempts); ?>/<?php echo $quiz['max_attempts']; ?>)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Attempt</th>
                                        <th>Started</th>
                                        <th>Status</th>
                                        <th>Score</th>
                                        <th>Time Taken</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($attempts as $attempt): ?>
                                        <tr>
                                            <td><?php echo $attempt['attempt_number']; ?></td>
                                            <td><?php echo date('M j, Y g:i A', strtotime($attempt['started_at'])); ?></td>
                                            <td>
                                                <?php if ($attempt['status'] === 'in_progress'): ?>
                                                    <span class="badge bg-warning">In Progress</span>
                                                <?php elseif ($attempt['status'] === 'submitted'): ?>
                                                    <?php if ($attempt['passed']): ?>
                                                        <span class="badge bg-success">Passed</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">Failed</span>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($attempt['status'] === 'submitted'): ?>
                                                    <?php echo number_format($attempt['percentage_score'], 1); ?>%
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($attempt['time_taken_minutes']): ?>
                                                    <?php echo $attempt['time_taken_minutes']; ?> min
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($attempt['status'] === 'in_progress'): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="continue_quiz">
                                                        <button type="submit" class="btn btn-sm btn-primary">
                                                            <i class="fas fa-play me-1"></i>Continue
                                                        </button>
                                                    </form>
                                                <?php elseif ($attempt['status'] === 'submitted' && $quiz['show_results_immediately']): ?>
                                                    <a href="<?php echo BASE_URL; ?>quizzes/results.php?attempt_id=<?php echo $attempt['id']; ?>" class="btn btn-sm btn-info">
                                                        <i class="fas fa-eye me-1"></i>View Results
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Start Quiz Section -->
            <div class="card">
                <div class="card-body text-center">
                    <?php if ($hasInProgressAttempt): ?>
                        <h4 class="text-warning mb-3">
                            <i class="fas fa-clock me-2"></i>You have an incomplete attempt
                        </h4>
                        <p class="mb-4">You started this quiz but haven't finished it yet. You can continue where you left off.</p>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="action" value="continue_quiz">
                            <button type="submit" class="btn btn-warning btn-lg">
                                <i class="fas fa-play me-2"></i>Continue Quiz
                            </button>
                        </form>
                        
                    <?php elseif ($canTakeQuiz): ?>
                        <h4 class="mb-3">Ready to start the quiz?</h4>
                        <p class="mb-4">Make sure you have enough time to complete the quiz and a stable internet connection.</p>
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Important:</strong> Once you start the quiz, you must complete it. 
                            <?php if ($quiz['time_limit_minutes']): ?>
                                You have <?php echo $quiz['time_limit_minutes']; ?> minutes to finish.
                            <?php endif; ?>
                        </div>
                        
                        <form method="POST" onsubmit="return confirm('Are you ready to start the quiz? Make sure you have enough time to complete it.')">
                            <input type="hidden" name="action" value="start_quiz">
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="fas fa-play me-2"></i>Start Quiz
                            </button>
                        </form>
                        
                    <?php else: ?>
                        <h4 class="text-danger mb-3">
                            <i class="fas fa-ban me-2"></i>Maximum Attempts Reached
                        </h4>
                        <p class="mb-4">You have used all <?php echo $quiz['max_attempts']; ?> attempts for this quiz.</p>
                        
                        <?php 
                        $bestAttempt = null;
                        foreach ($attempts as $attempt) {
                            if ($attempt['status'] === 'submitted' && ($bestAttempt === null || $attempt['percentage_score'] > $bestAttempt['percentage_score'])) {
                                $bestAttempt = $attempt;
                            }
                        }
                        ?>
                        
                        <?php if ($bestAttempt): ?>
                            <div class="alert alert-info">
                                <strong>Your best score:</strong> <?php echo number_format($bestAttempt['percentage_score'], 1); ?>%
                                <?php if ($bestAttempt['passed']): ?>
                                    <span class="badge bg-success ms-2">Passed</span>
                                <?php else: ?>
                                    <span class="badge bg-danger ms-2">Failed</span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <p class="text-muted">Contact your instructor if you need additional attempts.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
closeDB($conn);
include '../templates/footer.php'; 
?>
