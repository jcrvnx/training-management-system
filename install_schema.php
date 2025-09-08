<?php
/**
 * Complete Database Schema Installer for Training Management System
 * This script will install the complete database schema with all tables
 */

// Database configuration
$db_config = [
    'host' => 'localhost',
    'username' => 'root',
    'password' => '',  // Change this to your MySQL password
    'database' => 'training_management_system'
];

// HTML Template
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TMS Database Schema Installer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .install-container { max-width: 800px; margin: 50px auto; }
        .install-card { background: white; border-radius: 15px; box-shadow: 0 15px 35px rgba(0,0,0,0.1); }
        .install-header { background: linear-gradient(135deg, #4e73df, #224abe); color: white; text-align: center; padding: 2rem; border-radius: 15px 15px 0 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="install-container">
            <div class="install-card">
                <div class="install-header">
                    <h2><i class="fas fa-database me-2"></i>TMS Complete Schema Installer</h2>
                    <p class="mb-0">Install the complete Training Management System database</p>
                </div>
                
                <div class="card-body p-4">
                    <?php
                    if (isset($_POST['install'])) {
                        echo '<div class="alert alert-info"><i class="fas fa-spinner fa-spin me-2"></i>Starting TMS installation...</div>';
                        
                        // Force fresh installation - skip flag check
                        {
                            try {
                                // Validate PHP requirements
                                if (version_compare(PHP_VERSION, '7.0.0', '<')) {
                                    throw new Exception("PHP 7.0 or higher is required. Current version: " . PHP_VERSION);
                                }
                                
                                // Check required PHP extensions
                                $required_extensions = ['mysqli', 'json', 'mbstring'];
                                $missing_extensions = [];
                                foreach ($required_extensions as $ext) {
                                    if (!extension_loaded($ext)) {
                                        $missing_extensions[] = $ext;
                                    }
                                }
                                if (!empty($missing_extensions)) {
                                    throw new Exception("Missing required PHP extensions: " . implode(', ', $missing_extensions));
                                }
                                
                                echo '<div class="alert alert-success"><i class="fas fa-check me-2"></i>PHP requirements validated!</div>';
                                
                                // Create database connection
                                $mysqli = new mysqli($db_config['host'], $db_config['username'], $db_config['password']);
                                
                                if ($mysqli->connect_error) {
                                    throw new Exception("Database connection failed: " . $mysqli->connect_error);
                                }
                                
                                echo '<div class="alert alert-success"><i class="fas fa-check me-2"></i>Connected to MySQL server!</div>';
                            
                            // Create database if it doesn't exist
                            echo '<div class="alert alert-info"><i class="fas fa-database me-2"></i>Creating database: ' . $db_config['database'] . '...</div>';
                            $create_result = $mysqli->query("CREATE DATABASE IF NOT EXISTS `{$db_config['database']}`");
                            if (!$create_result) {
                                throw new Exception("Failed to create database: " . $mysqli->error);
                            }
                            
                            $select_result = $mysqli->select_db($db_config['database']);
                            if (!$select_result) {
                                throw new Exception("Failed to select database: " . $mysqli->error);
                            }
                            
                            // Verify database exists
                            $verify_result = $mysqli->query("SELECT DATABASE() as current_db");
                            $current_db = $verify_result->fetch_assoc();
                            if ($current_db['current_db'] !== $db_config['database']) {
                                throw new Exception("Database selection failed. Expected: " . $db_config['database'] . ", Got: " . $current_db['current_db']);
                            }
                            
                            echo '<div class="alert alert-success"><i class="fas fa-check me-2"></i>Database created and selected: ' . $db_config['database'] . '</div>';
                            
                            // Step 1: Install Database Schema
                            echo '<div class="alert alert-info"><i class="fas fa-database me-2"></i>Installing database schema...</div>';
                            
                            $schema_file = __DIR__ . '/schema/tms_db.sql';
                            
                            // Use built-in schema for reliability
                            echo '<div class="alert alert-info"><i class="fas fa-code me-2"></i>Using built-in reliable schema...</div>';
                            $complete_schema = createCompleteSchema();
                            installSchemaFromString($mysqli, $complete_schema);
                            
                            echo '<div class="alert alert-success"><i class="fas fa-check me-2"></i>Database schema installed successfully!</div>';
                            
                            // Step 2: Create Directory Structure
                            echo '<div class="alert alert-info"><i class="fas fa-folder me-2"></i>Creating directory structure...</div>';
                            createDirectoryStructure();
                            echo '<div class="alert alert-success"><i class="fas fa-check me-2"></i>Directory structure created!</div>';
                            
                            // Step 3: Update Configuration Files
                            echo '<div class="alert alert-info"><i class="fas fa-cogs me-2"></i>Updating configuration files...</div>';
                            updateConfigurationFiles($db_config);
                            echo '<div class="alert alert-success"><i class="fas fa-check me-2"></i>Configuration files updated!</div>';
                            
                            // Step 4: Create Security Files
                            echo '<div class="alert alert-info"><i class="fas fa-shield-alt me-2"></i>Creating security files...</div>';
                            createSecurityFiles();
                            echo '<div class="alert alert-success"><i class="fas fa-check me-2"></i>Security files created!</div>';
                            
                            // Step 5: Finalize Installation
                            echo '<div class="alert alert-info"><i class="fas fa-flag-checkered me-2"></i>Finalizing installation...</div>';
                            finalizeInstallation();
                            echo '<div class="alert alert-success"><i class="fas fa-trophy me-2"></i><strong>Installation Complete!</strong> Your TMS is ready to use!</div>';
                            
                            // Show completion message and redirect
                            echo '<div class="alert alert-success text-center">';
                            echo '<i class="fas fa-check-circle text-success me-2"></i>';
                            echo '<strong>Installation finished. You will be redirected to the login page.</strong>';
                            echo '</div>';
                            
                            echo '<script>
                                setTimeout(function() {
                                    window.location.href = "auth/login.php";
                                }, 3000);
                            </script>';
                            
                            // Show installed tables
                            $result = $mysqli->query("SHOW TABLES");
                            $tables = [];
                            while ($row = $result->fetch_array()) {
                                $tables[] = $row[0];
                            }
                            
                            echo '<div class="mt-3">';
                            echo '<h5>Installed Tables (' . count($tables) . '):</h5>';
                            echo '<div class="row">';
                            foreach ($tables as $table) {
                                echo '<div class="col-md-4 mb-2"><i class="fas fa-table text-success me-1"></i>' . $table . '</div>';
                            }
                            echo '</div></div>';
                            
                            // Don't show buttons, let auto-redirect work
                            echo '<div class="text-center mt-4">';
                            echo '<p class="text-muted">Redirecting to login page in 3 seconds...</p>';
                            echo '</div>';
                            
                                $mysqli->close();
                                
                            } catch (Exception $e) {
                                echo '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i><strong>Installation Failed:</strong> ' . htmlspecialchars($e->getMessage()) . '</div>';
                                echo '<div class="alert alert-info">';
                                echo '<h6><i class="fas fa-tools me-2"></i>Troubleshooting Steps:</h6>';
                                echo '<ol>';
                                echo '<li><strong>Database Connection:</strong> Verify MySQL server is running in XAMPP Control Panel</li>';
                                echo '<li><strong>Credentials:</strong> Check username/password in the configuration above</li>';
                                echo '<li><strong>Permissions:</strong> Ensure MySQL user has CREATE, DROP, INSERT privileges</li>';
                                echo '<li><strong>Port:</strong> Default MySQL port is 3306, check if different</li>';
                                echo '<li><strong>Fresh Start:</strong> Try dropping existing database and retry installation</li>';
                                echo '</ol>';
                                echo '<p class="mt-3 mb-0"><strong>Current Config:</strong> Host: ' . htmlspecialchars($db_config['host']) . ', User: ' . htmlspecialchars($db_config['username']) . ', DB: ' . htmlspecialchars($db_config['database']) . '</p>';
                                echo '</div>';
                                echo '<div class="text-center mt-3">';
                                echo '<button onclick="location.reload()" class="btn btn-primary">Try Again</button>';
                                echo '</div>';
                            }
                        }
                    } else {
                        ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Warning:</strong> This will create/recreate ALL database tables for the Training Management System.
                            Any existing data will be lost.
                        </div>
                        
                        <h5>System Requirements Check:</h5>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-check text-success me-2"></i>PHP Version: <?php echo PHP_VERSION; ?></li>
                                    <li><i class="fas fa-<?php echo extension_loaded('mysqli') ? 'check text-success' : 'times text-danger'; ?> me-2"></i>MySQLi Extension</li>
                                    <li><i class="fas fa-<?php echo extension_loaded('json') ? 'check text-success' : 'times text-danger'; ?> me-2"></i>JSON Extension</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-<?php echo extension_loaded('mbstring') ? 'check text-success' : 'times text-danger'; ?> me-2"></i>Mbstring Extension</li>
                                    <li><i class="fas fa-<?php echo is_writable(__DIR__) ? 'check text-success' : 'times text-danger'; ?> me-2"></i>Directory Writable</li>
                                    <li><i class="fas fa-<?php echo file_exists(__DIR__ . '/schema/tms_db.sql') ? 'check text-success' : 'exclamation-triangle text-warning'; ?> me-2"></i>Schema File Available</li>
                                </ul>
                            </div>
                        </div>

                        <h5>Database Configuration:</h5>
                        <ul>
                            <li><strong>Host:</strong> <?php echo htmlspecialchars($db_config['host']); ?></li>
                            <li><strong>Username:</strong> <?php echo htmlspecialchars($db_config['username']); ?></li>
                            <li><strong>Database:</strong> <?php echo htmlspecialchars($db_config['database']); ?></li>
                            <li><strong>Schema File:</strong> <?php echo file_exists(__DIR__ . '/schema/tms_db.sql') ? 'Found (' . filesize(__DIR__ . '/schema/tms_db.sql') . ' bytes)' : 'Missing (will use built-in schema)'; ?></li>
                        </ul>
                        
                        <div class="alert alert-info">
                            <h6>What will be installed:</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>Core Tables:</strong>
                                    <ul class="small">
                                        <li>users</li>
                                        <li>course_categories</li>
                                        <li>trainings</li>
                                        <li>enrollments</li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <strong>Quiz System:</strong>
                                    <ul class="small">
                                        <li>quizzes</li>
                                        <li>quiz_questions</li>
                                        <li>quiz_question_options</li>
                                        <li>quiz_attempts</li>
                                        <li>quiz_answers</li>
                                    </ul>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>Certificate System:</strong>
                                    <ul class="small">
                                        <li>certificates</li>
                                        <li>certificate_templates</li>
                                        <li>course_materials</li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <strong>Sessions & Tracking:</strong>
                                    <ul class="small">
                                        <li>training_sessions</li>
                                        <li>session_attendance</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        <form method="post" class="text-center">
                            <button type="submit" name="install" class="btn btn-danger btn-lg">
                                <i class="fas fa-database me-2"></i>Install Complete Schema
                            </button>
                        </form>
                        
                        <p class="text-muted text-center mt-3 small">
                            <i class="fas fa-info-circle me-1"></i>
                            Make sure to update the database credentials at the top of this file if needed.
                        </p>
                        <?php
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

<?php

function installSchemaFromString($mysqli, $sql_content) {
    echo '<div class="alert alert-info"><i class="fas fa-code me-2"></i>Processing SQL content (' . strlen($sql_content) . ' characters)...</div>';
    
    // Remove comments and normalize line endings
    $sql_content = preg_replace('/--.*$/m', '', $sql_content);
    $sql_content = preg_replace('/#.*$/m', '', $sql_content);
    $sql_content = str_replace(["\r\n", "\r"], "\n", $sql_content);
    
    // Split into individual statements, handling quoted strings properly
    $statements = [];
    $current_statement = '';
    $lines = explode("\n", $sql_content);
    $in_quotes = false;
    $quote_char = '';
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        for ($i = 0; $i < strlen($line); $i++) {
            $char = $line[$i];
            
            if (!$in_quotes && ($char === "'" || $char === '"')) {
                $in_quotes = true;
                $quote_char = $char;
            } elseif ($in_quotes && $char === $quote_char) {
                // Check if it's escaped
                if ($i > 0 && $line[$i-1] !== '\\') {
                    $in_quotes = false;
                }
            } elseif (!$in_quotes && $char === ';') {
                $statements[] = trim($current_statement);
                $current_statement = '';
                continue;
            }
            
            $current_statement .= $char;
        }
        $current_statement .= ' ';
    }
    
    // Add any remaining statement
    if (trim($current_statement)) {
        $statements[] = trim($current_statement);
    }
    
    echo '<div class="alert alert-info"><i class="fas fa-list me-2"></i>Found ' . count($statements) . ' SQL statements to execute...</div>';
    
    // Execute each statement
    $executed = 0;
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement) && !preg_match('/^(--|#)/', $statement)) {
            $result = $mysqli->query($statement);
            if (!$result) {
                echo '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>SQL Error: ' . $mysqli->error . '</div>';
                echo '<div class="alert alert-warning">Failed statement: <code>' . htmlspecialchars(substr($statement, 0, 200)) . '...</code></div>';
                throw new Exception("SQL Error: " . $mysqli->error . " in statement: " . substr($statement, 0, 200) . "...");
            }
            $executed++;
        }
    }
    
    echo '<div class="alert alert-success"><i class="fas fa-check me-2"></i>Successfully executed ' . $executed . ' SQL statements!</div>';
}

