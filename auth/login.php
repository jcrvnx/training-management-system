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

// Process login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password';
    } else {
        $conn = connectDB(false); // Connect without creating database
        
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
            // Tables don't exist, redirect to installation
            closeDB($conn);
            redirect(url('install.php'));
        }
        
        // Prepare statement to prevent SQL injection
        $stmt = $conn->prepare("SELECT id, full_name, email, password FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Verify password - in real app, use password_verify() with hashed passwords
            if ($password === $user['password']) { // Simple comparison for demo purposes
                // Set user session
                $_SESSION['user'] = [
                    'id' => $user['id'],
                    'name' => $user['full_name'],
                    'email' => $user['email']
                ];
                
                // Redirect to dashboard
                redirect(url('dashboard.php'));
            } else {
                $error = 'Invalid password';
            }
        } else {
            $error = 'User not found';
        }
        
        $stmt->close();
        closeDB($conn);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME; ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card shadow">
                    <div class="card-body p-5">
                        <h2 class="text-center mb-4"><?php echo SITE_NAME; ?></h2>
                        
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger">
                                <?php echo $error; ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Login</button>
                            </div>
                            
                            <div class="mt-3 text-center">
                                <p>Don't have an account? <a href="<?php echo url('auth/register.php'); ?>">Register now</a></p>
                                <small>
                                    <a href="<?php echo url('install.php'); ?>" class="text-muted">Run Installation</a>
                                </small>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 