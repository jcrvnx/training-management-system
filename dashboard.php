<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';
require_once 'includes/notification-manager.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect(url('auth/login.php'));
}

// Check if installation is complete
if (!file_exists('install_complete.flag')) {
    redirect(url('install_schema.php'));
}

// Get database connection
$conn = connectDB(false);

// If connection failed, redirect to installation
if (!$conn || !($conn instanceof mysqli)) {
    redirect(url('install_schema.php'));
}

// Check if required tables exist
$tablesExist = true;
$requiredTables = ['users', 'trainings', 'enrollments'];

foreach ($requiredTables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows === 0) {
        $tablesExist = false;
        break;
    }
}

if (!$tablesExist) {
    // Tables don't exist, redirect to installation
    closeDB($conn);
    redirect(url('install_schema.php'));
}

$currentUser = getCurrentUser();
$userId = $currentUser['id'];
$userRole = getCurrentUserRole();

// Get comprehensive analytics data
try {
    // Basic counts
    $trainingCount = $conn->query("SELECT COUNT(*) as count FROM trainings")->fetch_assoc()['count'];
    $userCount = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
    $enrollmentCount = $conn->query("SELECT COUNT(*) as count FROM enrollments")->fetch_assoc()['count'];
    
    // Quiz and certificate stats
    $quizCount = 0;
    $certificateCount = 0;
    $quizAttempts = 0;
    $passedQuizzes = 0;
    
    // Check if advanced tables exist
    $advancedTablesExist = true;
    $advancedTables = ['quizzes', 'certificates', 'quiz_attempts'];
    foreach ($advancedTables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result->num_rows === 0) {
            $advancedTablesExist = false;
            break;
        }
    }
    
    if ($advancedTablesExist) {
        $quizCount = $conn->query("SELECT COUNT(*) as count FROM quizzes WHERE is_active = 1")->fetch_assoc()['count'];
        $certificateCount = $conn->query("SELECT COUNT(*) as count FROM certificates WHERE status = 'active'")->fetch_assoc()['count'];
        $quizAttempts = $conn->query("SELECT COUNT(*) as count FROM quiz_attempts WHERE status = 'submitted'")->fetch_assoc()['count'];
        $passedQuizzes = $conn->query("SELECT COUNT(*) as count FROM quiz_attempts WHERE status = 'submitted' AND passed = 1")->fetch_assoc()['count'];
    }
    
    // Role-based data
    if (isAdmin()) {
        // Admin gets system-wide stats
        $myTrainings = $trainingCount;
        $myEnrollments = $enrollmentCount;
        $myCertificates = $certificateCount;
        
        // Get recent activities
        $recentActivities = $conn->query("
            SELECT 'enrollment' as type, u.full_name as user_name, t.title as training_title, e.enrolled_at as activity_date, e.id as item_id
            FROM enrollments e
            JOIN users u ON e.user_id = u.id
            JOIN trainings t ON e.training_id = t.id
            ORDER BY e.enrolled_at DESC
            LIMIT 10
        ");
        
    } elseif (isTrainer()) {
        // Trainer gets their assigned trainings
        $myTrainings = $conn->query("SELECT COUNT(*) as count FROM trainings WHERE instructor_id = {$userId}")->fetch_assoc()['count'];
        $myEnrollments = $conn->query("
            SELECT COUNT(*) as count FROM enrollments e
            JOIN trainings t ON e.training_id = t.id
            WHERE t.instructor_id = {$userId}
        ")->fetch_assoc()['count'];
        
        if ($advancedTablesExist) {
            $myCertificates = $conn->query("
                SELECT COUNT(*) as count FROM certificates c
                JOIN trainings t ON c.training_id = t.id
                WHERE t.instructor_id = {$userId} AND c.status = 'active'
            ")->fetch_assoc()['count'];
        } else {
            $myCertificates = 0;
        }
        
        // Get activities for trainer's trainings
        $recentActivities = $conn->query("
            SELECT 'enrollment' as type, u.full_name as user_name, t.title as training_title, e.enrolled_at as activity_date, e.id as item_id
            FROM enrollments e
            JOIN users u ON e.user_id = u.id
            JOIN trainings t ON e.training_id = t.id
            WHERE t.instructor_id = {$userId}
            ORDER BY e.enrolled_at DESC
            LIMIT 10
        ");
        
    } else {
        // Regular user gets their personal stats
        $myTrainings = $conn->query("SELECT COUNT(*) as count FROM enrollments WHERE user_id = {$userId}")->fetch_assoc()['count'];
        $myEnrollments = $myTrainings; // Same for users
        
        if ($advancedTablesExist) {
            $myCertificates = $conn->query("SELECT COUNT(*) as count FROM certificates WHERE user_id = {$userId} AND status = 'active'")->fetch_assoc()['count'];
            $myQuizAttempts = $conn->query("SELECT COUNT(*) as count FROM quiz_attempts WHERE user_id = {$userId} AND status = 'submitted'")->fetch_assoc()['count'];
            $myPassedQuizzes = $conn->query("SELECT COUNT(*) as count FROM quiz_attempts WHERE user_id = {$userId} AND status = 'submitted' AND passed = 1")->fetch_assoc()['count'];
        } else {
            $myCertificates = 0;
            $myQuizAttempts = 0;
            $myPassedQuizzes = 0;
        }
        
        // Get user's recent activities
        $recentActivities = $conn->query("
            SELECT 'enrollment' as type, u.full_name as user_name, t.title as training_title, e.enrolled_at as activity_date, e.id as item_id
            FROM enrollments e
            JOIN users u ON e.user_id = u.id
            JOIN trainings t ON e.training_id = t.id
            WHERE e.user_id = {$userId}
            ORDER BY e.enrolled_at DESC
            LIMIT 5
        ");
    }
    
    // Get notifications for current user
    $notificationManager = new NotificationManager();
    $unreadNotifications = $notificationManager->getUnreadCount($userId);
    $recentNotifications = $notificationManager->getUserNotifications($userId, 5);
    
} catch (Exception $e) {
    // Database error, redirect to installation
    closeDB($conn);
    redirect(url('install_schema.php'));
}

closeDB($conn);

// Include header
include_once 'templates/header.php';
?>

<!-- Dashboard Header -->
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                <i class="fas fa-tachometer-alt text-primary me-2" style="font-size: 2rem;"></i>
                <div>
                    <h1 class="mb-0">Dashboard</h1>
                    <p class="lead mt-1 mb-0">Welcome, <?php echo htmlspecialchars($currentUser['full_name']); ?>!</p>
                    <small class="text-muted">Role: <?php echo ucfirst($userRole); ?></small>
                </div>
            </div>
            
            <!-- Notifications Bell -->
            <div class="dropdown">
                <button class="btn btn-outline-primary position-relative" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-bell"></i>
                    <?php if ($unreadNotifications > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                            <?php echo $unreadNotifications > 99 ? '99+' : $unreadNotifications; ?>
                        </span>
                    <?php endif; ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end" style="width: 300px;">
                    <li class="dropdown-header d-flex justify-content-between align-items-center">
                        <span>Notifications</span>
                        <?php if ($unreadNotifications > 0): ?>
                            <button class="btn btn-sm btn-link p-0" onclick="markAllNotificationsRead()">Mark all read</button>
                        <?php endif; ?>
                    </li>
                    <?php if (!empty($recentNotifications)): ?>
                        <?php foreach ($recentNotifications as $notification): ?>
                            <li class="dropdown-item-text p-2 border-bottom">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div style="flex: 1;">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($notification['title']); ?></h6>
                                        <p class="mb-1 small"><?php echo htmlspecialchars(substr($notification['message'], 0, 100)); ?>...</p>
                                        <small class="text-muted"><?php echo date('M j, g:i A', strtotime($notification['created_at'])); ?></small>
                                    </div>
                                    <?php if ($notification['status'] === 'unread'): ?>
                                        <span class="badge bg-primary rounded-pill">New</span>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li class="dropdown-item-text text-center text-muted py-3">No notifications</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Role-based Statistics Cards -->
<?php if (isAdmin()): ?>
    <!-- Admin Dashboard -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Trainings</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $trainingCount; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-book fa-2x text-gray-300"></i>
                        </div>
                    </div>
                    <div class="mt-2">
                        <a href="<?php echo BASE_URL; ?>trainings/" class="btn btn-primary btn-sm">Manage</a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card border-left-success shadow">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Users</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $userCount; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
                        </div>
                    </div>
                    <div class="mt-2">
                        <a href="<?php echo BASE_URL; ?>users/" class="btn btn-success btn-sm">Manage</a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card border-left-info shadow">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Active Quizzes</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $quizCount; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-question-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                    <div class="mt-2">
                        <a href="<?php echo BASE_URL; ?>quizzes/" class="btn btn-info btn-sm">View</a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Certificates Issued</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $certificateCount; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-certificate fa-2x text-gray-300"></i>
                        </div>
                    </div>
                    <div class="mt-2">
                        <a href="<?php echo BASE_URL; ?>certificates/" class="btn btn-warning btn-sm">View</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- System Analytics -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">Training Completion Rate</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h4 class="small font-weight-bold">Quiz Attempts <span class="float-right"><?php echo $quizAttempts; ?></span></h4>
                            <div class="progress mb-4">
                                <div class="progress-bar bg-info" role="progressbar" style="width: 100%"></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h4 class="small font-weight-bold">Pass Rate 
                                <span class="float-right"><?php echo $quizAttempts > 0 ? round(($passedQuizzes / $quizAttempts) * 100, 1) : 0; ?>%</span>
                            </h4>
                            <div class="progress mb-4">
                                <div class="progress-bar bg-success" role="progressbar" 
                                     style="width: <?php echo $quizAttempts > 0 ? ($passedQuizzes / $quizAttempts) * 100 : 0; ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card shadow">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">Quick Stats</h6>
                </div>
                <div class="card-body">
                    <div class="text-center">
                        <div class="mb-3">
                            <i class="fas fa-chart-pie fa-3x text-primary"></i>
                        </div>
                        <p class="mb-2"><strong>Total Enrollments:</strong> <?php echo $enrollmentCount; ?></p>
                        <p class="mb-2"><strong>Success Rate:</strong> <?php echo $quizAttempts > 0 ? round(($passedQuizzes / $quizAttempts) * 100, 1) : 0; ?>%</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php elseif (isTrainer()): ?>
    <!-- Trainer Dashboard -->
    <div class="row mb-4">
        <div class="col-lg-4 col-md-6 mb-4">
            <div class="card border-left-primary shadow">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">My Trainings</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $myTrainings; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-chalkboard-teacher fa-2x text-gray-300"></i>
                        </div>
                    </div>
                    <div class="mt-2">
                        <a href="<?php echo BASE_URL; ?>trainings/create.php" class="btn btn-primary btn-sm">Create New</a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4 col-md-6 mb-4">
            <div class="card border-left-success shadow">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Students Enrolled</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $myEnrollments; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user-graduate fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4 col-md-6 mb-4">
            <div class="card border-left-warning shadow">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Certificates Issued</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $myCertificates; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-award fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- Student Dashboard -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">My Trainings</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $myTrainings; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-book-open fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card border-left-success shadow">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Quiz Attempts</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo isset($myQuizAttempts) ? $myQuizAttempts : 0; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-tasks fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card border-left-info shadow">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Passed Quizzes</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo isset($myPassedQuizzes) ? $myPassedQuizzes : 0; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">My Certificates</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $myCertificates; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-certificate fa-2x text-gray-300"></i>
                        </div>
                    </div>
                    <div class="mt-2">
                        <a href="<?php echo BASE_URL; ?>certificates/my-certificates.php" class="btn btn-warning btn-sm">View</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php endif; ?>

<!-- Recent Activities Section -->
<div class="row">
    <div class="col-12">
        <div class="card shadow">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-clock me-1"></i> Recent Activities
                </h6>
                <?php if (isAdmin() || isTrainer()): ?>
                    <a href="<?php echo BASE_URL; ?>enrollments/create.php" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus me-1"></i> New Enrollment
                    </a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="thead-light">
                            <tr>
                                <th>Activity</th>
                                <th>User</th>
                                <th>Training</th>
                                <th>Date</th>
                                <?php if (isAdmin() || isTrainer()): ?>
                                    <th>Action</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recentActivities && $recentActivities->num_rows > 0): ?>
                                <?php while ($activity = $recentActivities->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <?php if ($activity['type'] === 'enrollment'): ?>
                                                <span class="badge bg-primary">
                                                    <i class="fas fa-user-plus me-1"></i>Enrollment
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <i class="fas fa-user text-primary me-1"></i>
                                            <?php echo htmlspecialchars($activity['user_name']); ?>
                                        </td>
                                        <td>
                                            <i class="fas fa-book text-success me-2"></i>
                                            <?php echo htmlspecialchars($activity['training_title']); ?>
                                        </td>
                                        <td>
                                            <i class="fas fa-calendar text-muted me-1"></i>
                                            <?php echo date('M j, Y g:i A', strtotime($activity['activity_date'])); ?>
                                        </td>
                                        <?php if (isAdmin() || isTrainer()): ?>
                                            <td>
                                                <a href="<?php echo BASE_URL; ?>enrollments/edit.php?id=<?php echo $activity['item_id']; ?>" 
                                                   class="btn btn-sm btn-outline-info">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="<?php echo (isAdmin() || isTrainer()) ? '5' : '4'; ?>" class="text-center text-muted">
                                        <i class="fas fa-info-circle me-1"></i>No recent activities found
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Custom CSS for Dashboard -->
<style>
.border-left-primary {
    border-left: 0.25rem solid #4e73df !important;
}

.border-left-success {
    border-left: 0.25rem solid #1cc88a !important;
}

.border-left-info {
    border-left: 0.25rem solid #36b9cc !important;
}

.border-left-warning {
    border-left: 0.25rem solid #f6c23e !important;
}

.text-xs {
    font-size: 0.7rem;
}

.text-gray-300 {
    color: #dddfeb !important;
}

.text-gray-800 {
    color: #5a5c69 !important;
}

.shadow {
    box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15) !important;
}

.notification-badge {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% {
        box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7);
    }
    70% {
        box-shadow: 0 0 0 10px rgba(220, 53, 69, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(220, 53, 69, 0);
    }
}
</style>

<!-- Dashboard JavaScript -->
<script>
// Mark all notifications as read
function markAllNotificationsRead() {
    fetch('includes/notification-manager.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=notification_manager&operation=mark_all_read'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update the UI
            document.querySelectorAll('.badge.bg-primary.rounded-pill').forEach(badge => {
                badge.style.display = 'none';
            });
            document.querySelectorAll('.dropdown-item-text .badge.bg-primary').forEach(badge => {
                badge.remove();
            });
            location.reload(); // Refresh to update notification count
        } else {
            alert('Failed to mark notifications as read: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error marking notifications as read');
    });
}

// Auto-refresh notification count every 30 seconds
setInterval(function() {
    fetch('includes/notification-manager.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=notification_manager&operation=get_unread_count'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const badge = document.querySelector('.position-absolute.badge.rounded-pill');
            if (badge) {
                if (data.count > 0) {
                    badge.textContent = data.count > 99 ? '99+' : data.count;
                    badge.style.display = 'inline-block';
                    badge.classList.add('notification-badge');
                } else {
                    badge.style.display = 'none';
                    badge.classList.remove('notification-badge');
                }
            }
        }
    })
    .catch(error => {
        console.error('Error fetching notification count:', error);
    });
}, 30000);

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

<?php include_once 'templates/footer.php'; ?> 