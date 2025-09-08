<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect(url('auth/login.php'));
}

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['message'] = 'Invalid user ID';
    redirect(url('users/'));
}

$user_id = (int)$_GET['id'];
$error = '';
$user = null;

// Get database connection
$conn = connectDB(false);

if (!$conn || !($conn instanceof mysqli)) {
    redirect(url('install.php'));
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = sanitize($_POST['full_name']);
    $email = sanitize($_POST['email']);
    $role = sanitize($_POST['role']);
    $change_password = isset($_POST['change_password']) && $_POST['change_password'] == '1';
    $password = $change_password ? sanitize($_POST['password']) : '';
    $confirm_password = $change_password ? sanitize($_POST['confirm_password']) : '';
    
    // Validate input
    if (empty($full_name) || empty($email)) {
        $error = 'Please fill all required fields';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } elseif ($change_password && empty($password)) {
        $error = 'Please enter a new password';
    } elseif ($change_password && $password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif ($change_password && strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long';
    } elseif (!in_array($role, ['admin', 'user'])) {
        $error = 'Invalid role selected';
    } else {
        // Check if email already exists for other users
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->bind_param("si", $email, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = 'Email already exists';
            $stmt->close();
        } else {
            $stmt->close();
            
            // Update user with or without password
            if ($change_password) {
                $stmt = $conn->prepare(
                    "UPDATE users 
                     SET full_name = ?, email = ?, password = ?, role = ? 
                     WHERE id = ?"
                );
                $stmt->bind_param("ssssi", $full_name, $email, $password, $role, $user_id);
            } else {
                $stmt = $conn->prepare(
                    "UPDATE users 
                     SET full_name = ?, email = ?, role = ? 
                     WHERE id = ?"
                );
                $stmt->bind_param("sssi", $full_name, $email, $role, $user_id);
            }
            
            if ($stmt->execute()) {
                // If current user is being updated, update session data
                if ($user_id == $_SESSION['user']['id']) {
                    $_SESSION['user']['name'] = $full_name;
                    $_SESSION['user']['email'] = $email;
                }
                
                // Success
                $_SESSION['message'] = 'User has been updated successfully';
                redirect(url('users/'));
            } else {
                // Error
                $error = 'Error updating user: ' . $conn->error;
            }
            
            $stmt->close();
        }
    }
} else {
    // Get user data
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        closeDB($conn);
        $_SESSION['message'] = 'User not found';
        redirect(url('users/'));
    }
    
    $user = $result->fetch_assoc();
    $stmt->close();
}

closeDB($conn);

// Include header
include_once '../templates/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h1>Edit User</h1>
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
                <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
            </div>
            
            <div class="mb-3">
                <label for="email" class="form-label">Email *</label>
                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
            </div>
            
            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="change_password" name="change_password" value="1">
                <label class="form-check-label" for="change_password">Change Password</label>
            </div>
            
            <div id="password-fields" style="display: none;">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="password" class="form-label">New Password</label>
                        <input type="password" class="form-control" id="password" name="password">
                        <div class="form-text">Password must be at least 6 characters long.</div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                    </div>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="role" class="form-label">Role</label>
                <select class="form-select" id="role" name="role" <?php echo $user_id == $_SESSION['user']['id'] ? 'disabled' : ''; ?>>
                    <option value="user" <?php echo $user['role'] === 'user' ? 'selected' : ''; ?>>User</option>
                    <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Administrator</option>
                </select>
                <?php if ($user_id == $_SESSION['user']['id']): ?>
                <input type="hidden" name="role" value="<?php echo $user['role']; ?>">
                <div class="form-text text-muted">You cannot change your own role.</div>
                <?php endif; ?>
            </div>
            
            <div class="d-flex justify-content-between">
                <a href="<?php echo url('users/'); ?>" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Update User</button>
            </div>
        </form>
    </div>
</div>

<!-- JavaScript for password fields toggle -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const changePasswordCheckbox = document.getElementById('change_password');
    const passwordFields = document.getElementById('password-fields');
    
    changePasswordCheckbox.addEventListener('change', function() {
        passwordFields.style.display = this.checked ? 'block' : 'none';
        
        // Toggle required attribute on password fields
        const passwordInputs = passwordFields.querySelectorAll('input[type="password"]');
        passwordInputs.forEach(function(input) {
            input.required = changePasswordCheckbox.checked;
        });
    });
});
</script>

<?php include_once '../templates/footer.php'; ?> 