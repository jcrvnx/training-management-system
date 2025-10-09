<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

// Check if user is logged in and has admin permissions
if (!isLoggedIn()) {
    redirect(url('auth/login.php'));
}

if (!hasMinimumRole('trainer')) {
    redirect(url('auth/unauthorized.php'));
}

// Get database connection
$conn = connectDB(false);

if (!$conn) {
    redirect(url('install_schema.php'));
}

$currentUser = getCurrentUser();
$isAdmin = isAdmin();

// Initialize report data
$reportData = [
    'users' => [
        'total' => 0,
        'active' => 0,
        'trainers' => 0,
        'admins' => 0
    ],
    'trainings' => [
        'total' => 0,
        'active' => 0,
        'completed' => 0
    ],
    'enrollments' => [
        'total' => 0,
        'active' => 0,
        'completed' => 0
    ],
    'quizzes' => [
        'total' => 0,
        'attempts' => 0,
        'avg_score' => 0
    ],
    'certificates' => [
        'issued' => 0,
        'active' => 0
    ]
];

// Get user statistics
$userQuery = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN role = 'trainer' THEN 1 ELSE 0 END) as trainers,
                SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admins
              FROM users";
$userResult = $conn->query($userQuery);
if ($userResult) {
    $userData = $userResult->fetch_assoc();
    $reportData['users'] = $userData;
}

// Get training statistics
$trainingQuery = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN end_date < CURDATE() THEN 1 ELSE 0 END) as completed
                  FROM trainings";
$trainingResult = $conn->query($trainingQuery);
if ($trainingResult) {
    $trainingData = $trainingResult->fetch_assoc();
    $reportData['trainings']['total'] = $trainingData['total'];
    $reportData['trainings']['active'] = $trainingData['active'] ?: 0;
    $reportData['trainings']['completed'] = $trainingData['completed'] ?: 0;
}

// Get enrollment statistics
$enrollmentQuery = "SELECT 
                      COUNT(*) as total,
                      SUM(CASE WHEN status IN ('enrolled', 'in_progress') THEN 1 ELSE 0 END) as active,
                      SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
                    FROM enrollments";
$enrollmentResult = $conn->query($enrollmentQuery);
if ($enrollmentResult) {
    $enrollmentData = $enrollmentResult->fetch_assoc();
    $reportData['enrollments']['total'] = $enrollmentData['total'];
    $reportData['enrollments']['active'] = $enrollmentData['active'] ?: 0;
    $reportData['enrollments']['completed'] = $enrollmentData['completed'] ?: 0;
}

// Get quiz statistics (if table exists)
if ($conn->query("SHOW TABLES LIKE 'quizzes'")->num_rows > 0) {
    $quizQuery = "SELECT COUNT(*) as total FROM quizzes WHERE is_active = 1";
    $quizResult = $conn->query($quizQuery);
    if ($quizResult) {
        $reportData['quizzes']['total'] = $quizResult->fetch_assoc()['total'];
    }
    
    if ($conn->query("SHOW TABLES LIKE 'quiz_attempts'")->num_rows > 0) {
        $attemptQuery = "SELECT 
                          COUNT(*) as attempts,
                          AVG(percentage_score) as avg_score
                        FROM quiz_attempts 
                        WHERE status = 'submitted'";
        $attemptResult = $conn->query($attemptQuery);
        if ($attemptResult) {
            $attemptData = $attemptResult->fetch_assoc();
            $reportData['quizzes']['attempts'] = $attemptData['attempts'] ?: 0;
            $reportData['quizzes']['avg_score'] = $attemptData['avg_score'] ? round($attemptData['avg_score'], 1) : 0;
        }
    }
}

// Get certificate statistics (if table exists)
if ($conn->query("SHOW TABLES LIKE 'certificates'")->num_rows > 0) {
    $certQuery = "SELECT 
                    COUNT(*) as issued,
                    SUM(CASE WHEN is_valid = 1 THEN 1 ELSE 0 END) as active
                  FROM certificates";
    $certResult = $conn->query($certQuery);
    if ($certResult) {
        $certData = $certResult->fetch_assoc();
        $reportData['certificates']['issued'] = $certData['issued'] ?: 0;
        $reportData['certificates']['active'] = $certData['active'] ?: 0;
    }
}

