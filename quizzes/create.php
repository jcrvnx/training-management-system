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

// Check permissions
if (!hasPermission('quizzes.create')) {
    redirect('auth/unauthorized.php');
}

$conn = connectDB();
$errors = array();
$success = '';

// Get training ID from URL
$trainingId = isset($_GET['training_id']) ? (int)$_GET['training_id'] : 0;

// Verify training exists and user can manage it
$trainingStmt = $conn->prepare("SELECT id, title FROM trainings WHERE id = ?");
$trainingStmt->bind_param("i", $trainingId);
$trainingStmt->execute();
$trainingResult = $trainingStmt->get_result();

if ($trainingResult->num_rows === 0) {
    redirect('trainings/index.php');
}

$training = $trainingResult->fetch_assoc();
$trainingStmt->close();

// Check if user can manage this training
if (!isAdmin() && isTrainer()) {
    $userId = getCurrentUser()['id'];
    $accessStmt = $conn->prepare("SELECT id FROM trainings WHERE id = ? AND instructor_id = ?");
    $accessStmt->bind_param("ii", $trainingId, $userId);
    $accessStmt->execute();
    $accessResult = $accessStmt->get_result();
    
    if ($accessResult->num_rows === 0) {
        redirect('auth/unauthorized.php');
    }
    $accessStmt->close();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitize($_POST['title']);
    $description = sanitize($_POST['description']);
    $instructions = sanitize($_POST['instructions']);
    $timeLimitMinutes = !empty($_POST['time_limit_minutes']) ? (int)$_POST['time_limit_minutes'] : null;
    $maxAttempts = (int)$_POST['max_attempts'];
    $passingScore = (float)$_POST['passing_score'];
    $shuffleQuestions = isset($_POST['shuffle_questions']) ? 1 : 0;
    $showResultsImmediately = isset($_POST['show_results_immediately']) ? 1 : 0;
    
    // Validation
    if (empty($title)) {
        $errors[] = 'Quiz title is required';
    }
    
    if (empty($description)) {
        $errors[] = 'Quiz description is required';
    }
    
    if ($maxAttempts < 1) {
        $errors[] = 'Maximum attempts must be at least 1';
    }
    
    if ($passingScore < 0 || $passingScore > 100) {
        $errors[] = 'Passing score must be between 0 and 100';
    }
    
    if (empty($errors)) {
        try {
            $manager = new QuizManager();
            $result = $manager->createQuiz(
                $trainingId,
                $title,
                $description,
                $instructions,
                $timeLimitMinutes,
                $maxAttempts,
                $passingScore,
                $shuffleQuestions,
                $showResultsImmediately
            );
            
            if ($result['success']) {
                $success = $result['message'];
                $quizId = $result['quiz_id'];
                
                // Redirect to quiz management page
                redirect("quizzes/manage.php?id={$quizId}");
            } else {
                $errors[] = $result['message'];
            }
        } catch (Exception $e) {
            $errors[] = 'System error: ' . $e->getMessage();
        }
    }
}

include '../templates/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-md-10 offset-md-1">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">
                        <i class="fas fa-question-circle me-2"></i>Create Quiz for: <?php echo htmlspecialchars($training['title']); ?>
                    </h4>
                    <a href="<?php echo BASE_URL; ?>trainings/edit.php?id=<?php echo $trainingId; ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i>Back to Training
                    </a>
                </div>
                
                <div class="card-body">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <?php echo htmlspecialchars($success); ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" class="needs-validation" novalidate>
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="title" class="form-label">Quiz Title <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="title" name="title" 
                                           value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" required>
                                    <div class="invalid-feedback">Please enter a quiz title.</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="description" name="description" rows="3" required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                                    <div class="form-text">Brief description of what this quiz covers.</div>
                                    <div class="invalid-feedback">Please enter a quiz description.</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="instructions" class="form-label">Instructions</label>
                                    <textarea class="form-control" id="instructions" name="instructions" rows="4"><?php echo htmlspecialchars($_POST['instructions'] ?? ''); ?></textarea>
                                    <div class="form-text">Detailed instructions for students taking this quiz.</div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="card bg-light">
                                    <div class="card-header">
                                        <h6 class="mb-0">Quiz Settings</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label for="time_limit_minutes" class="form-label">Time Limit (minutes)</label>
                                            <input type="number" class="form-control" id="time_limit_minutes" name="time_limit_minutes" 
                                                   value="<?php echo htmlspecialchars($_POST['time_limit_minutes'] ?? ''); ?>" min="1" max="300">
                                            <div class="form-text">Leave empty for no time limit.</div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="max_attempts" class="form-label">Maximum Attempts <span class="text-danger">*</span></label>
                                            <input type="number" class="form-control" id="max_attempts" name="max_attempts" 
                                                   value="<?php echo htmlspecialchars($_POST['max_attempts'] ?? '1'); ?>" min="1" max="10" required>
                                            <div class="invalid-feedback">Please enter maximum attempts (1-10).</div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="passing_score" class="form-label">Passing Score (%) <span class="text-danger">*</span></label>
                                            <input type="number" class="form-control" id="passing_score" name="passing_score" 
                                                   value="<?php echo htmlspecialchars($_POST['passing_score'] ?? '70'); ?>" min="0" max="100" step="0.1" required>
                                            <div class="invalid-feedback">Please enter passing score (0-100).</div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="shuffle_questions" name="shuffle_questions" 
                                                       <?php echo isset($_POST['shuffle_questions']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="shuffle_questions">
                                                    Shuffle Questions
                                                </label>
                                            </div>
                                            <div class="form-text">Present questions in random order.</div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="show_results_immediately" name="show_results_immediately" 
                                                       <?php echo !isset($_POST['show_results_immediately']) || $_POST['show_results_immediately'] ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="show_results_immediately">
                                                    Show Results Immediately
                                                </label>
                                            </div>
                                            <div class="form-text">Display results after quiz submission.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>Next Steps:</strong> After creating the quiz, you'll be able to add questions and configure answer options.
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="<?php echo BASE_URL; ?>trainings/edit.php?id=<?php echo $trainingId; ?>" class="btn btn-secondary">
                                <i class="fas fa-times me-1"></i>Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-plus me-1"></i>Create Quiz
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Bootstrap form validation
(function() {
    'use strict';
    window.addEventListener('load', function() {
        var forms = document.getElementsByClassName('needs-validation');
        var validation = Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                if (form.checkValidity() === false) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();

// Auto-populate instructions based on quiz settings
document.addEventListener('DOMContentLoaded', function() {
    const timeLimit = document.getElementById('time_limit_minutes');
    const maxAttempts = document.getElementById('max_attempts');
    const passingScore = document.getElementById('passing_score');
    const instructions = document.getElementById('instructions');
    
    function updateInstructions() {
        if (instructions.value.trim() === '') {
            let inst = 'Please read each question carefully and select the best answer.\n\n';
            
            if (timeLimit.value) {
                inst += `You have ${timeLimit.value} minutes to complete this quiz.\n`;
            }
            
            inst += `You have ${maxAttempts.value} attempt(s) to pass this quiz.\n`;
            inst += `You need to score at least ${passingScore.value}% to pass.\n\n`;
            inst += 'Good luck!';
            
            instructions.value = inst;
        }
    }
    
    timeLimit.addEventListener('change', updateInstructions);
    maxAttempts.addEventListener('change', updateInstructions);
    passingScore.addEventListener('change', updateInstructions);
});
</script>

<?php 
closeDB($conn);
include '../templates/footer.php'; 
?>
