<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect(url('auth/login.php'));
}

// Check if installation is complete
if (!file_exists('install_complete.flag')) {
    redirect(url('install.php'));
}

// Get database connection
$conn = connectDB(false);

// If connection failed, redirect to installation
if (!$conn || !($conn instanceof mysqli)) {
    redirect(url('install.php'));
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
    redirect(url('install.php'));
}

// Get counts and recent data
try {
    $trainingCount = $conn->query("SELECT COUNT(*) as count FROM trainings")->fetch_assoc()['count'];
    $userCount = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];

    // Get recent enrollments with JOIN
    $recentEnrollments = $conn->query("
        SELECT e.id, u.full_name, t.title, e.enrolled_at
        FROM enrollments e
        JOIN users u ON e.user_id = u.id
        JOIN trainings t ON e.training_id = t.id
        ORDER BY e.enrolled_at DESC
        LIMIT 5
    ");
} catch (Exception $e) {
    // Database error, redirect to installation
    closeDB($conn);
    redirect(url('install.php'));
}

closeDB($conn);

// Include header
include_once 'templates/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex align-items-center">
            <i class="bi bi-speedometer2 text-primary me-2" style="font-size: 2rem;"></i>
            <h1 class="mb-0">Dashboard</h1>
        </div>
        <p class="lead mt-2">Welcome, <?php echo $_SESSION['user']['name']; ?>!</p>
    </div>
</div>

<div class="row mb-4">
    <div class="col-sm-12 col-md-6 col-lg-4 mb-4">
        <div class="card h-100 shadow-sm border-primary">
            <div class="card-body text-center">
                <div class="display-4 text-primary mb-3">
                    <i class="bi bi-journals"></i>
                </div>
                <h5 class="card-title">Trainings</h5>
                <p class="display-4"><?php echo $trainingCount; ?></p>
                <a href="<?php echo url('trainings/'); ?>" class="btn btn-primary">
                    <i class="bi bi-arrow-right-circle"></i> Manage Trainings
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-sm-12 col-md-6 col-lg-4 mb-4">
        <div class="card h-100 shadow-sm border-success">
            <div class="card-body text-center">
                <div class="display-4 text-success mb-3">
                    <i class="bi bi-people"></i>
                </div>
                <h5 class="card-title">Users</h5>
                <p class="display-4"><?php echo $userCount; ?></p>
                <a href="<?php echo url('users/'); ?>" class="btn btn-success">
                    <i class="bi bi-arrow-right-circle"></i> Manage Users
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-sm-12 col-lg-4 mb-4">
        <div class="card h-100 shadow-sm border-info">
            <div class="card-body text-center">
                <div class="display-4 text-info mb-3">
                    <i class="bi bi-person-check"></i>
                </div>
                <h5 class="card-title">Enrollments</h5>
                <p class="display-4"><?php echo $recentEnrollments->num_rows; ?>+</p>
                <a href="<?php echo url('enrollments/'); ?>" class="btn btn-info text-white">
                    <i class="bi bi-arrow-right-circle"></i> Manage Enrollments
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="bi bi-clock-history me-1"></i> Recent Enrollments
                </h5>
                <a href="<?php echo url('enrollments/create.php'); ?>" class="btn btn-sm btn-primary">
                    <i class="bi bi-plus-circle"></i> New Enrollment
                </a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>User</th>
                                <th>Training</th>
                                <th>Enrolled At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recentEnrollments->num_rows > 0): ?>
                                <?php while ($enrollment = $recentEnrollments->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <i class="bi bi-person text-primary me-1"></i>
                                            <?php echo $enrollment['full_name']; ?>
                                        </td>
                                        <td>
                                            <i class="bi bi-journal-text text-success me-1"></i>
                                            <?php echo $enrollment['title']; ?>
                                        </td>
                                        <td>
                                            <i class="bi bi-calendar-date text-muted me-1"></i>
                                            <?php echo date('F j, Y', strtotime($enrollment['enrolled_at'])); ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="text-center">No enrollments found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once 'templates/footer.php'; ?> 