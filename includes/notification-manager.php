<?php
/**
 * Notification Management System for TMS
 * Compatible with PHP 5.6+ and XAMPP 2015
 * Handles email notifications and user notifications
 */

require_once 'config.php';
require_once 'functions.php';
require_once 'db.php';

class NotificationManager {
    private $conn;
    
    public function __construct() {
        $this->conn = connectDB(false);
        if (!$this->conn) {
            throw new Exception('Database connection failed');
        }
    }
    
    /**
     * Send notification to user
     */
    public function sendNotification($userId, $type, $title, $message, $actionUrl = null, $sendEmail = true) {
        // Create in-app notification
        $stmt = $this->conn->prepare("
            INSERT INTO notifications (user_id, type, title, message, action_url, status) 
            VALUES (?, ?, ?, ?, ?, 'unread')
        ");
        
        $stmt->bind_param("issss", $userId, $type, $title, $message, $actionUrl);
        
        if ($stmt->execute()) {
            $notificationId = $this->conn->insert_id;
            $stmt->close();
            
            // Send email notification if enabled
            if ($sendEmail) {
                $this->sendEmailNotification($userId, $type, $title, $message, $actionUrl);
            }
            
            return array('success' => true, 'notification_id' => $notificationId);
        } else {
            $stmt->close();
            return array('success' => false, 'message' => 'Failed to create notification');
        }
    }
    
    /**
     * Send email notification
     */
    private function sendEmailNotification($userId, $type, $title, $message, $actionUrl = null) {
        // Get user email and preferences
        $userStmt = $this->conn->prepare("SELECT email, full_name FROM users WHERE id = ?");
        $userStmt->bind_param("i", $userId);
        $userStmt->execute();
        $userResult = $userStmt->get_result();
        
        if ($userResult->num_rows === 0) {
            $userStmt->close();
            return false;
        }
        
        $user = $userResult->fetch_assoc();
        $userStmt->close();
        
        // Check if user wants to receive email notifications for this type
        if (!$this->shouldSendEmailForType($userId, $type)) {
            return false;
        }
        
        // Get email template
        $template = $this->getEmailTemplate($type);
        if (!$template) {
            // Use default template
            $template = $this->getDefaultEmailTemplate();
        }
        
        // Replace template variables
        $emailBody = $this->replaceTemplateVariables($template['body'], array(
            'user_name' => $user['full_name'],
            'title' => $title,
            'message' => $message,
            'action_url' => $actionUrl ? BASE_URL . $actionUrl : '',
            'base_url' => BASE_URL,
            'system_name' => 'Training Management System'
        ));
        
        $emailSubject = $this->replaceTemplateVariables($template['subject'], array(
            'title' => $title,
            'system_name' => 'Training Management System'
        ));
        
        // Send email (using PHP's mail function - in production you'd use PHPMailer or similar)
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: TMS <noreply@" . $_SERVER['HTTP_HOST'] . ">" . "\r\n";
        
        return mail($user['email'], $emailSubject, $emailBody, $headers);
    }
    
    /**
     * Get user's unread notifications
     */
    public function getUserNotifications($userId, $limit = 10) {
        $stmt = $this->conn->prepare("
            SELECT * FROM notifications 
            WHERE user_id = ? AND status = 'unread' 
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        
        $stmt->bind_param("ii", $userId, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $notifications = array();
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
        
        $stmt->close();
        return $notifications;
    }
    
    /**
     * Mark notification as read
     */
    public function markAsRead($notificationId, $userId) {
        $stmt = $this->conn->prepare("
            UPDATE notifications 
            SET status = 'read', read_at = CURRENT_TIMESTAMP 
            WHERE id = ? AND user_id = ?
        ");
        
        $stmt->bind_param("ii", $notificationId, $userId);
        $success = $stmt->execute();
        $stmt->close();
        
        return $success;
    }
    
    /**
     * Mark all notifications as read for user
     */
    public function markAllAsRead($userId) {
        $stmt = $this->conn->prepare("
            UPDATE notifications 
            SET status = 'read', read_at = CURRENT_TIMESTAMP 
            WHERE user_id = ? AND status = 'unread'
        ");
        
        $stmt->bind_param("i", $userId);
        $success = $stmt->execute();
        $stmt->close();
        
        return $success;
    }
    
    /**
     * Get notification count for user
     */
    public function getUnreadCount($userId) {
        $stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND status = 'unread'");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        return (int)$row['count'];
    }
    
    /**
     * Send training enrollment notification
     */
    public function notifyTrainingEnrollment($userId, $trainingTitle, $trainingId) {
        $title = "New Training Enrollment";
        $message = "You have been enrolled in the training: {$trainingTitle}";
        $actionUrl = "trainings/view.php?id={$trainingId}";
        
        return $this->sendNotification($userId, 'enrollment', $title, $message, $actionUrl);
    }
    
    /**
     * Send training deadline reminder
     */
    public function notifyTrainingDeadline($userId, $trainingTitle, $trainingId, $daysRemaining) {
        $title = "Training Deadline Reminder";
        $message = "Training '{$trainingTitle}' is due in {$daysRemaining} day(s). Please complete it soon.";
        $actionUrl = "trainings/view.php?id={$trainingId}";
        
        return $this->sendNotification($userId, 'deadline', $title, $message, $actionUrl);
    }
    
    /**
     * Send quiz completion notification
     */
    public function notifyQuizCompletion($userId, $quizTitle, $trainingTitle, $score, $passed, $attemptId) {
        $status = $passed ? 'passed' : 'failed';
        $title = "Quiz {$status}: {$quizTitle}";
        $message = "You have {$status} the quiz '{$quizTitle}' for training '{$trainingTitle}' with a score of {$score}%.";
        $actionUrl = "quizzes/results.php?attempt_id={$attemptId}";
        
        return $this->sendNotification($userId, 'quiz_result', $title, $message, $actionUrl);
    }
    
    /**
     * Send certificate earned notification
     */
    public function notifyCertificateEarned($userId, $trainingTitle, $certificateId) {
        $title = "Certificate Earned";
        $message = "Congratulations! You have earned a certificate for completing '{$trainingTitle}'.";
        $actionUrl = "certificates/view.php?id={$certificateId}";
        
        return $this->sendNotification($userId, 'certificate', $title, $message, $actionUrl);
    }
    
    /**
     * Send bulk notification to multiple users
     */
    public function sendBulkNotification($userIds, $type, $title, $message, $actionUrl = null, $sendEmail = true) {
        $successCount = 0;
        $failureCount = 0;
        
        foreach ($userIds as $userId) {
            $result = $this->sendNotification($userId, $type, $title, $message, $actionUrl, $sendEmail);
            if ($result['success']) {
                $successCount++;
            } else {
                $failureCount++;
            }
        }
        
        return array(
            'success' => $failureCount === 0,
            'sent' => $successCount,
            'failed' => $failureCount
        );
    }
    
    /**
     * Check if user should receive email for notification type
     */
    private function shouldSendEmailForType($userId, $type) {
        // Check user preferences (if preferences table exists)
        $stmt = $this->conn->prepare("
            SELECT email_notifications 
            FROM user_preferences 
            WHERE user_id = ?
        ");
        
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $prefs = $result->fetch_assoc();
            $stmt->close();
            
            // Parse JSON preferences or use simple boolean
            if (is_string($prefs['email_notifications'])) {
                $emailPrefs = json_decode($prefs['email_notifications'], true);
                return isset($emailPrefs[$type]) ? $emailPrefs[$type] : true;
            }
            
            return (bool)$prefs['email_notifications'];
        }
        
        $stmt->close();
        // Default to true if no preferences set
        return true;
    }
    
    /**
     * Get email template for notification type
     */
    private function getEmailTemplate($type) {
        $stmt = $this->conn->prepare("SELECT * FROM email_templates WHERE template_name = ? AND is_active = 1");
        $stmt->bind_param("s", $type);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $template = $result->fetch_assoc();
            $stmt->close();
            return $template;
        }
        
        $stmt->close();
        return null;
    }
    
    /**
     * Get default email template
     */
    private function getDefaultEmailTemplate() {
        return array(
            'subject' => '[{system_name}] {title}',
            'body' => '
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .header { background: #007bff; color: white; padding: 20px; text-align: center; }
                        .content { padding: 20px; }
                        .footer { background: #f8f9fa; padding: 10px; text-align: center; font-size: 12px; color: #666; }
                        .btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <h1>{system_name}</h1>
                    </div>
                    <div class="content">
                        <h2>Hello {user_name},</h2>
                        <p>{message}</p>
                        {action_url?<p><a href="{action_url}" class="btn">View Details</a></p>}
                        <p>Thank you for using our training management system.</p>
                    </div>
                    <div class="footer">
                        <p>This is an automated message from {system_name}. Please do not reply to this email.</p>
                        <p><a href="{base_url}">Visit {system_name}</a></p>
                    </div>
                </body>
                </html>
            '
        );
    }
    
    /**
     * Replace template variables
     */
    private function replaceTemplateVariables($template, $variables) {
        $result = $template;
        
        foreach ($variables as $key => $value) {
            $result = str_replace('{' . $key . '}', $value, $result);
        }
        
        // Handle conditional sections like {action_url?<content>}
        $result = preg_replace_callback('/\{(\w+)\?\s*([^}]+)\}/', function($matches) use ($variables) {
            $key = $matches[1];
            $content = $matches[2];
            return !empty($variables[$key]) ? $content : '';
        }, $result);
        
        return $result;
    }
    
    /**
     * Send daily digest notifications
     */
    public function sendDailyDigest() {
        // Get users who want daily digest
        $userStmt = $this->conn->prepare("
            SELECT DISTINCT u.id, u.email, u.full_name 
            FROM users u
            LEFT JOIN user_preferences up ON u.id = up.user_id
            WHERE up.daily_digest = 1 OR up.daily_digest IS NULL
        ");
        
        $userStmt->execute();
        $users = $userStmt->get_result();
        
        $sent = 0;
        while ($user = $users->fetch_assoc()) {
            if ($this->generateAndSendDigest($user['id'], $user['email'], $user['full_name'])) {
                $sent++;
            }
        }
        
        $userStmt->close();
        return $sent;
    }
    
    /**
     * Generate and send daily digest for user
     */
    private function generateAndSendDigest($userId, $email, $fullName) {
        // Get unread notifications from last 24 hours
        $stmt = $this->conn->prepare("
            SELECT * FROM notifications 
            WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
            ORDER BY created_at DESC
        ");
        
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $notifications = array();
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
        $stmt->close();
        
        if (empty($notifications)) {
            return false; // No notifications to send
        }
        
        // Generate digest HTML
        $digestHtml = $this->generateDigestHTML($fullName, $notifications);
        
        // Send email
        $subject = "Daily Digest - Training Management System";
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: TMS <noreply@" . $_SERVER['HTTP_HOST'] . ">" . "\r\n";
        
        return mail($email, $subject, $digestHtml, $headers);
    }
    
    /**
     * Generate digest HTML
     */
    private function generateDigestHTML($userName, $notifications) {
        $notificationHtml = '';
        
        foreach ($notifications as $notification) {
            $icon = $this->getNotificationIcon($notification['type']);
            $notificationHtml .= "
                <div style='border: 1px solid #ddd; margin: 10px 0; padding: 15px; border-radius: 5px;'>
                    <h4 style='margin: 0 0 10px 0; color: #007bff;'>
                        {$icon} {$notification['title']}
                    </h4>
                    <p style='margin: 0 0 10px 0; color: #333;'>{$notification['message']}</p>
                    <small style='color: #666;'>
                        {$notification['created_at']}
                        " . ($notification['action_url'] ? " | <a href='" . BASE_URL . $notification['action_url'] . "'>View Details</a>" : "") . "
                    </small>
                </div>
            ";
        }
        
        return "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                    .container { max-width: 600px; margin: 0 auto; background: white; }
                    .header { background: #007bff; color: white; padding: 20px; text-align: center; }
                    .content { padding: 20px; }
                    .footer { background: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; color: #666; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>Daily Digest</h1>
                        <p>Training Management System</p>
                    </div>
                    <div class='content'>
                        <h2>Hello {$userName},</h2>
                        <p>Here's your daily summary of training activities:</p>
                        
                        <h3>Recent Notifications (" . count($notifications) . ")</h3>
                        {$notificationHtml}
                        
                        <hr style='margin: 30px 0;'>
                        <p>Visit the <a href='" . BASE_URL . "'>Training Management System</a> to view more details and take action on your trainings.</p>
                    </div>
                    <div class='footer'>
                        <p>This is an automated daily digest from Training Management System.</p>
                        <p>To unsubscribe from daily digests, update your preferences in your user profile.</p>
                    </div>
                </div>
            </body>
            </html>
        ";
    }
    
    /**
     * Get notification icon based on type
     */
    private function getNotificationIcon($type) {
        $icons = array(
            'enrollment' => '📚',
            'deadline' => '⏰',
            'quiz_result' => '📝',
            'certificate' => '🏆',
            'announcement' => '📢',
            'reminder' => '🔔'
        );
        
        return isset($icons[$type]) ? $icons[$type] : '📬';
    }
    
    public function __destruct() {
        if ($this->conn) {
            closeDB($this->conn);
        }
    }
}

// Convenience functions for common notifications
function notifyTrainingEnrollment($userId, $trainingTitle, $trainingId) {
    try {
        $manager = new NotificationManager();
        return $manager->notifyTrainingEnrollment($userId, $trainingTitle, $trainingId);
    } catch (Exception $e) {
        error_log('Notification failed: ' . $e->getMessage());
        return array('success' => false, 'message' => 'Notification failed');
    }
}

function notifyQuizCompletion($userId, $quizTitle, $trainingTitle, $score, $passed, $attemptId) {
    try {
        $manager = new NotificationManager();
        return $manager->notifyQuizCompletion($userId, $quizTitle, $trainingTitle, $score, $passed, $attemptId);
    } catch (Exception $e) {
        error_log('Notification failed: ' . $e->getMessage());
        return array('success' => false, 'message' => 'Notification failed');
    }
}

function notifyCertificateEarned($userId, $trainingTitle, $certificateId) {
    try {
        $manager = new NotificationManager();
        return $manager->notifyCertificateEarned($userId, $trainingTitle, $certificateId);
    } catch (Exception $e) {
        error_log('Notification failed: ' . $e->getMessage());
        return array('success' => false, 'message' => 'Notification failed');
    }
}

// Handle AJAX requests
if (isset($_POST['action']) && $_POST['action'] === 'notification_manager') {
    header('Content-Type: application/json');
    
    if (!isLoggedIn()) {
        echo json_encode(array('success' => false, 'message' => 'Authentication required'));
        exit();
    }
    
    $manager = new NotificationManager();
    $userId = getCurrentUser()['id'];
    $response = array('success' => false, 'message' => 'Invalid request');
    
    switch ($_POST['operation']) {
        case 'get_notifications':
            $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 10;
            $notifications = $manager->getUserNotifications($userId, $limit);
            $response = array('success' => true, 'notifications' => $notifications);
            break;
            
        case 'mark_read':
            $notificationId = (int)$_POST['notification_id'];
            $success = $manager->markAsRead($notificationId, $userId);
            $response = array('success' => $success, 'message' => $success ? 'Marked as read' : 'Failed to mark as read');
            break;
            
        case 'mark_all_read':
            $success = $manager->markAllAsRead($userId);
            $response = array('success' => $success, 'message' => $success ? 'All marked as read' : 'Failed to mark all as read');
            break;
            
        case 'get_unread_count':
            $count = $manager->getUnreadCount($userId);
            $response = array('success' => true, 'count' => $count);
            break;
    }
    
    echo json_encode($response);
    exit();
}
?>
