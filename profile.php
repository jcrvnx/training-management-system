<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect(url('auth/login.php'));
}

$currentUser = getCurrentUser();
$userId = $currentUser['id'];

// Get database connection
$conn = connectDB(false);

if (!$conn) {
    redirect(url('install_schema.php'));
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $department = trim($_POST['department']);
    $position = trim($_POST['position']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    $errors = [];
    
    // Validation
    if (empty($full_name)) {
        $errors[] = 'Full name is required.';
    }
    
    if (empty($email)) {
        $errors[] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    
    // Check if email is already taken by another user
    $emailCheckQuery = "SELECT id FROM users WHERE email = ? AND id != ?";
    $emailCheckStmt = $conn->prepare($emailCheckQuery);
    $emailCheckStmt->bind_param("si", $email, $userId);
    $emailCheckStmt->execute();
    $emailCheckResult = $emailCheckStmt->get_result();
    if ($emailCheckResult->num_rows > 0) {
        $errors[] = 'Email address is already in use by another user.';
    }
    $emailCheckStmt->close();
    
    // Password change validation
    if (!empty($new_password)) {
        if (empty($current_password)) {
            $errors[] = 'Current password is required to change password.';
        } else {
            // Verify current password
            $currentPasswordQuery = "SELECT password FROM users WHERE id = ?";
            $currentPasswordStmt = $conn->prepare($currentPasswordQuery);
            $currentPasswordStmt->bind_param("i", $userId);
            $currentPasswordStmt->execute();
            $currentPasswordResult = $currentPasswordStmt->get_result();
            $currentPasswordData = $currentPasswordResult->fetch_assoc();
            $currentPasswordStmt->close();
            
            if (!password_verify($current_password, $currentPasswordData['password'])) {
                $errors[] = 'Current password is incorrect.';
            }
        }
        
        if (strlen($new_password) < 6) {
            $errors[] = 'New password must be at least 6 characters long.';
        }
        
        if ($new_password !== $confirm_password) {
            $errors[] = 'New password and confirmation do not match.';
        }
    }
    
    if (empty($errors)) {
        // Update user profile
        $updateFields = "full_name = ?, email = ?";
        $updateParams = [$full_name, $email];
        $paramTypes = "ss";
        
        // Add optional fields if they exist in the database
        $checkColumns = $conn->query("SHOW COLUMNS FROM users");
        $availableColumns = [];
        while ($column = $checkColumns->fetch_assoc()) {
            $availableColumns[] = $column['Field'];
        }
        
        if (in_array('phone', $availableColumns)) {
            $updateFields .= ", phone = ?";
            $updateParams[] = $phone;
            $paramTypes .= "s";
        }
        
        if (in_array('department', $availableColumns)) {
            $updateFields .= ", department = ?";
            $updateParams[] = $department;
            $paramTypes .= "s";
        }
        
        if (in_array('position', $availableColumns)) {
            $updateFields .= ", position = ?";
            $updateParams[] = $position;
            $paramTypes .= "s";
        }
        
        // Add password if being changed
        if (!empty($new_password)) {
            $updateFields .= ", password = ?";
            $updateParams[] = password_hash($new_password, PASSWORD_DEFAULT);
            $paramTypes .= "s";
        }
        
        // Add updated_at if column exists
        if (in_array('updated_at', $availableColumns)) {
            $updateFields .= ", updated_at = CURRENT_TIMESTAMP";
        }
        
        $updateParams[] = $userId;
        $paramTypes .= "i";
        
        $updateQuery = "UPDATE users SET {$updateFields} WHERE id = ?";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bind_param($paramTypes, ...$updateParams);
        
        if ($updateStmt->execute()) {
            $_SESSION['user']['name'] = $full_name;
            $_SESSION['user']['email'] = $email;
            $_SESSION['message'] = 'Profile updated successfully!';
            redirect(url('profile.php'));
        } else {
            $errors[] = 'Failed to update profile. Please try again.';
        }
        $updateStmt->close();
    }
}

// Get current user data
$userQuery = "SELECT * FROM users WHERE id = ?";
$userStmt = $conn->prepare($userQuery);
$userStmt->bind_param("i", $userId);
$userStmt->execute();
$userResult = $userStmt->get_result();
$user = $userResult->fetch_assoc();
$userStmt->close();

// Get user statistics
$stats = [
    'enrollments' => 0,
    'completed' => 0,
    'certificates' => 0,
    'quiz_attempts' => 0
];

$enrollmentQuery = "SELECT COUNT(*) as total, 
                           SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed 
                    FROM enrollments WHERE user_id = ?";
$enrollmentStmt = $conn->prepare($enrollmentQuery);
$enrollmentStmt->bind_param("i", $userId);
$enrollmentStmt->execute();
$enrollmentResult = $enrollmentStmt->get_result();
$enrollmentData = $enrollmentResult->fetch_assoc();
$stats['enrollments'] = $enrollmentData['total'];
$stats['completed'] = $enrollmentData['completed'] ?: 0;
$enrollmentStmt->close();

// Get certificate count if table exists
if ($conn->query("SHOW TABLES LIKE 'certificates'")->num_rows > 0) {
    $certQuery = "SELECT COUNT(*) as count FROM certificates WHERE user_id = ? AND is_valid = 1";
    $certStmt = $conn->prepare($certQuery);
    $certStmt->bind_param("i", $userId);
    $certStmt->execute();
    $certResult = $certStmt->get_result();
    $stats['certificates'] = $certResult->fetch_assoc()['count'];
    $certStmt->close();
}

// Get quiz attempts count if table exists
if ($conn->query("SHOW TABLES LIKE 'quiz_attempts'")->num_rows > 0) {
    $quizQuery = "SELECT COUNT(*) as count FROM quiz_attempts WHERE user_id = ? AND status = 'submitted'";
    $quizStmt = $conn->prepare($quizQuery);
    $quizStmt->bind_param("i", $userId);
    $quizStmt->execute();
    $quizResult = $quizStmt->get_result();
    $stats['quiz_attempts'] = $quizResult->fetch_assoc()['count'];
    $quizStmt->close();
}

closeDB($conn);

// Include header
include_once 'templates/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1><i class="fas fa-user-circle text-primary me-2"></i>My Profile</h1>
                <p class="text-muted mb-0">Manage your account information and settings</p>
            </div>
            <div>
                <a href="<?php echo url('dashboard.php'); ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                </a>
            </div>
        </div>
    </div>
</div>

<?php if (isset($_SESSION['message'])): ?>
<div class="alert alert-success alert-dismissible fade show">
    <?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <ul class="mb-0">
        <?php foreach ($errors as $error): ?>
        <li><?php echo htmlspecialchars($error); ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="row">
    <!-- Profile Stats -->
    <div class="col-md-4">
        <div class="card shadow mb-4">
            <div class="card-body text-center">
                <div class="avatar-circle mx-auto mb-3" style="width: 80px; height: 80px; font-size: 2rem;">
                    <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                </div>
                <h4><?php echo htmlspecialchars($user['full_name']); ?></h4>
                <p class="text-muted"><?php echo htmlspecialchars($user['email']); ?></p>
                <span class="badge bg-<?php
                switch($user['role']) {
                    case 'admin': echo 'danger'; break;
                    case 'trainer': echo 'warning'; break;
                    default: echo 'primary';
                } 
                ?> fs-6">
                    <?php echo ucfirst($user['role']); ?>
                </span>
            </div>
        </div>
        
        <!-- Quick Stats -->
        <div class="card shadow">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i>My Statistics</h6>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6 mb-3">
                        <div class="stat-number text-primary"><?php echo $stats['enrollments']; ?></div>
                        <div class="stat-label">Enrollments</div>
                    </div>
                    <div class="col-6 mb-3">
                        <div class="stat-number text-success"><?php echo $stats['completed']; ?></div>
                        <div class="stat-label">Completed</div>
                    </div>
                    <div class="col-6">
                        <div class="stat-number text-warning"><?php echo $stats['certificates']; ?></div>
                        <div class="stat-label">Certificates</div>
                    </div>
                    <div class="col-6">
                        <div class="stat-number text-info"><?php echo $stats['quiz_attempts']; ?></div>
                        <div class="stat-label">Quiz Attempts</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Profile Form -->
    <div class="col-md-8">
        <div class="card shadow">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Edit Profile</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <!-- Personal Information -->
                    <h6 class="text-primary mb-3">Personal Information</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="full_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="full_name" name="full_name" 
                                       value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone</label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="department" class="form-label">Department</label>
                                <input type="text" class="form-control" id="department" name="department" 
                                       value="<?php echo htmlspecialchars($user['department'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="position" class="form-label">Position</label>
                                <input type="text" class="form-control" id="position" name="position" 
                                       value="<?php echo htmlspecialchars($user['position'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Member Since</label>
                                <input type="text" class="form-control" readonly
                                       value="<?php echo date('F j, Y', strtotime($user['created_at'])); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <!-- Change Password -->
                    <h6 class="text-primary mb-3">Change Password</h6>
                    <p class="text-muted small">Leave password fields empty if you don't want to change your password.</p>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="current_password" class="form-label">Current Password</label>
                                <input type="password" class="form-control" id="current_password" name="current_password">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" minlength="6">
                                <small class="form-text text-muted">Minimum 6 characters</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="6">
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <!-- Submit Buttons -->
                    <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-secondary me-2" onclick="window.location.reload()">
                            <i class="fas fa-undo me-1"></i>Reset
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.avatar-circle {
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}

.stat-number {
    font-size: 2rem;
    font-weight: bold;
    line-height: 1;
}

.stat-label {
    font-size: 0.875rem;
    color: #6c757d;
    margin-top: 5px;
}
</style>

<script>
// Password confirmation validation
document.getElementById('confirm_password').addEventListener('input', function() {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = this.value;
    
    if (newPassword !== confirmPassword) {
        this.setCustomValidity('Passwords do not match');
    } else {
        this.setCustomValidity('');
    }
});

// Require current password if new password is entered
document.getElementById('new_password').addEventListener('input', function() {
    const currentPassword = document.getElementById('current_password');
    if (this.value) {
        currentPassword.required = true;
    } else {
        currentPassword.required = false;
    }
});
</script>

<?php include_once 'templates/footer.php'; ?>