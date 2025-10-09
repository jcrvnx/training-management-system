<?php
/**
 * Certificate Management System for TMS
 * Compatible with PHP 5.6+ and XAMPP 2015
 * Handles certificate generation and management
 */

require_once 'config.php';
require_once 'functions.php';
require_once 'db.php';

class CertificateManager {
    private $conn;
    
    public function __construct() {
        $this->conn = connectDB(false);
        if (!$this->conn) {
            throw new Exception('Database connection failed');
        }
    }
    
    /**
     * Generate certificate for a passed quiz attempt
     */
    public function generateCertificate($quizAttemptId) {
        // Get attempt details
        $attemptStmt = $this->conn->prepare("
            SELECT qa.*, q.title as quiz_title, q.passing_score,
                   t.title as training_title, t.description as training_description,
                   u.full_name, u.email, u.id as user_id
            FROM quiz_attempts qa
            JOIN quizzes q ON qa.quiz_id = q.id
            JOIN trainings t ON q.training_id = t.id
            JOIN users u ON qa.user_id = u.id
            WHERE qa.id = ? AND qa.status = 'submitted' AND qa.passed = 1
        ");
        
        $attemptStmt->bind_param("i", $quizAttemptId);
        $attemptStmt->execute();
        $result = $attemptStmt->get_result();
        
        if ($result->num_rows === 0) {
            $attemptStmt->close();
            return array('success' => false, 'message' => 'Quiz attempt not found or not passed');
        }
        
        $attempt = $result->fetch_assoc();
        if (!$attempt) {
            $attemptStmt->close();
            return array('success' => false, 'message' => 'Error retrieving quiz attempt data');
        }
        $attemptStmt->close();
        
        // Check if certificate already exists
        $existingStmt = $this->conn->prepare("SELECT id FROM certificates WHERE quiz_attempt_id = ?");
        $existingStmt->bind_param("i", $quizAttemptId);
        $existingStmt->execute();
        $existingResult = $existingStmt->get_result();
        
        if ($existingResult->num_rows > 0) {
            $existing = $existingResult->fetch_assoc();
            if ($existing) {
                $existingStmt->close();
                return array('success' => true, 'message' => 'Certificate already exists', 'certificate_id' => $existing['id']);
            }
        }
        $existingStmt->close();
        
        // Generate unique certificate number
        $certificateNumber = $this->generateCertificateNumber();
        
        // Create certificate record
        $insertStmt = $this->conn->prepare("
            INSERT INTO certificates (
                user_id, training_id, quiz_id, quiz_attempt_id, certificate_number,
                certificate_name, training_title, completion_date, score, 
                issued_date, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, 'active')
        ");
        
        $certificateName = "Certificate of Completion - " . $attempt['training_title'];
        $completionDate = $attempt['submitted_at'];
        $score = $attempt['percentage_score'];
        
        $insertStmt->bind_param("iiiiisssd", 
            $attempt['user_id'],
            $attempt['training_id'], 
            $attempt['quiz_id'],
            $quizAttemptId,
            $certificateNumber,
            $certificateName,
            $attempt['training_title'],
            $completionDate,
            $score
        );
        
        if ($insertStmt->execute()) {
            $certificateId = $this->conn->insert_id;
            $insertStmt->close();
            
            // Log activity
            logActivity('CREATE', 'certificates', $certificateId, null, array(
                'user_id' => $attempt['user_id'],
                'training_title' => $attempt['training_title'],
                'certificate_number' => $certificateNumber
            ));
            
            return array(
                'success' => true, 
                'message' => 'Certificate generated successfully',
                'certificate_id' => $certificateId,
                'certificate_number' => $certificateNumber
            );
        } else {
            $insertStmt->close();
            return array('success' => false, 'message' => 'Failed to generate certificate');
        }
    }
    
    /**
     * Get certificate information
     */
    public function getCertificate($certificateId) {
        $stmt = $this->conn->prepare("
            SELECT c.*, u.full_name, u.email, t.title as training_title, 
                   t.description as training_description, q.title as quiz_title
            FROM certificates c
            JOIN users u ON c.user_id = u.id
            JOIN trainings t ON c.training_id = t.id
            JOIN quizzes q ON c.quiz_id = q.id
            WHERE c.id = ?
        ");
        
        $stmt->bind_param("i", $certificateId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $stmt->close();
            return null;
        }
        
        $certificate = $result->fetch_assoc();
        $stmt->close();
        return $certificate ? $certificate : null;
    }
    
    /**
     * Get certificate by certificate number
     */
    public function getCertificateByNumber($certificateNumber) {
        $stmt = $this->conn->prepare("
            SELECT c.*, u.full_name, u.email, t.title as training_title, 
                   t.description as training_description, q.title as quiz_title
            FROM certificates c
            JOIN users u ON c.user_id = u.id
            JOIN trainings t ON c.training_id = t.id
            JOIN quizzes q ON c.quiz_id = q.id
            WHERE c.certificate_number = ?
        ");
        
        $stmt->bind_param("s", $certificateNumber);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $stmt->close();
            return null;
        }
        
        $certificate = $result->fetch_assoc();
        $stmt->close();
        return $certificate ? $certificate : null;
    }
    
    /**
     * Get user's certificates
     */
    public function getUserCertificates($userId) {
        $stmt = $this->conn->prepare("
            SELECT c.*, t.title as training_title, q.title as quiz_title
            FROM certificates c
            JOIN trainings t ON c.training_id = t.id
            JOIN quizzes q ON c.quiz_id = q.id
            WHERE c.user_id = ? AND c.status = 'active'
            ORDER BY c.issued_date DESC
        ");
        
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $certificates = array();
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $certificates[] = $row;
            }
        }
        
        $stmt->close();
        return $certificates;
    }
    
    /**
     * Verify certificate authenticity
     */
    public function verifyCertificate($certificateNumber) {
        $certificate = $this->getCertificateByNumber($certificateNumber);
        
        if (!$certificate) {
            return array('valid' => false, 'message' => 'Certificate not found');
        }
        
        if ($certificate['status'] !== 'active') {
            return array('valid' => false, 'message' => 'Certificate is not active');
        }
        
        return array(
            'valid' => true,
            'certificate' => $certificate,
            'message' => 'Certificate is valid'
        );
    }
    
    /**
     * Generate HTML certificate
     */
    public function generateHTMLCertificate($certificateId) {
        $certificate = $this->getCertificate($certificateId);
        
        if (!$certificate) {
            return null;
        }
        
        $html = $this->getCertificateTemplate($certificate);
        return $html;
    }
    
    /**
     * Get certificate template HTML
     */
    private function getCertificateTemplate($certificate) {
        $completionDate = date('F j, Y', strtotime($certificate['completion_date']));
        $issuedDate = date('F j, Y', strtotime($certificate['issued_date']));
        
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Certificate of Completion</title>
            <style>
                @page {
                    size: A4 landscape;
                    margin: 0;
                }
                
                body {
                    font-family: "Times New Roman", serif;
                    margin: 0;
                    padding: 40px;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                
                .certificate {
                    background: white;
                    width: 800px;
                    min-height: 560px;
                    padding: 60px;
                    border: 15px solid #2c3e50;
                    border-radius: 20px;
                    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                    text-align: center;
                    position: relative;
                }
                
                .certificate::before {
                    content: "";
                    position: absolute;
                    top: 20px;
                    left: 20px;
                    right: 20px;
                    bottom: 20px;
                    border: 3px solid #3498db;
                    border-radius: 10px;
                }
                
                .header {
                    color: #2c3e50;
                    margin-bottom: 30px;
                }
                
                .title {
                    font-size: 36px;
                    font-weight: bold;
                    color: #e74c3c;
                    text-transform: uppercase;
                    letter-spacing: 3px;
                    margin-bottom: 10px;
                }
                
                .subtitle {
                    font-size: 24px;
                    color: #34495e;
                    margin-bottom: 40px;
                    font-style: italic;
                }
                
                .recipient {
                    font-size: 28px;
                    color: #2c3e50;
                    margin: 30px 0;
                    text-decoration: underline;
                    text-decoration-color: #3498db;
                    text-underline-offset: 8px;
                }
                
                .training-title {
                    font-size: 32px;
                    color: #8e44ad;
                    font-weight: bold;
                    margin: 30px 0;
                    font-style: italic;
                }
                
                .details {
                    margin: 40px 0;
                    color: #34495e;
                }
                
                .score {
                    font-size: 20px;
                    color: #27ae60;
                    font-weight: bold;
                    margin: 20px 0;
                }
                
                .signatures {
                    display: flex;
                    justify-content: space-between;
                    margin-top: 60px;
                    padding-top: 20px;
                }
                
                .signature-line {
                    width: 200px;
                    text-align: center;
                    border-top: 2px solid #34495e;
                    padding-top: 10px;
                    color: #34495e;
                }
                
                .certificate-number {
                    position: absolute;
                    bottom: 20px;
                    right: 30px;
                    font-size: 12px;
                    color: #7f8c8d;
                }
                
                .verification-url {
                    position: absolute;
                    bottom: 20px;
                    left: 30px;
                    font-size: 12px;
                    color: #7f8c8d;
                }
                
                .seal {
                    position: absolute;
                    top: 60px;
                    right: 80px;
                    width: 100px;
                    height: 100px;
                    border: 5px solid #e74c3c;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    background: #f8f9fa;
                    font-size: 14px;
                    font-weight: bold;
                    color: #e74c3c;
                    text-align: center;
                    line-height: 1.2;
                }
            </style>
        </head>
        <body>
            <div class="certificate">
                <div class="seal">
                    OFFICIAL<br>SEAL
                </div>
                
                <div class="header">
                    <div class="title">Certificate of Completion</div>
                    <div class="subtitle">Training Management System</div>
                </div>
                
                <p style="font-size: 18px; color: #34495e; margin: 20px 0;">
                    This is to certify that
                </p>
                
                <div class="recipient">
                    ' . htmlspecialchars($certificate['full_name']) . '
                </div>
                
                <p style="font-size: 18px; color: #34495e; margin: 20px 0;">
                    has successfully completed the training course
                </p>
                
                <div class="training-title">
                    "' . htmlspecialchars($certificate['training_title']) . '"
                </div>
                
                <div class="details">
                    <p style="font-size: 16px; margin: 10px 0;">
                        Completed on <strong>' . $completionDate . '</strong>
                    </p>
                    
                    <div class="score">
                        Final Score: ' . number_format($certificate['score'], 1) . '%
                    </div>
                </div>
                
                <p style="font-size: 16px; color: #34495e; margin: 30px 0;">
                    This certificate demonstrates the holder\'s commitment to professional development
                    and successful completion of all required coursework and assessments.
                </p>
                
                <div class="signatures">
                    <div class="signature-line">
                        <div>Training Administrator</div>
                    </div>
                    <div class="signature-line">
                        <div>Date: ' . $issuedDate . '</div>
                    </div>
                </div>
                
                <div class="certificate-number">
                    Certificate #: ' . htmlspecialchars($certificate['certificate_number']) . '
                </div>
                
                <div class="verification-url">
                    Verify at: ' . BASE_URL . 'certificates/verify.php
                </div>
            </div>
        </body>
        </html>';
        
        return $html;
    }
    
    /**
     * Generate unique certificate number
     */
    private function generateCertificateNumber() {
        $year = date('Y');
        $month = date('m');
        
        // Get next sequence number for this month
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as count 
            FROM certificates 
            WHERE certificate_number LIKE ?
        ");
        
        $pattern = "TMS-{$year}-{$month}-%";
        $stmt->bind_param("s", $pattern);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        $sequence = str_pad($row['count'] + 1, 4, '0', STR_PAD_LEFT);
        return "TMS-{$year}-{$month}-{$sequence}";
    }
    
    /**
     * Auto-generate certificate after successful quiz completion
     */
    public function autoGenerateCertificate($quizAttemptId) {
        // This will be called automatically when a quiz attempt is submitted and passed
        return $this->generateCertificate($quizAttemptId);
    }
    
    public function __destruct() {
        if ($this->conn) {
            closeDB($this->conn);
        }
    }
}

// Auto-generate certificate when quiz is completed successfully
// This function should be called from the quiz submission process
function autoGenerateCertificateForAttempt($quizAttemptId) {
    try {
        $manager = new CertificateManager();
        return $manager->autoGenerateCertificate($quizAttemptId);
    } catch (Exception $e) {
        error_log('Certificate generation failed: ' . $e->getMessage());
        return array('success' => false, 'message' => 'Certificate generation failed');
    }
}

// Handle AJAX requests
if (isset($_POST['action']) && $_POST['action'] === 'certificate_manager') {
    header('Content-Type: application/json');
    
    if (!isLoggedIn()) {
        echo json_encode(array('success' => false, 'message' => 'Authentication required'));
        exit();
    }
    
    $manager = new CertificateManager();
    $response = array('success' => false, 'message' => 'Invalid request');
    
    switch ($_POST['operation']) {
        case 'generate':
            if (!hasPermission('certificates.create')) {
                $response = array('success' => false, 'message' => 'Permission denied');
                break;
            }
            
            $attemptId = (int)$_POST['attempt_id'];
            $response = $manager->generateCertificate($attemptId);
            break;
            
        case 'verify':
            $certificateNumber = sanitize($_POST['certificate_number']);
            $response = $manager->verifyCertificate($certificateNumber);
            break;
            
        case 'get_user_certificates':
            $userId = getCurrentUser()['id'];
            $certificates = $manager->getUserCertificates($userId);
            $response = array('success' => true, 'certificates' => $certificates);
            break;
    }
    
    echo json_encode($response);
    exit();
}
?>
