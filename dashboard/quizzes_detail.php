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

// Role-based query for quizzes
if (isAdmin()) {
    $query = "
        SELECT q.*,
               COALESCE(t.title, 'Unknown Training') as training_title, 
               COALESCE(u.full_name, 'Unknown Creator') as creator_name,
               (SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id = q.id) as attempt_count,
               0 as pass_count
        FROM quizzes q 
        LEFT JOIN trainings t ON q.training_id = t.id
        LEFT JOIN users u ON q.created_by = u.id
        ORDER BY q.created_at DESC
    ";
} elseif (isTrainer()) {
    $userId = $currentUser['id'];
    $query = "
        SELECT q.*,
               COALESCE(t.title, 'Unknown Training') as training_title, 
               COALESCE(u.full_name, 'Unknown Creator') as creator_name,
               (SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id = q.id) as attempt_count,
               0 as pass_count
        FROM quizzes q 
        LEFT JOIN trainings t ON q.training_id = t.id
        LEFT JOIN users u ON q.created_by = u.id
        WHERE q.created_by = {$userId}
        ORDER BY q.created_at DESC
    ";
} else {
    // Regular users see quizzes from trainings they're enrolled in
    $userId = $currentUser['id'];
    $query = "
        SELECT q.*,
               COALESCE(t.title, 'Unknown Training') as training_title, 
               COALESCE(u.full_name, 'Unknown Creator') as creator_name,
               COALESCE(qa.status, 'not_attempted') as my_attempt_status, 
               COALESCE(qa.percentage_score, 0) as percentage_score,
               0 as passed
        FROM quizzes q 
        LEFT JOIN trainings t ON q.training_id = t.id
        LEFT JOIN users u ON q.created_by = u.id
        INNER JOIN enrollments e ON t.id = e.training_id
        LEFT JOIN quiz_attempts qa ON q.id = qa.quiz_id AND qa.user_id = {$userId}
        WHERE e.user_id = {$userId}
        ORDER BY q.created_at DESC
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
                <h1><i class="fas fa-question-circle text-primary me-2"></i>Quizzes Details</h1>
                <p class="text-muted">Comprehensive view of system quizzes</p>
            </div>
            <div>
                <a href="<?php echo url('dashboard.php'); ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                </a>
                <?php if (hasMinimumRole('trainer')): ?>
                <a href="<?php echo url('quizzes/create.php'); ?>" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i>Create Quiz
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Row -->
<div class="row mb-4">
    <?php 
    $totalQuizzes = 0;
    $activeQuizzes = 0;
    $inactiveQuizzes = 0;
    $totalAttempts = 0;
    $totalPasses = 0;
    
    if ($result->num_rows > 0) {
        $result->data_seek(0); // Reset result pointer
        while ($quiz = $result->fetch_assoc()) {
            $totalQuizzes++;
            
            if ($quiz['is_active']) {
                $activeQuizzes++;
            } else {
                $inactiveQuizzes++;
            }
            
            if (isset($quiz['attempt_count'])) {
                $totalAttempts += $quiz['attempt_count'];
            }
            
            if (isset($quiz['pass_count'])) {
                $totalPasses += $quiz['pass_count'];
            }
        }
        $result->data_seek(0); // Reset again for display
    }
    
    $passRate = $totalAttempts > 0 ? round(($totalPasses / $totalAttempts) * 100, 1) : 0;
    ?>
    <div class="col-md-3 mb-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="text-white-75">Total Quizzes</div>
                        <div class="h2 mb-0"><?php echo $totalQuizzes; ?></div>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-question-circle fa-2x"></i>
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
                        <div class="h2 mb-0"><?php echo $activeQuizzes; ?></div>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-play fa-2x"></i>
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
                        <div class="text-white-75">Attempts</div>
                        <div class="h2 mb-0"><?php echo $totalAttempts; ?></div>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-user-edit fa-2x"></i>
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
                        <div class="text-white-75">Pass Rate</div>
                        <div class="h2 mb-0"><?php echo $passRate; ?>%</div>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-chart-line fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quizzes Table -->
<div class="card shadow">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-list me-2"></i>All Quizzes</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover" id="quizzesTable">
                <thead>
                    <tr>
                        <th>Quiz Title</th>
                        <th>Training</th>
                        <th>Created By</th>
                        <th>Passing Score</th>
                        <?php if (hasMinimumRole('trainer')): ?>
                        <th>Attempts</th>
                        <th>Pass Rate</th>
                        <?php else: ?>
                        <th>My Score</th>
                        <th>Status</th>
                        <?php endif; ?>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($quiz = $result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($quiz['title']); ?></strong>
                                    <?php if ($quiz['description']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars(substr($quiz['description'], 0, 80)) . (strlen($quiz['description']) > 80 ? '...' : ''); ?></small>
                                    <?php endif; ?>
                                    <?php if ($quiz['time_limit_minutes']): ?>
                                        <br><small class="badge bg-secondary"><i class="fas fa-clock me-1"></i><?php echo $quiz['time_limit_minutes']; ?> min</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($quiz['training_title']); ?></strong>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($quiz['creator_name']); ?>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo number_format($quiz['passing_score'], 0); ?>%</span>
                                </td>
                                <?php if (hasMinimumRole('trainer')): ?>
                                <td>
                                    <span class="badge bg-secondary"><?php echo $quiz['attempt_count']; ?></span>
                                </td>
                                <td>
                                    <?php 
                                    $quizPassRate = $quiz['attempt_count'] > 0 ? round(($quiz['pass_count'] / $quiz['attempt_count']) * 100, 1) : 0;
                                    $passRateColor = $quizPassRate >= 70 ? 'success' : ($quizPassRate >= 50 ? 'warning' : 'danger');
                                    ?>
                                    <span class="badge bg-<?php echo $passRateColor; ?>"><?php echo $quizPassRate; ?>%</span>
                                </td>
                                <?php else: ?>
                                <td>
                                    <?php if (isset($quiz['percentage_score']) && $quiz['percentage_score'] !== null): ?>
                                        <span class="badge bg-<?php echo $quiz['passed'] ? 'success' : 'danger'; ?>">
                                            <?php echo number_format($quiz['percentage_score'], 1); ?>%
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">Not attempted</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (isset($quiz['my_attempt_status'])): ?>
                                        <?php
                                        $statusColor = 'secondary';
                                        $statusIcon = 'question';
                                        switch ($quiz['my_attempt_status']) {
                                            case 'in_progress':
                                                $statusColor = 'warning';
                                                $statusIcon = 'play';
                                                break;
                                            case 'submitted':
                                                $statusColor = $quiz['passed'] ? 'success' : 'danger';
                                                $statusIcon = $quiz['passed'] ? 'check' : 'times';
                                                break;
                                        }
                                        ?>
                                        <span class="badge bg-<?php echo $statusColor; ?>">
                                            <i class="fas fa-<?php echo $statusIcon; ?> me-1"></i>
                                            <?php echo ucfirst(str_replace('_', ' ', $quiz['my_attempt_status'])); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-dark">Available</span>
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                                <td>
                                    <?php if ($quiz['is_active']): ?>
                                        <span class="badge bg-success">
                                            <i class="fas fa-check me-1"></i>Active
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">
                                            <i class="fas fa-pause me-1"></i>Inactive
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php if (hasMinimumRole('trainer')): ?>
                                    <a href="<?php echo url('quizzes/edit.php?id=' . $quiz['id']); ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="<?php echo url('quizzes/results.php?id=' . $quiz['id']); ?>" class="btn btn-sm btn-outline-info">
                                        <i class="fas fa-chart-bar"></i>
                                    </a>
                                    <?php else: ?>
                                    <?php if (!isset($quiz['my_attempt_status']) || $quiz['my_attempt_status'] === null): ?>
                                    <a href="<?php echo url('quizzes/take.php?id=' . $quiz['id']); ?>" class="btn btn-sm btn-success">
                                        <i class="fas fa-play me-1"></i>Take Quiz
                                    </a>
                                    <?php elseif ($quiz['my_attempt_status'] === 'in_progress'): ?>
                                    <a href="<?php echo url('quizzes/continue.php?id=' . $quiz['id']); ?>" class="btn btn-sm btn-warning">
                                        <i class="fas fa-play me-1"></i>Continue
                                    </a>
                                    <?php else: ?>
                                    <a href="<?php echo url('quizzes/view_result.php?quiz_id=' . $quiz['id']); ?>" class="btn btn-sm btn-outline-info">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted">
                                <i class="fas fa-question-circle fa-3x mb-3"></i>
                                <p>No quizzes found</p>
                                <?php if (hasMinimumRole('trainer')): ?>
                                    <a href="<?php echo url('quizzes/create.php'); ?>" class="btn btn-primary">
                                        <i class="fas fa-plus me-1"></i>Create First Quiz
                                    </a>
                                <?php elseif (isUser()): ?>
                                    <p class="text-muted">Enroll in trainings to access quizzes!</p>
                                    <a href="<?php echo url('trainings/'); ?>" class="btn btn-primary">
                                        <i class="fas fa-book me-1"></i>Browse Trainings
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
        $('#quizzesTable').DataTable({
            responsive: true,
            pageLength: 25,
            order: [[0, 'asc']], // Sort by quiz title
            columnDefs: [
                { orderable: false, targets: [-1] } // Disable sorting on actions column
            ]
        });
    });
}
</script>

<?php include_once '../templates/footer.php'; ?>