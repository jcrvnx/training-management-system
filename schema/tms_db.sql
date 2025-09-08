-- Create the database
CREATE DATABASE IF NOT EXISTS training_management_system;
USE training_management_system;

-- Disable foreign key checks for installation
SET FOREIGN_KEY_CHECKS = 0;

-- Enhanced Users table with all fields from migrations
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(20) DEFAULT NULL,
    department VARCHAR(100) DEFAULT NULL,
    position VARCHAR(100) DEFAULT NULL,
    profile_image VARCHAR(255) DEFAULT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'trainer', 'user') DEFAULT 'user',
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Course categories table (must come before trainings for foreign key)
CREATE TABLE IF NOT EXISTS course_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    color VARCHAR(7) DEFAULT '#007bff',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Enhanced Trainings table with all fields from migrations
CREATE TABLE IF NOT EXISTS trainings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    description TEXT,
    category_id INT DEFAULT NULL,
    instructor_id INT DEFAULT NULL,
    difficulty_level ENUM('beginner', 'intermediate', 'advanced') DEFAULT 'beginner',
    estimated_duration_hours DECIMAL(4,2) DEFAULT NULL,
    is_mandatory BOOLEAN DEFAULT FALSE,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    enrollment_deadline DATE DEFAULT NULL,
    completion_deadline DATE DEFAULT NULL,
    location VARCHAR(100),
    max_participants INT DEFAULT 20,
    passing_score INT DEFAULT 70,
    certificate_template VARCHAR(255) DEFAULT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT FK_trainings_category FOREIGN KEY (category_id) REFERENCES course_categories(id) ON DELETE SET NULL,
    CONSTRAINT FK_trainings_instructor FOREIGN KEY (instructor_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Enhanced Enrollments table with all fields from migrations
CREATE TABLE IF NOT EXISTS enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    training_id INT NOT NULL,
    status ENUM('enrolled', 'in_progress', 'completed', 'failed', 'withdrawn') DEFAULT 'enrolled',
    progress_percentage DECIMAL(5,2) DEFAULT 0.00,
    started_at TIMESTAMP NULL DEFAULT NULL,
    completed_at TIMESTAMP NULL DEFAULT NULL,
    final_score DECIMAL(5,2) DEFAULT NULL,
    certificate_issued BOOLEAN DEFAULT FALSE,
    certificate_id VARCHAR(50) DEFAULT NULL,
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (training_id) REFERENCES trainings(id) ON DELETE CASCADE,
    UNIQUE KEY (user_id, training_id)
);

-- Insert default course categories
INSERT INTO course_categories (name, description, color) VALUES
('Technology', 'IT and technology related courses', '#007bff'),
('Safety', 'Workplace safety and compliance courses', '#dc3545'),
('Management', 'Leadership and management training', '#28a745'),
('HR', 'Human resources and soft skills', '#17a2b8'),
('Finance', 'Financial and accounting training', '#ffc107');

-- User permissions table for granular access control
CREATE TABLE IF NOT EXISTS user_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    permission_name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    module VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Role permissions mapping
CREATE TABLE IF NOT EXISTS role_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role ENUM('admin', 'trainer', 'user') NOT NULL,
    permission_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (permission_id) REFERENCES user_permissions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_role_permission (role, permission_id)
);

-- Course materials table
CREATE TABLE IF NOT EXISTS course_materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    training_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size BIGINT NOT NULL,
    file_type VARCHAR(50) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    sort_order INT DEFAULT 0,
    is_downloadable BOOLEAN DEFAULT TRUE,
    uploaded_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (training_id) REFERENCES trainings(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE RESTRICT
);

-- Course prerequisites table
CREATE TABLE IF NOT EXISTS course_prerequisites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    training_id INT NOT NULL,
    prerequisite_training_id INT NOT NULL,
    is_mandatory BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (training_id) REFERENCES trainings(id) ON DELETE CASCADE,
    FOREIGN KEY (prerequisite_training_id) REFERENCES trainings(id) ON DELETE CASCADE,
    UNIQUE KEY unique_prerequisite (training_id, prerequisite_training_id)
);

-- Training sessions table for attendance tracking
CREATE TABLE IF NOT EXISTS training_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    training_id INT NOT NULL,
    session_title VARCHAR(200) NOT NULL,
    session_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    location VARCHAR(200) DEFAULT NULL,
    max_attendees INT DEFAULT NULL,
    session_type ENUM('in_person', 'online', 'hybrid') DEFAULT 'in_person',
    meeting_link VARCHAR(500) DEFAULT NULL,
    notes TEXT,
    is_mandatory BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (training_id) REFERENCES trainings(id) ON DELETE CASCADE
);

