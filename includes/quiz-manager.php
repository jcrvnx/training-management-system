<?php
/**
 * Quiz Management System for TMS
 * Compatible with PHP 5.6+ and XAMPP 2015
 * Handles quiz creation, management, and scoring
 */

require_once 'config.php';
require_once 'functions.php';
require_once 'db.php';

class QuizManager {
    private $conn;
    
    public function __construct() {
        $this->conn = connectDB(false);
        if (!$this->conn) {
            throw new Exception('Database connection failed');
        }
    }
    
    /**
     * Create a new quiz
     */
    public function createQuiz($trainingId, $title, $description, $instructions, $timeLimitMinutes = null, $maxAttempts = 1, $passingScore = 70.0, $shuffleQuestions = false, $showResultsImmediately = true) {
        if (!hasPermission('quizzes.create')) {
            return array('success' => false, 'message' => 'Permission denied');
        }
        
        // Verify training exists and user can manage it
        if (!$this->canManageTraining($trainingId)) {
            return array('success' => false, 'message' => 'Training not found or access denied');
        }
        
        $stmt = $this->conn->prepare("
            INSERT INTO quizzes (training_id, title, description, instructions, time_limit_minutes, max_attempts, passing_score, shuffle_questions, show_results_immediately, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $userId = getCurrentUser()['id'];
        $stmt->bind_param("isssididii", 
            $trainingId, 
            $title, 
            $description, 
            $instructions,
            $timeLimitMinutes,
            $maxAttempts,
            $passingScore,
            $shuffleQuestions,
            $showResultsImmediately,
            $userId
        );
        
        if ($stmt->execute()) {
            $quizId = $this->conn->insert_id;
            $stmt->close();
            
            // Log activity
            logActivity('CREATE', 'quizzes', $quizId, null, array(
                'training_id' => $trainingId,
                'title' => $title,
                'passing_score' => $passingScore
            ));
            
            return array('success' => true, 'message' => 'Quiz created successfully', 'quiz_id' => $quizId);
        } else {
            $stmt->close();
            return array('success' => false, 'message' => 'Failed to create quiz');
        }
    }
    
    /**
     * Add question to quiz
     */
    public function addQuestion($quizId, $questionType, $questionText, $explanation = null, $points = 1.0, $sortOrder = 0, $isRequired = true) {
        if (!hasPermission('quizzes.create')) {
            return array('success' => false, 'message' => 'Permission denied');
        }
        
        // Verify quiz exists and user can manage it
        if (!$this->canManageQuiz($quizId)) {
            return array('success' => false, 'message' => 'Quiz not found or access denied');
        }
        
        $validTypes = array('multiple_choice', 'true_false', 'short_answer', 'essay');
        if (!in_array($questionType, $validTypes)) {
            return array('success' => false, 'message' => 'Invalid question type');
        }
        
        $stmt = $this->conn->prepare("
            INSERT INTO quiz_questions (quiz_id, question_type, question_text, explanation, points, sort_order, is_required) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param("isssdii", 
            $quizId, 
            $questionType, 
            $questionText, 
            $explanation,
            $points,
            $sortOrder,
            $isRequired
        );
        
        if ($stmt->execute()) {
            $questionId = $this->conn->insert_id;
            $stmt->close();
            
            // Log activity
            logActivity('CREATE', 'quiz_questions', $questionId, null, array(
                'quiz_id' => $quizId,
                'question_type' => $questionType,
                'points' => $points
            ));
            
            return array('success' => true, 'message' => 'Question added successfully', 'question_id' => $questionId);
        } else {
            $stmt->close();
            return array('success' => false, 'message' => 'Failed to add question');
        }
    }
    
    /**
     * Add option to multiple choice or true/false question
     */
    public function addQuestionOption($questionId, $optionText, $isCorrect = false, $sortOrder = 0) {
        if (!hasPermission('quizzes.create')) {
            return array('success' => false, 'message' => 'Permission denied');
        }
        
        // Verify question exists and user can manage it
        $question = $this->getQuestionInfo($questionId);
        if (!$question || !$this->canManageQuiz($question['quiz_id'])) {
            return array('success' => false, 'message' => 'Question not found or access denied');
        }
        
        $stmt = $this->conn->prepare("
            INSERT INTO question_options (question_id, option_text, is_correct, sort_order) 
            VALUES (?, ?, ?, ?)
        ");
        
        $stmt->bind_param("isii", $questionId, $optionText, $isCorrect, $sortOrder);
        
        if ($stmt->execute()) {
            $optionId = $this->conn->insert_id;
            $stmt->close();
            return array('success' => true, 'message' => 'Option added successfully', 'option_id' => $optionId);
        } else {
            $stmt->close();
            return array('success' => false, 'message' => 'Failed to add option');
        }
    }
    
    /**
     * Get quiz information
     */
    public function getQuizInfo($quizId) {
        $stmt = $this->conn->prepare("
            SELECT q.*, t.title as training_title, u.full_name as creator_name 
            FROM quizzes q
            LEFT JOIN trainings t ON q.training_id = t.id 
            LEFT JOIN users u ON q.created_by = u.id 
            WHERE q.id = ?
        ");
        
        $stmt->bind_param("i", $quizId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $stmt->close();
            return null;
        }
        
        $quiz = $result->fetch_assoc();
        $stmt->close();
        return $quiz;
    }
    
    /**
     * Get questions for a quiz
     */
    public function getQuizQuestions($quizId, $shuffle = false) {
        $orderBy = $shuffle ? "RAND()" : "sort_order ASC, id ASC";
        
        $stmt = $this->conn->prepare("
            SELECT * FROM quiz_questions 
            WHERE quiz_id = ? 
            ORDER BY {$orderBy}
        ");
        
        $stmt->bind_param("i", $quizId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $questions = array();
        while ($row = $result->fetch_assoc()) {
            // Get options for multiple choice/true-false questions
            if (in_array($row['question_type'], ['multiple_choice', 'true_false'])) {
                $row['options'] = $this->getQuestionOptions($row['id']);
            }
            $questions[] = $row;
        }
        
        $stmt->close();
        return $questions;
    }
    
    /**
     * Get options for a question
     */
    public function getQuestionOptions($questionId, $shuffle = false) {
        $orderBy = $shuffle ? "RAND()" : "sort_order ASC, id ASC";
        
        $stmt = $this->conn->prepare("
            SELECT * FROM question_options 
            WHERE question_id = ? 
            ORDER BY {$orderBy}
        ");
        
        $stmt->bind_param("i", $questionId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $options = array();
        while ($row = $result->fetch_assoc()) {
            $options[] = $row;
        }
        
        $stmt->close();
        return $options;
    }
    
    /**
     * Start quiz attempt
     */
    public function startQuizAttempt($quizId, $userId = null) {
        if (!isLoggedIn()) {
            return array('success' => false, 'message' => 'Authentication required');
        }
        
        $userId = $userId ?: getCurrentUser()['id'];
        
        // Check if user can take this quiz
        if (!$this->canTakeQuiz($quizId, $userId)) {
            return array('success' => false, 'message' => 'Cannot take this quiz');
        }
        
        // Check attempt limits
        $attemptCount = $this->getUserAttemptCount($quizId, $userId);
        $quiz = $this->getQuizInfo($quizId);
        
        if ($attemptCount >= $quiz['max_attempts']) {
            return array('success' => false, 'message' => 'Maximum attempts exceeded');
        }
        
        $attemptNumber = $attemptCount + 1;
        
        $stmt = $this->conn->prepare("
            INSERT INTO quiz_attempts (quiz_id, user_id, attempt_number, status, started_at) 
            VALUES (?, ?, ?, 'in_progress', CURRENT_TIMESTAMP)
        ");
        
        $stmt->bind_param("iii", $quizId, $userId, $attemptNumber);
        
        if ($stmt->execute()) {
            $attemptId = $this->conn->insert_id;
            $stmt->close();
            
            // Log activity
            logActivity('CREATE', 'quiz_attempts', $attemptId, null, array(
                'quiz_id' => $quizId,
                'attempt_number' => $attemptNumber
            ));
            
            return array('success' => true, 'message' => 'Quiz attempt started', 'attempt_id' => $attemptId);
        } else {
            $stmt->close();
            return array('success' => false, 'message' => 'Failed to start quiz attempt');
        }
    }
    
    /**
     * Submit quiz answer
     */
    public function submitAnswer($attemptId, $questionId, $answerText = null, $selectedOptionId = null) {
        if (!$this->canModifyAttempt($attemptId)) {
            return array('success' => false, 'message' => 'Cannot modify this attempt');
        }
        
        // Check if answer already exists
        $stmt = $this->conn->prepare("SELECT id FROM quiz_answers WHERE attempt_id = ? AND question_id = ?");
        $stmt->bind_param("ii", $attemptId, $questionId);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result->num_rows > 0;
        $stmt->close();
        
        if ($exists) {
            // Update existing answer
            $stmt = $this->conn->prepare("
                UPDATE quiz_answers 
                SET answer_text = ?, selected_option_id = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE attempt_id = ? AND question_id = ?
            ");
            $stmt->bind_param("siii", $answerText, $selectedOptionId, $attemptId, $questionId);
        } else {
            // Insert new answer
            $stmt = $this->conn->prepare("
                INSERT INTO quiz_answers (attempt_id, question_id, answer_text, selected_option_id) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->bind_param("iisi", $attemptId, $questionId, $answerText, $selectedOptionId);
        }
        
        if ($stmt->execute()) {
            $stmt->close();
            return array('success' => true, 'message' => 'Answer saved');
        } else {
            $stmt->close();
            return array('success' => false, 'message' => 'Failed to save answer');
        }
    }
    
    /**
     * Submit quiz attempt
     */
    public function submitQuizAttempt($attemptId) {
        if (!$this->canModifyAttempt($attemptId)) {
            return array('success' => false, 'message' => 'Cannot submit this attempt');
        }
        
        // Get attempt info
        $attempt = $this->getAttemptInfo($attemptId);
        if (!$attempt) {
            return array('success' => false, 'message' => 'Attempt not found');
        }
        
        // Get quiz information
        $quiz = $this->getQuizInfo($attempt['quiz_id']);
        if (!$quiz) {
            return array('success' => false, 'message' => 'Quiz not found');
        }
        
        // Calculate time taken
        $timeTaken = round((time() - strtotime($attempt['started_at'])) / 60); // minutes
        
        // Grade the attempt
        $gradingResult = $this->gradeAttempt($attemptId);
        
        if (!$gradingResult['success']) {
            return $gradingResult;
        }
        
        $totalScore = $gradingResult['total_score'];
        $percentageScore = $gradingResult['percentage_score'];
        $passed = $gradingResult['passed'];
        
        // Update attempt
        $stmt = $this->conn->prepare("
            UPDATE quiz_attempts 
            SET status = 'submitted', submitted_at = CURRENT_TIMESTAMP, time_taken_minutes = ?, total_score = ?, percentage_score = ?, passed = ?
            WHERE id = ?
        ");
        
        $stmt->bind_param("iddii", $timeTaken, $totalScore, $percentageScore, $passed, $attemptId);
        
        if ($stmt->execute()) {
            $stmt->close();
            
            // Update user progress
            $this->updateUserProgress($attempt['quiz_id'], $attempt['user_id'], $percentageScore, $passed);
            
            // Generate certificate if passed
            if ($passed) {
                require_once 'certificate-manager.php';
                $certificateResult = autoGenerateCertificateForAttempt($attemptId);
                
                if ($certificateResult['success']) {
                    logActivity('CREATE', 'certificates', $certificateResult['certificate_id'], null, array(
                        'user_id' => $attempt['user_id'],
                        'quiz_attempt_id' => $attemptId,
                        'certificate_number' => $certificateResult['certificate_number']
                    ));
                    
                    // Send certificate notification
                    require_once 'notification-manager.php';
                    $manager = new NotificationManager();
                    $manager->notifyCertificateEarned($attempt['user_id'], $quiz['training_title'], $certificateResult['certificate_id']);
                }
            }
            
            // Send quiz completion notification
            require_once 'notification-manager.php';
            $manager = new NotificationManager();
            $manager->notifyQuizCompletion($attempt['user_id'], $quiz['title'], $quiz['training_title'], $percentageScore, $passed, $attemptId);
            
            // Log activity
            logActivity('UPDATE', 'quiz_attempts', $attemptId, null, array(
                'status' => 'submitted',
                'score' => $percentageScore,
                'passed' => $passed
            ));
            
            return array(
                'success' => true, 
                'message' => 'Quiz submitted successfully', 
                'score' => $percentageScore,
                'passed' => $passed
            );
        } else {
            $stmt->close();
            return array('success' => false, 'message' => 'Failed to submit quiz');
        }
    }
    
    /**
     * Grade quiz attempt
     */
    public function gradeAttempt($attemptId) {
        $attempt = $this->getAttemptInfo($attemptId);
        if (!$attempt) {
            return array('success' => false, 'message' => 'Attempt not found');
        }
        
        $quiz = $this->getQuizInfo($attempt['quiz_id']);
        $questions = $this->getQuizQuestions($attempt['quiz_id']);
        
        $totalPoints = 0;
        $earnedPoints = 0;
        
        foreach ($questions as $question) {
            $totalPoints += $question['points'];
            
            // Get user's answer
            $stmt = $this->conn->prepare("SELECT * FROM quiz_answers WHERE attempt_id = ? AND question_id = ?");
            $stmt->bind_param("ii", $attemptId, $question['id']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $stmt->close();
                continue; // No answer provided
            }
            
            $answer = $result->fetch_assoc();
            $stmt->close();
            
            $points = 0;
            $isCorrect = false;
            
            // Grade based on question type
            switch ($question['question_type']) {
                case 'multiple_choice':
                case 'true_false':
                    if ($answer['selected_option_id']) {
                        $optionStmt = $this->conn->prepare("SELECT is_correct FROM question_options WHERE id = ?");
                        $optionStmt->bind_param("i", $answer['selected_option_id']);
                        $optionStmt->execute();
                        $optionResult = $optionStmt->get_result();
                        
                        if ($optionResult->num_rows > 0) {
                            $option = $optionResult->fetch_assoc();
                            $isCorrect = (bool)$option['is_correct'];
                            if ($isCorrect) {
                                $points = $question['points'];
                            }
                        }
                        $optionStmt->close();
                    }
                    break;
                    
                case 'short_answer':
                case 'essay':
                    // These need manual grading, set as correct for now
                    $isCorrect = null; // Needs manual review
                    $points = 0; // Will be set during manual grading
                    break;
            }
            
            // Update answer with grading results
            $updateStmt = $this->conn->prepare("UPDATE quiz_answers SET is_correct = ?, points_earned = ? WHERE id = ?");
            $updateStmt->bind_param("idi", $isCorrect, $points, $answer['id']);
            $updateStmt->execute();
            $updateStmt->close();
            
            $earnedPoints += $points;
        }
        
        $percentageScore = $totalPoints > 0 ? ($earnedPoints / $totalPoints) * 100 : 0;
        $passed = $percentageScore >= $quiz['passing_score'];
        
        return array(
            'success' => true,
            'total_score' => $earnedPoints,
            'total_possible' => $totalPoints,
            'percentage_score' => $percentageScore,
            'passed' => $passed
        );
    }
    
    /**
     * Check if user can manage training
     */
    private function canManageTraining($trainingId) {
        if (isAdmin()) {
            return true;
        }
        
        if (isTrainer()) {
            $stmt = $this->conn->prepare("SELECT id FROM trainings WHERE id = ? AND instructor_id = ?");
            $userId = getCurrentUser()['id'];
            $stmt->bind_param("ii", $trainingId, $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $canManage = $result->num_rows > 0;
            $stmt->close();
            return $canManage;
        }
        
        return false;
    }
    
    /**
     * Check if user can manage quiz
     */
    private function canManageQuiz($quizId) {
        $quiz = $this->getQuizInfo($quizId);
        return $quiz && $this->canManageTraining($quiz['training_id']);
    }
    
    /**
     * Check if user can take quiz
     */
    private function canTakeQuiz($quizId, $userId) {
        $quiz = $this->getQuizInfo($quizId);
        if (!$quiz || !$quiz['is_active']) {
            return false;
        }
        
        // Check if user is enrolled in the training
        $stmt = $this->conn->prepare("SELECT id FROM enrollments WHERE training_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $quiz['training_id'], $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $isEnrolled = $result->num_rows > 0;
        $stmt->close();
        
        return $isEnrolled;
    }
    
    /**
     * Check if user can modify attempt
     */
    private function canModifyAttempt($attemptId) {
        $attempt = $this->getAttemptInfo($attemptId);
        if (!$attempt) {
            return false;
        }
        
        $userId = getCurrentUser()['id'];
        return $attempt['user_id'] == $userId && $attempt['status'] === 'in_progress';
    }
    
    /**
     * Get attempt information
     */
    private function getAttemptInfo($attemptId) {
        $stmt = $this->conn->prepare("SELECT * FROM quiz_attempts WHERE id = ?");
        $stmt->bind_param("i", $attemptId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $stmt->close();
            return null;
        }
        
        $attempt = $result->fetch_assoc();
        $stmt->close();
        return $attempt;
    }
    
    /**
     * Get question information
     */
    private function getQuestionInfo($questionId) {
        $stmt = $this->conn->prepare("SELECT * FROM quiz_questions WHERE id = ?");
        $stmt->bind_param("i", $questionId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $stmt->close();
            return null;
        }
        
        $question = $result->fetch_assoc();
        $stmt->close();
        return $question;
    }
    
    /**
     * Get user attempt count for quiz
     */
    private function getUserAttemptCount($quizId, $userId) {
        $stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM quiz_attempts WHERE quiz_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $quizId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        return (int)$row['count'];
    }
    
    /**
     * Update user progress based on quiz completion
     */
    private function updateUserProgress($quizId, $userId, $score, $passed) {
        // Update enrollment with quiz results
        $quiz = $this->getQuizInfo($quizId);
        
        $stmt = $this->conn->prepare("
            UPDATE enrollments 
            SET final_score = ?, status = ?, updated_at = CURRENT_TIMESTAMP
            WHERE training_id = ? AND user_id = ?
        ");
        
        $status = $passed ? 'completed' : 'failed';
        $stmt->bind_param("dsii", $score, $status, $quiz['training_id'], $userId);
        $stmt->execute();
        $stmt->close();
        
        // Record progress activity
        $progressData = json_encode(array(
            'quiz_id' => $quizId,
            'score' => $score,
            'passed' => $passed
        ));
        
        $stmt = $this->conn->prepare("
            INSERT INTO user_progress (user_id, training_id, quiz_id, activity_type, progress_data) 
            VALUES (?, ?, ?, 'quiz_attempt', ?)
        ");
        
        $stmt->bind_param("iiis", $userId, $quiz['training_id'], $quizId, $progressData);
        $stmt->execute();
        $stmt->close();
    }
    
    public function __destruct() {
        if ($this->conn) {
            closeDB($this->conn);
        }
    }
}

// Handle AJAX requests
if (isset($_POST['action']) && $_POST['action'] === 'quiz_manager') {
    header('Content-Type: application/json');
    
    if (!isLoggedIn()) {
        echo json_encode(array('success' => false, 'message' => 'Authentication required'));
        exit();
    }
    
    $manager = new QuizManager();
    $response = array('success' => false, 'message' => 'Invalid request');
    
    switch ($_POST['operation']) {
        case 'start_attempt':
            $response = $manager->startQuizAttempt((int)$_POST['quiz_id']);
            break;
            
        case 'submit_answer':
            $response = $manager->submitAnswer(
                (int)$_POST['attempt_id'],
                (int)$_POST['question_id'],
                isset($_POST['answer_text']) ? sanitize($_POST['answer_text']) : null,
                isset($_POST['selected_option_id']) ? (int)$_POST['selected_option_id'] : null
            );
            break;
            
        case 'submit_attempt':
            $response = $manager->submitQuizAttempt((int)$_POST['attempt_id']);
            break;
            
        case 'get_quiz_questions':
            $questions = $manager->getQuizQuestions((int)$_POST['quiz_id']);
            $response = array('success' => true, 'questions' => $questions);
            break;
    }
    
    echo json_encode($response);
    exit();
}
?>
