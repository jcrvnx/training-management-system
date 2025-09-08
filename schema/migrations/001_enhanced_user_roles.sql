-- Migration 001: Enhanced User Roles System
-- Compatible with MySQL 5.7+ (XAMPP 2015)
-- Author: TMS Enhancement Project
-- Date: 2025-09-08

-- Add trainer role to existing users table
ALTER TABLE users 
MODIFY COLUMN role ENUM('admin', 'trainer', 'user') DEFAULT 'user';

-- Add additional user profile fields
ALTER TABLE users 
ADD COLUMN phone VARCHAR(20) DEFAULT NULL AFTER email,
ADD COLUMN department VARCHAR(100) DEFAULT NULL AFTER phone,
ADD COLUMN position VARCHAR(100) DEFAULT NULL AFTER department,
ADD COLUMN profile_image VARCHAR(255) DEFAULT NULL AFTER position,
ADD COLUMN is_active BOOLEAN DEFAULT TRUE AFTER profile_image,
ADD COLUMN last_login TIMESTAMP NULL DEFAULT NULL AFTER is_active,
ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER last_login;

-- Create user permissions table for granular access control
CREATE TABLE IF NOT EXISTS user_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    permission_name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    module VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create role permissions mapping
CREATE TABLE IF NOT EXISTS role_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role ENUM('admin', 'trainer', 'user') NOT NULL,
    permission_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (permission_id) REFERENCES user_permissions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_role_permission (role, permission_id)
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
-- Admin: All permissions
INSERT INTO role_permissions (role, permission_id) 
SELECT 'admin', id FROM user_permissions;

-- Trainer: Training, quiz, and enrollment management
INSERT INTO role_permissions (role, permission_id) 
SELECT 'trainer', id FROM user_permissions 
WHERE permission_name IN (
    'trainings.view', 'trainings.create', 'trainings.edit', 'trainings.upload_materials',
    'enrollments.view', 'enrollments.create', 'enrollments.edit',
    'quizzes.view', 'quizzes.create', 'quizzes.edit', 'quizzes.grade',
    'reports.view', 'reports.analytics',
    'certificates.view', 'certificates.generate'
);

-- User: Basic access to take trainings and view own progress
INSERT INTO role_permissions (role, permission_id) 
SELECT 'user', id FROM user_permissions 
WHERE permission_name IN (
    'trainings.view', 
    'enrollments.view',
    'quizzes.view', 'quizzes.take',
    'certificates.view', 'certificates.download'
);
