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

// Get quiz ID from URL
$quizId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get quiz information
$manager = new QuizManager();
$quiz = $manager->getQuizInfo($quizId);

if (!$quiz) {
    redirect('trainings/index.php');
}

// Check if user can manage this quiz
if (!isAdmin() && isTrainer()) {
    $userId = getCurrentUser()['id'];
    $accessStmt = $conn->prepare("SELECT id FROM trainings WHERE id = ? AND instructor_id = ?");
    $accessStmt->bind_param("ii", $quiz['training_id'], $userId);
    $accessStmt->execute();
    $accessResult = $accessStmt->get_result();
    
    if ($accessResult->num_rows === 0) {
        redirect('auth/unauthorized.php');
    }
    $accessStmt->close();
}

// Get existing questions
$questions = $manager->getQuizQuestions($quizId);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_question':
                $questionType = sanitize($_POST['question_type']);
                $questionText = sanitize($_POST['question_text']);
                $explanation = sanitize($_POST['explanation']);
                $points = (float)$_POST['points'];
                $sortOrder = (int)$_POST['sort_order'];
                $isRequired = isset($_POST['is_required']) ? 1 : 0;
                
                if (empty($questionText)) {
                    $errors[] = 'Question text is required';
                } elseif ($points <= 0) {
                    $errors[] = 'Points must be greater than 0';
                } else {
                    $result = $manager->addQuestion($quizId, $questionType, $questionText, $explanation, $points, $sortOrder, $isRequired);
                    if ($result['success']) {
                        $success = $result['message'];
                        // Refresh questions
                        $questions = $manager->getQuizQuestions($quizId);
                    } else {
                        $errors[] = $result['message'];
                    }
                }
                break;
                
            case 'add_option':
                $questionId = (int)$_POST['question_id'];
                $optionText = sanitize($_POST['option_text']);
                $isCorrect = isset($_POST['is_correct']) ? 1 : 0;
                $sortOrder = (int)$_POST['option_sort_order'];
                
                if (empty($optionText)) {
                    $errors[] = 'Option text is required';
                } else {
                    $result = $manager->addQuestionOption($questionId, $optionText, $isCorrect, $sortOrder);
                    if ($result['success']) {
                        $success = $result['message'];
                        // Refresh questions
                        $questions = $manager->getQuizQuestions($quizId);
                    } else {
                        $errors[] = $result['message'];
                    }
                }
                break;
        }
    }
}

