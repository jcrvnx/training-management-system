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
$userId = $currentUser['id'];

// Get user's certificates
$query = "
    SELECT c.*, t.title as training_title, t.description as training_description
    FROM certificates c 
    LEFT JOIN trainings t ON c.training_id = t.id
    WHERE c.user_id = ?
    ORDER BY c.issued_date DESC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

$stmt->close();
closeDB($conn);

// Include header
include_once '../templates/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1><i class="fas fa-certificate text-warning me-2"></i>My Certificates</h1>
                <p class="text-muted">Your earned training certificates</p>
            </div>
            <div>
                <a href="<?php echo url('dashboard.php'); ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Row -->
<div class="row mb-4">
    <?php 
    $totalCerts = 0;
    $activeCerts = 0;
    $expiredCerts = 0;
    $revokedCerts = 0;
    
    if ($result->num_rows > 0) {
        $result->data_seek(0);
        while ($cert = $result->fetch_assoc()) {
            $totalCerts++;
            switch ($cert['status']) {
                case 'active':
                    $activeCerts++;
                    break;
                case 'expired':
                    $expiredCerts++;
                    break;
                case 'revoked':
                    $revokedCerts++;
                    break;
            }
        }
        $result->data_seek(0);
    }
    ?>
    <div class="col-md-3 mb-3">
        <div class="card bg-primary text-white">
            <div class="card-body text-center">
                <i class="fas fa-certificate fa-2x mb-2"></i>
                <div class="h3 mb-0"><?php echo $totalCerts; ?></div>
                <div class="small">Total Certificates</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card bg-success text-white">
            <div class="card-body text-center">
                <i class="fas fa-check-circle fa-2x mb-2"></i>
                <div class="h3 mb-0"><?php echo $activeCerts; ?></div>
                <div class="small">Active</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card bg-warning text-white">
            <div class="card-body text-center">
                <i class="fas fa-clock fa-2x mb-2"></i>
                <div class="h3 mb-0"><?php echo $expiredCerts; ?></div>
                <div class="small">Expired</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card bg-danger text-white">
            <div class="card-body text-center">
                <i class="fas fa-ban fa-2x mb-2"></i>
                <div class="h3 mb-0"><?php echo $revokedCerts; ?></div>
                <div class="small">Revoked</div>
            </div>
        </div>
    </div>
</div>

<!-- Certificates Grid -->
<?php if ($result && $result->num_rows > 0): ?>
    <div class="row">
        <?php while ($cert = $result->fetch_assoc()): ?>
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
            <div class="col-lg-6 col-xl-4 mb-4">
                <div class="card h-100 certificate-card">
                    <div class="card-header bg-gradient-primary text-white text-center">
                        <i class="fas fa-certificate fa-2x mb-2"></i>
                        <h5 class="mb-0">Certificate of Completion</h5>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-3">
                            <h6 class="text-primary"><?php echo htmlspecialchars($cert['training_title']); ?></h6>
                            <?php if ($cert['training_description']): ?>
                                <p class="text-muted small"><?php echo htmlspecialchars(substr($cert['training_description'], 0, 100)) . (strlen($cert['training_description']) > 100 ? '...' : ''); ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="certificate-details">
                            <div class="row text-center">
                                <div class="col-6">
                                    <strong>Certificate #</strong>
                                    <br><code class="small"><?php echo htmlspecialchars($cert['certificate_number']); ?></code>
                                </div>
                                <div class="col-6">
                                    <strong>Issue Date</strong>
                                    <br><span class="small"><?php echo date('M d, Y', strtotime($cert['issued_date'])); ?></span>
                                </div>
                            </div>
                            
                            <div class="text-center mt-3">
                                <span class="badge bg-<?php echo $statusColor; ?> px-3 py-2">
                                    <i class="fas fa-<?php echo $statusIcon; ?> me-1"></i>
                                    <?php echo ucfirst($cert['status']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-footer bg-light text-center">
                        <?php if ($cert['status'] === 'active'): ?>
                        <a href="<?php echo url('certificates/view.php?id=' . $cert['id']); ?>" class="btn btn-primary btn-sm">
                            <i class="fas fa-eye me-1"></i>View
                        </a>
                        <a href="<?php echo url('certificates/view.php?id=' . $cert['id'] . '&download=pdf'); ?>" class="btn btn-outline-success btn-sm" target="_blank">
                            <i class="fas fa-download me-1"></i>Download
                        </a>
                        <button class="btn btn-outline-info btn-sm" onclick="shareCertificate('<?php echo htmlspecialchars($cert['certificate_number']); ?>')">
                            <i class="fas fa-share me-1"></i>Share
                        </button>
                        <?php else: ?>
                        <a href="<?php echo url('certificates/view.php?id=' . $cert['id']); ?>" class="btn btn-secondary btn-sm">
                            <i class="fas fa-eye me-1"></i>View
                        </a>
                        <button class="btn btn-secondary btn-sm" disabled>
                            <i class="fas fa-download me-1"></i>Download
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
<?php else: ?>
    <!-- No Certificates State -->
    <div class="card shadow">
        <div class="card-body text-center py-5">
            <i class="fas fa-certificate fa-4x text-muted mb-4"></i>
            <h3 class="text-muted mb-3">No Certificates Yet</h3>
            <p class="lead text-muted mb-4">
                Complete trainings and pass their quizzes to earn certificates!
            </p>
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="d-grid gap-2">
                        <a href="<?php echo url('trainings/'); ?>" class="btn btn-primary btn-lg">
                            <i class="fas fa-book me-2"></i>Browse Available Trainings
                        </a>
                        <a href="<?php echo url('dashboard.php'); ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<style>
.certificate-card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    border: none;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.certificate-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 15px rgba(0, 0, 0, 0.2);
}

.bg-gradient-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.certificate-details {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 0.5rem;
    margin: 1rem 0;
}

@media print {
    .certificate-card {
        break-inside: avoid;
        page-break-inside: avoid;
    }
}
</style>

<script>
function shareCertificate(certificateNumber) {
    if (navigator.share) {
        navigator.share({
            title: 'Training Certificate',
            text: 'I earned a training certificate! Certificate #' + certificateNumber,
            url: window.location.href
        }).then(() => {
            console.log('Certificate shared successfully');
        }).catch((error) => {
            console.log('Error sharing certificate:', error);
            fallbackShare(certificateNumber);
        });
    } else {
        fallbackShare(certificateNumber);
    }
}

function fallbackShare(certificateNumber) {
    const shareText = 'I earned a training certificate! Certificate #' + certificateNumber + ' - ' + window.location.href;
    
    if (navigator.clipboard) {
        navigator.clipboard.writeText(shareText).then(() => {
            alert('Certificate link copied to clipboard!');
        }).catch(() => {
            showShareModal(shareText);
        });
    } else {
        showShareModal(shareText);
    }
}

function showShareModal(shareText) {
    const modal = document.createElement('div');
    modal.innerHTML = `
        <div class="modal fade" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Share Certificate</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Copy this text to share your certificate:</p>
                        <textarea class="form-control" rows="3" readonly>${shareText}</textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    const modalEl = new bootstrap.Modal(modal.querySelector('.modal'));
    modalEl.show();
    
    modal.addEventListener('hidden.bs.modal', () => {
        document.body.removeChild(modal);
    });
}
</script>

<?php include_once '../templates/footer.php'; ?>