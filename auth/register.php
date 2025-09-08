<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

// If user is already logged in, redirect to dashboard
if (isset($_SESSION['user'])) {
    redirect(url('dashboard.php'));
}

// Check if installation is complete
if (!file_exists('../install_complete.flag')) {
    redirect(url('install.php'));
}

$error = '';
$success = '';

// Process registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = sanitize($_POST['full_name']);
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate input
    if (empty($full_name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'Please fill in all fields';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long';
    } else {
        $conn = connectDB(false);
        
        if (!$conn || !($conn instanceof mysqli)) {
            redirect(url('install.php'));
        }
        
        // Check if users table exists
        $tablesExist = true;
        $result = $conn->query("SHOW TABLES LIKE 'users'");
        if ($result->num_rows === 0) {
            $tablesExist = false;
        }
        
        if (!$tablesExist) {
            closeDB($conn);
            redirect(url('install.php'));
        }
        
        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = 'Email already registered. Please use a different email or login.';
            $stmt->close();
        } else {
            // Check if this is the first user (make them admin)
            $result = $conn->query("SELECT COUNT(*) as count FROM users");
            $isFirstUser = ($result->fetch_assoc()['count'] == 0);
            $role = $isFirstUser ? 'admin' : 'user';
            
            // Insert new user
            $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $full_name, $email, $password, $role);
            
            if ($stmt->execute()) {
                $success = 'Registration successful! You can now log in.';
                if ($isFirstUser) {
                    $success .= ' You have been registered as an administrator.';
                }
            } else {
                $error = 'Error creating account: ' . $conn->error;
            }
            
            $stmt->close();
        }
        
        closeDB($conn);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?php echo SITE_NAME; ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card shadow">
                    <div class="card-body p-5">
                        <h2 class="text-center mb-4">Create Account</h2>
                        
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger">
                                <?php echo $error; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($success)): ?>
                            <div class="alert alert-success">
                                <?php echo $success; ?>
                                <div class="mt-2">
                                    <a href="<?php echo url('auth/login.php'); ?>" class="alert-link">Click here to login</a>
                                </div>
                            </div>
                        <?php else: ?>
                            <form method="POST">
                                <div class="mb-3">
                                    <label for="full_name" class="form-label">Full Name</label>
                                    <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo isset($full_name) ? $full_name : ''; ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo isset($email) ? $email : ''; ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="password" class="form-label">Password</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <div class="form-text">Password must be at least 6 characters long.</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm Password</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                                
                                <div class="d-grid mb-3">
                                    <button type="submit" class="btn btn-primary">Register</button>
                                </div>
                                
                                <div class="text-center">
                                    Already have an account? <a href="<?php echo url('auth/login.php'); ?>">Login</a>
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