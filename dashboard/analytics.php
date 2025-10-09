<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

// Check if user is logged in and has appropriate permissions
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
$userRole = getCurrentUserRole();

// Get analytics data based on user role
if (isAdmin()) {
    // Admin sees system-wide analytics
    $trainingFilter = "";
    $userFilter = "";
} else {
    // Trainer sees only their trainings
    $userId = $currentUser['id'];
    $trainingFilter = "WHERE t.instructor_id = $userId";
    $userFilter = "AND t.instructor_id = $userId";
}

// Training completion rates
$completionQuery = "
    SELECT 
        t.title as training_title,
        COUNT(DISTINCT e.user_id) as total_enrolled,
        COUNT(DISTINCT CASE WHEN e.status = 'completed' THEN e.user_id END) as completed,
        COUNT(DISTINCT CASE WHEN c.id IS NOT NULL THEN c.user_id END) as certified
    FROM trainings t
    LEFT JOIN enrollments e ON t.id = e.training_id
    LEFT JOIN certificates c ON t.id = c.training_id AND c.status = 'active'
    $trainingFilter
    GROUP BY t.id, t.title
    HAVING total_enrolled > 0
    ORDER BY total_enrolled DESC
    LIMIT 10
";

$completionResult = $conn->query($completionQuery);
$completionData = array();
if ($completionResult) {
    while ($row = $completionResult->fetch_assoc()) {
        $completionData[] = $row;
    }
}

// Monthly enrollment trends
$enrollmentTrendQuery = "
    SELECT 
        DATE_FORMAT(e.enrolled_at, '%Y-%m') as month,
        COUNT(*) as enrollments
    FROM enrollments e
    JOIN trainings t ON e.training_id = t.id
    WHERE e.enrolled_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH) $userFilter
    GROUP BY DATE_FORMAT(e.enrolled_at, '%Y-%m')
    ORDER BY month ASC
";

$enrollmentTrendResult = $conn->query($enrollmentTrendQuery);
$enrollmentTrendData = array();
if ($enrollmentTrendResult) {
    while ($row = $enrollmentTrendResult->fetch_assoc()) {
        $enrollmentTrendData[] = $row;
    }
}

// Quiz performance statistics
$quizPerformanceQuery = "
    SELECT 
        q.title as quiz_title,
        AVG(qa.percentage_score) as avg_score,
        COUNT(*) as total_attempts,
        COUNT(CASE WHEN qa.passed = 1 THEN 1 END) as passed_attempts,
        MIN(qa.percentage_score) as min_score,
        MAX(qa.percentage_score) as max_score
    FROM quiz_attempts qa
    JOIN quizzes q ON qa.quiz_id = q.id
    JOIN trainings t ON q.training_id = t.id
    WHERE qa.status = 'submitted' $userFilter
    GROUP BY q.id, q.title
    HAVING total_attempts >= 5
    ORDER BY avg_score DESC
    LIMIT 10
";

$quizPerformanceResult = $conn->query($quizPerformanceQuery);
$quizPerformanceData = array();
if ($quizPerformanceResult) {
    while ($row = $quizPerformanceResult->fetch_assoc()) {
        $row['pass_rate'] = $row['total_attempts'] > 0 ? ($row['passed_attempts'] / $row['total_attempts']) * 100 : 0;
        $quizPerformanceData[] = $row;
    }
}

// User engagement data
$engagementQuery = "
    SELECT 
        u.role,
        COUNT(DISTINCT u.id) as user_count,
        COUNT(DISTINCT e.id) as total_enrollments,
        COUNT(DISTINCT qa.id) as quiz_attempts,
        COUNT(DISTINCT c.id) as certificates_earned
    FROM users u
    LEFT JOIN enrollments e ON u.id = e.user_id
    LEFT JOIN quiz_attempts qa ON u.id = qa.user_id AND qa.status = 'submitted'
    LEFT JOIN certificates c ON u.id = c.user_id AND c.status = 'active'
    " . (isAdmin() ? "" : "JOIN enrollments e2 ON u.id = e2.user_id JOIN trainings t2 ON e2.training_id = t2.id WHERE t2.instructor_id = $userId") . "
    GROUP BY u.role
    ORDER BY user_count DESC
";