// Get recent activity data
$recentActivities = [];
$activityQuery = "SELECT 
                    'enrollment' as type,
                    u.full_name as user_name,
                    t.title as item_name,
                    e.enrolled_at as activity_date
                  FROM enrollments e
                  JOIN users u ON e.user_id = u.id
                  JOIN trainings t ON e.training_id = t.id
                  ORDER BY e.enrolled_at DESC
                  LIMIT 20";
$activityResult = $conn->query($activityQuery);
if ($activityResult) {
    while ($activity = $activityResult->fetch_assoc()) {
        $recentActivities[] = $activity;
    }
}

// Get top performing users (by completed enrollments)
$topUsers = [];
$topUsersQuery = "SELECT 
                    u.full_name,
                    COUNT(e.id) as completed_trainings
                  FROM users u
                  LEFT JOIN enrollments e ON u.id = e.user_id AND e.status = 'completed'
                  WHERE u.role != 'admin'
                  GROUP BY u.id, u.full_name
                  HAVING completed_trainings > 0
                  ORDER BY completed_trainings DESC
                  LIMIT 10";
$topUsersResult = $conn->query($topUsersQuery);
if ($topUsersResult) {
    while ($user = $topUsersResult->fetch_assoc()) {
        $topUsers[] = $user;
    }
}

// Get popular trainings
$popularTrainings = [];
$popularQuery = "SELECT 
                   t.title,
                   COUNT(e.id) as enrollment_count
                 FROM trainings t
                 LEFT JOIN enrollments e ON t.id = e.training_id
                 GROUP BY t.id, t.title
                 HAVING enrollment_count > 0
                 ORDER BY enrollment_count DESC
                 LIMIT 10";
$popularResult = $conn->query($popularQuery);
if ($popularResult) {
    while ($training = $popularResult->fetch_assoc()) {
        $popularTrainings[] = $training;
    }
}

closeDB($conn);

// Include header
include_once '../templates/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1><i class="fas fa-chart-line text-primary me-2"></i>Reports & Analytics</h1>
                <p class="text-muted mb-0">System performance and usage statistics</p>
            </div>
            <div>
                <a href="<?php echo url('dashboard.php'); ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                </a>
                <?php if ($isAdmin): ?>
                <button class="btn btn-success" onclick="exportReports()">
                    <i class="fas fa-download me-1"></i>Export Reports
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Overview -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white shadow">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-white-75">Total Users</div>
                        <div class="h2 mb-0"><?php echo number_format($reportData['users']['total']); ?></div>
                        <small class="text-white-50"><?php echo $reportData['users']['active']; ?> active</small>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-users fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white shadow">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-white-75">Trainings</div>
                        <div class="h2 mb-0"><?php echo number_format($reportData['trainings']['total']); ?></div>
                        <small class="text-white-50"><?php echo $reportData['trainings']['active']; ?> active</small>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-book fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-white shadow">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-white-75">Enrollments</div>
                        <div class="h2 mb-0"><?php echo number_format($reportData['enrollments']['total']); ?></div>
                        <small class="text-white-50"><?php echo $reportData['enrollments']['completed']; ?> completed</small>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-user-graduate fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white shadow">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-white-75">Certificates</div>
                        <div class="h2 mb-0"><?php echo number_format($reportData['certificates']['issued']); ?></div>
                        <small class="text-white-50"><?php echo $reportData['certificates']['active']; ?> active</small>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-certificate fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Charts Section -->
    <div class="col-md-8">
        <!-- User Roles Breakdown -->
        <div class="card shadow mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-pie-chart me-2"></i>User Roles Distribution</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-4">
                        <div class="stat-circle bg-primary text-white">
                            <div class="stat-number"><?php echo $reportData['users']['admins']; ?></div>
                            <div class="stat-label">Admins</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-circle bg-warning text-white">
                            <div class="stat-number"><?php echo $reportData['users']['trainers']; ?></div>
                            <div class="stat-label">Trainers</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-circle bg-success text-white">
                            <div class="stat-number"><?php echo $reportData['users']['total'] - $reportData['users']['admins'] - $reportData['users']['trainers']; ?></div>
                            <div class="stat-label">Users</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Training Progress -->
        <div class="card shadow mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Training Progress Overview</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Enrollment Status</h6>
                        <div class="progress mb-2" style="height: 25px;">
                            <?php 
                            $totalEnrollments = $reportData['enrollments']['total'];
                            $completedPct = $totalEnrollments > 0 ? ($reportData['enrollments']['completed'] / $totalEnrollments) * 100 : 0;
                            $activePct = $totalEnrollments > 0 ? ($reportData['enrollments']['active'] / $totalEnrollments) * 100 : 0;
                            ?>
                            <div class="progress-bar bg-success" style="width: <?php echo $completedPct; ?>%">
                                Completed (<?php echo $reportData['enrollments']['completed']; ?>)
                            </div>
                            <div class="progress-bar bg-warning" style="width: <?php echo $activePct; ?>%">
                                Active (<?php echo $reportData['enrollments']['active']; ?>)
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6>Quiz Performance</h6>
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <div class="progress" style="height: 25px;">
                                    <div class="progress-bar bg-info" style="width: <?php echo $reportData['quizzes']['avg_score']; ?>%">
                                        <?php echo $reportData['quizzes']['avg_score']; ?>%
                                    </div>
                                </div>
                            </div>
                            <span class="ms-2">Avg Score</span>
                        </div>
                        <small class="text-muted"><?php echo number_format($reportData['quizzes']['attempts']); ?> total attempts</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Side Information -->
    <div class="col-md-4">
        <!-- Top Performers -->
        <?php if (!empty($topUsers)): ?>
        <div class="card shadow mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-trophy me-2"></i>Top Performers</h6>
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    <?php foreach (array_slice($topUsers, 0, 5) as $index => $user): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <div>
                            <span class="badge bg-warning me-2"><?php echo $index + 1; ?></span>
                            <?php echo htmlspecialchars($user['full_name']); ?>
                        </div>
                        <span class="badge bg-success"><?php echo $user['completed_trainings']; ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Popular Trainings -->
        <?php if (!empty($popularTrainings)): ?>
        <div class="card shadow">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-star me-2"></i>Popular Trainings</h6>
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    <?php foreach (array_slice($popularTrainings, 0, 5) as $training): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <div class="flex-grow-1">
                            <div class="fw-bold"><?php echo htmlspecialchars($training['title']); ?></div>
                        </div>
                        <span class="badge bg-primary"><?php echo $training['enrollment_count']; ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Recent Activities -->
