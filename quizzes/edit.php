<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

// Check if user is logged in and has appropriate permissions
if (!isLoggedIn()) {
    redirect(url('auth/login.php'));
}

if (!hasMinimumRole('trainer')) {
    redirect(url('auth/unauthorized.php'));
}

// Check if quiz ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = 'Invalid quiz ID.';
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

// Get quiz details and verify permissions
$quizQuery = "SELECT q.*, t.title as training_title, t.instructor_id 
              FROM quizzes q 
              LEFT JOIN trainings t ON q.training_id = t.id 
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

// Check if user has permission to edit this quiz
if (!isAdmin() && $quiz['created_by'] !== $currentUser['id'] && 
    (isset($quiz['instructor_id']) && $quiz['instructor_id'] !== $currentUser['id'])) {
    $_SESSION['error'] = 'You do not have permission to edit this quiz.';
    redirect(url('quizzes/manage.php'));
}

// Get all trainings for the dropdown
$trainingsQuery = "SELECT id, title FROM trainings ORDER BY title";
$trainingsResult = $conn->query($trainingsQuery);
$trainings = [];
while ($training = $trainingsResult->fetch_assoc()) {
    $trainings[] = $training;
}

// Get quiz questions
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $instructions = trim($_POST['instructions']);
    $training_id = (int)$_POST['training_id'];
    $time_limit_minutes = !empty($_POST['time_limit_minutes']) ? (int)$_POST['time_limit_minutes'] : null;
    $max_attempts = !empty($_POST['max_attempts']) ? (int)$_POST['max_attempts'] : 1;
    $passing_score = !empty($_POST['passing_score']) ? (float)$_POST['passing_score'] : 70.00;
    $shuffle_questions = isset($_POST['shuffle_questions']) ? 1 : 0;
    $show_results_immediately = isset($_POST['show_results_immediately']) ? 1 : 0;
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    $questions_data = isset($_POST['questions']) ? $_POST['questions'] : [];
    
    // Validation
    $errors = [];
    
    if (empty($title)) {
        $errors[] = 'Quiz title is required.';
    }
    
    if (empty($training_id)) {
        $errors[] = 'Please select a training.';
    }
    
    if (empty($questions_data)) {
        $errors[] = 'At least one question is required.';
    }
    
    foreach ($questions_data as $index => $question_data) {
        if (empty(trim($question_data['question_text']))) {
            $errors[] = "Question " . ($index + 1) . " text is required.";
        }
        
        if ($question_data['question_type'] === 'multiple_choice') {
            $has_correct = false;
            $option_count = 0;
            
            if (isset($question_data['options'])) {
                foreach ($question_data['options'] as $opt_index => $option) {
                    if (!empty(trim($option['option_text']))) {
                        $option_count++;
                        if (isset($option['is_correct'])) {
                            $has_correct = true;
                        }
                    }
                }
            }
            
            if ($option_count < 2) {
                $errors[] = "Question " . ($index + 1) . " must have at least 2 options.";
            }
            
            if (!$has_correct) {
                $errors[] = "Question " . ($index + 1) . " must have at least one correct answer.";
            }
        }
    }
    
    if (empty($errors)) {
        $conn->begin_transaction();
        
        try {
            // Update quiz
            $updateQuizQuery = "UPDATE quizzes SET 
                              title = ?, description = ?, instructions = ?, training_id = ?, 
                              time_limit_minutes = ?, max_attempts = ?, passing_score = ?, 
                              shuffle_questions = ?, show_results_immediately = ?, is_active = ?, 
                              updated_at = CURRENT_TIMESTAMP 
                              WHERE id = ?";
            $updateQuizStmt = $conn->prepare($updateQuizQuery);
            $updateQuizStmt->bind_param("sssiidiiiii", $title, $description, $instructions, $training_id, 
                                       $time_limit_minutes, $max_attempts, $passing_score, 
                                       $shuffle_questions, $show_results_immediately, $is_active, $quizId);
            $updateQuizStmt->execute();
            $updateQuizStmt->close();
            
            // Delete existing questions and options
            $deleteOptionsQuery = "DELETE qo FROM question_options qo 
                                 JOIN quiz_questions qq ON qo.question_id = qq.id 
                                 WHERE qq.quiz_id = ?";
            $deleteOptionsStmt = $conn->prepare($deleteOptionsQuery);
            $deleteOptionsStmt->bind_param("i", $quizId);
            $deleteOptionsStmt->execute();
            $deleteOptionsStmt->close();
            
            $deleteQuestionsQuery = "DELETE FROM quiz_questions WHERE quiz_id = ?";
            $deleteQuestionsStmt = $conn->prepare($deleteQuestionsQuery);
            $deleteQuestionsStmt->bind_param("i", $quizId);
            $deleteQuestionsStmt->execute();
            $deleteQuestionsStmt->close();
            
            // Insert new questions
            $insertQuestionQuery = "INSERT INTO quiz_questions 
                                  (quiz_id, question_type, question_text, explanation, points, sort_order, is_required) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?)";
            $insertQuestionStmt = $conn->prepare($insertQuestionQuery);
            
            $insertOptionQuery = "INSERT INTO question_options 
                                (question_id, option_text, is_correct, sort_order) 
                                VALUES (?, ?, ?, ?)";
            $insertOptionStmt = $conn->prepare($insertOptionQuery);
            
            foreach ($questions_data as $sort_order => $question_data) {
                $question_type = $question_data['question_type'];
                $question_text = trim($question_data['question_text']);
                $explanation = trim($question_data['explanation']);
                $points = !empty($question_data['points']) ? (float)$question_data['points'] : 1.00;
                $is_required = isset($question_data['is_required']) ? 1 : 0;
                
                $insertQuestionStmt->bind_param("isssdii", $quizId, $question_type, $question_text, 
                                               $explanation, $points, $sort_order, $is_required);
                $insertQuestionStmt->execute();
                $questionId = $conn->insert_id;
                
                // Insert options for multiple choice questions
                if ($question_type === 'multiple_choice' && isset($question_data['options'])) {
                    foreach ($question_data['options'] as $opt_sort_order => $option) {
                        $option_text = trim($option['option_text']);
                        if (!empty($option_text)) {
                            $is_correct = isset($option['is_correct']) ? 1 : 0;
                            
                            $insertOptionStmt->bind_param("isii", $questionId, $option_text, $is_correct, $opt_sort_order);
                            $insertOptionStmt->execute();
                        }
                    }
                }
                
                // For true/false questions, automatically create True/False options
                if ($question_type === 'true_false') {
                    $correct_answer = $question_data['correct_answer'] ?? 'true';
                    
                    $insertOptionStmt->bind_param("isii", $questionId, "True", 
                                                 $correct_answer === 'true' ? 1 : 0, 0);
                    $insertOptionStmt->execute();
                    
                    $insertOptionStmt->bind_param("isii", $questionId, "False", 
                                                 $correct_answer === 'false' ? 1 : 0, 1);
                    $insertOptionStmt->execute();
                }
            }
            
            $insertQuestionStmt->close();
            $insertOptionStmt->close();
            
            $conn->commit();
            
            $_SESSION['message'] = 'Quiz updated successfully!';
            redirect(url('quizzes/manage.php'));
            
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = 'Error updating quiz: ' . $e->getMessage();
        }
    }
}