$engagementResult = $conn->query($engagementQuery);
$engagementData = array();
if ($engagementResult) {
    while ($row = $engagementResult->fetch_assoc()) {
        $engagementData[] = $row;
    }
}

// Top performing users
$topUsersQuery = "
    SELECT 
        u.full_name,
        u.role,
        COUNT(DISTINCT e.training_id) as trainings_enrolled,
        COUNT(DISTINCT qa.quiz_id) as quizzes_taken,
        AVG(qa.percentage_score) as avg_score,
        COUNT(DISTINCT c.id) as certificates_earned
    FROM users u
    LEFT JOIN enrollments e ON u.id = e.user_id
    LEFT JOIN quiz_attempts qa ON u.id = qa.user_id AND qa.status = 'submitted'
    LEFT JOIN certificates c ON u.id = c.user_id AND c.status = 'active'
    " . (isAdmin() ? "" : "JOIN enrollments e2 ON u.id = e2.user_id JOIN trainings t2 ON e2.training_id = t2.id WHERE t2.instructor_id = $userId AND") . "
    " . (isAdmin() ? "WHERE" : "AND") . " u.role IN ('user', 'trainer')
    GROUP BY u.id, u.full_name, u.role
    HAVING trainings_enrolled > 0
    ORDER BY certificates_earned DESC, avg_score DESC
    LIMIT 10
";

$topUsersResult = $conn->query($topUsersQuery);
$topUsersData = array();
if ($topUsersResult) {
    while ($row = $topUsersResult->fetch_assoc()) {
        $topUsersData[] = $row;
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
                <h1><i class="fas fa-chart-line text-primary me-2"></i>Training Analytics</h1>
                <p class="text-muted"><?php echo isAdmin() ? 'System-wide' : 'Your training'; ?> performance insights</p>
            </div>
            <div>
                <a href="<?php echo url('dashboard.php'); ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Training Completion Rates Chart -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Training Completion Rates</h5>
            </div>
            <div class="card-body">
                <canvas id="completionRatesChart" height="100"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Enrollment Trends and Quiz Performance -->
<div class="row mb-4">
    <div class="col-lg-6 mb-4">
        <div class="card shadow h-100">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Monthly Enrollment Trends</h5>
            </div>
            <div class="card-body">
                <canvas id="enrollmentTrendChart"></canvas>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6 mb-4">
        <div class="card shadow h-100">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-percentage me-2"></i>Quiz Pass Rates</h5>
            </div>
            <div class="card-body">
                <canvas id="quizPerformanceChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- User Engagement and Top Performers -->
<div class="row mb-4">
    <div class="col-lg-4 mb-4">
        <div class="card shadow h-100">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-users me-2"></i>User Engagement by Role</h5>
            </div>
            <div class="card-body">
                <canvas id="engagementChart"></canvas>
            </div>
        </div>
    </div>
    
    <div class="col-lg-8 mb-4">
        <div class="card shadow h-100">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-trophy me-2"></i>Top Performing Users</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Role</th>
                                <th>Trainings</th>
                                <th>Avg Score</th>
                                <th>Certificates</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topUsersData as $user): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($user['full_name']); ?></strong>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $user['role'] === 'trainer' ? 'warning' : 'primary'; ?>">
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </td>
                                <td><?php echo $user['trainings_enrolled']; ?></td>
                                <td>
                                    <?php if ($user['avg_score']): ?>
                                        <span class="badge bg-<?php echo $user['avg_score'] >= 80 ? 'success' : ($user['avg_score'] >= 60 ? 'warning' : 'danger'); ?>">
                                            <?php echo number_format($user['avg_score'], 1); ?>%
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-success"><?php echo $user['certificates_earned']; ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($topUsersData)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted">No user data available</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Summary Cards -->
<div class="row mb-4">
    <?php
    $totalEnrollments = array_sum(array_column($completionData, 'total_enrolled'));
    $totalCompletions = array_sum(array_column($completionData, 'completed'));
    $totalCertificates = array_sum(array_column($completionData, 'certified'));
    $avgCompletionRate = $totalEnrollments > 0 ? ($totalCompletions / $totalEnrollments) * 100 : 0;
    ?>
    
    <div class="col-md-3 mb-3">
        <div class="card bg-primary text-white">
            <div class="card-body text-center">
                <i class="fas fa-user-plus fa-2x mb-2"></i>
                <div class="h3 mb-0"><?php echo $totalEnrollments; ?></div>
                <div class="small">Total Enrollments</div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card bg-success text-white">
            <div class="card-body text-center">
                <i class="fas fa-check-circle fa-2x mb-2"></i>
                <div class="h3 mb-0"><?php echo $totalCompletions; ?></div>
                <div class="small">Completions</div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card bg-warning text-white">
            <div class="card-body text-center">
                <i class="fas fa-certificate fa-2x mb-2"></i>
                <div class="h3 mb-0"><?php echo $totalCertificates; ?></div>
                <div class="small">Certificates</div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card bg-info text-white">
            <div class="card-body text-center">
                <i class="fas fa-percentage fa-2x mb-2"></i>
                <div class="h3 mb-0"><?php echo number_format($avgCompletionRate, 1); ?>%</div>
                <div class="small">Completion Rate</div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js Integration -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Training Completion Rates Chart
const completionCtx = document.getElementById('completionRatesChart').getContext('2d');
const completionChart = new Chart(completionCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($completionData, 'training_title')); ?>,
        datasets: [{
            label: 'Enrolled',
            data: <?php echo json_encode(array_column($completionData, 'total_enrolled')); ?>,
            backgroundColor: 'rgba(54, 162, 235, 0.8)',
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: 1
        }, {
            label: 'Completed',
            data: <?php echo json_encode(array_column($completionData, 'completed')); ?>,
            backgroundColor: 'rgba(75, 192, 192, 0.8)',
            borderColor: 'rgba(75, 192, 192, 1)',
            borderWidth: 1
        }, {
            label: 'Certified',
            data: <?php echo json_encode(array_column($completionData, 'certified')); ?>,
            backgroundColor: 'rgba(255, 206, 86, 0.8)',
            borderColor: 'rgba(255, 206, 86, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true
            }
        },
        plugins: {
            legend: {
                position: 'top',
            },
            title: {
                display: true,
                text: 'Training Completion Overview'
            }
        }
    }
});