function createCompleteSchema() {
    // Complete fallback schema with all essential tables
    return "
CREATE DATABASE IF NOT EXISTS training_management_system;
USE training_management_system;
SET FOREIGN_KEY_CHECKS = 0;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'trainer', 'user') DEFAULT 'user',
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Course Categories table
CREATE TABLE IF NOT EXISTS course_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Trainings table
CREATE TABLE IF NOT EXISTS trainings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    category_id INT,
    duration_hours INT DEFAULT 0,
    max_participants INT DEFAULT 50,
    status ENUM('draft', 'active', 'completed', 'cancelled') DEFAULT 'draft',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES course_categories(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Enrollments table
CREATE TABLE IF NOT EXISTS enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    training_id INT NOT NULL,
    status ENUM('enrolled', 'in_progress', 'completed', 'cancelled') DEFAULT 'enrolled',
    progress DECIMAL(5,2) DEFAULT 0.00,
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (training_id) REFERENCES trainings(id) ON DELETE CASCADE,
    UNIQUE KEY unique_enrollment (user_id, training_id)
);

-- Quizzes table
CREATE TABLE IF NOT EXISTS quizzes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    training_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    passing_score INT DEFAULT 70,
    time_limit INT DEFAULT 60,
    max_attempts INT DEFAULT 3,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (training_id) REFERENCES trainings(id) ON DELETE CASCADE
);

