<?php
// Installation script for TMS

// Set a flag to indicate installation is in progress
define('INSTALLATION_IN_PROGRESS', true);

require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';

// Set page title
$pageTitle = 'Install Training Management System';

// Flag to track installation status
$installed = false;
$error = '';

// Check if error was passed in URL
if (isset($_GET['error'])) {
    $error = urldecode($_GET['error']);
}

// Process installation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Create database connection without selecting the database
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
        
        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }
        
        // Create database if not exists
        if (!$conn->query("CREATE DATABASE IF NOT EXISTS " . DB_NAME)) {
            throw new Exception("Error creating database: " . $conn->error);
        }
        
        // Select the database
        $conn->select_db(DB_NAME);
        
        // Delete installation flag file if it exists
        if (file_exists('install_complete.flag')) {
            unlink('install_complete.flag');
        }
        
        // Drop any existing tables to start fresh
        $conn->query("DROP TABLE IF EXISTS enrollments");
        $conn->query("DROP TABLE IF EXISTS trainings");
        $conn->query("DROP TABLE IF EXISTS users");
        
        // Create Users Table
        $conn->query("
            CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                full_name VARCHAR(100) NOT NULL,
                email VARCHAR(100) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                role ENUM('admin', 'user') DEFAULT 'user',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Create Trainings Table
        $conn->query("
            CREATE TABLE IF NOT EXISTS trainings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(100) NOT NULL,
                description TEXT,
                start_date DATE NOT NULL,
                end_date DATE NOT NULL,
                location VARCHAR(100),
                max_participants INT DEFAULT 20,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Create Enrollments Table
        $conn->query("
            CREATE TABLE IF NOT EXISTS enrollments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                training_id INT NOT NULL,
                enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (training_id) REFERENCES trainings(id) ON DELETE CASCADE,
                UNIQUE KEY (user_id, training_id)
            )
        ");
        
        // Add sample training data only (no users)
        $conn->query("
            INSERT INTO trainings (title, description, start_date, end_date, location, max_participants) VALUES 
            ('PHP Basics', 'Introduction to PHP programming language', '2023-06-01', '2023-06-05', 'Room 101', 15),
            ('Web Development Fundamentals', 'HTML, CSS, and JavaScript basics', '2023-06-15', '2023-06-20', 'Room 102', 20),
            ('Database Design', 'SQL and database normalization', '2023-07-01', '2023-07-03', 'Room 201', 12),
            ('Bootstrap Framework', 'Responsive design with Bootstrap', '2023-07-15', '2023-07-17', 'Room 301', 25)
        ");
        
        // Create an installation flag file to indicate successful installation
        file_put_contents('install_complete.flag', date('Y-m-d H:i:s'));
        
        $installed = true;
        $conn->close();
        
    } catch (Exception $e) {
        $error = "Installation failed: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card shadow">
                    <div class="card-body p-5">
                        <h2 class="text-center mb-4"><?php echo SITE_NAME; ?> - Installation</h2>
                        
                        <?php if ($installed): ?>
                            <div class="alert alert-success">
                                <h4 class="alert-heading">Installation Complete!</h4>
                                <p>The database has been successfully set up.</p>
                                <hr>
                                <p class="mb-0">Please <a href="<?php echo url('auth/register.php'); ?>" class="alert-link">register</a> to create the first admin account.</p>
                                <p class="small text-muted mt-2">Note: The first user who registers will automatically become an administrator.</p>
                            </div>
                            
                            <div class="text-center mt-4">
                                <a href="<?php echo url('auth/register.php'); ?>" class="btn btn-primary btn-lg">Register Now</a>
                            </div>
                        <?php elseif (!empty($error)): ?>
                            <div class="alert alert-danger">
                                <?php echo $error; ?>
                            </div>
                            <div class="alert alert-warning">
                                <h4 class="alert-heading">Database Connection Issues</h4>
                                <p>Please check your database configuration in <code>includes/config.php</code>:</p>
                                <ul>
                                    <li>Make sure MySQL server is running</li>
                                    <li>Verify database username and password are correct</li>
                                    <li>Ensure the database user has CREATE DATABASE privileges</li>
                                </ul>
                                <p>Current settings:</p>
                                <ul>
                                    <li>Host: <?php echo DB_HOST; ?></li>
                                    <li>User: <?php echo DB_USER; ?></li>
                                    <li>Database: <?php echo DB_NAME; ?></li>
                                </ul>
                            </div>
                            <form method="post" class="mt-4">
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-danger">Try Again</button>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <h4 class="alert-heading">Fresh Installation</h4>
                                <p>This will set up your Training Management System database from scratch.</p>
                                <hr>
                                <p class="mb-0"><strong>Warning:</strong> This will remove all existing data if the database already exists.</p>
                            </div>
                            
                            <form method="post" class="mt-4">
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary btn-lg">Install Now</button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 