-- Session attendance tracking
CREATE TABLE IF NOT EXISTS session_attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    user_id INT NOT NULL,
    status ENUM('present', 'absent', 'late', 'excused') DEFAULT 'absent',
    check_in_time TIMESTAMP NULL DEFAULT NULL,
    check_out_time TIMESTAMP NULL DEFAULT NULL,
    notes TEXT,
    marked_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES training_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (marked_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_session_user (session_id, user_id)
);

-- Quiz system tables
CREATE TABLE IF NOT EXISTS quizzes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    training_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    instructions TEXT,
    time_limit_minutes INT DEFAULT NULL,
    max_attempts INT DEFAULT 1,
    passing_score DECIMAL(5,2) DEFAULT 70.00,
    shuffle_questions BOOLEAN DEFAULT FALSE,
    show_results_immediately BOOLEAN DEFAULT TRUE,
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (training_id) REFERENCES trainings(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS quiz_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT NOT NULL,
    question_type ENUM('multiple_choice', 'true_false', 'short_answer', 'essay') DEFAULT 'multiple_choice',
    question_text TEXT NOT NULL,
    explanation TEXT,
    points DECIMAL(4,2) DEFAULT 1.00,
    sort_order INT DEFAULT 0,
    is_required BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS question_options (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_id INT NOT NULL,
    option_text TEXT NOT NULL,
    is_correct BOOLEAN DEFAULT FALSE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (question_id) REFERENCES quiz_questions(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS quiz_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT NOT NULL,
    user_id INT NOT NULL,
    attempt_number INT NOT NULL DEFAULT 1,
    status ENUM('in_progress', 'submitted', 'graded', 'expired') DEFAULT 'in_progress',
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    submitted_at TIMESTAMP NULL DEFAULT NULL,
    time_taken_minutes INT DEFAULT NULL,
    total_score DECIMAL(5,2) DEFAULT NULL,
    percentage_score DECIMAL(5,2) DEFAULT NULL,
    passed BOOLEAN DEFAULT NULL,
    feedback TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_attempt (quiz_id, user_id, attempt_number)
);

CREATE TABLE IF NOT EXISTS quiz_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    attempt_id INT NOT NULL,
    question_id INT NOT NULL,
    answer_text TEXT,
    selected_option_id INT DEFAULT NULL,
    is_correct BOOLEAN DEFAULT NULL,
    points_earned DECIMAL(4,2) DEFAULT NULL,
    feedback TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (attempt_id) REFERENCES quiz_attempts(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES quiz_questions(id) ON DELETE CASCADE,
    FOREIGN KEY (selected_option_id) REFERENCES question_options(id) ON DELETE SET NULL,
    UNIQUE KEY unique_attempt_question (attempt_id, question_id)
);

-- Certificate system tables
CREATE TABLE IF NOT EXISTS certificates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    certificate_number VARCHAR(50) NOT NULL UNIQUE,
    user_id INT NOT NULL,
    training_id INT NOT NULL,
    quiz_attempt_id INT DEFAULT NULL,
    template_name VARCHAR(100) DEFAULT 'default',
    issue_date DATE NOT NULL,
    expiry_date DATE DEFAULT NULL,
    verification_code VARCHAR(100) NOT NULL UNIQUE,
    certificate_data TEXT,
    file_path VARCHAR(500) DEFAULT NULL,
    is_valid BOOLEAN DEFAULT TRUE,
    issued_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (training_id) REFERENCES trainings(id) ON DELETE CASCADE,
    FOREIGN KEY (quiz_attempt_id) REFERENCES quiz_attempts(id) ON DELETE SET NULL,
    FOREIGN KEY (issued_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_certificate_number (certificate_number),
    INDEX idx_verification_code (verification_code)
);

CREATE TABLE IF NOT EXISTS certificate_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    background_image VARCHAR(255) DEFAULT NULL,
    template_html TEXT NOT NULL,
    css_styles TEXT,
    is_default BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
);
-- Notification system tables
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
    metadata TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_status_scheduled (status, scheduled_at),
    INDEX idx_user_category (user_id, category),
    INDEX idx_type_status (type, status)
);

