<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/notification-manager.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit();
}

$pageTitle = 'Email Settings';
$success = $error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['test_email'])) {
        // Test email functionality
        $testEmail = trim($_POST['test_email']);
        
        if (filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
            try {
                $manager = new NotificationManager();
                $result = $manager->sendEmailWithPHPMailer(
                    $testEmail,
                    'Test Email - Training Management System',
                    '
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <meta charset="UTF-8">
                        <title>Test Email</title>
                    </head>
                    <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
                        <div style="background: #007bff; color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0;">
                            <h1 style="margin: 0;">✅ Email Test Successful!</h1>
                            <p style="margin: 10px 0 0 0;">Training Management System</p>
                        </div>
                        
                        <div style="background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; border: 1px solid #e9ecef;">
                            <h2>Email Configuration Test</h2>
                            <p>If you receive this email, your email configuration is working correctly!</p>
                            
                            <div style="background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0;">
                                <strong>✓ Success:</strong> Email notifications are properly configured.
                            </div>
                            
                            <p><strong>Test Details:</strong></p>
                            <ul>
                                <li>Date: ' . date('Y-m-d H:i:s') . '</li>
                                <li>From: ' . FROM_EMAIL . '</li>
                                <li>System: Training Management System</li>
                            </ul>
                            
                            <hr style="border: none; border-top: 1px solid #dee2e6; margin: 30px 0;">
                            <p style="color: #6c757d; font-size: 14px;">This is a test message from the Training Management System.</p>
                        </div>
                    </body>
                    </html>
                    '
                );
                
                if ($result['success']) {
                    $success = "Test email sent successfully to $testEmail. Check your inbox!";
                } else {
                    $error = "Failed to send test email: " . $result['message'];
                }
            } catch (Exception $e) {
                $error = "Email test failed: " . $e->getMessage();
            }
        } else {
            $error = "Please enter a valid email address.";
        }
    }
}

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1><i class="fas fa-envelope-open-text text-primary"></i> Email Settings</h1>
                <a href="../dashboard.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
            
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    <button type="button" class="close" data-dismiss="alert">
                        <span>&times;</span>
                    </button>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                    <button type="button" class="close" data-dismiss="alert">
                        <span>&times;</span>
                    </button>
                </div>
            <?php endif; ?>
            
            <div class="row">
                <!-- Current Email Configuration -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-cogs"></i> Current Email Configuration</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>SMTP Host:</strong></td>
                                    <td><?php echo defined('SMTP_HOST') ? SMTP_HOST : 'Not configured'; ?></td>
                                </tr>
                                <tr>
                                    <td><strong>SMTP Port:</strong></td>
                                    <td><?php echo defined('SMTP_PORT') ? SMTP_PORT : 'Not configured'; ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Encryption:</strong></td>
                                    <td><?php echo defined('SMTP_SECURE') ? SMTP_SECURE : 'Not configured'; ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Username:</strong></td>
                                    <td><?php echo defined('SMTP_USERNAME') ? SMTP_USERNAME : 'Not configured'; ?></td>
                                </tr>
                                <tr>
                                    <td><strong>From Email:</strong></td>
                                    <td><?php echo defined('FROM_EMAIL') ? FROM_EMAIL : 'Not configured'; ?></td>
                                </tr>
                                <tr>
                                    <td><strong>From Name:</strong></td>
                                    <td><?php echo defined('FROM_NAME') ? FROM_NAME : 'Not configured'; ?></td>
                                </tr>
                            </table>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i>
                                <strong>Note:</strong> To modify email settings, edit the file:
                                <code>/includes/email-config.php</code>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Test Email -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-paper-plane"></i> Test Email Configuration</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="form-group">
                                    <label for="test_email">Test Email Address:</label>
                                    <input type="email" class="form-control" id="test_email" name="test_email" 
                                           placeholder="Enter email to test" required>
                                    <small class="form-text text-muted">
                                        We'll send a test email to verify your configuration.
                                    </small>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane"></i> Send Test Email
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-question-circle"></i> Setup Instructions</h5>
                        </div>
                        <div class="card-body">
                            <h6>For Gmail SMTP:</h6>
                            <ol>
                                <li>Enable 2-factor authentication on your Gmail account</li>
                                <li>Generate an App Password for this application</li>
                                <li>Use these settings in <code>email-config.php</code>:
                                    <ul>
                                        <li>Host: <code>smtp.gmail.com</code></li>
                                        <li>Port: <code>587</code></li>
                                        <li>Encryption: <code>tls</code></li>
                                    </ul>
                                </li>
                            </ol>
                            
                            <h6 class="mt-3">For Other Providers:</h6>
                            <p class="text-muted">Check your email provider's SMTP documentation for the correct settings.</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Email Statistics -->
            <div class="row mt-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-chart-bar"></i> Email Statistics</h5>
                        </div>
                        <div class="card-body">
                            <?php
                            try {
                                // Get email statistics from notifications table
                                $stmt = $conn->prepare("
                                    SELECT 
                                        COUNT(*) as total_emails,
                                        SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent_emails,
                                        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_emails
                                    FROM notifications 
                                    WHERE recipient_email IS NOT NULL
                                ");
                                $stmt->execute();
                                $stats = $stmt->get_result()->fetch_assoc();
                                $stmt->close();
                                
                                $success_rate = $stats['total_emails'] > 0 ? 
                                    round(($stats['sent_emails'] / $stats['total_emails']) * 100, 2) : 0;
                            ?>
                            
                            <div class="row text-center">
                                <div class="col-md-3">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h3 class="text-primary"><?php echo $stats['total_emails']; ?></h3>
                                            <p class="mb-0">Total Emails</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h3 class="text-success"><?php echo $stats['sent_emails']; ?></h3>
                                            <p class="mb-0">Sent Successfully</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h3 class="text-danger"><?php echo $stats['failed_emails']; ?></h3>
                                            <p class="mb-0">Failed</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h3 class="text-info"><?php echo $success_rate; ?>%</h3>
                                            <p class="mb-0">Success Rate</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <?php
                            } catch (Exception $e) {
                                echo '<div class="alert alert-warning">Email statistics are not available. The notifications table may need to be updated.</div>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.card {
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    border: none;
    margin-bottom: 20px;
}

.card-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-bottom: none;
}

.table td {
    padding: 0.5rem;
    border: none;
}

code {
    background: #f8f9fa;
    padding: 2px 4px;
    border-radius: 3px;
    font-size: 0.9em;
}
</style>

<?php include '../includes/footer.php'; ?>