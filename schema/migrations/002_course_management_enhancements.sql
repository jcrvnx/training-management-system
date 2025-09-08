-- Migration 002: Course Management Enhancements
-- Compatible with MySQL 5.7+ (XAMPP 2015)
-- Author: TMS Enhancement Project
-- Date: 2025-09-08

-- Create course categories table
CREATE TABLE IF NOT EXISTS course_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    color VARCHAR(7) DEFAULT '#007bff',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default categories
INSERT INTO course_categories (name, description, color) VALUES
('Technology', 'IT and technology related courses', '#007bff'),
('Safety', 'Workplace safety and compliance courses', '#dc3545'),
('Management', 'Leadership and management training', '#28a745'),
('HR', 'Human resources and soft skills', '#17a2b8'),
('Finance', 'Financial and accounting training', '#ffc107');

-- Enhance trainings table
ALTER TABLE trainings 
ADD COLUMN category_id INT DEFAULT NULL AFTER description,
ADD COLUMN instructor_id INT DEFAULT NULL AFTER category_id,
ADD COLUMN difficulty_level ENUM('beginner', 'intermediate', 'advanced') DEFAULT 'beginner' AFTER instructor_id,
ADD COLUMN estimated_duration_hours DECIMAL(4,2) DEFAULT NULL AFTER difficulty_level,
ADD COLUMN is_mandatory BOOLEAN DEFAULT FALSE AFTER estimated_duration_hours,
ADD COLUMN enrollment_deadline DATE DEFAULT NULL AFTER is_mandatory,
ADD COLUMN completion_deadline DATE DEFAULT NULL AFTER enrollment_deadline,
ADD COLUMN passing_score INT DEFAULT 70 AFTER completion_deadline,
ADD COLUMN certificate_template VARCHAR(255) DEFAULT NULL AFTER passing_score,
ADD COLUMN is_active BOOLEAN DEFAULT TRUE AFTER certificate_template,
ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

-- Add foreign key constraints
ALTER TABLE trainings 
ADD CONSTRAINT FK_trainings_category FOREIGN KEY (category_id) REFERENCES course_categories(id) ON DELETE SET NULL,
ADD CONSTRAINT FK_trainings_instructor FOREIGN KEY (instructor_id) REFERENCES users(id) ON DELETE SET NULL;

-- Create course materials table
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

-- Create course prerequisites table
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

-- Enhance enrollments table with progress tracking
ALTER TABLE enrollments 
ADD COLUMN status ENUM('enrolled', 'in_progress', 'completed', 'failed', 'withdrawn') DEFAULT 'enrolled' AFTER training_id,
ADD COLUMN progress_percentage DECIMAL(5,2) DEFAULT 0.00 AFTER status,
ADD COLUMN started_at TIMESTAMP NULL DEFAULT NULL AFTER progress_percentage,
ADD COLUMN completed_at TIMESTAMP NULL DEFAULT NULL AFTER started_at,
ADD COLUMN final_score DECIMAL(5,2) DEFAULT NULL AFTER completed_at,
ADD COLUMN certificate_issued BOOLEAN DEFAULT FALSE AFTER final_score,
ADD COLUMN certificate_id VARCHAR(50) DEFAULT NULL AFTER certificate_issued,
ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER enrolled_at;

-- Create training sessions table for attendance tracking
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

-- Create attendance tracking table
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