// Enrollment Trends Chart
const enrollmentCtx = document.getElementById('enrollmentTrendChart').getContext('2d');
const enrollmentChart = new Chart(enrollmentCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_column($enrollmentTrendData, 'month')); ?>,
        datasets: [{
            label: 'Monthly Enrollments',
            data: <?php echo json_encode(array_column($enrollmentTrendData, 'enrollments')); ?>,
            backgroundColor: 'rgba(75, 192, 192, 0.2)',
            borderColor: 'rgba(75, 192, 192, 1)',
            borderWidth: 2,
            fill: true
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true
            }
        },
        plugins: {
            legend: {
                display: false
            }
        }
    }
});

// Quiz Performance Chart
const quizCtx = document.getElementById('quizPerformanceChart').getContext('2d');
const quizChart = new Chart(quizCtx, {
    type: 'horizontalBar',
    data: {
        labels: <?php echo json_encode(array_column($quizPerformanceData, 'quiz_title')); ?>,
        datasets: [{
            label: 'Pass Rate (%)',
            data: <?php echo json_encode(array_map(function($item) { return round($item['pass_rate'], 1); }, $quizPerformanceData)); ?>,
            backgroundColor: function(context) {
                const value = context.parsed.x;
                if (value >= 80) return 'rgba(75, 192, 192, 0.8)';
                if (value >= 60) return 'rgba(255, 206, 86, 0.8)';
                return 'rgba(255, 99, 132, 0.8)';
            },
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        indexAxis: 'y',
        scales: {
            x: {
                beginAtZero: true,
                max: 100
            }
        },
        plugins: {
            legend: {
                display: false
            }
        }
    }
});

// User Engagement Chart
const engagementCtx = document.getElementById('engagementChart').getContext('2d');
const engagementChart = new Chart(engagementCtx, {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode(array_map('ucfirst', array_column($engagementData, 'role'))); ?>,
        datasets: [{
            data: <?php echo json_encode(array_column($engagementData, 'user_count')); ?>,
            backgroundColor: [
                'rgba(255, 99, 132, 0.8)',
                'rgba(54, 162, 235, 0.8)',
                'rgba(255, 206, 86, 0.8)',
                'rgba(75, 192, 192, 0.8)'
            ],
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});
</script>

<?php include_once '../templates/footer.php'; ?>