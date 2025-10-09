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

// Get attempt ID from URL
$attemptId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get attempt information
$attemptStmt = $conn->prepare("
    SELECT qa.*, q.title as quiz_title, q.time_limit_minutes, q.shuffle_questions, 
           t.title as training_title, u.full_name as user_name
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
if (!$attempt) {
    redirect('dashboard.php');
}
$attemptStmt->close();

$userId = getCurrentUser()['id'];

// Security check: only allow user to access their own attempt
if ($attempt['user_id'] != $userId) {
    redirect('auth/unauthorized.php');
}

// Check if attempt is still in progress
if ($attempt['status'] !== 'in_progress') {
    redirect("quizzes/results.php?attempt_id={$attemptId}");
}

// Get quiz manager and questions
$manager = new QuizManager();
$questions = $manager->getQuizQuestions($attempt['quiz_id'], $attempt['shuffle_questions']);

// Get existing answers for this attempt
$answersStmt = $conn->prepare("SELECT * FROM quiz_answers WHERE attempt_id = ?");
$answersStmt->bind_param("i", $attemptId);
$answersStmt->execute();
$answersResult = $answersStmt->get_result();
$existingAnswers = array();
if ($answersResult && $answersResult->num_rows > 0) {
    while ($row = $answersResult->fetch_assoc()) {
        $existingAnswers[$row['question_id']] = $row;
    }
}
$answersStmt->close();

// Handle AJAX answer submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['ajax_action']) {
        case 'save_answer':
            $questionId = (int)$_POST['question_id'];
            $answerText = isset($_POST['answer_text']) ? sanitize($_POST['answer_text']) : null;
            $selectedOptionId = isset($_POST['selected_option_id']) ? (int)$_POST['selected_option_id'] : null;
            
            $result = $manager->submitAnswer($attemptId, $questionId, $answerText, $selectedOptionId);
            echo json_encode($result);
            exit();
            
        case 'submit_quiz':
            $result = $manager->submitQuizAttempt($attemptId);
            if ($result['success']) {
                $result['redirect_url'] = BASE_URL . "quizzes/results.php?attempt_id={$attemptId}";
            }
            echo json_encode($result);
            exit();
    }
}

// Calculate time remaining
$timeRemaining = null;
if ($attempt['time_limit_minutes']) {
    $startTime = strtotime($attempt['started_at']);
    $timeLimit = $attempt['time_limit_minutes'] * 60; // Convert to seconds
    $elapsedTime = time() - $startTime;
    $timeRemaining = max(0, $timeLimit - $elapsedTime);
}

