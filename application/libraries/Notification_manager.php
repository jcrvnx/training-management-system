<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Notification_manager {

    protected $CI;

    public function __construct() {
        $this->CI =& get_instance();
        $this->CI->config->load('email');
        $this->CI->load->library('email');
        $this->CI->load->model('User_model');
    }

    /**
     * Send training enrollment notification
     */
    public function send_enrollment_notification($user_id, $training_id) {
        $user = $this->CI->User_model->get_user($user_id);
        $training = $this->CI->db->get_where('trainings', ['id' => $training_id])->row_array();
        
        if (!$user || !$training) {
            return false;
        }

        $subject = 'Training Enrollment Confirmation - ' . $training['title'];
        $message = $this->_get_enrollment_template($user, $training);
        
        return $this->_send_email($user['email'], $subject, $message);
    }

    /**
     * Send training completion notification
     */
    public function send_completion_notification($user_id, $training_id) {
        $user = $this->CI->User_model->get_user($user_id);
        $training = $this->CI->db->get_where('trainings', ['id' => $training_id])->row_array();
        
        if (!$user || !$training) {
            return false;
        }

        $subject = 'Training Completed Successfully - ' . $training['title'];
        $message = $this->_get_completion_template($user, $training);
        
        return $this->_send_email($user['email'], $subject, $message);
    }

    /**
     * Send training reminder notification
     */
    public function send_training_reminder($user_id, $training_id, $days_until = 7) {
        $user = $this->CI->User_model->get_user($user_id);
        $training = $this->CI->db->get_where('trainings', ['id' => $training_id])->row_array();
        
        if (!$user || !$training) {
            return false;
        }

        $subject = 'Training Reminder - ' . $training['title'] . ' starts in ' . $days_until . ' days';
        $message = $this->_get_reminder_template($user, $training, $days_until);
        
        return $this->_send_email($user['email'], $subject, $message);
    }

    /**
     * Send certificate issued notification
     */
    public function send_certificate_notification($user_id, $certificate_id) {
        $user = $this->CI->User_model->get_user($user_id);
        
        $this->CI->db->select('c.*, t.title as training_title');
        $this->CI->db->from('certificates c');
        $this->CI->db->join('trainings t', 't.id = c.training_id');
        $this->CI->db->where('c.id', $certificate_id);
        $certificate = $this->CI->db->get()->row_array();
        
        if (!$user || !$certificate) {
            return false;
        }

        $subject = 'Certificate Issued - ' . $certificate['training_title'];
        $message = $this->_get_certificate_template($user, $certificate);
        
        return $this->_send_email($user['email'], $subject, $message);
    }

    /**
     * Send quiz attempt notification
     */
    public function send_quiz_result_notification($user_id, $attempt_id) {
        $user = $this->CI->User_model->get_user($user_id);
        
        $this->CI->db->select('qa.*, q.title as quiz_title, t.title as training_title');
        $this->CI->db->from('quiz_attempts qa');
        $this->CI->db->join('quizzes q', 'q.id = qa.quiz_id');
        $this->CI->db->join('trainings t', 't.id = q.training_id', 'left');
        $this->CI->db->where('qa.id', $attempt_id);
        $attempt = $this->CI->db->get()->row_array();
        
        if (!$user || !$attempt) {
            return false;
        }

        $passed = $attempt['passed'] ? 'Passed' : 'Failed';
        $subject = 'Quiz Result - ' . $attempt['quiz_title'] . ' (' . $passed . ')';
        $message = $this->_get_quiz_result_template($user, $attempt);
        
        return $this->_send_email($user['email'], $subject, $message);
    }

    /**
     * Send bulk notification to multiple users
     */
    public function send_bulk_notification($user_ids, $subject, $message, $template_data = []) {
        $sent_count = 0;
        
        foreach ($user_ids as $user_id) {
            $user = $this->CI->User_model->get_user($user_id);
            if ($user && $user['is_active']) {
                $personalized_message = $this->_personalize_message($message, $user, $template_data);
                if ($this->_send_email($user['email'], $subject, $personalized_message)) {
                    $sent_count++;
                }
            }
        }
        
        return $sent_count;
    }

    /**
     * Create system notifications (in-app)
     */
    public function create_system_notification($user_id, $title, $message, $type = 'info', $related_id = null, $related_type = null) {
        // Check if notifications table exists
        if (!$this->CI->db->table_exists('notifications')) {
            return false;
        }

        $data = [
            'user_id' => $user_id,
            'title' => $title,
            'message' => $message,
            'type' => $type, // info, success, warning, error
            'related_id' => $related_id,
            'related_type' => $related_type, // training, quiz, certificate, etc.
            'is_read' => 0,
            'created_at' => date('Y-m-d H:i:s')
        ];

        return $this->CI->db->insert('notifications', $data);
    }

    /**
     * Get unread notifications for a user
     */
    public function get_user_notifications($user_id, $limit = 10) {
        if (!$this->CI->db->table_exists('notifications')) {
            return [];
        }

        $this->CI->db->where('user_id', $user_id);
        $this->CI->db->where('is_read', 0);
        $this->CI->db->order_by('created_at', 'DESC');
        $this->CI->db->limit($limit);
        
        return $this->CI->db->get('notifications')->result_array();
    }

    /**
     * Mark notification as read
     */
    public function mark_as_read($notification_id, $user_id = null) {
        if (!$this->CI->db->table_exists('notifications')) {
            return false;
        }

        $this->CI->db->where('id', $notification_id);
        if ($user_id) {
            $this->CI->db->where('user_id', $user_id);
        }
        
        return $this->CI->db->update('notifications', ['is_read' => 1, 'read_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * Send email using CodeIgniter email library
     */
    private function _send_email($to, $subject, $message) {
        try {
            $this->CI->email->from($this->CI->config->item('smtp_user'), 'Training Management System');
            $this->CI->email->to($to);
            $this->CI->email->subject($subject);
            $this->CI->email->message($message);
            
            return $this->CI->email->send();
        } catch (Exception $e) {
            log_message('error', 'Email sending failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get enrollment email template
     */
    private function _get_enrollment_template($user, $training) {
        return "
        <h2>Training Enrollment Confirmation</h2>
        <p>Dear {$user['full_name']},</p>
        <p>You have been successfully enrolled in the following training:</p>
        <ul>
            <li><strong>Training:</strong> {$training['title']}</li>
            <li><strong>Start Date:</strong> " . date('F j, Y', strtotime($training['start_date'])) . "</li>
            <li><strong>End Date:</strong> " . date('F j, Y', strtotime($training['end_date'])) . "</li>
            <li><strong>Duration:</strong> {$training['duration_hours']} hours</li>
        </ul>
        <p>Please log in to the Training Management System to access your course materials and track your progress.</p>
        <p>Best regards,<br>Training Management Team</p>
        ";
    }

    /**
     * Get completion email template
     */
    private function _get_completion_template($user, $training) {
        return "
        <h2>Congratulations on Completing Your Training!</h2>
        <p>Dear {$user['full_name']},</p>
        <p>Congratulations! You have successfully completed the training:</p>
        <ul>
            <li><strong>Training:</strong> {$training['title']}</li>
            <li><strong>Completion Date:</strong> " . date('F j, Y') . "</li>
        </ul>
        <p>Your certificate will be available shortly in your dashboard. You can download and print it for your records.</p>
        <p>Thank you for your dedication to professional development!</p>
        <p>Best regards,<br>Training Management Team</p>
        ";
    }

    /**
     * Get reminder email template
     */
    private function _get_reminder_template($user, $training, $days_until) {
        return "
        <h2>Training Reminder</h2>
        <p>Dear {$user['full_name']},</p>
        <p>This is a friendly reminder that your training is starting soon:</p>
        <ul>
            <li><strong>Training:</strong> {$training['title']}</li>
            <li><strong>Start Date:</strong> " . date('F j, Y', strtotime($training['start_date'])) . "</li>
            <li><strong>Days Until Start:</strong> {$days_until} days</li>
        </ul>
        <p>Please make sure you are prepared and have access to all necessary materials.</p>
        <p>Best regards,<br>Training Management Team</p>
        ";
    }

    /**
     * Get certificate email template
     */
    private function _get_certificate_template($user, $certificate) {
        return "
        <h2>Your Certificate is Ready!</h2>
        <p>Dear {$user['full_name']},</p>
        <p>Your certificate for the training '{$certificate['training_title']}' has been issued.</p>
        <ul>
            <li><strong>Certificate Number:</strong> {$certificate['certificate_number']}</li>
            <li><strong>Issue Date:</strong> " . date('F j, Y', strtotime($certificate['issued_date'])) . "</li>
        </ul>
        <p>You can download your certificate from the Training Management System dashboard.</p>
        <p>Congratulations on your achievement!</p>
        <p>Best regards,<br>Training Management Team</p>
        ";
    }

    /**
     * Get quiz result email template
     */
    private function _get_quiz_result_template($user, $attempt) {
        $status = $attempt['passed'] ? 'passed' : 'failed';
        $status_color = $attempt['passed'] ? 'green' : 'red';
        
        return "
        <h2>Quiz Results</h2>
        <p>Dear {$user['full_name']},</p>
        <p>Your quiz results are now available:</p>
        <ul>
            <li><strong>Quiz:</strong> {$attempt['quiz_title']}</li>
            <li><strong>Score:</strong> {$attempt['score']}/{$attempt['max_score']} ({$attempt['percentage']}%)</li>
            <li><strong>Status:</strong> <span style='color: {$status_color}; font-weight: bold;'>" . strtoupper($status) . "</span></li>
            <li><strong>Completed:</strong> " . date('F j, Y g:i A', strtotime($attempt['completed_at'])) . "</li>
        </ul>
        <p>You can view detailed results in the Training Management System.</p>
        <p>Best regards,<br>Training Management Team</p>
        ";
    }

    /**
     * Personalize message with user data
     */
    private function _personalize_message($message, $user, $template_data = []) {
        $replacements = array_merge([
            '{{USER_NAME}}' => $user['full_name'],
            '{{USER_EMAIL}}' => $user['email'],
            '{{USER_DEPARTMENT}}' => $user['department'] ?? 'N/A'
        ], $template_data);

        return str_replace(array_keys($replacements), array_values($replacements), $message);
    }
}