CREATE TABLE IF NOT EXISTS email_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    subject VARCHAR(200) NOT NULL,
    template_html TEXT NOT NULL,
    template_text TEXT,
    variables TEXT,
    category ENUM('enrollment', 'deadline', 'completion', 'reminder', 'system', 'certificate') NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
);

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

CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    table_name VARCHAR(100) NOT NULL,
    record_id INT DEFAULT NULL,
    action ENUM('CREATE', 'UPDATE', 'DELETE', 'LOGIN', 'LOGOUT') NOT NULL,
    old_values TEXT,
    new_values TEXT,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_action (user_id, action),
    INDEX idx_table_record (table_name, record_id),
    INDEX idx_created_at (created_at)
);

CREATE TABLE IF NOT EXISTS file_uploads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    original_filename VARCHAR(255) NOT NULL,
    stored_filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size BIGINT NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    file_hash VARCHAR(64) NOT NULL,
    upload_type ENUM('course_material', 'user_avatar', 'certificate_template', 'system') DEFAULT 'course_material',
    reference_id INT DEFAULT NULL,
    uploaded_by INT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_reference (upload_type, reference_id),
    INDEX idx_hash (file_hash),
    INDEX idx_uploaded_by (uploaded_by)
);

CREATE TABLE IF NOT EXISTS user_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    training_id INT NOT NULL,
    material_id INT DEFAULT NULL,
    session_id INT DEFAULT NULL,
    quiz_id INT DEFAULT NULL,
    activity_type ENUM('enrollment', 'material_view', 'session_attendance', 'quiz_attempt', 'completion') NOT NULL,
    progress_data TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (training_id) REFERENCES trainings(id) ON DELETE CASCADE,
    FOREIGN KEY (material_id) REFERENCES course_materials(id) ON DELETE CASCADE,
    FOREIGN KEY (session_id) REFERENCES training_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
    INDEX idx_user_training (user_id, training_id),
    INDEX idx_activity_type (activity_type),
    INDEX idx_timestamp (timestamp)
);

-- Insert default permissions
INSERT INTO user_permissions (permission_name, description, module) VALUES
-- User Management
('users.view', 'View users list', 'users'),
('users.create', 'Create new users', 'users'),
('users.edit', 'Edit user information', 'users'),
('users.delete', 'Delete users', 'users'),
-- Training Management
('trainings.view', 'View trainings list', 'trainings'),
('trainings.create', 'Create new trainings', 'trainings'),
('trainings.edit', 'Edit training information', 'trainings'),
('trainings.delete', 'Delete trainings', 'trainings'),
('trainings.upload_materials', 'Upload training materials', 'trainings'),
-- Enrollment Management
('enrollments.view', 'View enrollments', 'enrollments'),
('enrollments.create', 'Create enrollments', 'enrollments'),
('enrollments.edit', 'Edit enrollments', 'enrollments'),
('enrollments.delete', 'Delete enrollments', 'enrollments'),
-- Quiz Management
('quizzes.view', 'View quizzes', 'quizzes'),
('quizzes.create', 'Create quizzes', 'quizzes'),
('quizzes.edit', 'Edit quizzes', 'quizzes'),
('quizzes.delete', 'Delete quizzes', 'quizzes'),
('quizzes.take', 'Take quizzes', 'quizzes'),
('quizzes.grade', 'Grade quiz attempts', 'quizzes'),
-- Reporting
('reports.view', 'View reports', 'reports'),
('reports.compliance', 'Access compliance reports', 'reports'),
('reports.analytics', 'Access analytics dashboard', 'reports'),
('reports.export', 'Export reports', 'reports'),
-- Certificates
('certificates.view', 'View certificates', 'certificates'),
('certificates.generate', 'Generate certificates', 'certificates'),
('certificates.download', 'Download certificates', 'certificates');

-- Assign permissions to roles
INSERT INTO role_permissions (role, permission_id) SELECT 'admin', id FROM user_permissions;
INSERT INTO role_permissions (role, permission_id) SELECT 'trainer', id FROM user_permissions 
WHERE permission_name IN ('trainings.view', 'trainings.create', 'trainings.edit', 'trainings.upload_materials',
    'enrollments.view', 'enrollments.create', 'enrollments.edit', 'quizzes.view', 'quizzes.create', 
    'quizzes.edit', 'quizzes.grade', 'reports.view', 'reports.analytics', 'certificates.view', 'certificates.generate');
INSERT INTO role_permissions (role, permission_id) SELECT 'user', id FROM user_permissions 
WHERE permission_name IN ('trainings.view', 'enrollments.view', 'quizzes.view', 'quizzes.take', 
    'certificates.view', 'certificates.download');

