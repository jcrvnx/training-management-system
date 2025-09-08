-- Migration 004: Notifications and System Configuration
-- Compatible with MySQL 5.7+ (XAMPP 2015)
-- Author: TMS Enhancement Project
-- Date: 2025-09-08

-- Create notifications table for email queue and system notifications
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    type ENUM('email', 'system', 'sms') DEFAULT 'email',
    category ENUM('enrollment', 'deadline', 'completion', 'reminder', 'system', 'certificate') NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    email_template VARCHAR(100) DEFAULT NULL,
    recipient_email VARCHAR(255) DEFAULT NULL,
    status ENUM('pending', 'sent', 'failed', 'cancelled') DEFAULT 'pending',
    priority ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
    scheduled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sent_at TIMESTAMP NULL DEFAULT NULL,
    error_message TEXT DEFAULT NULL,
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 3,
    metadata TEXT, -- JSON data for additional parameters
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_status_scheduled (status, scheduled_at),
    INDEX idx_user_category (user_id, category),
    INDEX idx_type_status (type, status)
);

-- Create email templates table
CREATE TABLE IF NOT EXISTS email_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    subject VARCHAR(200) NOT NULL,
    template_html TEXT NOT NULL,
    template_text TEXT,
    variables TEXT, -- JSON array of available variables
    category ENUM('enrollment', 'deadline', 'completion', 'reminder', 'system', 'certificate') NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
);

-- Insert default email templates
INSERT INTO email_templates (name, subject, template_html, template_text, variables, category, created_by) 
SELECT 
    'enrollment_confirmation',
    'Training Enrollment Confirmation - {{training_title}}',
    '<h2>Training Enrollment Confirmation</h2>
    <p>Dear {{user_name}},</p>
    <p>You have been successfully enrolled in the following training:</p>
    <ul>
        <li><strong>Training:</strong> {{training_title}}</li>
        <li><strong>Start Date:</strong> {{start_date}}</li>
        <li><strong>End Date:</strong> {{end_date}}</li>
        <li><strong>Location:</strong> {{location}}</li>
    </ul>
    <p>Please log in to your account to access course materials and track your progress.</p>
    <p>Best regards,<br>Training Management Team</p>',
    'Training Enrollment Confirmation\n\nDear {{user_name}},\n\nYou have been successfully enrolled in: {{training_title}}\nStart Date: {{start_date}}\nEnd Date: {{end_date}}\nLocation: {{location}}\n\nPlease log in to access course materials.\n\nBest regards,\nTraining Management Team',
    '["user_name", "training_title", "start_date", "end_date", "location"]',
    'enrollment',
    1
WHERE EXISTS (SELECT 1 FROM users WHERE id = 1)
UNION ALL SELECT 
    'deadline_reminder',
    'Training Deadline Reminder - {{training_title}}',
    '<h2>Training Deadline Reminder</h2>
    <p>Dear {{user_name}},</p>
    <p>This is a reminder that the following training has an upcoming deadline:</p>
    <ul>
        <li><strong>Training:</strong> {{training_title}}</li>
        <li><strong>Deadline:</strong> {{deadline_date}}</li>
        <li><strong>Current Progress:</strong> {{progress_percentage}}%</li>
    </ul>
    <p>Please complete your training before the deadline to avoid any issues.</p>
    <p>Best regards,<br>Training Management Team</p>',
    'Training Deadline Reminder\n\nDear {{user_name}},\n\nTraining: {{training_title}}\nDeadline: {{deadline_date}}\nCurrent Progress: {{progress_percentage}}%\n\nPlease complete before the deadline.\n\nBest regards,\nTraining Management Team',
    '["user_name", "training_title", "deadline_date", "progress_percentage"]',
    'deadline',
    1
WHERE EXISTS (SELECT 1 FROM users WHERE id = 1)
UNION ALL SELECT 
    'completion_certificate',
    'Training Completion Certificate - {{training_title}}',
    '<h2>Congratulations on Training Completion!</h2>
    <p>Dear {{user_name}},</p>
    <p>Congratulations! You have successfully completed the training:</p>
    <ul>
        <li><strong>Training:</strong> {{training_title}}</li>
        <li><strong>Completion Date:</strong> {{completion_date}}</li>
        <li><strong>Final Score:</strong> {{final_score}}%</li>
        <li><strong>Certificate Number:</strong> {{certificate_number}}</li>
    </ul>
    <p>Your certificate is now available for download in your account.</p>
    <p>Best regards,<br>Training Management Team</p>',
    'Training Completion Certificate\n\nDear {{user_name}},\n\nCongratulations! You completed: {{training_title}}\nCompletion Date: {{completion_date}}\nFinal Score: {{final_score}}%\nCertificate Number: {{certificate_number}}\n\nYour certificate is available for download.\n\nBest regards,\nTraining Management Team',
    '["user_name", "training_title", "completion_date", "final_score", "certificate_number"]',
    'completion',
    1