include '../templates/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-md-9">
            <!-- Quiz Header -->
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="mb-0"><?php echo htmlspecialchars($attempt['quiz_title']); ?></h4>
                        <small class="text-muted">Attempt <?php echo $attempt['attempt_number']; ?> of <?php echo $attempt['quiz_title']; ?></small>
                    </div>
                    <div class="d-flex align-items-center">
                        <?php if ($timeRemaining !== null): ?>
                            <div class="alert alert-warning mb-0 me-3" style="padding: 0.25rem 0.5rem;">
                                <i class="fas fa-clock me-1"></i>
                                <span id="timeRemaining"><?php echo gmdate("H:i:s", $timeRemaining); ?></span>
                            </div>
                        <?php endif; ?>
                        <button type="button" class="btn btn-success" onclick="submitQuiz()">
                            <i class="fas fa-paper-plane me-1"></i>Submit Quiz
                        </button>
                    </div>
                </div>
            </div>

            <!-- Questions -->
            <form id="quizForm">
                <?php foreach ($questions as $index => $question): ?>
                    <div class="card mb-3 question-card" data-question-id="<?php echo $question['id']; ?>">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">Question <?php echo $index + 1; ?> of <?php echo count($questions); ?></h6>
                                <div>
                                    <span class="badge bg-primary"><?php echo $question['points']; ?> point(s)</span>
                                    <?php if ($question['is_required']): ?>
                                        <span class="badge bg-danger">Required</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <p class="question-text"><?php echo nl2br(htmlspecialchars($question['question_text'])); ?></p>
                            
                            <?php 
                            $existingAnswer = isset($existingAnswers[$question['id']]) ? $existingAnswers[$question['id']] : null;
                            ?>
                            
                            <?php if ($question['question_type'] === 'multiple_choice'): ?>
                                <div class="answer-options">
                                    <?php foreach ($question['options'] as $option): ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" 
                                                   name="question_<?php echo $question['id']; ?>" 
                                                   id="option_<?php echo $option['id']; ?>" 
                                                   value="<?php echo $option['id']; ?>"
                                                   <?php echo ($existingAnswer && $existingAnswer['selected_option_id'] == $option['id']) ? 'checked' : ''; ?>
                                                   onchange="saveAnswer(<?php echo $question['id']; ?>)">
                                            <label class="form-check-label" for="option_<?php echo $option['id']; ?>">
                                                <?php echo htmlspecialchars($option['option_text']); ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                            <?php elseif ($question['question_type'] === 'true_false'): ?>
                                <div class="answer-options">
                                    <?php foreach ($question['options'] as $option): ?>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" 
                                                   name="question_<?php echo $question['id']; ?>" 
                                                   id="option_<?php echo $option['id']; ?>" 
                                                   value="<?php echo $option['id']; ?>"
                                                   <?php echo ($existingAnswer && $existingAnswer['selected_option_id'] == $option['id']) ? 'checked' : ''; ?>
                                                   onchange="saveAnswer(<?php echo $question['id']; ?>)">
                                            <label class="form-check-label" for="option_<?php echo $option['id']; ?>">
                                                <?php echo htmlspecialchars($option['option_text']); ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                            <?php elseif ($question['question_type'] === 'short_answer'): ?>
                                <div class="answer-text">
                                    <input type="text" class="form-control" 
                                           name="question_<?php echo $question['id']; ?>" 
                                           id="text_<?php echo $question['id']; ?>"
                                           value="<?php echo $existingAnswer ? htmlspecialchars($existingAnswer['answer_text']) : ''; ?>"
                                           placeholder="Enter your answer..."
                                           onchange="saveAnswer(<?php echo $question['id']; ?>)">
                                </div>
                                
                            <?php elseif ($question['question_type'] === 'essay'): ?>
                                <div class="answer-text">
                                    <textarea class="form-control" rows="5" 
                                              name="question_<?php echo $question['id']; ?>" 
                                              id="essay_<?php echo $question['id']; ?>"
                                              placeholder="Enter your detailed answer..."
                                              onchange="saveAnswer(<?php echo $question['id']; ?>)"><?php echo $existingAnswer ? htmlspecialchars($existingAnswer['answer_text']) : ''; ?></textarea>
                                </div>
                            <?php endif; ?>
                            
                            <div class="answer-status mt-2" id="status_<?php echo $question['id']; ?>">
                                <?php if ($existingAnswer): ?>
                                    <small class="text-success"><i class="fas fa-check me-1"></i>Answer saved</small>
                                <?php else: ?>
                                    <small class="text-muted"><i class="fas fa-circle me-1"></i>Not answered</small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </form>
            
            <!-- Submit Section -->
            <div class="card">
                <div class="card-body text-center">
                    <h5>Ready to submit your quiz?</h5>
                    <p class="text-muted">Make sure you've answered all questions before submitting. You cannot change your answers after submission.</p>
                    <button type="button" class="btn btn-success btn-lg" onclick="submitQuiz()">
                        <i class="fas fa-paper-plane me-2"></i>Submit Quiz
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Sidebar -->
        <div class="col-md-3">
            <div class="card sticky-top" style="top: 20px;">
                <div class="card-header">
                    <h6 class="mb-0">Quiz Progress</h6>
                </div>
                <div class="card-body">
                    <?php if ($timeRemaining !== null): ?>
                        <div class="mb-3">
                            <label class="form-label">Time Remaining:</label>
                            <div class="alert alert-warning mb-0">
                                <i class="fas fa-clock me-1"></i>
                                <span id="sidebarTimer"><?php echo gmdate("H:i:s", $timeRemaining); ?></span>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label class="form-label">Questions:</label>
                        <div class="progress mb-2">
                            <div class="progress-bar" role="progressbar" id="progressBar" 
                                 style="width: <?php echo count($existingAnswers) / count($questions) * 100; ?>%">
                            </div>
                        </div>
                        <small class="text-muted">
                            <span id="answeredCount"><?php echo count($existingAnswers); ?></span> of 
                            <?php echo count($questions); ?> answered
                        </small>
                    </div>
                    
                    <div class="question-navigator">
                        <label class="form-label">Quick Navigation:</label>
                        <div class="d-flex flex-wrap gap-1">
                            <?php foreach ($questions as $index => $question): ?>
                                <button type="button" class="btn btn-sm question-nav-btn <?php echo isset($existingAnswers[$question['id']]) ? 'btn-success' : 'btn-outline-secondary'; ?>" 
                                        onclick="scrollToQuestion(<?php echo $question['id']; ?>)">
                                    <?php echo $index + 1; ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let timeRemaining = <?php echo $timeRemaining ?: 0; ?>;
let timerInterval = null;

// Start countdown timer
if (timeRemaining > 0) {
    timerInterval = setInterval(updateTimer, 1000);
}

function updateTimer() {
    timeRemaining--;
    
    if (timeRemaining <= 0) {
        clearInterval(timerInterval);
        alert('Time is up! Your quiz will be automatically submitted.');
        submitQuiz();
        return;
    }
    
    const hours = Math.floor(timeRemaining / 3600);
    const minutes = Math.floor((timeRemaining % 3600) / 60);
    const seconds = timeRemaining % 60;
    
    const timeString = String(hours).padStart(2, '0') + ':' + 
                      String(minutes).padStart(2, '0') + ':' + 
                      String(seconds).padStart(2, '0');
    
    document.getElementById('timeRemaining').textContent = timeString;
    document.getElementById('sidebarTimer').textContent = timeString;
    
    // Change color when time is running low
    if (timeRemaining <= 300) { // 5 minutes
        document.getElementById('timeRemaining').parentElement.className = 'alert alert-danger mb-0 me-3';
        document.getElementById('sidebarTimer').parentElement.className = 'alert alert-danger mb-0';
    } else if (timeRemaining <= 600) { // 10 minutes
        document.getElementById('timeRemaining').parentElement.className = 'alert alert-warning mb-0 me-3';
        document.getElementById('sidebarTimer').parentElement.className = 'alert alert-warning mb-0';
    }
}