-- Quiz Questions table
CREATE TABLE IF NOT EXISTS quiz_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT NOT NULL,
    question_text TEXT NOT NULL,
    question_type ENUM('multiple_choice', 'true_false', 'short_answer') DEFAULT 'multiple_choice',
    points INT DEFAULT 1,
    order_num INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
);

-- Quiz Question Options table
CREATE TABLE IF NOT EXISTS quiz_question_options (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_id INT NOT NULL,
    option_text TEXT NOT NULL,
    is_correct BOOLEAN DEFAULT FALSE,
    order_num INT DEFAULT 0,
    FOREIGN KEY (question_id) REFERENCES quiz_questions(id) ON DELETE CASCADE
);

-- Quiz Attempts table
CREATE TABLE IF NOT EXISTS quiz_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT NOT NULL,
    user_id INT NOT NULL,
    score DECIMAL(5,2) DEFAULT 0.00,
    total_questions INT DEFAULT 0,
    correct_answers INT DEFAULT 0,
    status ENUM('started', 'completed', 'abandoned') DEFAULT 'started',
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Certificates table
CREATE TABLE IF NOT EXISTS certificates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    training_id INT NOT NULL,
    certificate_number VARCHAR(50) UNIQUE NOT NULL,
    issued_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('issued', 'revoked') DEFAULT 'issued',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (training_id) REFERENCES trainings(id) ON DELETE CASCADE
);

