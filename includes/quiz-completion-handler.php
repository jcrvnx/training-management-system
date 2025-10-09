<?php
/**
 * Quiz Completion Handler - Auto-generates certificates when users pass quizzes
 */

require_once 'config.php';
require_once 'functions.php';
require_once 'db.php';
require_once 'certificate-manager.php';
require_once 'notification-manager.php';

class QuizCompletionHandler {
    private $conn;
    private $certificateManager;
    private $notificationManager;
    
    public function __construct() {
        $this->conn = connectDB(false);
        if (!$this->conn) {
            throw new Exception('Database connection failed');
        }
        
        try {
            $this->certificateManager = new CertificateManager();
            $this->notificationManager = new NotificationManager();
        } catch (Exception $e) {
            error_log('Error initializing managers: ' . $e->getMessage());
        }
    }
    
    /**
     * Handle quiz completion and auto-generate certificate if passed
     */
    public function handleQuizCompletion($attemptId) {
        try {
            // Get quiz attempt details
            $stmt = $this->conn->prepare("
                SELECT qa.*, q.title as quiz_title, q.passing_score, q.training_id,
                       t.title as training_title, u.full_name, u.email, u.id as user_id
                FROM quiz_attempts qa
                JOIN quizzes q ON qa.quiz_id = q.id
                JOIN trainings t ON q.training_id = t.id
                JOIN users u ON qa.user_id = u.id
                WHERE qa.id = ? AND qa.status = 'submitted'
            ");
            
            $stmt->bind_param("i", $attemptId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $stmt->close();
                return array('success' => false, 'message' => 'Quiz attempt not found');
            }
            
            $attempt = $result->fetch_assoc();
            $stmt->close();
            
            $passed = $attempt['percentage_score'] >= $attempt['passing_score'];
            
            // Update the passed status in the attempt record
            $stmt = $this->conn->prepare("UPDATE quiz_attempts SET passed = ? WHERE id = ?");
            $stmt->bind_param("ii", $passed ? 1 : 0, $attemptId);
            $stmt->execute();
            $stmt->close();
            
            $response = array(
                'success' => true,
                'passed' => $passed,
                'score' => $attempt['percentage_score'],
                'passing_score' => $attempt['passing_score'],
                'message' => $passed ? 'Congratulations! You passed the quiz!' : 'You did not meet the passing score. Please try again.'
            );
            
            // If passed, generate certificate
            if ($passed) {
                $certificateResult = $this->generateCertificateForPassing($attempt);
                $response['certificate'] = $certificateResult;
                
                // Send notification
                if ($certificateResult['success']) {
                    $this->sendCompletionNotifications($attempt, $certificateResult);
                }
            } else {
                // Send failure notification (encouragement)
                $this->sendRetryNotification($attempt);
            }
            
            return $response;
            
        } catch (Exception $e) {
            error_log('Error handling quiz completion: ' . $e->getMessage());
            return array('success' => false, 'message' => 'Error processing quiz completion');
        }
    }
    
    /**
     * Generate certificate for passing quiz
     */
    private function generateCertificateForPassing($attempt) {
        try {
            if (!$this->certificateManager) {
                return array('success' => false, 'message' => 'Certificate manager not available');
            }
            
            // Check if certificate already exists for this training
            $stmt = $this->conn->prepare("
                SELECT id, certificate_number FROM certificates 
                WHERE user_id = ? AND training_id = ? AND status = 'active'
            ");
            $stmt->bind_param("ii", $attempt['user_id'], $attempt['training_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $existing = $result->fetch_assoc();
                $stmt->close();
                return array(
                    'success' => true, 
                    'certificate_id' => $existing['id'],
                    'certificate_number' => $existing['certificate_number'],
                    'message' => 'Certificate already exists for this training',
                    'already_exists' => true
                );
            }
            $stmt->close();
            
            // Generate new certificate
            $certificateResult = $this->certificateManager->generateCertificate(
                $attempt['user_id'], 
                $attempt['training_id'], 
                $attempt['id']
            );
            
            if ($certificateResult['success']) {
                // Update enrollment status
                $this->updateEnrollmentCompletion($attempt['user_id'], $attempt['training_id']);
                
                return array(
                    'success' => true,
                    'certificate_id' => $certificateResult['certificate_id'],
                    'certificate_number' => $certificateResult['certificate_number'],
                    'message' => 'Certificate generated successfully!',
                    'new_certificate' => true
                );
            } else {
                return $certificateResult;
            }
            
        } catch (Exception $e) {
            error_log('Error generating certificate: ' . $e->getMessage());
            return array('success' => false, 'message' => 'Error generating certificate');
        }
    }
    
    /**
     * Update enrollment completion status
     */
    private function updateEnrollmentCompletion($userId, $trainingId) {
        $stmt = $this->conn->prepare("
            UPDATE enrollments 
            SET status = 'completed', 
                completed_at = NOW(), 
                certificate_issued = 1,
                progress_percentage = 100.00
            WHERE user_id = ? AND training_id = ?
        ");
        $stmt->bind_param("ii", $userId, $trainingId);
        $stmt->execute();
        $stmt->close();
    }
    
    /**
     * Send completion notifications
     */
    private function sendCompletionNotifications($attempt, $certificateResult) {
        if (!$this->notificationManager) {
            return;
        }
        
        try {
            // Send quiz completion notification
            $this->notificationManager->notifyQuizCompletion(
                $attempt['user_id'],
                $attempt['quiz_title'],
                $attempt['training_title'],
                $attempt['percentage_score'],
                true,
                $attempt['id']
            );
            
            // Send certificate earned notification if new certificate
            if (isset($certificateResult['new_certificate']) && $certificateResult['new_certificate']) {
                $this->notificationManager->notifyCertificateEarned(
                    $attempt['user_id'],
                    $attempt['training_title'],
                    $certificateResult['certificate_id']
                );
                
                // Optional: Email the certificate
                if ($this->certificateManager) {
                    $emailResult = $this->certificateManager->emailCertificate(
                        $certificateResult['certificate_id']
                    );
                    
                    if (!$emailResult['success']) {
                        error_log('Failed to email certificate: ' . $emailResult['message']);
                    }
                }
            }
            
        } catch (Exception $e) {
            error_log('Error sending completion notifications: ' . $e->getMessage());
        }
    }
    
    /**
     * Send retry encouragement notification
     */
    private function sendRetryNotification($attempt) {
        if (!$this->notificationManager) {
            return;
        }
        
        try {
            $this->notificationManager->notifyQuizCompletion(
                $attempt['user_id'],
                $attempt['quiz_title'],
                $attempt['training_title'],
                $attempt['percentage_score'],
                false,
                $attempt['id']
            );
            
        } catch (Exception $e) {
            error_log('Error sending retry notification: ' . $e->getMessage());
        }
    }
    
    /**
     * Get user's quiz statistics
     */
    public function getUserQuizStats($userId, $trainingId = null) {
        $whereClause = $trainingId ? "AND t.id = ?" : "";
        
        $stmt = $this->conn->prepare("
            SELECT 
                COUNT(DISTINCT qa.quiz_id) as total_quizzes_taken,
                COUNT(CASE WHEN qa.passed = 1 THEN 1 END) as quizzes_passed,
                AVG(qa.percentage_score) as average_score,
                COUNT(DISTINCT c.id) as certificates_earned
            FROM quiz_attempts qa
            JOIN quizzes q ON qa.quiz_id = q.id
            JOIN trainings t ON q.training_id = t.id
            LEFT JOIN certificates c ON c.user_id = qa.user_id AND c.training_id = t.id AND c.status = 'active'
            WHERE qa.user_id = ? AND qa.status = 'submitted' {$whereClause}
        ");
        
        if ($trainingId) {
            $stmt->bind_param("ii", $userId, $trainingId);
        } else {
            $stmt->bind_param("i", $userId);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $stats = $result->fetch_assoc();
        $stmt->close();
        
        return $stats;
    }
    
    /**
     * Check if user can take quiz (enrollment and attempt limits)
     */
    public function canUserTakeQuiz($userId, $quizId) {
        // Get quiz and training info
        $stmt = $this->conn->prepare("
            SELECT q.max_attempts, q.training_id, t.title as training_title
            FROM quizzes q 
            JOIN trainings t ON q.training_id = t.id
            WHERE q.id = ? AND q.is_active = 1
        ");
        $stmt->bind_param("i", $quizId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $stmt->close();
            return array('can_take' => false, 'reason' => 'Quiz not found or inactive');
        }
        
        $quiz = $result->fetch_assoc();
        $stmt->close();
        
        // Check enrollment
        $stmt = $this->conn->prepare("
            SELECT id FROM enrollments 
            WHERE user_id = ? AND training_id = ?
        ");
        $stmt->bind_param("ii", $userId, $quiz['training_id']);
        $stmt->execute();
        $enrollResult = $stmt->get_result();
        
        if ($enrollResult->num_rows === 0) {
            $stmt->close();
            return array(
                'can_take' => false, 
                'reason' => 'You must be enrolled in the training: ' . $quiz['training_title']
            );
        }
        $stmt->close();
        
        // Check attempt count
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as attempt_count 
            FROM quiz_attempts 
            WHERE user_id = ? AND quiz_id = ? AND status IN ('submitted', 'graded')
        ");
        $stmt->bind_param("ii", $userId, $quizId);
        $stmt->execute();
        $attemptResult = $stmt->get_result();
        $attemptData = $attemptResult->fetch_assoc();
        $stmt->close();
        
        if ($attemptData['attempt_count'] >= $quiz['max_attempts']) {
            return array(
                'can_take' => false, 
                'reason' => 'You have used all allowed attempts (' . $quiz['max_attempts'] . ') for this quiz'
            );
        }
        
        return array(
            'can_take' => true, 
            'attempts_remaining' => $quiz['max_attempts'] - $attemptData['attempt_count']
        );
    }
    
    public function __destruct() {
        if ($this->conn) {
            closeDB($this->conn);
        }
    }
}

// Convenience function for handling quiz completion
function handleQuizCompletion($attemptId) {
    try {
        $handler = new QuizCompletionHandler();
        return $handler->handleQuizCompletion($attemptId);
    } catch (Exception $e) {
        error_log('Quiz completion handler error: ' . $e->getMessage());
        return array('success' => false, 'message' => 'Error processing quiz completion');
    }
}

// Function to check if user can take quiz
function canUserTakeQuiz($userId, $quizId) {
    try {
        $handler = new QuizCompletionHandler();
        return $handler->canUserTakeQuiz($userId, $quizId);
    } catch (Exception $e) {
        error_log('Quiz permission check error: ' . $e->getMessage());
        return array('can_take' => false, 'reason' => 'Error checking quiz permissions');
    }
}
?>