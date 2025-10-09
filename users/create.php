<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/validator.php';

// Check permissions
requirePermission('users.create');

$error = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize input data
    $data = array(
        'full_name' => sanitizeString($_POST['full_name']),
        'email' => sanitizeEmail($_POST['email']),
        'password' => $_POST['password'],
        'confirm_password' => $_POST['confirm_password'],
        'role' => sanitizeString($_POST['role']),
        'phone' => sanitizeString($_POST['phone']),
        'department' => sanitizeString($_POST['department']),
        'position' => sanitizeString($_POST['position'])
    );
    
    // Define validation rules
    $rules = array(
        'full_name' => 'required|alpha|min_length:2|max_length:100',
        'email' => 'required|email|max_length:100|unique:users.email',
        'password' => 'required|min_length:6|max_length:50|regex:/^(?=.*[a-zA-Z])(?=.*\d).+$/',
        'confirm_password' => 'required|confirmed:password',
        'role' => 'required|in:admin,trainer,user',
        'phone' => 'max_length:20|regex:/^[\+]?[0-9\s\-\(\)]+$/',
        'department' => 'max_length:100',
        'position' => 'max_length:100'
    );
    
    // Custom validation messages
    $messages = array(
        'full_name.alpha' => 'Full name may only contain letters and spaces.',
        'password.regex' => 'Password must contain at least one letter and one number.',
        'confirm_password.confirmed' => 'Password confirmation does not match.',
        'role.in' => 'Please select a valid role.',
        'phone.regex' => 'Please enter a valid phone number.'
    );
    
    // Perform validation
    $validator = validate($data, $rules, $messages);
    
    if ($validator->validate()) {
        // Get database connection
        $conn = connectDB(false);
        
        if (!$conn || !($conn instanceof mysqli)) {
            redirect(url('install_schema.php'));
        }
        
        // Hash password for security
        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
        
        // Insert new user (email uniqueness is already validated)
        $stmt = $conn->prepare(
            "INSERT INTO users (full_name, email, password, role, phone, department, position) VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        
        if (!$stmt) {
            $error = 'Error preparing insert statement: ' . $conn->error;
        } else {
            $stmt->bind_param("sssssss", 
                $data['full_name'], 
                $data['email'], 
                $hashedPassword, 
                $data['role'], 
                $data['phone'], 
                $data['department'], 
                $data['position']
            );
    
            if ($stmt->execute()) {
                $userId = $conn->insert_id;
                
                // Log activity
                logActivity('CREATE', 'users', $userId, null, array(
                    'full_name' => $data['full_name'],
                    'email' => $data['email'],
                    'role' => $data['role']
                ));
                
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
    } else {
        // Validation failed - get first error
        $errors = $validator->errors();
        $error = reset($errors); // Get first error message
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
        <form method="POST" id="createUserForm" novalidate>
            <div class="mb-3">
                <label for="full_name" class="form-label">Full Name *</label>
                <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo isset($data['full_name']) ? $data['full_name'] : ''; ?>" required>
            </div>
            
            <div class="mb-3">
                <label for="email" class="form-label">Email *</label>
                <input type="email" class="form-control" id="email" name="email" value="<?php echo isset($data['email']) ? $data['email'] : ''; ?>" required>
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
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="phone" class="form-label">Phone Number</label>
                    <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo isset($data['phone']) ? $data['phone'] : ''; ?>">
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="role" class="form-label">Role *</label>
                    <select class="form-select" id="role" name="role" required>
                        <option value="user" <?php echo (isset($data['role']) && $data['role'] === 'user') || !isset($data['role']) ? 'selected' : ''; ?>>User</option>
                        <option value="trainer" <?php echo isset($data['role']) && $data['role'] === 'trainer' ? 'selected' : ''; ?>>Trainer</option>
                        <?php if (isAdmin()): ?>
                        <option value="admin" <?php echo isset($data['role']) && $data['role'] === 'admin' ? 'selected' : ''; ?>>Administrator</option>
                        <?php endif; ?>
                    </select>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="department" class="form-label">Department</label>
                    <input type="text" class="form-control" id="department" name="department" value="<?php echo isset($data['department']) ? $data['department'] : ''; ?>">
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="position" class="form-label">Position</label>
                    <input type="text" class="form-control" id="position" name="position" value="<?php echo isset($data['position']) ? $data['position'] : ''; ?>">
                </div>
            </div>
            
            <div class="d-flex justify-content-between">
                <a href="<?php echo url('users/'); ?>" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Create User</button>
            </div>
        </form>
    </div>
</div>

<!-- Include validation library -->
<script src="<?php echo url('assets/js/validation.js'); ?>"></script>
<script>
// Initialize form validation
document.addEventListener('DOMContentLoaded', function() {
    const validator = new TMSValidator('#createUserForm');
    
    // Add validation rules
    validator.addRules({
        full_name: ['required', 'alpha', 'minLength:2', 'maxLength:100'],
        email: ['required', 'email', 'maxLength:100'],
        password: ['required', 'minLength:6', 'maxLength:50', 'regex:^(?=.*[a-zA-Z])(?=.*\\d).+$'],
        confirm_password: ['required', 'confirmed:password'],
        role: ['required'],
        phone: ['maxLength:20', 'regex:^[\\+]?[0-9\\s\\-\\(\\)]+$'],
        department: ['maxLength:100'],
        position: ['maxLength:100']
    });
    
    // Add custom error messages
    validator.customMessages = {
        'full_name': {
            'alpha': 'Full name may only contain letters and spaces.',
            'minLength': 'Full name must be at least 2 characters.'
        },
        'password': {
            'regex': 'Password must contain at least one letter and one number.'
        },
        'confirm_password': {
            'confirmed': 'Password confirmation does not match.'
        },
        'phone': {
            'regex': 'Please enter a valid phone number.'
        }
    };
    
    // Add real-time validation feedback
    const form = document.getElementById('createUserForm');
    const inputs = form.querySelectorAll('input, select');
    
    inputs.forEach(input => {
        input.addEventListener('blur', function() {
            if (this.name && validator.rules[this.name]) {
                validator.validateField(this.name);
            }
        });
        
        // Special handling for password confirmation
        if (input.name === 'confirm_password') {
            input.addEventListener('input', function() {
                const password = form.querySelector('[name="password"]').value;
                if (this.value && this.value !== password) {
                    this.classList.add('is-invalid');
                    let feedback = this.parentNode.querySelector('.invalid-feedback');
                    if (!feedback) {
                        feedback = document.createElement('div');
                        feedback.className = 'invalid-feedback';
                        this.parentNode.appendChild(feedback);
                    }
                    feedback.textContent = 'Password confirmation does not match.';
                } else if (this.value === password && password.length >= 6) {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                    const feedback = this.parentNode.querySelector('.invalid-feedback');
                    if (feedback) feedback.remove();
                }
            });
        }
    });
    
    // Password strength indicator
    const passwordField = document.getElementById('password');
    const strengthIndicator = document.createElement('div');
    strengthIndicator.className = 'password-strength mt-1';
    passwordField.parentNode.appendChild(strengthIndicator);
    
    passwordField.addEventListener('input', function() {
        const password = this.value;
        let strength = 0;
        let feedback = [];
        
        if (password.length >= 6) strength++; else feedback.push('at least 6 characters');
        if (/[a-z]/.test(password)) strength++; else feedback.push('lowercase letter');
        if (/[A-Z]/.test(password)) strength++; else feedback.push('uppercase letter');
        if (/\d/.test(password)) strength++; else feedback.push('number');
        if (/[^\w\s]/.test(password)) strength++;
        
        const strengthTexts = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong'];
        const strengthColors = ['text-danger', 'text-warning', 'text-info', 'text-success', 'text-success'];
        
        if (password.length > 0) {
            strengthIndicator.innerHTML = `
                <small class="${strengthColors[strength]}">Password Strength: ${strengthTexts[strength]}</small>
                ${feedback.length > 0 ? `<br><small class="text-muted">Add: ${feedback.join(', ')}</small>` : ''}
            `;
        } else {
            strengthIndicator.innerHTML = '';
        }
    });
});
</script>

<?php include_once '../templates/footer.php'; ?>