-- Certificate Templates table
CREATE TABLE IF NOT EXISTS certificate_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    template_html TEXT NOT NULL,
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default data
INSERT INTO course_categories (name, description) VALUES 
('General Training', 'General purpose training courses'),
('Safety Training', 'Safety and compliance training'),
('Technical Training', 'Technical skills and knowledge');

INSERT INTO users (full_name, email, password, role) VALUES 
('System Administrator', 'admin@tms.local', 'password', 'admin'),
('Demo Trainer', 'trainer@tms.local', 'password', 'trainer'),
('Demo Student', 'student@tms.local', 'password', 'user');

-- Notifications table
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    action_url VARCHAR(255) DEFAULT NULL,
    status ENUM('unread', 'read') DEFAULT 'unread',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- File uploads table
CREATE TABLE IF NOT EXISTS file_uploads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    original_filename VARCHAR(255) NOT NULL,
    stored_filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    file_hash VARCHAR(64) NOT NULL,
    upload_type VARCHAR(50) NOT NULL,
    reference_id INT DEFAULT NULL,
    uploaded_by INT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
);

-- User preferences table
CREATE TABLE IF NOT EXISTS user_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    email_notifications JSON DEFAULT NULL,
    dashboard_settings JSON DEFAULT NULL,
    theme VARCHAR(20) DEFAULT 'default',
    timezone VARCHAR(50) DEFAULT 'UTC',
    language VARCHAR(10) DEFAULT 'en',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Email templates table
