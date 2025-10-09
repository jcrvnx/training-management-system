<?php
/**
 * Install missing tables for TMS bug fixes
 */

require_once 'includes/config.php';
require_once 'includes/db.php';

echo "Creating missing database tables...\n";

$conn = connectDB(true);

if (!$conn) {
    die("Could not connect to database\n");
}

// Create notifications table if it doesn't exist
$notifications_sql = "
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    action_url VARCHAR(500) DEFAULT NULL,
    status ENUM('unread', 'read') DEFAULT 'unread',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";

if ($conn->query($notifications_sql)) {
    echo "✓ Notifications table created/exists\n";
} else {
    echo "✗ Error creating notifications table: " . $conn->error . "\n";
}

// Create certificates table if it doesn't exist
$certificates_sql = "
CREATE TABLE IF NOT EXISTS certificates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    training_id INT NOT NULL,
    certificate_number VARCHAR(100) UNIQUE NOT NULL,
    issued_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('active', 'revoked', 'expired') DEFAULT 'active',
    certificate_data JSON DEFAULT NULL,
    file_path VARCHAR(500) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (training_id) REFERENCES trainings(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_training (user_id, training_id)
)";

if ($conn->query($certificates_sql)) {
    echo "✓ Certificates table created/exists\n";
} else {
    echo "✗ Error creating certificates table: " . $conn->error . "\n";
}

// Create course_categories table if it doesn't exist
$categories_sql = "
CREATE TABLE IF NOT EXISTS course_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    color VARCHAR(7) DEFAULT '#007bff',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if ($conn->query($categories_sql)) {
    echo "✓ Course categories table created/exists\n";
} else {
    echo "✗ Error creating course categories table: " . $conn->error . "\n";
}

// Insert default categories if table is empty
$count_result = $conn->query("SELECT COUNT(*) as count FROM course_categories");
$count = $count_result->fetch_assoc()['count'];

if ($count == 0) {
    $default_categories = [
        ['Safety Training', 'Health and safety related courses', '#dc3545'],
        ['Technical Skills', 'Technical and professional development', '#007bff'],
        ['Compliance', 'Regulatory compliance and legal requirements', '#ffc107'],
        ['Leadership', 'Management and leadership development', '#28a745'],
        ['Soft Skills', 'Communication, teamwork and interpersonal skills', '#6f42c1'],
        ['IT & Digital', 'Information technology and digital literacy', '#17a2b8']
    ];
    
    foreach ($default_categories as $cat) {
        $stmt = $conn->prepare("INSERT INTO course_categories (name, description, color) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $cat[0], $cat[1], $cat[2]);
        $stmt->execute();
        $stmt->close();
    }
    echo "✓ Default categories inserted\n";
}

// Create quizzes table if it doesn't exist
$quizzes_sql = "
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
)";

if ($conn->query($quizzes_sql)) {
    echo "✓ Quizzes table created/exists\n";
} else {
    echo "✗ Error creating quizzes table: " . $conn->error . "\n";
}

// Create quiz_attempts table if it doesn't exist  
$quiz_attempts_sql = "
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
)";

if ($conn->query($quiz_attempts_sql)) {
    echo "✓ Quiz attempts table created/exists\n";
} else {
    echo "✗ Error creating quiz attempts table: " . $conn->error . "\n";
}

// Update trainings table to have is_active column if missing
$check_column = $conn->query("SHOW COLUMNS FROM trainings LIKE 'is_active'");
if ($check_column->num_rows == 0) {
    if ($conn->query("ALTER TABLE trainings ADD COLUMN is_active BOOLEAN DEFAULT TRUE")) {
        echo "✓ Added is_active column to trainings table\n";
    } else {
        echo "✗ Error adding is_active column to trainings: " . $conn->error . "\n";
    }
}

// Update users table to have is_active column if missing
$check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'is_active'");
if ($check_column->num_rows == 0) {
    if ($conn->query("ALTER TABLE users ADD COLUMN is_active BOOLEAN DEFAULT TRUE")) {
        echo "✓ Added is_active column to users table\n";
    } else {
        echo "✗ Error adding is_active column to users: " . $conn->error . "\n";
    }
}

// Create admin user if no users exist
$count_result = $conn->query("SELECT COUNT(*) as count FROM users");
$user_count = $count_result->fetch_assoc()['count'];

if ($user_count == 0) {
    $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, ?)");
    $full_name = "System Administrator";
    $email = "admin@tms.local";
    $role = "admin";
    $stmt->bind_param("ssss", $full_name, $email, $admin_password, $role);
    
    if ($stmt->execute()) {
        echo "✓ Default admin user created (admin@tms.local / admin123)\n";
    } else {
        echo "✗ Error creating admin user: " . $conn->error . "\n";
    }
    $stmt->close();
}

closeDB($conn);

echo "\nDatabase setup completed!\n";
echo "You can now use the system with the following credentials:\n";
echo "Email: admin@tms.local\n";  
echo "Password: admin123\n";
?>