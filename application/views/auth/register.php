<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Training Management System</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .register-container {
            max-width: 500px;
            margin: 0 auto;
        }
        .register-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .register-header {
            background: linear-gradient(135deg, #1cc88a, #13855c);
            color: white;
            text-align: center;
            padding: 2rem 1rem 1rem;
        }
        .register-body {
            padding: 2rem;
        }
        .form-control:focus {
            border-color: #1cc88a;
            box-shadow: 0 0 0 0.2rem rgba(28, 200, 138, 0.25);
        }
        .btn-register {
            background: linear-gradient(135deg, #1cc88a, #13855c);
            border: none;
            padding: 12px 30px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .btn-register:hover {
            background: linear-gradient(135deg, #13855c, #1cc88a);
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="register-container">
            <div class="register-card">
                <div class="register-header">
                    <h3><i class="fas fa-user-plus me-2"></i>Create Account</h3>
                    <p class="mb-0">Join Training Management System</p>
                </div>
                <div class="register-body">
                    <!-- Flash Messages -->
                    <?php echo flash_message(); ?>
                    
                    <?php echo form_open('auth/register', ['class' => 'needs-validation', 'novalidate' => '']); ?>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="first_name" class="form-label">
                                    <i class="fas fa-user me-1"></i>First Name
                                </label>
                                <input type="text" class="form-control" id="first_name" name="first_name" 
                                       value="<?php echo set_value('first_name'); ?>" required>
                                <?php echo form_error('first_name', '<div class="invalid-feedback d-block">', '</div>'); ?>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="last_name" class="form-label">
                                    <i class="fas fa-user me-1"></i>Last Name
                                </label>
                                <input type="text" class="form-control" id="last_name" name="last_name" 
                                       value="<?php echo set_value('last_name'); ?>" required>
                                <?php echo form_error('last_name', '<div class="invalid-feedback d-block">', '</div>'); ?>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">
                                <i class="fas fa-envelope me-1"></i>Email Address
                            </label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo set_value('email'); ?>" required>
                            <?php echo form_error('email', '<div class="invalid-feedback d-block">', '</div>'); ?>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="password" class="form-label">
                                    <i class="fas fa-lock me-1"></i>Password
                                </label>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <?php echo form_error('password', '<div class="invalid-feedback d-block">', '</div>'); ?>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="confirm_password" class="form-label">
                                    <i class="fas fa-lock me-1"></i>Confirm Password
                                </label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                <?php echo form_error('confirm_password', '<div class="invalid-feedback d-block">', '</div>'); ?>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="department" class="form-label">
                                <i class="fas fa-building me-1"></i>Department (Optional)
                            </label>
                            <input type="text" class="form-control" id="department" name="department" 
                                   value="<?php echo set_value('department'); ?>">
                            <?php echo form_error('department', '<div class="invalid-feedback d-block">', '</div>'); ?>
                        </div>
                        
                        <div class="mb-3">
                            <label for="phone" class="form-label">
                                <i class="fas fa-phone me-1"></i>Phone Number (Optional)
                            </label>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   value="<?php echo set_value('phone'); ?>">
                            <?php echo form_error('phone', '<div class="invalid-feedback d-block">', '</div>'); ?>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
                            <label class="form-check-label" for="terms">
                                I agree to the <a href="#" class="text-decoration-none">Terms of Service</a> and 
                                <a href="#" class="text-decoration-none">Privacy Policy</a>
                            </label>
                            <?php echo form_error('terms', '<div class="invalid-feedback d-block">', '</div>'); ?>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-success btn-register">
                                <i class="fas fa-user-plus me-2"></i>Create Account
                            </button>
                        </div>
                        
                        <div class="text-center mt-3">
                            <span class="text-muted">Already have an account? </span>
                            <a href="<?php echo base_url('auth/login'); ?>" class="text-decoration-none">
                                Login here
                            </a>
                        </div>
                    <?php echo form_close(); ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
        // Password strength indicator
        $('#password').on('input', function() {
            const password = $(this).val();
            let strength = 0;
            
            if (password.length >= 8) strength++;
            if (password.match(/[a-z]/)) strength++;
            if (password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^a-zA-Z0-9]/)) strength++;
            
            const strengthTexts = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong'];
            const strengthColors = ['#dc3545', '#fd7e14', '#ffc107', '#20c997', '#28a745'];
            
            if (password.length > 0) {
                $(this).after('<div class="password-strength mt-1" style="color: ' + strengthColors[strength] + '">Password strength: ' + strengthTexts[strength] + '</div>');
            }
            $('.password-strength').remove();
            if (password.length > 0) {
                $(this).after('<div class="password-strength mt-1 small" style="color: ' + strengthColors[strength] + '">Password strength: ' + strengthTexts[strength] + '</div>');
            }
        });
        
        // Password confirmation matching
        $('#confirm_password').on('input', function() {
            const password = $('#password').val();
            const confirmPassword = $(this).val();
            
            $('.password-match').remove();
            if (confirmPassword.length > 0) {
                const message = password === confirmPassword ? 
                    '<div class="password-match mt-1 small text-success">Passwords match</div>' : 
                    '<div class="password-match mt-1 small text-danger">Passwords do not match</div>';
                $(this).after(message);
            }
        });
        
        // Auto-hide alerts
        setTimeout(function() {
            $('.alert').fadeOut('slow');
        }, 5000);
        
        // Bootstrap form validation
        (function() {
            'use strict';
            window.addEventListener('load', function() {
                var forms = document.getElementsByClassName('needs-validation');
                var validation = Array.prototype.filter.call(forms, function(form) {
                    form.addEventListener('submit', function(event) {
                        if (form.checkValidity() === false) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                        form.classList.add('was-validated');
                    }, false);
                });
            }, false);
        })();
    </script>
</body>
</html>