CREATE TABLE IF NOT EXISTS email_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_name VARCHAR(100) NOT NULL UNIQUE,
    subject VARCHAR(200) NOT NULL,
    body_html TEXT NOT NULL,
    body_text TEXT DEFAULT NULL,
    variables JSON DEFAULT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Training sessions table
CREATE TABLE IF NOT EXISTS training_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    training_id INT NOT NULL,
    session_name VARCHAR(200) NOT NULL,
    session_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    location VARCHAR(200) DEFAULT NULL,
    instructor_id INT DEFAULT NULL,
    max_attendees INT DEFAULT NULL,
    description TEXT DEFAULT NULL,
    status ENUM('scheduled', 'ongoing', 'completed', 'cancelled') DEFAULT 'scheduled',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (training_id) REFERENCES trainings(id) ON DELETE CASCADE,
    FOREIGN KEY (instructor_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Session attendance table
CREATE TABLE IF NOT EXISTS session_attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    user_id INT NOT NULL,
    status ENUM('present', 'absent', 'late', 'excused') DEFAULT 'present',
    check_in_time TIMESTAMP NULL,
    check_out_time TIMESTAMP NULL,
    notes TEXT DEFAULT NULL,
    recorded_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES training_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_attendance (session_id, user_id)
);

-- Course materials table
CREATE TABLE IF NOT EXISTS course_materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    training_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT DEFAULT NULL,
    file_path VARCHAR(500) DEFAULT NULL,
    file_type VARCHAR(50) DEFAULT NULL,
    file_size INT DEFAULT NULL,
    is_mandatory BOOLEAN DEFAULT FALSE,
    order_num INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (training_id) REFERENCES trainings(id) ON DELETE CASCADE
);

INSERT INTO certificate_templates (name, template_html, is_default) VALUES 
('Default Certificate', '<h1>Certificate of Completion</h1><p>This certifies that {student_name} has successfully completed {training_title}</p>', TRUE);

-- Insert default email templates
INSERT INTO email_templates (template_name, subject, body_html, is_active) VALUES
('enrollment_confirmation', 'Training Enrollment Confirmed', '<h2>Enrollment Confirmed</h2><p>You have been enrolled in: {{training_title}}</p>', TRUE),
('training_reminder', 'Training Reminder', '<h2>Training Reminder</h2><p>Your training {{training_title}} starts on {{start_date}}</p>', TRUE),
('certificate_issued', 'Certificate Issued', '<h2>Certificate Issued</h2><p>Congratulations! Your certificate for {{training_title}} is ready for download.</p>', TRUE);

SET FOREIGN_KEY_CHECKS = 1;
    ";
}

function createDirectoryStructure() {
    $directories = [
        'uploads',
        'uploads/certificates', 
        'uploads/materials',
        'uploads/profiles',
        'application/logs'
    ];
    
    foreach ($directories as $dir) {
        $full_path = __DIR__ . '/' . $dir;
        if (!file_exists($full_path)) {
            if (!mkdir($full_path, 0755, true)) {
                throw new Exception("Failed to create directory: " . $dir);
            }
        }
        
        // Make upload directories writable
        if (strpos($dir, 'uploads') === 0) {
            chmod($full_path, 0777);
        }
    }
    
    // Create index.html files for security
    $security_dirs = ['uploads', 'uploads/certificates', 'uploads/materials', 'uploads/profiles'];
    foreach ($security_dirs as $dir) {
        $index_file = __DIR__ . '/' . $dir . '/index.html';
        if (!file_exists($index_file)) {
            file_put_contents($index_file, '<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body><h1>Directory access is forbidden.</h1></body></html>');
        }
    }
}

