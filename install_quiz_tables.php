<?php
/**
 * Install additional quiz tables for TMS
 */

require_once 'includes/config.php';
require_once 'includes/db.php';

echo "Creating additional quiz tables...\n";

$conn = connectDB(true);

if (!$conn) {
    die("Could not connect to database\n");
}

// Create quiz_questions table
$sql = "CREATE TABLE IF NOT EXISTS quiz_questions (
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
)";

if ($conn->query($sql)) {
    echo "✓ quiz_questions table created/exists\n";
} else {
    echo "✗ Error creating quiz_questions: " . $conn->error . "\n";
}

// Create question_options table  
$sql = "CREATE TABLE IF NOT EXISTS question_options (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_id INT NOT NULL,
    option_text TEXT NOT NULL,
    is_correct BOOLEAN DEFAULT FALSE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (question_id) REFERENCES quiz_questions(id) ON DELETE CASCADE
)";

if ($conn->query($sql)) {
    echo "✓ question_options table created/exists\n";
} else {
    echo "✗ Error creating question_options: " . $conn->error . "\n";
}

// Create quiz_answers table
$sql = "CREATE TABLE IF NOT EXISTS quiz_answers (
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
)";

if ($conn->query($sql)) {
    echo "✓ quiz_answers table created/exists\n";
} else {
    echo "✗ Error creating quiz_answers: " . $conn->error . "\n";
}

closeDB($conn);
echo "\nQuiz tables setup completed!\n";
?>