include '../templates/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2><i class="fas fa-question-circle me-2"></i><?php echo htmlspecialchars($quiz['title']); ?></h2>
                    <p class="text-muted mb-0">Training: <?php echo htmlspecialchars($quiz['training_title']); ?></p>
                </div>
                <div>
                    <a href="<?php echo BASE_URL; ?>trainings/edit.php?id=<?php echo $quiz['training_id']; ?>" class="btn btn-secondary me-2">
                        <i class="fas fa-arrow-left me-1"></i>Back to Training
                    </a>
                    <a href="<?php echo BASE_URL; ?>quizzes/preview.php?id=<?php echo $quizId; ?>" class="btn btn-info">
                        <i class="fas fa-eye me-1"></i>Preview Quiz
                    </a>
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
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <div class="row">
                <!-- Quiz Information Panel -->
                <div class="col-md-4 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Quiz Information</h5>
                        </div>
                        <div class="card-body">
                            <p><strong>Description:</strong><br><?php echo nl2br(htmlspecialchars($quiz['description'])); ?></p>
                            
                            <?php if ($quiz['instructions']): ?>
                                <p><strong>Instructions:</strong><br><?php echo nl2br(htmlspecialchars($quiz['instructions'])); ?></p>
                            <?php endif; ?>
                            
                            <hr>
                            
                            <div class="row">
                                <div class="col-6">
                                    <strong>Time Limit:</strong><br>
                                    <?php echo $quiz['time_limit_minutes'] ? $quiz['time_limit_minutes'] . ' min' : 'No limit'; ?>
                                </div>
                                <div class="col-6">
                                    <strong>Max Attempts:</strong><br>
                                    <?php echo $quiz['max_attempts']; ?>
                                </div>
                            </div>
                            
                            <div class="row mt-2">
                                <div class="col-6">
                                    <strong>Passing Score:</strong><br>
                                    <?php echo $quiz['passing_score']; ?>%
                                </div>
                                <div class="col-6">
                                    <strong>Questions:</strong><br>
                                    <?php echo count($questions); ?>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="quizActive" <?php echo $quiz['is_active'] ? 'checked' : ''; ?> disabled>
                                <label class="form-check-label" for="quizActive">
                                    Quiz Active
                                </label>
                            </div>
                            
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="shuffleQuestions" <?php echo $quiz['shuffle_questions'] ? 'checked' : ''; ?> disabled>
                                <label class="form-check-label" for="shuffleQuestions">
                                    Shuffle Questions
                                </label>
                            </div>
                            
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="showResults" <?php echo $quiz['show_results_immediately'] ? 'checked' : ''; ?> disabled>
                                <label class="form-check-label" for="showResults">
                                    Show Results Immediately
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Questions Management Panel -->
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Questions (<?php echo count($questions); ?>)</h5>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addQuestionModal">
                                <i class="fas fa-plus me-1"></i>Add Question
                            </button>
                        </div>
                        <div class="card-body">
                            <?php if (empty($questions)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-question-circle fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No questions added yet</h5>
                                    <p class="text-muted">Click "Add Question" to start building your quiz.</p>
                                </div>
                            <?php else: ?>
                                <div class="accordion" id="questionsAccordion">
                                    <?php foreach ($questions as $index => $question): ?>
                                        <div class="accordion-item">
                                            <h2 class="accordion-header" id="heading<?php echo $question['id']; ?>">
                                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" 
                                                        data-bs-target="#collapse<?php echo $question['id']; ?>" aria-expanded="false">
                                                    <div class="d-flex justify-content-between align-items-center w-100 me-3">
                                                        <div>
                                                            <strong>Q<?php echo $index + 1; ?>:</strong> 
                                                            <?php echo htmlspecialchars(substr($question['question_text'], 0, 60)); ?>
                                                            <?php if (strlen($question['question_text']) > 60): ?>...<?php endif; ?>
                                                        </div>
                                                        <div>
                                                            <span class="badge bg-secondary me-2"><?php echo ucfirst(str_replace('_', ' ', $question['question_type'])); ?></span>
                                                            <span class="badge bg-primary"><?php echo $question['points']; ?> pts</span>
                                                        </div>
                                                    </div>
                                                </button>
                                            </h2>
                                            <div id="collapse<?php echo $question['id']; ?>" class="accordion-collapse collapse" 
                                                 data-bs-parent="#questionsAccordion">
                                                <div class="accordion-body">
                                                    <div class="mb-3">
                                                        <strong>Question:</strong><br>
                                                        <?php echo nl2br(htmlspecialchars($question['question_text'])); ?>
                                                    </div>
                                                    
                                                    <?php if ($question['explanation']): ?>
                                                        <div class="mb-3">
                                                            <strong>Explanation:</strong><br>
                                                            <?php echo nl2br(htmlspecialchars($question['explanation'])); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (in_array($question['question_type'], ['multiple_choice', 'true_false'])): ?>
                                                        <div class="mb-3">
                                                            <strong>Options:</strong>
                                                            <?php if (!empty($question['options'])): ?>
                                                                <ul class="list-group list-group-flush mt-2">
                                                                    <?php foreach ($question['options'] as $option): ?>
                                                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                                                            <?php echo htmlspecialchars($option['option_text']); ?>
                                                                            <?php if ($option['is_correct']): ?>
                                                                                <span class="badge bg-success">Correct</span>
                                                                            <?php endif; ?>
                                                                        </li>
                                                                    <?php endforeach; ?>
                                                                </ul>
                                                            <?php else: ?>
                                                                <p class="text-muted">No options added yet.</p>
                                                            <?php endif; ?>
                                                            
                                                            <button type="button" class="btn btn-sm btn-outline-primary mt-2" 
                                                                    onclick="showAddOptionModal(<?php echo $question['id']; ?>, '<?php echo $question['question_type']; ?>')">
                                                                <i class="fas fa-plus me-1"></i>Add Option
                                                            </button>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <small class="text-muted">Points: <?php echo $question['points']; ?></small>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <small class="text-muted">Required: <?php echo $question['is_required'] ? 'Yes' : 'No'; ?></small>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Question Modal -->
<div class="modal fade" id="addQuestionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add_question">
                <div class="modal-header">
                    <h5 class="modal-title">Add Question</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="question_type" class="form-label">Question Type</label>
                        <select class="form-select" id="question_type" name="question_type" required>
                            <option value="">Select question type...</option>
                            <option value="multiple_choice">Multiple Choice</option>
                            <option value="true_false">True/False</option>
                            <option value="short_answer">Short Answer</option>
                            <option value="essay">Essay</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="question_text" class="form-label">Question Text</label>
                        <textarea class="form-control" id="question_text" name="question_text" rows="4" required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="explanation" class="form-label">Explanation (Optional)</label>
                        <textarea class="form-control" id="explanation" name="explanation" rows="2"></textarea>
                        <div class="form-text">Explanation shown after answer submission.</div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="points" class="form-label">Points</label>
                                <input type="number" class="form-control" id="points" name="points" value="1" min="0.1" step="0.1" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="sort_order" class="form-label">Sort Order</label>
                                <input type="number" class="form-control" id="sort_order" name="sort_order" value="<?php echo count($questions) + 1; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="is_required" name="is_required" checked>
                        <label class="form-check-label" for="is_required">
                            Required Question
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Question</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Option Modal -->
<div class="modal fade" id="addOptionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add_option">
                <input type="hidden" name="question_id" id="option_question_id">
                <div class="modal-header">
                    <h5 class="modal-title">Add Option</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="option_text" class="form-label">Option Text</label>
                        <input type="text" class="form-control" id="option_text" name="option_text" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="option_sort_order" class="form-label">Sort Order</label>
                        <input type="number" class="form-control" id="option_sort_order" name="option_sort_order" value="1">
                    </div>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="is_correct" name="is_correct">
                        <label class="form-check-label" for="is_correct">
                            This is the correct answer
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Option</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showAddOptionModal(questionId, questionType) {
    document.getElementById('option_question_id').value = questionId;
    
    // Set default options for true/false questions
    if (questionType === 'true_false') {
        // Check how many options already exist for this question
        const questionCard = document.querySelector(`#collapse${questionId}`);
        const optionCount = questionCard.querySelectorAll('.list-group-item').length;
        
        if (optionCount === 0) {
            document.getElementById('option_text').value = 'True';
        } else if (optionCount === 1) {
            document.getElementById('option_text').value = 'False';
        }
    } else {
        document.getElementById('option_text').value = '';
    }
    
    new bootstrap.Modal(document.getElementById('addOptionModal')).show();
}

// Auto-refresh page after successful operations
<?php if ($success): ?>
    setTimeout(function() {
        window.location.reload();
    }, 2000);
<?php endif; ?>
</script>

<?php 
closeDB($conn);
include '../templates/footer.php'; 
?>