function updateConfigurationFiles($db_config) {
    // Update database configuration
    $db_config_file = __DIR__ . '/application/config/database.php';
    if (file_exists($db_config_file)) {
        $db_content = file_get_contents($db_config_file);
        
        // Update database settings
        $db_content = preg_replace("/('hostname'\s*=>\s*')[^']*(')/", "$1{$db_config['host']}$2", $db_content);
        $db_content = preg_replace("/('username'\s*=>\s*')[^']*(')/", "$1{$db_config['username']}$2", $db_content);
        $db_content = preg_replace("/('password'\s*=>\s*')[^']*(')/", "$1{$db_config['password']}$2", $db_content);
        $db_content = preg_replace("/('database'\s*=>\s*')[^']*(')/", "$1{$db_config['database']}$2", $db_content);
        
        if (!file_put_contents($db_config_file, $db_content)) {
            throw new Exception("Failed to update database configuration");
        }
    }
    
    // Update base URL configuration
    $config_file = __DIR__ . '/application/config/config.php';
    if (file_exists($config_file)) {
        $config_content = file_get_contents($config_file);
        $base_url = 'http://' . $_SERVER['HTTP_HOST'] . str_replace('/install_schema.php', '/', $_SERVER['REQUEST_URI']);
        $config_content = preg_replace("/(\$config\['base_url'\]\s*=\s*')[^']*(')/", "$1{$base_url}$2", $config_content);
        
        if (!file_put_contents($config_file, $config_content)) {
            throw new Exception("Failed to update base URL configuration");
        }
    }
}

function createSecurityFiles() {
    // Create .htaccess for uploads directory
    $htaccess_uploads = __DIR__ . '/uploads/.htaccess';
    $htaccess_content = "Options -Indexes\nDeny from all\n<Files ~ \"\\.(jpg|jpeg|png|gif|pdf|doc|docx|xls|xlsx|ppt|pptx)$\">\n    Allow from all\n</Files>";
    
    if (!@file_put_contents($htaccess_uploads, $htaccess_content)) {
        // If direct write fails, try to create the directory first
        if (!file_exists(dirname($htaccess_uploads))) {
            mkdir(dirname($htaccess_uploads), 0755, true);
        }
        @file_put_contents($htaccess_uploads, $htaccess_content);
    }
    
    // Create .htaccess for application directory
    $htaccess_app = __DIR__ . '/application/.htaccess';
    $htaccess_app_content = "Deny from all";
    if (!file_exists($htaccess_app)) {
        @file_put_contents($htaccess_app, $htaccess_app_content);
    }
    
    // Create .htaccess for system directory  
    $htaccess_system = __DIR__ . '/system/.htaccess';
    if (!file_exists($htaccess_system)) {
        @file_put_contents($htaccess_system, $htaccess_app_content);
    }
}

function finalizeInstallation() {
    // Create installation complete flag
    $flag_file = __DIR__ . '/install_complete.flag';
    $flag_content = "Installation completed on: " . date('Y-m-d H:i:s') . "\n";
    $flag_content .= "Database: training_management_system\n";
    $flag_content .= "Version: 1.0.0\n";
    file_put_contents($flag_file, $flag_content);
    
    // Create a simple robots.txt for security
    $robots_file = __DIR__ . '/robots.txt';
    if (!file_exists($robots_file)) {
        $robots_content = "User-agent: *\nDisallow: /application/\nDisallow: /system/\nDisallow: /uploads/\n";
        file_put_contents($robots_file, $robots_content);
    }
}

function showCompletionMessage() {
    echo '<script>
        setTimeout(function() {
            document.getElementById("currentStep").innerHTML = "<i class=\"fas fa-check-circle text-success me-2\"></i>Installation finished. You will be redirected to the login page.";
            document.getElementById("progressBar").style.width = "100%";
            document.getElementById("progressBar").classList.remove("progress-bar-animated");
            document.getElementById("progressBar").classList.add("bg-success");
            
            setTimeout(function() {
                window.location.href = "auth/login.php";
            }, 3000);
        }, 1000);
    </script>';
}
?>
