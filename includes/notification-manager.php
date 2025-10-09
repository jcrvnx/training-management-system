<?php
/**
 * Notification Management System for TMS
 * Compatible with PHP 5.6+ and XAMPP 2015
 * Handles email notifications and user notifications
 */

require_once 'config.php';
require_once 'functions.php';
require_once 'db.php';
require_once dirname(__FILE__) . '/email-config.php';
require_once dirname(__FILE__) . '/phpmailer/PHPMailer.php';

class NotificationManager {
    private $conn;
    private $mail;
    
    public function __construct() {
        $this->conn = connectDB(false);
        if (!$this->conn) {
            throw new Exception('Database connection failed');
        }
        $this->initializeMailer();
    }
    
    private function initializeMailer() {
        $this->mail = new PHPMailer(true);
        
        // Server settings
        $this->mail->isSMTP();
        $this->mail->Host = SMTP_HOST;
        $this->mail->SMTPAuth = true;
        $this->mail->Username = SMTP_USERNAME;
        $this->mail->Password = SMTP_PASSWORD;
        $this->mail->SMTPSecure = SMTP_SECURE;
        $this->mail->Port = SMTP_PORT;
        
        // Default sender
        $this->mail->setFrom(FROM_EMAIL, FROM_NAME);
        $this->mail->addReplyTo(REPLY_TO_EMAIL, FROM_NAME);
        
        // Content settings
        $this->mail->isHTML(true);
        $this->mail->CharSet = 'UTF-8';
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
        
        // Send email using PHPMailer
        return $this->sendEmailWithPHPMailer($user['email'], $emailSubject, $emailBody);
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
     * Send enhanced quiz completion notification
     */
    public function notifyQuizCompletion($userId, $quizTitle, $trainingTitle, $score, $passed, $attemptId) {
        try {
            // Get user details
            $userStmt = $this->conn->prepare("SELECT email, full_name FROM users WHERE id = ?");
            $userStmt->bind_param("i", $userId);
            $userStmt->execute();
            $userResult = $userStmt->get_result();
            
            if ($userResult->num_rows === 0) {
                $userStmt->close();
                return array('success' => false, 'message' => 'User not found');
            }
            
            $user = $userResult->fetch_assoc();
            $userStmt->close();
            
            // Create in-app notification
            $status = $passed ? 'passed' : 'failed';
            $title = "Quiz {$status}: {$quizTitle}";
            $message = "You have {$status} the quiz '{$quizTitle}' for training '{$trainingTitle}' with a score of {$score}%.";
            $actionUrl = "quizzes/results.php?attempt_id={$attemptId}";
            
            $this->sendNotification($userId, 'quiz_result', $title, $message, $actionUrl, false);
            
            // Send enhanced email
            $subject = $passed ? 
                "Congratulations! You passed the quiz: $quizTitle" : 
                "Quiz Attempt Completed: $quizTitle";
            
            $body = $this->getQuizCompletionEmailTemplate($user['full_name'], $quizTitle, $trainingTitle, $score, $passed, $attemptId);
            
            return $this->sendEmailWithPHPMailer($user['email'], $subject, $body);
            
        } catch (Exception $e) {
            error_log("Quiz completion notification error: " . $e->getMessage());
            return array('success' => false, 'message' => 'Failed to send notification');
        }
    }
    
    /**
     * Send certificate earned notification with attachment
     */
    public function notifyCertificateEarned($userId, $trainingTitle, $certificateId) {
        try {
            // Get user details
            $userStmt = $this->conn->prepare("SELECT email, full_name FROM users WHERE id = ?");
            $userStmt->bind_param("i", $userId);
            $userStmt->execute();
            $userResult = $userStmt->get_result();
            
            if ($userResult->num_rows === 0) {
                $userStmt->close();
                return array('success' => false, 'message' => 'User not found');
            }
            
            $user = $userResult->fetch_assoc();
            $userStmt->close();
            
            // Get certificate details
            $certStmt = $this->conn->prepare("SELECT * FROM certificates WHERE id = ?");
            $certStmt->bind_param("i", $certificateId);
            $certStmt->execute();
            $certResult = $certStmt->get_result();
            
            if ($certResult->num_rows === 0) {
                $certStmt->close();
                return array('success' => false, 'message' => 'Certificate not found');
            }
            
            $certificate = $certResult->fetch_assoc();
            $certStmt->close();
            
            // Create in-app notification
            $title = "Certificate Earned";
            $message = "Congratulations! You have earned a certificate for completing '{$trainingTitle}'.";
            $actionUrl = "certificates/view.php?id={$certificateId}";
            
            $this->sendNotification($userId, 'certificate', $title, $message, $actionUrl, false);
            
            // Send enhanced email with certificate attachment
            $subject = "🎉 Certificate Earned: $trainingTitle";
            $body = $this->getCertificateEarnedEmailTemplate($user['full_name'], $trainingTitle, $certificate);
            
            // Prepare attachments
            $attachments = array();
            if ($certificate['file_path'] && file_exists($certificate['file_path'])) {
                $attachments[] = $certificate['file_path'];
            }
            
            return $this->sendEmailWithAttachments($user['email'], $subject, $body, $attachments);
            
        } catch (Exception $e) {
            error_log("Certificate notification error: " . $e->getMessage());
            return array('success' => false, 'message' => 'Failed to send notification');
        }
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
    
    /**
     * Send email with PHPMailer
     */
    public function sendEmailWithPHPMailer($to, $subject, $body) {
        try {
            // Clear previous recipients and attachments
            $this->mail->clearAllRecipients();
            $this->mail->clearAttachments();
            
            // Set recipient
            $this->mail->addAddress($to);
            
            // Set subject and body
            $this->mail->Subject = $subject;
            $this->mail->Body = $body;
            
            // Send email
            $result = $this->mail->send();
            
            // Log notification to database
            $this->logEmailNotification($to, $subject, $result ? 'sent' : 'failed');
            
            return array('success' => $result, 'message' => $result ? 'Email sent successfully' : 'Failed to send email');
            
        } catch (Exception $e) {
            error_log("Email sending error: " . $e->getMessage());
            $this->logEmailNotification($to, $subject, 'failed', $e->getMessage());
            return array('success' => false, 'message' => 'Email sending failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Send email with attachments
     */
    private function sendEmailWithAttachments($to, $subject, $body, $attachments = array()) {
        try {
            // Clear previous recipients and attachments
            $this->mail->clearAllRecipients();
            $this->mail->clearAttachments();
            
            // Set recipient
            $this->mail->addAddress($to);
            
            // Set subject and body
            $this->mail->Subject = $subject;
            $this->mail->Body = $body;
            
            // Add attachments
            if (!empty($attachments)) {
                foreach ($attachments as $attachment) {
                    if (file_exists($attachment)) {
                        $this->mail->addAttachment($attachment);
                    }
                }
            }
            
            // Send email
            $result = $this->mail->send();
            
            // Log notification to database
            $this->logEmailNotification($to, $subject, $result ? 'sent' : 'failed');
            
            return array('success' => $result, 'message' => $result ? 'Email sent successfully' : 'Failed to send email');
            
        } catch (Exception $e) {
            error_log("Email sending error: " . $e->getMessage());
            $this->logEmailNotification($to, $subject, 'failed', $e->getMessage());
            return array('success' => false, 'message' => 'Email sending failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Log email notification to database
     */
    private function logEmailNotification($recipient, $subject, $status, $error = null) {
        try {
            // Check if notifications table has the required columns
            $stmt = $this->conn->prepare("INSERT INTO notifications (recipient_email, subject, status, error_message, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->bind_param("ssss", $recipient, $subject, $status, $error);
            $stmt->execute();
            $stmt->close();
        } catch (Exception $e) {
            // If the table structure doesn't support logging, skip it
            error_log("Failed to log email notification: " . $e->getMessage());
        }
    }
    
    /**
     * Get quiz completion email template
     */
    private function getQuizCompletionEmailTemplate($userName, $quizTitle, $trainingTitle, $score, $passed, $attemptId) {
        $statusMessage = $passed ? 
            "<span style='color: #28a745; font-weight: bold;'>PASSED</span>" : 
            "<span style='color: #dc3545; font-weight: bold;'>NEEDS IMPROVEMENT</span>";
        
        $congratsMessage = $passed ? 
            "<p style='color: #28a745; font-size: 18px; font-weight: bold;'>🎉 Congratulations! You have successfully passed the quiz!</p>" : 
            "<p style='color: #dc3545; font-size: 16px;'>Keep learning! You can retake the quiz to improve your score.</p>";
        
        $baseUrl = 'http://' . $_SERVER['HTTP_HOST'] . '/training-management-system';
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Quiz Completion</title>
        </head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;'>
            <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center; color: white; border-radius: 10px 10px 0 0;'>
                <h1 style='margin: 0; font-size: 28px;'>Quiz Completed!</h1>
                <p style='margin: 10px 0 0 0; font-size: 16px; opacity: 0.9;'>Training Management System</p>
            </div>
            
            <div style='background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; border: 1px solid #e9ecef;'>
                <h2 style='color: #495057; margin-top: 0;'>Hello $userName,</h2>
                
                <p>You have completed a quiz for the training program. Here are your results:</p>
                
                <div style='background: white; padding: 20px; border-radius: 8px; border-left: 4px solid #007bff; margin: 20px 0;'>
                    <h3 style='margin-top: 0; color: #007bff;'>Quiz Results</h3>
                    <p><strong>Training:</strong> $trainingTitle</p>
                    <p><strong>Quiz:</strong> $quizTitle</p>
                    <p><strong>Score:</strong> $score%</p>
                    <p><strong>Status:</strong> $statusMessage</p>
                    <p><strong>Attempt ID:</strong> #$attemptId</p>
                </div>
                
                $congratsMessage
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='$baseUrl/dashboard.php' style='background: #007bff; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;'>View Dashboard</a>
                </div>
                
                <hr style='border: none; border-top: 1px solid #dee2e6; margin: 30px 0;'>
                
                <p style='color: #6c757d; font-size: 14px; margin: 0;'>
                    This is an automated message from the Training Management System. Please do not reply to this email.
                </p>
            </div>
        </body>
        </html>
        ";
    }
    
    /**
     * Get certificate earned email template
     */
    private function getCertificateEarnedEmailTemplate($userName, $trainingTitle, $certificate) {
        $baseUrl = 'http://' . $_SERVER['HTTP_HOST'] . '/training-management-system';
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Certificate Earned</title>
        </head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;'>
            <div style='background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%); padding: 30px; text-align: center; color: #8b4513; border-radius: 10px 10px 0 0;'>
                <h1 style='margin: 0; font-size: 28px;'>🎉 Certificate Earned!</h1>
                <p style='margin: 10px 0 0 0; font-size: 16px; opacity: 0.9;'>Training Management System</p>
            </div>
            
            <div style='background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; border: 1px solid #e9ecef;'>
                <h2 style='color: #495057; margin-top: 0;'>Congratulations $userName!</h2>
                
                <p style='font-size: 18px; color: #28a745; font-weight: bold;'>🎊 You have successfully earned a certificate!</p>
                
                <div style='background: white; padding: 20px; border-radius: 8px; border-left: 4px solid #28a745; margin: 20px 0;'>
                    <h3 style='margin-top: 0; color: #28a745;'>Certificate Details</h3>
                    <p><strong>Training Program:</strong> $trainingTitle</p>
                    <p><strong>Certificate ID:</strong> #{$certificate['id']}</p>
                    <p><strong>Issue Date:</strong> " . date('F j, Y', strtotime($certificate['issued_at'])) . "</p>
                    <p><strong>Valid Until:</strong> " . ($certificate['expires_at'] ? date('F j, Y', strtotime($certificate['expires_at'])) : 'No expiration') . "</p>
                </div>
                
                <div style='background: #e8f5e8; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                    <p style='margin: 0; color: #155724;'><strong>🏆 Achievement Unlocked!</strong></p>
                    <p style='margin: 10px 0 0 0; color: #155724;'>You have demonstrated mastery of the training material and earned this certificate of completion.</p>
                </div>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='$baseUrl/certificates/my-certificates.php' style='background: #28a745; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold; margin-right: 10px;'>View My Certificates</a>
                    <a href='$baseUrl/dashboard.php' style='background: #007bff; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;'>Dashboard</a>
                </div>
                
                <hr style='border: none; border-top: 1px solid #dee2e6; margin: 30px 0;'>
                
                <p style='color: #6c757d; font-size: 14px; margin: 0;'>
                    This certificate has been attached to this email. You can also download it anytime from your dashboard.
                </p>
            </div>
        </body>
        </html>
        ";
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
