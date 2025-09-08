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

// Get attempt ID from URL
$attemptId = isset($_GET['attempt_id']) ? (int)$_GET['attempt_id'] : 0;

// Get attempt information with quiz and training details
$attemptStmt = $conn->prepare("
    SELECT qa.*, q.title as quiz_title, q.description as quiz_description, 
           q.passing_score, q.show_results_immediately, q.max_attempts,
           t.id as training_id, t.title as training_title, 
           u.full_name as user_name, u.email as user_email
    FROM quiz_attempts qa
    JOIN quizzes q ON qa.quiz_id = q.id
    JOIN trainings t ON q.training_id = t.id
    JOIN users u ON qa.user_id = u.id
    WHERE qa.id = ?
");
$attemptStmt->bind_param("i", $attemptId);
$attemptStmt->execute();
$attemptResult = $attemptStmt->get_result();

if ($attemptResult->num_rows === 0) {
    redirect('dashboard.php');
}

$attempt = $attemptResult->fetch_assoc();
$attemptStmt->close();

$userId = getCurrentUser()['id'];

// Security check: only allow user to access their own attempt or allow instructors/admins to view
$canView = false;
if ($attempt['user_id'] == $userId) {
    $canView = true;
} elseif (isAdmin()) {
    $canView = true;
} elseif (isTrainer()) {
    // Check if trainer is instructor for this training
    $instructorStmt = $conn->prepare("SELECT id FROM trainings WHERE id = ? AND instructor_id = ?");
    $instructorStmt->bind_param("ii", $attempt['training_id'], $userId);
    $instructorStmt->execute();
    $instructorResult = $instructorStmt->get_result();
    $canView = $instructorResult->num_rows > 0;
    $instructorStmt->close();
}

if (!$canView) {
    redirect('auth/unauthorized.php');
}

// Check if results should be shown
if (!$attempt['show_results_immediately'] && $attempt['user_id'] == $userId && !isAdmin() && !isTrainer()) {
    redirect('dashboard.php');
}

// Check if attempt is submitted
if ($attempt['status'] !== 'submitted') {
    redirect("quizzes/attempt.php?id={$attemptId}");
}

// Get quiz manager and questions
$manager = new QuizManager();
$questions = $manager->getQuizQuestions($attempt['quiz_id']);

// Get user's answers for this attempt
$answersStmt = $conn->prepare("
    SELECT qa.*, qo.option_text, qo.is_correct as option_is_correct
    FROM quiz_answers qa
    LEFT JOIN question_options qo ON qa.selected_option_id = qo.id
    WHERE qa.attempt_id = ?
");
$answersStmt->bind_param("i", $attemptId);
$answersStmt->execute();
$answersResult = $answersStmt->get_result();
$userAnswers = array();
while ($row = $answersResult->fetch_assoc()) {
    $userAnswers[$row['question_id']] = $row;
}
$answersStmt->close();

// Check if certificate exists
$certificateStmt = $conn->prepare("SELECT * FROM certificates WHERE quiz_attempt_id = ?");
$certificateStmt->bind_param("i", $attemptId);
$certificateStmt->execute();
$certificateResult = $certificateStmt->get_result();
$certificate = $certificateResult->num_rows > 0 ? $certificateResult->fetch_assoc() : null;
$certificateStmt->close();

include '../templates/header.php';
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <!-- Results Header -->
            <div class="card mb-4">
                <div class="card-header <?php echo $attempt['passed'] ? 'bg-success text-white' : 'bg-danger text-white'; ?>">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="mb-0">
                                <i class="fas <?php echo $attempt['passed'] ? 'fa-check-circle' : 'fa-times-circle'; ?> me-2"></i>
                                Quiz Results: <?php echo $attempt['passed'] ? 'PASSED' : 'FAILED'; ?>
                            </h3>
                            <p class="mb-0"><?php echo htmlspecialchars($attempt['quiz_title']); ?></p>
                        </div>
                        <div class="text-end">
                            <div class="fs-1 fw-bold"><?php echo number_format($attempt['percentage_score'], 1); ?>%</div>
                            <small>Passing Score: <?php echo $attempt['passing_score']; ?>%</small>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <strong>Student:</strong><br>
                            <?php echo htmlspecialchars($attempt['user_name']); ?>
                        </div>
                        <div class="col-md-3">
                            <strong>Attempt:</strong><br>
                            <?php echo $attempt['attempt_number']; ?> of <?php echo $attempt['max_attempts']; ?>
                        </div>
                        <div class="col-md-3">
                            <strong>Completed:</strong><br>
                            <?php echo date('M j, Y g:i A', strtotime($attempt['submitted_at'])); ?>
                        </div>
                        <div class="col-md-3">
                            <strong>Time Taken:</strong><br>
                            <?php echo $attempt['time_taken_minutes'] ? $attempt['time_taken_minutes'] . ' minutes' : 'N/A'; ?>
                        </div>
                    </div>
                    
                    <?php if ($attempt['passed'] && $certificate): ?>
                        <hr>
                        <div class="alert alert-success">
                            <i class="fas fa-certificate me-2"></i>
                            <strong>Congratulations!</strong> You have earned a certificate for completing this training.
                            <a href="<?php echo BASE_URL; ?>certificates/view.php?id=<?php echo $certificate['id']; ?>" 
                               class="btn btn-success btn-sm ms-2">
                                <i class="fas fa-download me-1"></i>View Certificate
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Score Breakdown -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Score Breakdown</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <?php 
                            $totalQuestions = count($questions);
                            $answeredQuestions = count($userAnswers);
                            $correctAnswers = 0;
                            foreach ($userAnswers as $answer) {
                                if ($answer['is_correct'] === '1') {
                                    $correctAnswers++;
                                }
                            }
                            ?>
                            
                            <div class="progress mb-2" style="height: 25px;">
                                <div class="progress-bar bg-success" style="width: <?php echo ($correctAnswers / $totalQuestions) * 100; ?>%">
                                    Correct: <?php echo $correctAnswers; ?>
                                </div>
                                <div class="progress-bar bg-danger" style="width: <?php echo (($answeredQuestions - $correctAnswers) / $totalQuestions) * 100; ?>%">
                                    Incorrect: <?php echo $answeredQuestions - $correctAnswers; ?>
                                </div>
                                <div class="progress-bar bg-secondary" style="width: <?php echo (($totalQuestions - $answeredQuestions) / $totalQuestions) * 100; ?>%">
                                    Not Answered: <?php echo $totalQuestions - $answeredQuestions; ?>
                                </div>
                            </div>
                            
                            <div class="row text-center">
                                <div class="col-4">
                                    <div class="text-success">
                                        <strong><?php echo $correctAnswers; ?></strong><br>
                                        <small>Correct</small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="text-danger">
                                        <strong><?php echo $answeredQuestions - $correctAnswers; ?></strong><br>
                                        <small>Incorrect</small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="text-secondary">
                                        <strong><?php echo $totalQuestions - $answeredQuestions; ?></strong><br>
                                        <small>Skipped</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-sm">
                                <tr>
                                    <td><strong>Total Points Possible:</strong></td>
                                    <td><?php 
                                        $totalPoints = 0;
                                        foreach ($questions as $q) {
                                            $totalPoints += $q['points'];
                                        }
                                        echo $totalPoints;
                                    ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Points Earned:</strong></td>
                                    <td><?php echo number_format($attempt['total_score'], 1); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Percentage Score:</strong></td>
                                    <td><strong><?php echo number_format($attempt['percentage_score'], 1); ?>%</strong></td>
                                </tr>
                                <tr class="<?php echo $attempt['passed'] ? 'table-success' : 'table-danger'; ?>">
                                    <td><strong>Result:</strong></td>
                                    <td><strong><?php echo $attempt['passed'] ? 'PASSED' : 'FAILED'; ?></strong></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Question Review -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Question Review</h5>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="toggleExplanations">
                        <i class="fas fa-eye me-1"></i>Show Explanations
                    </button>
                </div>
                <div class="card-body">
                    <?php foreach ($questions as $index => $question): ?>
                        <?php 
                        $userAnswer = isset($userAnswers[$question['id']]) ? $userAnswers[$question['id']] : null;
                        $isCorrect = $userAnswer && $userAnswer['is_correct'] === '1';
                        $wasAnswered = $userAnswer !== null;
                        ?>
                        
                        <div class="question-review mb-4 <?php echo $index > 0 ? 'border-top pt-4' : ''; ?>">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <h6>
                                    Question <?php echo $index + 1; ?> 
                                    <span class="badge bg-primary ms-1"><?php echo $question['points']; ?> pts</span>
                                </h6>
                                <div>
                                    <?php if (!$wasAnswered): ?>
                                        <span class="badge bg-secondary">Not Answered</span>
                                    <?php elseif ($isCorrect): ?>
                                        <span class="badge bg-success">Correct</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Incorrect</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <p class="question-text"><?php echo nl2br(htmlspecialchars($question['question_text'])); ?></p>
                            
                            <?php if (in_array($question['question_type'], ['multiple_choice', 'true_false'])): ?>
                                <div class="options-review">
                                    <?php foreach ($question['options'] as $option): ?>
                                        <?php 
                                        $isUserSelection = $userAnswer && $userAnswer['selected_option_id'] == $option['id'];
                                        $isCorrectOption = $option['is_correct'];
                                        
                                        $optionClass = '';
                                        if ($isCorrectOption) {
                                            $optionClass = 'border-success bg-light-success';
                                        } elseif ($isUserSelection && !$isCorrectOption) {
                                            $optionClass = 'border-danger bg-light-danger';
                                        }
                                        ?>
                                        
                                        <div class="form-check p-2 mb-2 border rounded <?php echo $optionClass; ?>">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div class="d-flex align-items-center">
                                                    <?php if ($isUserSelection): ?>
                                                        <i class="fas fa-dot-circle text-primary me-2"></i>
                                                    <?php else: ?>
                                                        <i class="far fa-circle text-muted me-2"></i>
                                                    <?php endif; ?>
                                                    <span><?php echo htmlspecialchars($option['option_text']); ?></span>
                                                </div>
                                                <div>
                                                    <?php if ($isCorrectOption): ?>
                                                        <i class="fas fa-check text-success" title="Correct Answer"></i>
                                                    <?php endif; ?>
                                                    <?php if ($isUserSelection && !$isCorrectOption): ?>
                                                        <i class="fas fa-times text-danger" title="Your Answer"></i>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                            <?php elseif (in_array($question['question_type'], ['short_answer', 'essay'])): ?>
                                <div class="text-answer-review">
                                    <div class="card">
                                        <div class="card-header">
                                            <small>Your Answer:</small>
                                        </div>
                                        <div class="card-body">
                                            <?php if ($userAnswer && $userAnswer['answer_text']): ?>
                                                <p><?php echo nl2br(htmlspecialchars($userAnswer['answer_text'])); ?></p>
                                            <?php else: ?>
                                                <p class="text-muted fst-italic">No answer provided</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <?php if ($userAnswer && $userAnswer['is_correct'] !== null): ?>
                                        <div class="mt-2">
                                            <?php if ($userAnswer['is_correct'] === '1'): ?>
                                                <span class="badge bg-success">Marked Correct</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Marked Incorrect</span>
                                            <?php endif; ?>
                                            
                                            <?php if ($userAnswer['points_earned'] > 0): ?>
                                                <span class="badge bg-primary ms-1">
                                                    <?php echo $userAnswer['points_earned']; ?> / <?php echo $question['points']; ?> points
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="mt-2">
                                            <span class="badge bg-warning">Pending Manual Review</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($question['explanation']): ?>
                                <div class="explanation mt-3" style="display: none;">
                                    <div class="alert alert-info">
                                        <strong><i class="fas fa-lightbulb me-1"></i>Explanation:</strong><br>
                                        <?php echo nl2br(htmlspecialchars($question['explanation'])); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Actions -->
            <div class="card">
                <div class="card-body text-center">
                    <a href="<?php echo BASE_URL; ?>dashboard.php" class="btn btn-secondary me-2">
                        <i class="fas fa-home me-1"></i>Back to Dashboard
                    </a>
                    
                    <?php if ($attempt['user_id'] == $userId): ?>
                        <a href="<?php echo BASE_URL; ?>quizzes/take.php?id=<?php echo $attempt['quiz_id']; ?>" class="btn btn-primary me-2">
                            <i class="fas fa-redo me-1"></i>View Quiz Info
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($attempt['passed'] && $certificate): ?>
                        <a href="<?php echo BASE_URL; ?>certificates/view.php?id=<?php echo $certificate['id']; ?>" class="btn btn-success">
                            <i class="fas fa-certificate me-1"></i>View Certificate
                        </a>
                    <?php endif; ?>
                    
                    <?php if (isAdmin() || (isTrainer() && $attempt['user_id'] != $userId)): ?>
                        <a href="<?php echo BASE_URL; ?>quizzes/manage.php?id=<?php echo $attempt['quiz_id']; ?>" class="btn btn-info">
                            <i class="fas fa-cog me-1"></i>Manage Quiz
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.bg-light-success {
    background-color: #d4edda !important;
}
.bg-light-danger {
    background-color: #f8d7da !important;
}
</style>

<script>
document.getElementById('toggleExplanations').addEventListener('click', function() {
    const explanations = document.querySelectorAll('.explanation');
    const isShowing = explanations[0] && explanations[0].style.display !== 'none';
    
    explanations.forEach(function(explanation) {
        explanation.style.display = isShowing ? 'none' : 'block';
    });
    
    this.innerHTML = isShowing 
        ? '<i class="fas fa-eye me-1"></i>Show Explanations'
        : '<i class="fas fa-eye-slash me-1"></i>Hide Explanations';
});

// Print functionality
function printResults() {
    window.print();
}
</script>

<?php 
closeDB($conn);
include '../templates/footer.php'; 
?>
