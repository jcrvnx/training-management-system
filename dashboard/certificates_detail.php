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

// Role-based query for certificates
if (isAdmin()) {
    $query = "
        SELECT c.*, u.full_name, t.title as training_title, u.email
        FROM certificates c 
        LEFT JOIN users u ON c.user_id = u.id
        LEFT JOIN trainings t ON c.training_id = t.id
        ORDER BY c.issued_date DESC
    ";
} elseif (isTrainer()) {
    $userId = $currentUser['id'];
    $query = "
        SELECT c.*, u.full_name, t.title as training_title, u.email
        FROM certificates c 
        LEFT JOIN users u ON c.user_id = u.id
        LEFT JOIN trainings t ON c.training_id = t.id
        WHERE t.instructor_id = {$userId}
        ORDER BY c.issued_date DESC
    ";
} else {
    // Regular users see only their certificates
    $userId = $currentUser['id'];
    $query = "
        SELECT c.*, u.full_name, t.title as training_title, u.email
        FROM certificates c 
        LEFT JOIN users u ON c.user_id = u.id
        LEFT JOIN trainings t ON c.training_id = t.id
        WHERE c.user_id = {$userId}
        ORDER BY c.issued_date DESC
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
                <h1><i class="fas fa-certificate text-primary me-2"></i>Certificates Details</h1>
                <p class="text-muted">Comprehensive view of issued certificates</p>
            </div>
            <div>
                <a href="<?php echo url('dashboard.php'); ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                </a>
                <?php if (hasMinimumRole('trainer')): ?>
                <a href="<?php echo url('certificates/generate.php'); ?>" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i>Issue Certificate
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Row -->
<div class="row mb-4">
    <?php 
    $totalCertificates = 0;
    $activeCertificates = 0;
    $revokedCertificates = 0;
    $expiredCertificates = 0;
    
    if ($result->num_rows > 0) {
        $result->data_seek(0); // Reset result pointer
        while ($cert = $result->fetch_assoc()) {
            $totalCertificates++;
            
            switch ($cert['status']) {
                case 'active':
                    $activeCertificates++;
                    break;
                case 'revoked':
                    $revokedCertificates++;
                    break;
                case 'expired':
                    $expiredCertificates++;
                    break;
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
                        <div class="text-white-75">Total Certificates</div>
                        <div class="h2 mb-0"><?php echo $totalCertificates; ?></div>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-certificate fa-2x"></i>
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
                        <div class="h2 mb-0"><?php echo $activeCertificates; ?></div>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-check-circle fa-2x"></i>
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
                        <div class="text-white-75">Expired</div>
                        <div class="h2 mb-0"><?php echo $expiredCertificates; ?></div>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-clock fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card bg-danger text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="text-white-75">Revoked</div>
                        <div class="h2 mb-0"><?php echo $revokedCertificates; ?></div>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-ban fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Certificates Table -->
<div class="card shadow">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-list me-2"></i>All Certificates</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover" id="certificatesTable">
                <thead>
                    <tr>
                        <th>Certificate #</th>
                        <th>Recipient</th>
                        <th>Training</th>
                        <th>Issue Date</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($cert = $result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <code><?php echo htmlspecialchars($cert['certificate_number']); ?></code>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-circle me-2">
                                            <?php echo strtoupper(substr($cert['full_name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <strong><?php echo htmlspecialchars($cert['full_name']); ?></strong>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($cert['email']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($cert['training_title']); ?></strong>
                                </td>
                                <td>
                                    <small>
                                        <i class="fas fa-calendar me-1"></i>
                                        <?php echo date('M d, Y', strtotime($cert['issued_date'])); ?>
                                        <br><small class="text-muted"><?php echo date('H:i', strtotime($cert['issued_date'])); ?></small>
                                    </small>
                                </td>
                                <td>
                                    <?php
                                    $statusColor = 'secondary';
                                    $statusIcon = 'question';
                                    switch ($cert['status']) {
                                        case 'active':
                                            $statusColor = 'success';
                                            $statusIcon = 'check-circle';
                                            break;
                                        case 'revoked':
                                            $statusColor = 'danger';
                                            $statusIcon = 'ban';
                                            break;
                                        case 'expired':
                                            $statusColor = 'warning';
                                            $statusIcon = 'clock';
                                            break;
                                    }
                                    ?>
                                    <span class="badge bg-<?php echo $statusColor; ?>">
                                        <i class="fas fa-<?php echo $statusIcon; ?> me-1"></i>
                                        <?php echo ucfirst($cert['status']); ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="<?php echo url('certificates/view.php?id=' . $cert['id']); ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="<?php echo url('certificates/download.php?id=' . $cert['id']); ?>" class="btn btn-sm btn-outline-success">
                                        <i class="fas fa-download"></i>
                                    </a>
                                    <?php if (hasMinimumRole('trainer') && $cert['status'] === 'active'): ?>
                                    <a href="<?php echo url('certificates/email.php?id=' . $cert['id']); ?>" class="btn btn-sm btn-outline-info">
                                        <i class="fas fa-envelope"></i>
                                    </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted">
                                <i class="fas fa-certificate fa-3x mb-3"></i>
                                <p>No certificates found</p>
                                <?php if (isUser()): ?>
                                    <p class="text-muted">Complete trainings and pass quizzes to earn certificates!</p>
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

<style>
.avatar-circle {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
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
        $('#certificatesTable').DataTable({
            responsive: true,
            pageLength: 25,
            order: [[3, 'desc']], // Sort by issue date
            columnDefs: [
                { orderable: false, targets: [-1] } // Disable sorting on actions column
            ]
        });
    });
}
</script>

<?php include_once '../templates/footer.php'; ?>