closeDB($conn);

// Include header
include_once '../templates/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1><i class="fas fa-edit text-primary me-2"></i>Edit Quiz</h1>
                <p class="text-muted mb-0">Modify quiz questions and settings</p>
            </div>
            <div>
                <a href="<?php echo url('quizzes/manage.php'); ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i>Back to Manage Quizzes
                </a>
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

<form method="POST" id="quizForm">
    <!-- Quiz Basic Information -->
    <div class="card shadow mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Quiz Information</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="title" class="form-label">Quiz Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="title" name="title" 
                               value="<?php echo htmlspecialchars($quiz['title']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="training_id" class="form-label">Training <span class="text-danger">*</span></label>
                        <select class="form-select" id="training_id" name="training_id" required>
                            <option value="">Select Training...</option>
                            <?php foreach ($trainings as $training): ?>
                            <option value="<?php echo $training['id']; ?>" 
                                    <?php echo $training['id'] == $quiz['training_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($training['title']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="time_limit_minutes" class="form-label">Time Limit (minutes)</label>
                        <input type="number" class="form-control" id="time_limit_minutes" name="time_limit_minutes" 
                               value="<?php echo $quiz['time_limit_minutes']; ?>" min="1">
                        <small class="form-text text-muted">Leave empty for no time limit</small>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="max_attempts" class="form-label">Maximum Attempts</label>
                        <input type="number" class="form-control" id="max_attempts" name="max_attempts" 
                               value="<?php echo $quiz['max_attempts']; ?>" min="1" max="10">
                    </div>
                    
                    <div class="mb-3">
                        <label for="passing_score" class="form-label">Passing Score (%)</label>
                        <input type="number" class="form-control" id="passing_score" name="passing_score" 
                               value="<?php echo $quiz['passing_score']; ?>" min="0" max="100" step="0.1">
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="shuffle_questions" 
                                   name="shuffle_questions" <?php echo $quiz['shuffle_questions'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="shuffle_questions">
                                Shuffle Questions
                            </label>
                        </div>
                        
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="show_results_immediately" 
                                   name="show_results_immediately" <?php echo $quiz['show_results_immediately'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="show_results_immediately">
                                Show Results Immediately
                            </label>
                        </div>
                        
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_active" 
                                   name="is_active" <?php echo $quiz['is_active'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="is_active">
                                Active (available to students)
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="description" class="form-label">Description</label>
                <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($quiz['description']); ?></textarea>
            </div>
            
            <div class="mb-3">
                <label for="instructions" class="form-label">Instructions</label>
                <textarea class="form-control" id="instructions" name="instructions" rows="4"><?php echo htmlspecialchars($quiz['instructions']); ?></textarea>
                <small class="form-text text-muted">Instructions that will be shown to students before they start the quiz</small>
            </div>
        </div>
    </div>
    
    <!-- Questions Section -->
    <div class="card shadow mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-question-circle me-2"></i>Questions</h5>
            <button type="button" class="btn btn-success btn-sm" onclick="addQuestion()">
                <i class="fas fa-plus me-1"></i>Add Question
            </button>
        </div>
        <div class="card-body">
            <div id="questionsContainer">
                <?php foreach ($questions as $index => $question): ?>
                <div class="question-card" data-question-index="<?php echo $index; ?>">
                    <div class="card border-secondary mb-3">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span>Question <?php echo $index + 1; ?></span>
                            <button type="button" class="btn btn-danger btn-sm" onclick="removeQuestion(this)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                        <div class="card-body">
                            <input type="hidden" name="questions[<?php echo $index; ?>][id]" value="<?php echo $question['id']; ?>">
                            
                            <div class="row mb-3">
                                <div class="col-md-8">
                                    <label class="form-label">Question Text <span class="text-danger">*</span></label>
                                    <textarea class="form-control" name="questions[<?php echo $index; ?>][question_text]" rows="3" required><?php echo htmlspecialchars($question['question_text']); ?></textarea>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Question Type</label>
                                    <select class="form-select" name="questions[<?php echo $index; ?>][question_type]" onchange="toggleQuestionType(this)">
                                        <option value="multiple_choice" <?php echo $question['question_type'] === 'multiple_choice' ? 'selected' : ''; ?>>Multiple Choice</option>
                                        <option value="true_false" <?php echo $question['question_type'] === 'true_false' ? 'selected' : ''; ?>>True/False</option>
                                        <option value="short_answer" <?php echo $question['question_type'] === 'short_answer' ? 'selected' : ''; ?>>Short Answer</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Points</label>
                                    <input type="number" class="form-control" name="questions[<?php echo $index; ?>][points]" 
                                           value="<?php echo $question['points']; ?>" min="0.1" step="0.1">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Explanation (optional)</label>
                                    <textarea class="form-control" name="questions[<?php echo $index; ?>][explanation]" rows="2"><?php echo htmlspecialchars($question['explanation']); ?></textarea>
                                </div>
                            </div>
                            
                            <!-- Multiple Choice Options -->
                            <div class="options-container" style="<?php echo $question['question_type'] !== 'multiple_choice' ? 'display: none;' : ''; ?>">
                                <label class="form-label">Answer Options</label>
                                <div class="options-list">
                                    <?php foreach ($question['options'] as $optIndex => $option): ?>
                                    <div class="input-group mb-2">
                                        <div class="input-group-text">
                                            <input type="checkbox" name="questions[<?php echo $index; ?>][options][<?php echo $optIndex; ?>][is_correct]" 
                                                   <?php echo $option['is_correct'] ? 'checked' : ''; ?>>
                                        </div>
                                        <input type="text" class="form-control" 
                                               name="questions[<?php echo $index; ?>][options][<?php echo $optIndex; ?>][option_text]" 
                                               value="<?php echo htmlspecialchars($option['option_text']); ?>" 
                                               placeholder="Option text">
                                        <button type="button" class="btn btn-outline-danger" onclick="removeOption(this)">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="addOption(this)">
                                    <i class="fas fa-plus me-1"></i>Add Option
                                </button>
                            </div>
                            
                            <!-- True/False Options -->
                            <div class="true-false-container" style="<?php echo $question['question_type'] !== 'true_false' ? 'display: none;' : ''; ?>">
                                <label class="form-label">Correct Answer</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="questions[<?php echo $index; ?>][correct_answer]" 
                                           value="true" <?php echo (isset($question['options'][0]) && $question['options'][0]['is_correct']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label">True</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="questions[<?php echo $index; ?>][correct_answer]" 
                                           value="false" <?php echo (isset($question['options'][1]) && $question['options'][1]['is_correct']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label">False</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (empty($questions)): ?>
            <p class="text-muted text-center">No questions added yet. Click "Add Question" to get started.</p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Submit Button -->
    <div class="card shadow">
        <div class="card-body text-center">
            <button type="submit" class="btn btn-primary btn-lg me-3">
                <i class="fas fa-save me-2"></i>Update Quiz
            </button>
            <a href="<?php echo url('quizzes/manage.php'); ?>" class="btn btn-secondary btn-lg">
                <i class="fas fa-times me-2"></i>Cancel
            </a>
        </div>
    </div>
</form>

<script>
let questionCounter = <?php echo count($questions); ?>;

function addQuestion() {
    const container = document.getElementById('questionsContainer');
    const questionHtml = `
        <div class="question-card" data-question-index="${questionCounter}">
            <div class="card border-secondary mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Question ${questionCounter + 1}</span>
                    <button type="button" class="btn btn-danger btn-sm" onclick="removeQuestion(this)">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-8">
                            <label class="form-label">Question Text <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="questions[${questionCounter}][question_text]" rows="3" required></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Question Type</label>
                            <select class="form-select" name="questions[${questionCounter}][question_type]" onchange="toggleQuestionType(this)">
                                <option value="multiple_choice">Multiple Choice</option>
                                <option value="true_false">True/False</option>
                                <option value="short_answer">Short Answer</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Points</label>
                            <input type="number" class="form-control" name="questions[${questionCounter}][points]" value="1.0" min="0.1" step="0.1">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Explanation (optional)</label>
                            <textarea class="form-control" name="questions[${questionCounter}][explanation]" rows="2"></textarea>
                        </div>
                    </div>
                    
                    <div class="options-container">
                        <label class="form-label">Answer Options</label>
                        <div class="options-list">
                            <div class="input-group mb-2">
                                <div class="input-group-text">
                                    <input type="checkbox" name="questions[${questionCounter}][options][0][is_correct]">
                                </div>
                                <input type="text" class="form-control" name="questions[${questionCounter}][options][0][option_text]" placeholder="Option text">
                                <button type="button" class="btn btn-outline-danger" onclick="removeOption(this)">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <div class="input-group mb-2">
                                <div class="input-group-text">
                                    <input type="checkbox" name="questions[${questionCounter}][options][1][is_correct]">
                                </div>
                                <input type="text" class="form-control" name="questions[${questionCounter}][options][1][option_text]" placeholder="Option text">
                                <button type="button" class="btn btn-outline-danger" onclick="removeOption(this)">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="addOption(this)">
                            <i class="fas fa-plus me-1"></i>Add Option
                        </button>
                    </div>
                    
                    <div class="true-false-container" style="display: none;">
                        <label class="form-label">Correct Answer</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="questions[${questionCounter}][correct_answer]" value="true">
                            <label class="form-check-label">True</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="questions[${questionCounter}][correct_answer]" value="false">
                            <label class="form-check-label">False</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', questionHtml);
    questionCounter++;
    updateQuestionNumbers();
}

function removeQuestion(button) {
    const questionCard = button.closest('.question-card');
    questionCard.remove();
    updateQuestionNumbers();
}

function addOption(button) {
    const optionsList = button.previousElementSibling;
    const questionIndex = button.closest('.question-card').dataset.questionIndex;
    const optionIndex = optionsList.children.length;
    
    const optionHtml = `
        <div class="input-group mb-2">
            <div class="input-group-text">
                <input type="checkbox" name="questions[${questionIndex}][options][${optionIndex}][is_correct]">
            </div>
            <input type="text" class="form-control" name="questions[${questionIndex}][options][${optionIndex}][option_text]" placeholder="Option text">
            <button type="button" class="btn btn-outline-danger" onclick="removeOption(this)">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    
    optionsList.insertAdjacentHTML('beforeend', optionHtml);
}

function removeOption(button) {
    const optionGroup = button.closest('.input-group');
    optionGroup.remove();
}

function toggleQuestionType(select) {
    const questionCard = select.closest('.question-card');
    const optionsContainer = questionCard.querySelector('.options-container');
    const trueFalseContainer = questionCard.querySelector('.true-false-container');
    
    if (select.value === 'multiple_choice') {
        optionsContainer.style.display = 'block';
        trueFalseContainer.style.display = 'none';
    } else if (select.value === 'true_false') {
        optionsContainer.style.display = 'none';
        trueFalseContainer.style.display = 'block';
    } else {
        optionsContainer.style.display = 'none';
        trueFalseContainer.style.display = 'none';
    }
}

function updateQuestionNumbers() {
    const questionCards = document.querySelectorAll('.question-card');
    questionCards.forEach((card, index) => {
        const header = card.querySelector('.card-header span');
        header.textContent = `Question ${index + 1}`;
        card.dataset.questionIndex = index;
    });
}
</script>

<?php include_once '../templates/footer.php'; ?>