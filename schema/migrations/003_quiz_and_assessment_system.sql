-- Migration 003: Quiz and Assessment System
-- Compatible with MySQL 5.7+ (XAMPP 2015)
-- Author: TMS Enhancement Project
-- Date: 2025-09-08

-- Create quizzes table
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

-- Create quiz questions table
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

-- Create question options table (for multiple choice and true/false)
CREATE TABLE IF NOT EXISTS question_options (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_id INT NOT NULL,
    option_text TEXT NOT NULL,
    is_correct BOOLEAN DEFAULT FALSE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (question_id) REFERENCES quiz_questions(id) ON DELETE CASCADE
);

-- Create quiz attempts table
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

-- Create quiz answers table
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

-- Create certificates table
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
    certificate_data TEXT, -- JSON data for certificate details
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

-- Create certificate templates table
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

-- Insert default certificate template
INSERT INTO certificate_templates (name, title, description, template_html, css_styles, is_default, created_by) 
SELECT 
    'default',
    'Default Certificate Template',
    'Standard certificate template for training completion',
    '<div class="certificate">
        <h1>Certificate of Completion</h1>
        <p>This is to certify that</p>
        <h2>{{user_name}}</h2>
        <p>has successfully completed the training course</p>
        <h3>{{training_title}}</h3>
        <p>on {{completion_date}}</p>
        <div class="signatures">
            <div>{{instructor_name}}</div>
            <div>Instructor</div>
        </div>
        <div class="certificate-number">Certificate #: {{certificate_number}}</div>
    </div>',
    '.certificate { 
        text-align: center; 
        padding: 50px; 
        font-family: Arial, sans-serif; 
        border: 10px solid #gold; 
        background: linear-gradient(45deg, #f9f9f9, #ffffff);
    }
    .certificate h1 { color: #333; margin-bottom: 30px; }
    .certificate h2 { color: #007bff; margin: 20px 0; }
    .certificate h3 { color: #28a745; margin: 20px 0; }
    .signatures { margin-top: 50px; }
    .certificate-number { margin-top: 30px; font-size: 12px; color: #666; }',
    TRUE,
    1
WHERE EXISTS (SELECT 1 FROM users WHERE id = 1);

-- Create progress tracking table for detailed course progress
CREATE TABLE IF NOT EXISTS user_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    training_id INT NOT NULL,
    material_id INT DEFAULT NULL,
    session_id INT DEFAULT NULL,
    quiz_id INT DEFAULT NULL,
    activity_type ENUM('enrollment', 'material_view', 'session_attendance', 'quiz_attempt', 'completion') NOT NULL,
    progress_data TEXT, -- JSON data for activity details
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
