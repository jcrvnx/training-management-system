<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect(url('auth/login.php'));
}

// Get database connection
$conn = connectDB(false);

if (!$conn) {
    redirect(url('install_schema.php'));
}

$currentUser = getCurrentUser();
$userRole = getCurrentUserRole();

// Role-based query for trainings
if (isAdmin()) {
    $query = "
        SELECT t.*,
               (SELECT COUNT(*) FROM enrollments WHERE training_id = t.id) as enrollment_count,
               'Uncategorized' as category_name,
               'Not assigned' as instructor_name
        FROM trainings t 
        ORDER BY t.created_at DESC
    ";
} elseif (isTrainer()) {
    $userId = $currentUser['id'];
    $query = "
        SELECT t.*,
               (SELECT COUNT(*) FROM enrollments WHERE training_id = t.id) as enrollment_count,
               'Uncategorized' as category_name,
               'Not assigned' as instructor_name
        FROM trainings t 
        WHERE t.id IN (SELECT DISTINCT training_id FROM enrollments WHERE training_id = t.id)
        ORDER BY t.created_at DESC
    ";
} else {
    // Regular users see trainings they're enrolled in
    $userId = $currentUser['id'];
    $query = "
        SELECT t.*, e.enrolled_at, 
               COALESCE(e.status, 'enrolled') as enrollment_status,
               'Uncategorized' as category_name,
               'Not assigned' as instructor_name
        FROM trainings t 
        INNER JOIN enrollments e ON t.id = e.training_id
        WHERE e.user_id = {$userId}
        ORDER BY e.enrolled_at DESC
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
                <h1><i class="fas fa-book text-primary me-2"></i>Trainings Details</h1>
                <p class="text-muted">Comprehensive view of all trainings</p>
            </div>
            <div>
                <a href="<?php echo url('dashboard.php'); ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                </a>
                <?php if (hasMinimumRole('trainer')): ?>
                <a href="<?php echo url('trainings/create.php'); ?>" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i>Add Training
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Row -->
<div class="row mb-4">
    <?php 
    $totalTrainings = 0;
    $activeTrainings = 0;
    $upcomingTrainings = 0;
    $completedTrainings = 0;
    
    if ($result->num_rows > 0) {
        $result->data_seek(0); // Reset result pointer
        while ($training = $result->fetch_assoc()) {
            $totalTrainings++;
            $currentDate = date('Y-m-d');
            
            if ($training['start_date'] > $currentDate) {
                $upcomingTrainings++;
            } elseif ($training['end_date'] >= $currentDate) {
                $activeTrainings++;
            } else {
                $completedTrainings++;
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
                        <div class="text-white-75">Total Trainings</div>
                        <div class="h2 mb-0"><?php echo $totalTrainings; ?></div>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-book fa-2x"></i>
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
                        <div class="h2 mb-0"><?php echo $activeTrainings; ?></div>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-play fa-2x"></i>
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
                        <div class="text-white-75">Upcoming</div>
                        <div class="h2 mb-0"><?php echo $upcomingTrainings; ?></div>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-clock fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card bg-secondary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="text-white-75">Completed</div>
                        <div class="h2 mb-0"><?php echo $completedTrainings; ?></div>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-check fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Trainings Table -->
<div class="card shadow">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-list me-2"></i>All Trainings</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover" id="trainingsTable">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Instructor</th>
                        <th>Duration</th>
                        <th>Status</th>
                        <?php if (isAdmin()): ?>
                        <th>Enrolled</th>
                        <?php elseif (isUser()): ?>
                        <th>My Status</th>
                        <?php endif; ?>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($training = $result->fetch_assoc()): ?>
                            <?php
                            $currentDate = date('Y-m-d');
                            $status = 'completed';
                            $statusClass = 'secondary';
                            $statusIcon = 'check';
                            
                            if ($training['start_date'] > $currentDate) {
                                $status = 'upcoming';
                                $statusClass = 'warning';
                                $statusIcon = 'clock';
                            } elseif ($training['end_date'] >= $currentDate) {
                                $status = 'active';
                                $statusClass = 'success';
                                $statusIcon = 'play';
                            }
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($training['title']); ?></strong>
                                    <?php if ($training['description']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars(substr($training['description'], 0, 100)) . (strlen($training['description']) > 100 ? '...' : ''); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($training['category_name']): ?>
                                        <span class="badge bg-light text-dark"><?php echo htmlspecialchars($training['category_name']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">No category</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo $training['instructor_name'] ? htmlspecialchars($training['instructor_name']) : 'Not assigned'; ?>
                                </td>
                                <td>
                                    <small>
                                        <i class="fas fa-calendar-alt me-1"></i>
                                        <?php echo date('M d, Y', strtotime($training['start_date'])); ?><br>
                                        <i class="fas fa-calendar-check me-1"></i>
                                        <?php echo date('M d, Y', strtotime($training['end_date'])); ?>
                                    </small>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $statusClass; ?>">
                                        <i class="fas fa-<?php echo $statusIcon; ?> me-1"></i>
                                        <?php echo ucfirst($status); ?>
                                    </span>
                                </td>
                                <?php if (isAdmin()): ?>
                                <td>
                                    <span class="badge bg-info"><?php echo isset($training['enrollment_count']) ? $training['enrollment_count'] : 0; ?> enrolled</span>
                                </td>
                                <?php elseif (isUser()): ?>
                                <td>
                                    <span class="badge bg-primary"><?php echo ucfirst($training['enrollment_status']); ?></span>
                                </td>
                                <?php endif; ?>
                                <td class="text-end">
                                    <?php if (hasMinimumRole('trainer')): ?>
                                    <a href="<?php echo url('trainings/edit.php?id=' . $training['id']); ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php endif; ?>
                                    <a href="<?php echo url('trainings/view.php?id=' . $training['id']); ?>" class="btn btn-sm btn-outline-info">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted">
                                <i class="fas fa-book fa-3x mb-3"></i>
                                <p>No trainings found</p>
                                <?php if (hasMinimumRole('trainer')): ?>
                                <a href="<?php echo url('trainings/create.php'); ?>" class="btn btn-primary">
                                    <i class="fas fa-plus me-1"></i>Create First Training
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

<script>
// Initialize DataTables if available
if (typeof $ !== 'undefined' && $.fn.DataTable) {
    $(document).ready(function() {
        $('#trainingsTable').DataTable({
            responsive: true,
            pageLength: 25,
            order: [[4, 'asc']], // Sort by status
            columnDefs: [
                { orderable: false, targets: [-1] } // Disable sorting on actions column
            ]
        });
    });
}
</script>

<?php include_once '../templates/footer.php'; ?>