<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/certificate-manager.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('auth/login.php');
}

$conn = connectDB();
$errors = array();

// Get certificate ID from URL
$certificateId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get certificate manager and certificate info
$manager = new CertificateManager();
$certificate = $manager->getCertificate($certificateId);

if (!$certificate) {
    redirect('dashboard.php');
}

$userId = getCurrentUser()['id'];

// Security check: only allow user to view their own certificate or allow instructors/admins
$canView = false;
if ($certificate['user_id'] == $userId) {
    $canView = true;
} elseif (isAdmin()) {
    $canView = true;
} elseif (isTrainer()) {
    // Check if trainer is instructor for this training
    $instructorStmt = $conn->prepare("SELECT id FROM trainings WHERE id = ? AND instructor_id = ?");
    $instructorStmt->bind_param("ii", $certificate['training_id'], $userId);
    $instructorStmt->execute();
    $instructorResult = $instructorStmt->get_result();
    $canView = $instructorResult->num_rows > 0;
    $instructorStmt->close();
}

if (!$canView) {
    redirect('auth/unauthorized.php');
}

// Handle PDF download
if (isset($_GET['download']) && $_GET['download'] === 'pdf') {
    // For now, we'll use the HTML version and suggest browser print-to-PDF
    // In a full implementation, you could use libraries like TCPDF or DOMPDF
    $html = $manager->generateHTMLCertificate($certificateId);
    
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: inline; filename="certificate-' . $certificate['certificate_number'] . '.html"');
    echo $html;
    exit();
}

include '../templates/header.php';
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <!-- Certificate Header -->
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="mb-0">
                                <i class="fas fa-certificate me-2"></i>Certificate of Completion
                            </h3>
                            <p class="mb-0">Certificate #: <?php echo htmlspecialchars($certificate['certificate_number']); ?></p>
                        </div>
                        <div>
                            <a href="<?php echo BASE_URL; ?>certificates/view.php?id=<?php echo $certificateId; ?>&download=pdf" 
                               class="btn btn-light" target="_blank">
                                <i class="fas fa-download me-1"></i>Download
                            </a>
                            <a href="<?php echo BASE_URL; ?>certificates/verify.php?number=<?php echo urlencode($certificate['certificate_number']); ?>" 
                               class="btn btn-outline-light" target="_blank">
                                <i class="fas fa-shield-alt me-1"></i>Verify
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Recipient:</strong> <?php echo htmlspecialchars($certificate['full_name']); ?></p>
                            <p><strong>Training:</strong> <?php echo htmlspecialchars($certificate['training_title']); ?></p>
                            <p><strong>Quiz:</strong> <?php echo htmlspecialchars($certificate['quiz_title']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Completion Date:</strong> <?php echo date('F j, Y', strtotime($certificate['completion_date'])); ?></p>
                            <p><strong>Issued Date:</strong> <?php echo date('F j, Y', strtotime($certificate['issued_date'])); ?></p>
                            <p><strong>Final Score:</strong> <span class="badge bg-success"><?php echo number_format($certificate['score'], 1); ?>%</span></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Certificate Preview -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Certificate Preview</h5>
                </div>
                <div class="card-body p-0">
                    <div class="certificate-preview" style="background: #f8f9fa; min-height: 600px;">
                        <?php echo $manager->generateHTMLCertificate($certificateId); ?>
                    </div>
                </div>
                <div class="card-footer text-center">
                    <a href="<?php echo BASE_URL; ?>certificates/view.php?id=<?php echo $certificateId; ?>&download=pdf" 
                       class="btn btn-success me-2" target="_blank">
                        <i class="fas fa-download me-1"></i>Download Certificate
                    </a>
                    <a href="<?php echo BASE_URL; ?>certificates/verify.php?number=<?php echo urlencode($certificate['certificate_number']); ?>" 
                       class="btn btn-info me-2" target="_blank">
                        <i class="fas fa-shield-alt me-1"></i>Verify Authenticity
                    </a>
                    <button type="button" class="btn btn-secondary" onclick="window.print()">
                        <i class="fas fa-print me-1"></i>Print Certificate
                    </button>
                </div>
            </div>
            
            <!-- Actions -->
            <div class="text-center mt-4">
                <a href="<?php echo BASE_URL; ?>dashboard.php" class="btn btn-secondary me-2">
                    <i class="fas fa-home me-1"></i>Back to Dashboard
                </a>
                
                <?php if ($certificate['user_id'] == $userId): ?>
                    <a href="<?php echo BASE_URL; ?>certificates/my-certificates.php" class="btn btn-primary">
                        <i class="fas fa-certificate me-1"></i>View All My Certificates
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    .container, .card, .card-header, .card-footer, .btn, .navbar, header, footer {
        display: none !important;
    }
    
    .certificate-preview {
        display: block !important;
        background: white !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    
    body {
        margin: 0 !important;
        padding: 0 !important;
    }
}

.certificate-preview iframe,
.certificate-preview .certificate {
    border: none;
    width: 100%;
    min-height: 600px;
}
</style>

<script>
// Auto-resize certificate iframe if needed
function resizeCertificate() {
    const preview = document.querySelector('.certificate-preview');
    if (preview) {
        const iframe = preview.querySelector('iframe');
        if (iframe) {
            iframe.style.height = '600px';
        }
    }
}

document.addEventListener('DOMContentLoaded', resizeCertificate);
</script>

<?php 
closeDB($conn);
include '../templates/footer.php'; 
?>