-- Insert default certificate template
INSERT INTO certificate_templates (name, title, description, template_html, css_styles, is_default, created_by) 
SELECT 'default', 'Default Certificate Template', 'Standard certificate template for training completion',
    '<div class="certificate"><h1>Certificate of Completion</h1><p>This is to certify that</p><h2>{{user_name}}</h2><p>has successfully completed the training course</p><h3>{{training_title}}</h3><p>on {{completion_date}}</p><div class="signatures"><div>{{instructor_name}}</div><div>Instructor</div></div><div class="certificate-number">Certificate #: {{certificate_number}}</div></div>',
    '.certificate { text-align: center; padding: 50px; font-family: Arial, sans-serif; border: 10px solid #gold; background: linear-gradient(45deg, #f9f9f9, #ffffff); } .certificate h1 { color: #333; margin-bottom: 30px; } .certificate h2 { color: #007bff; margin: 20px 0; } .certificate h3 { color: #28a745; margin: 20px 0; } .signatures { margin-top: 50px; } .certificate-number { margin-top: 30px; font-size: 12px; color: #666; }',
    TRUE, 1 WHERE EXISTS (SELECT 1 FROM users WHERE id = 1);

-- Insert default email templates  
INSERT INTO email_templates (name, subject, template_html, template_text, variables, category, created_by) 
SELECT 'enrollment_confirmation', 'Training Enrollment Confirmation - {{training_title}}',
    '<h2>Training Enrollment Confirmation</h2><p>Dear {{user_name}},</p><p>You have been successfully enrolled in: {{training_title}}</p><p>Start Date: {{start_date}}</p><p>Location: {{location}}</p><p>Best regards,<br>Training Management Team</p>',
    'Dear {{user_name}}, You have been enrolled in: {{training_title}}. Start Date: {{start_date}}. Location: {{location}}. Best regards, Training Management Team',
    '["user_name", "training_title", "start_date", "end_date", "location"]', 'enrollment', 1
WHERE EXISTS (SELECT 1 FROM users WHERE id = 1);

-- Insert default system settings
INSERT INTO system_settings (setting_key, setting_value, setting_type, description, category, is_public) VALUES
('smtp_host', 'localhost', 'string', 'SMTP server hostname', 'email', FALSE),
('smtp_port', '587', 'integer', 'SMTP server port', 'email', FALSE),
('from_email', 'noreply@trainingms.com', 'string', 'Default from email address', 'email', FALSE),
('from_name', 'Training Management System', 'string', 'Default from name', 'email', TRUE),
('max_file_size', '104857600', 'integer', 'Maximum file size in bytes (100MB)', 'files', FALSE),
('site_name', 'Training Management System', 'string', 'Site name', 'general', TRUE),
('default_passing_score', '70', 'integer', 'Default passing score percentage', 'training', FALSE),
('email_enabled', '1', 'boolean', 'Enable email notifications', 'notifications', FALSE);

-- Sample Data: Insert admin user
INSERT INTO users (full_name, email, password, role) VALUES 
('Admin User', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Sample Data: Insert regular users
INSERT INTO users (full_name, email, password) VALUES 
('John Doe', 'john@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('Jane Smith', 'jane@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Sample Data: Insert trainings
INSERT INTO trainings (title, description, start_date, end_date, location, max_participants, category_id) VALUES 
('PHP Basics', 'Introduction to PHP programming language', '2024-06-01', '2024-06-05', 'Room 101', 15, 1),
('Web Development Fundamentals', 'HTML, CSS, and JavaScript basics', '2024-06-15', '2024-06-20', 'Room 102', 20, 1);

-- Sample Data: Insert enrollments
INSERT INTO enrollments (user_id, training_id) VALUES (2, 1), (3, 1), (2, 2), (3, 2);

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- INSTALLATION COMPLETE
-- =====================================================
-- 
-- Your Training Management System database is now ready!
-- 
-- Default Login Credentials:
--   Email: admin@example.com  
--   Password: secret (bcrypt hashed)
--
-- Next Steps:
-- 1. Update includes/config.php with correct database name
-- 2. Create upload directories with proper permissions
-- 3. Visit your TMS installation and login as admin
-- 4. Run the integration test suite to verify everything works
-- 
-- Tables Created: 21 tables with all enhanced features
-- Features Included: Quiz system, Certificate generation, 
--                   Notification system, Advanced analytics
-- =====================================================