function saveAnswer(questionId) {
    const questionCard = document.querySelector(`[data-question-id="${questionId}"]`);
    const statusElement = document.getElementById(`status_${questionId}`);
    
    // Get answer data based on question type
    let answerText = null;
    let selectedOptionId = null;
    
    const radioInputs = questionCard.querySelectorAll('input[type="radio"]:checked');
    const textInput = questionCard.querySelector('input[type="text"]');
    const textareaInput = questionCard.querySelector('textarea');
    
    if (radioInputs.length > 0) {
        selectedOptionId = radioInputs[0].value;
    } else if (textInput) {
        answerText = textInput.value.trim();
    } else if (textareaInput) {
        answerText = textareaInput.value.trim();
    }
    
    // Show saving status
    statusElement.innerHTML = '<small class="text-info"><i class="fas fa-spinner fa-spin me-1"></i>Saving...</small>';
    
    // Send AJAX request
    const formData = new FormData();
    formData.append('ajax_action', 'save_answer');
    formData.append('question_id', questionId);
    if (answerText) formData.append('answer_text', answerText);
    if (selectedOptionId) formData.append('selected_option_id', selectedOptionId);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            statusElement.innerHTML = '<small class="text-success"><i class="fas fa-check me-1"></i>Answer saved</small>';
            updateProgress();
            updateNavigationButton(questionId, true);
        } else {
            statusElement.innerHTML = '<small class="text-danger"><i class="fas fa-exclamation-triangle me-1"></i>Save failed</small>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        statusElement.innerHTML = '<small class="text-danger"><i class="fas fa-exclamation-triangle me-1"></i>Error saving</small>';
    });
}

function submitQuiz() {
    if (!confirm('Are you sure you want to submit your quiz? You cannot change your answers after submission.')) {
        return;
    }
    
    // Disable submit buttons
    const submitButtons = document.querySelectorAll('button[onclick="submitQuiz()"]');
    submitButtons.forEach(btn => {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Submitting...';
    });
    
    const formData = new FormData();
    formData.append('ajax_action', 'submit_quiz');
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Quiz submitted successfully!');
            if (data.redirect_url) {
                window.location.href = data.redirect_url;
            }
        } else {
            alert('Error submitting quiz: ' + data.message);
            // Re-enable buttons
            submitButtons.forEach(btn => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-paper-plane me-1"></i>Submit Quiz';
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error submitting quiz. Please try again.');
        // Re-enable buttons
        submitButtons.forEach(btn => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane me-1"></i>Submit Quiz';
        });
    });
}

function scrollToQuestion(questionId) {
    const questionCard = document.querySelector(`[data-question-id="${questionId}"]`);
    questionCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function updateProgress() {
    const answeredQuestions = document.querySelectorAll('.answer-status .text-success').length;
    const totalQuestions = <?php echo count($questions); ?>;
    const percentage = (answeredQuestions / totalQuestions) * 100;
    
    document.getElementById('progressBar').style.width = percentage + '%';
    document.getElementById('answeredCount').textContent = answeredQuestions;
}

function updateNavigationButton(questionId, isAnswered) {
    const buttons = document.querySelectorAll('.question-nav-btn');
    const questionIndex = Array.from(document.querySelectorAll('[data-question-id]')).findIndex(
        el => el.getAttribute('data-question-id') == questionId
    );
    
    if (questionIndex !== -1 && buttons[questionIndex]) {
        if (isAnswered) {
            buttons[questionIndex].className = 'btn btn-sm question-nav-btn btn-success';
        } else {
            buttons[questionIndex].className = 'btn btn-sm question-nav-btn btn-outline-secondary';
        }
    }
}

// Prevent accidental page reload
window.addEventListener('beforeunload', function(e) {
    e.preventDefault();
    e.returnValue = '';
});

// Auto-save periodically
setInterval(function() {
    // Auto-save all answered questions
    document.querySelectorAll('.question-card').forEach(card => {
        const questionId = card.getAttribute('data-question-id');
        const hasAnswer = card.querySelector('input:checked, input[type="text"][value], textarea[value]');
        if (hasAnswer) {
            // Only save if not already saved recently
            const statusElement = document.getElementById(`status_${questionId}`);
            if (!statusElement.innerHTML.includes('text-success')) {
                saveAnswer(parseInt(questionId));
            }
        }
    });
}, 30000); // Every 30 seconds
</script>

<?php 
closeDB($conn);
include '../templates/footer.php'; 
?>
