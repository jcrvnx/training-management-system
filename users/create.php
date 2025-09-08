<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect(url('auth/login.php'));
}

$error = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = sanitize($_POST['full_name']);
    $email = sanitize($_POST['email']);
    $password = sanitize($_POST['password']);
    $confirm_password = sanitize($_POST['confirm_password']);
    $role = sanitize($_POST['role']);
    
    // Validate input
    if (empty($full_name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'Please fill all required fields';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long';
    } elseif (!in_array($role, ['admin', 'user'])) {
        $error = 'Invalid role selected';
    } else {
        // Get database connection
        $conn = connectDB(false);
        
        if (!$conn || !($conn instanceof mysqli)) {
            redirect(url('install.php'));
        }
        
        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = 'Email already exists';
            $stmt->close();
        } else {
            // Insert new user
            $stmt = $conn->prepare(
                "INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, ?)"
            );
            $stmt->bind_param("ssss", $full_name, $email, $password, $role);
            
            if ($stmt->execute()) {
                // Success
                $_SESSION['message'] = 'User has been created successfully';
                redirect(url('users/'));
            } else {
                // Error
                $error = 'Error creating user: ' . $conn->error;
            }
            
            $stmt->close();
        }
        
        closeDB($conn);
    }
}

// Include header
include_once '../templates/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h1>Create New User</h1>
    </div>
</div>

<?php if (!empty($error)): ?>
    <?php echo showAlert($error, 'danger'); ?>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body">
        <form method="POST">
            <div class="mb-3">
                <label for="full_name" class="form-label">Full Name *</label>
                <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo isset($full_name) ? $full_name : ''; ?>" required>
            </div>
            
            <div class="mb-3">
                <label for="email" class="form-label">Email *</label>
                <input type="email" class="form-control" id="email" name="email" value="<?php echo isset($email) ? $email : ''; ?>" required>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="password" class="form-label">Password *</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                    <div class="form-text">Password must be at least 6 characters long.</div>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="confirm_password" class="form-label">Confirm Password *</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="role" class="form-label">Role</label>
                <select class="form-select" id="role" name="role">
                    <option value="user" selected>User</option>
                    <option value="admin">Administrator</option>
                </select>
            </div>
            
            <div class="d-flex justify-content-between">
                <a href="<?php echo url('users/'); ?>" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Create User</button>
            </div>
        </form>
    </div>
</div>

<?php include_once '../templates/footer.php'; ?> 