<?php if (!empty($recentActivities)): ?>
<div class="row mt-4">
    <div class="col-12">
        <div class="card shadow">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Activities</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Activity</th>
                                <th>Item</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($recentActivities, 0, 15) as $activity): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($activity['user_name']); ?></td>
                                <td>
                                    <span class="badge bg-info">
                                        <i class="fas fa-<?php 
                                        switch($activity['type']) {
                                            case 'enrollment': echo 'user-plus'; break;
                                            case 'completion': echo 'check'; break;
                                            case 'quiz': echo 'question-circle'; break;
                                            default: echo 'info';
                                        } 
                                        ?> me-1"></i>
                                        <?php echo ucfirst($activity['type']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($activity['item_name']); ?></td>
                                <td>
                                    <small><?php echo date('M d, Y g:i A', strtotime($activity['activity_date'])); ?></small>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
.stat-circle {
    padding: 30px;
    border-radius: 50%;
    width: 120px;
    height: 120px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    margin: 0 auto;
}

.stat-circle .stat-number {
    font-size: 2rem;
    font-weight: bold;
    line-height: 1;
}

.stat-circle .stat-label {
    font-size: 0.875rem;
    margin-top: 5px;
}
</style>

<script>
function exportReports() {
    // Simple CSV export functionality
    const data = [
        ['Metric', 'Value'],
        ['Total Users', '<?php echo $reportData['users']['total']; ?>'],
        ['Active Users', '<?php echo $reportData['users']['active']; ?>'],
        ['Total Trainings', '<?php echo $reportData['trainings']['total']; ?>'],
        ['Active Trainings', '<?php echo $reportData['trainings']['active']; ?>'],
        ['Total Enrollments', '<?php echo $reportData['enrollments']['total']; ?>'],
        ['Completed Enrollments', '<?php echo $reportData['enrollments']['completed']; ?>'],
        ['Certificates Issued', '<?php echo $reportData['certificates']['issued']; ?>'],
        ['Quiz Attempts', '<?php echo $reportData['quizzes']['attempts']; ?>'],
        ['Average Quiz Score', '<?php echo $reportData['quizzes']['avg_score']; ?>%']
    ];
    
    let csvContent = "data:text/csv;charset=utf-8,";
    data.forEach(row => {
        csvContent += row.join(",") + "\r\n";
    });
    
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", "training_system_report_" + new Date().toISOString().slice(0,10) + ".csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<?php include_once '../templates/footer.php'; ?>