WHERE EXISTS (SELECT 1 FROM users WHERE id = 1);

-- Create system settings table
CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_type ENUM('string', 'integer', 'boolean', 'json', 'text') DEFAULT 'string',
    description TEXT,
    category VARCHAR(50) DEFAULT 'general',
    is_public BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_public (is_public)
);

-- Insert default system settings
INSERT INTO system_settings (setting_key, setting_value, setting_type, description, category, is_public) VALUES
-- Email Configuration
('smtp_host', 'localhost', 'string', 'SMTP server hostname', 'email', FALSE),
('smtp_port', '587', 'integer', 'SMTP server port', 'email', FALSE),
('smtp_username', '', 'string', 'SMTP username', 'email', FALSE),
('smtp_password', '', 'string', 'SMTP password', 'email', FALSE),
('smtp_encryption', 'tls', 'string', 'SMTP encryption (tls/ssl)', 'email', FALSE),
('from_email', 'noreply@trainingms.com', 'string', 'Default from email address', 'email', FALSE),
('from_name', 'Training Management System', 'string', 'Default from name', 'email', TRUE),

-- File Upload Settings
('max_file_size', '104857600', 'integer', 'Maximum file size in bytes (100MB)', 'files', FALSE),
('allowed_file_types', '["pdf","doc","docx","ppt","pptx","xls","xlsx","jpg","jpeg","png","gif","mp4","avi","mov"]', 'json', 'Allowed file extensions', 'files', FALSE),
('upload_path', 'uploads/course_materials/', 'string', 'Upload directory path', 'files', FALSE),

-- System Configuration
('site_name', 'Training Management System', 'string', 'Site name', 'general', TRUE),
('site_description', 'Comprehensive training management solution', 'string', 'Site description', 'general', TRUE),
('timezone', 'Asia/Manila', 'string', 'System timezone', 'general', FALSE),
('date_format', 'Y-m-d', 'string', 'Default date format', 'general', FALSE),
('time_format', 'H:i:s', 'string', 'Default time format', 'general', FALSE),

-- Training Settings
('default_passing_score', '70', 'integer', 'Default passing score percentage', 'training', FALSE),
('max_quiz_attempts', '3', 'integer', 'Maximum quiz attempts allowed', 'training', FALSE),
('auto_enroll_deadline_days', '7', 'integer', 'Days before training start to stop auto-enrollment', 'training', FALSE),
('certificate_expiry_years', '2', 'integer', 'Certificate validity in years (0 = never expires)', 'training', FALSE),

-- Notification Settings
('email_enabled', '1', 'boolean', 'Enable email notifications', 'notifications', FALSE),
('reminder_days', '7,3,1', 'string', 'Days before deadline to send reminders', 'notifications', FALSE),
('max_notification_attempts', '3', 'integer', 'Maximum attempts for failed notifications', 'notifications', FALSE);

-- Create audit log table for tracking system changes
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    table_name VARCHAR(100) NOT NULL,
    record_id INT DEFAULT NULL,
    action ENUM('CREATE', 'UPDATE', 'DELETE', 'LOGIN', 'LOGOUT') NOT NULL,
    old_values TEXT, -- JSON data of old values
    new_values TEXT, -- JSON data of new values
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_action (user_id, action),
    INDEX idx_table_record (table_name, record_id),
    INDEX idx_created_at (created_at)
);

-- Create file uploads tracking table
CREATE TABLE IF NOT EXISTS file_uploads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    original_filename VARCHAR(255) NOT NULL,
    stored_filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size BIGINT NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    file_hash VARCHAR(64) NOT NULL, -- SHA-256 hash for integrity
    upload_type ENUM('course_material', 'user_avatar', 'certificate_template', 'system') DEFAULT 'course_material',
    reference_id INT DEFAULT NULL, -- ID of related record
    uploaded_by INT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_reference (upload_type, reference_id),
    INDEX idx_hash (file_hash),
    INDEX idx_uploaded_by (uploaded_by)
);
