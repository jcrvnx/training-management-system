<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

// Check if user is logged in and has appropriate permissions
if (!isLoggedIn()) {
    redirect(url('auth/login.php'));
}

// Only admin and trainers can view user details
if (!hasMinimumRole('trainer')) {
    redirect(url('auth/unauthorized.php'));
}

// Get database connection
$conn = connectDB(false);

if (!$conn) {
    redirect(url('install_schema.php'));
}

$currentUser = getCurrentUser();
$userRole = getCurrentUserRole();

// Role-based query for users
if (isAdmin()) {
    $query = "
        SELECT u.*, 
               (SELECT COUNT(*) FROM enrollments WHERE user_id = u.id) as enrollment_count,
               (SELECT COUNT(*) FROM certificates WHERE user_id = u.id AND status = 'active') as certificate_count
        FROM users u 
        ORDER BY u.created_at DESC
    ";
} else {
    // Trainers can only see users enrolled in their trainings
    $userId = $currentUser['id'];
    $query = "
        SELECT DISTINCT u.*, 
               (SELECT COUNT(*) FROM enrollments WHERE user_id = u.id) as enrollment_count,
               (SELECT COUNT(*) FROM certificates WHERE user_id = u.id AND status = 'active') as certificate_count
        FROM users u 
        INNER JOIN enrollments e ON u.id = e.user_id
        INNER JOIN trainings t ON e.training_id = t.id
        WHERE t.instructor_id = {$userId}
        ORDER BY u.created_at DESC
    ";
}

$result = $conn->query($query);

if (!$result) {
    die('Database query failed: ' . $conn->error);
}

closeDB($conn);

// Include header
include_once '../templates/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1><i class="fas fa-users text-primary me-2"></i>Users Details</h1>
                <p class="text-muted">Comprehensive view of system users</p>
            </div>
            <div>
                <a href="<?php echo url('dashboard.php'); ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                </a>
                <?php if (isAdmin()): ?>
                <a href="<?php echo url('users/create.php'); ?>" class="btn btn-primary">
                    <i class="fas fa-user-plus me-1"></i>Add User
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Row -->
<div class="row mb-4">
    <?php 
    $totalUsers = 0;
    $adminUsers = 0;
    $trainerUsers = 0;
    $regularUsers = 0;
    $activeUsers = 0;
    
    if ($result->num_rows > 0) {
        $result->data_seek(0); // Reset result pointer
        while ($user = $result->fetch_assoc()) {
            $totalUsers++;
            
            switch ($user['role']) {
                case 'admin':
                    $adminUsers++;
                    break;
                case 'trainer':
                    $trainerUsers++;
                    break;
                default:
                    $regularUsers++;
                    break;
            }
            
            if ($user['is_active']) {
                $activeUsers++;
            }
        }
        $result->data_seek(0); // Reset again for display
    }
    ?>
    <div class="col-md-3 mb-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="text-white-75">Total Users</div>
                        <div class="h2 mb-0"><?php echo $totalUsers; ?></div>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-users fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="text-white-75">Active</div>
                        <div class="h2 mb-0"><?php echo $activeUsers; ?></div>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-user-check fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card bg-warning text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="text-white-75">Trainers</div>
                        <div class="h2 mb-0"><?php echo $trainerUsers; ?></div>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-chalkboard-teacher fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="text-white-75">Admins</div>
                        <div class="h2 mb-0"><?php echo $adminUsers; ?></div>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-user-shield fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Users Table -->
<div class="card shadow">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-list me-2"></i>All Users</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover" id="usersTable">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Department</th>
                        <th>Enrollments</th>
                        <th>Certificates</th>
                        <th>Status</th>
                        <th>Joined</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($user = $result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-circle me-2">
                                            <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <strong><?php echo htmlspecialchars($user['full_name']); ?></strong>
                                            <?php if ($user['position']): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($user['position']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <?php
                                    $roleColor = 'secondary';
                                    $roleIcon = 'user';
                                    switch ($user['role']) {
                                        case 'admin':
                                            $roleColor = 'danger';
                                            $roleIcon = 'user-shield';
                                            break;
                                        case 'trainer':
                                            $roleColor = 'warning';
                                            $roleIcon = 'chalkboard-teacher';
                                            break;
                                        default:
                                            $roleColor = 'primary';
                                            $roleIcon = 'user';
                                            break;
                                    }
                                    ?>
                                    <span class="badge bg-<?php echo $roleColor; ?>">
                                        <i class="fas fa-<?php echo $roleIcon; ?> me-1"></i>
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo $user['department'] ? htmlspecialchars($user['department']) : '<span class="text-muted">Not set</span>'; ?>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo $user['enrollment_count']; ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-success"><?php echo $user['certificate_count']; ?></span>
                                </td>
                                <td>
                                    <?php if ($user['is_active']): ?>
                                        <span class="badge bg-success">
                                            <i class="fas fa-check me-1"></i>Active
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">
                                            <i class="fas fa-times me-1"></i>Inactive
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small><?php echo date('M d, Y', strtotime($user['created_at'])); ?></small>
                                </td>
                                <td class="text-end">
                                    <?php if (isAdmin()): ?>
                                    <a href="<?php echo url('users/edit.php?id=' . $user['id']); ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php endif; ?>
                                    <a href="<?php echo url('users/view.php?id=' . $user['id']); ?>" class="btn btn-sm btn-outline-info">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted">
                                <i class="fas fa-users fa-3x mb-3"></i>
                                <p>No users found</p>
                                <?php if (isAdmin()): ?>
                                <a href="<?php echo url('users/create.php'); ?>" class="btn btn-primary">
                                    <i class="fas fa-user-plus me-1"></i>Create First User
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
.avatar-circle {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 16px;
}
</style>

<script>
// Initialize DataTables if available
if (typeof $ !== 'undefined' && $.fn.DataTable) {
    $(document).ready(function() {
        $('#usersTable').DataTable({
            responsive: true,
            pageLength: 25,
            order: [[7, 'desc']], // Sort by joined date
            columnDefs: [
                { orderable: false, targets: [-1] } // Disable sorting on actions column
            ]
        });
    });
}
</script>

<?php include_once '../templates/footer.php'; ?>