<?php
/**
 * Secure File Upload Handler for TMS
 * Compatible with PHP 5.6+ and XAMPP 2015
 * Handles course materials, user avatars, and other file uploads
 */

require_once 'config.php';
require_once 'functions.php';
require_once 'db.php';

class FileUploader {
    private $uploadBasePath;
    private $maxFileSize;
    private $allowedTypes;
    private $allowedExtensions;
    
    public function __construct() {
        $this->uploadBasePath = __DIR__ . '/../uploads/';
        $this->maxFileSize = getSystemSetting('max_file_size', 104857600); // 100MB default
        $this->allowedExtensions = getSystemSetting('allowed_file_types', array(
            'pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx',
            'jpg', 'jpeg', 'png', 'gif', 'svg',
            'mp4', 'avi', 'mov', 'wmv',
            'txt', 'rtf'
        ));
        
        $this->allowedTypes = array(
            // Documents
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain',
            'application/rtf',
            
            // Images
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/gif',
            'image/svg+xml',
            
            // Videos
            'video/mp4',
            'video/avi',
            'video/quicktime',
            'video/x-ms-wmv'
        );
        
        $this->createUploadDirectories();
    }
    
    /**
     * Create necessary upload directories
     */
    private function createUploadDirectories() {
        $directories = array(
            'course_materials',
            'user_avatars',
            'certificates',
            'certificate_templates',
            'temp'
        );
        
        foreach ($directories as $dir) {
            $fullPath = $this->uploadBasePath . $dir;
            if (!is_dir($fullPath)) {
                mkdir($fullPath, 0755, true);
            }
            
            // Create .htaccess for security (except for certificates which need to be accessible)
            if ($dir !== 'certificates') {
                $htaccessPath = $fullPath . '/.htaccess';
                if (!file_exists($htaccessPath)) {
                    file_put_contents($htaccessPath, "deny from all\n");
                }
            }
        }
    }
    
    /**
     * Validate uploaded file
     */
    private function validateFile($file, $allowedExtensions = null, $maxSize = null) {
        $errors = array();
        
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            switch ($file['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $errors[] = 'File is too large.';
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $errors[] = 'File upload was interrupted.';
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $errors[] = 'No file was uploaded.';
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $errors[] = 'Missing temporary upload directory.';
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    $errors[] = 'Failed to write file to disk.';
                    break;
                default:
                    $errors[] = 'Unknown upload error.';
            }
            return $errors;
        }
        
        // Check file size
        $maxSize = $maxSize ?: $this->maxFileSize;
        if ($file['size'] > $maxSize) {
            $errors[] = 'File size exceeds maximum allowed size (' . $this->formatBytes($maxSize) . ').';
        }
        
        // Check file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExt = $allowedExtensions ?: $this->allowedExtensions;
        
        if (!in_array($extension, $allowedExt)) {
            $errors[] = 'File type not allowed. Allowed types: ' . implode(', ', $allowedExt);
        }
        
        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $this->allowedTypes)) {
            $errors[] = 'File MIME type not allowed.';
        }
        
        // Additional security checks
        if ($this->isExecutableFile($file['tmp_name'])) {
            $errors[] = 'Executable files are not allowed.';
        }
        
        return $errors;
    }
    
    /**
     * Check if file is executable
     */
    private function isExecutableFile($filePath) {
        $executableSignatures = array(
            "\x4D\x5A", // PE executable
            "\x7F\x45\x4C\x46", // ELF executable
            "#!", // Shell script
            "<?php", // PHP script
            "<script" // HTML/JS script
        );
        
        $fileContent = file_get_contents($filePath, false, null, 0, 1024);
        
        foreach ($executableSignatures as $signature) {
            if (strpos($fileContent, $signature) === 0 || strpos($fileContent, $signature) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Generate unique filename
     */
    private function generateUniqueFilename($originalName, $directory) {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $basename = pathinfo($originalName, PATHINFO_FILENAME);
        
        // Sanitize basename
        $basename = preg_replace('/[^a-zA-Z0-9_-]/', '', $basename);
        $basename = substr($basename, 0, 50);
        
        $counter = 0;
        do {
            $filename = $basename . ($counter > 0 ? "_$counter" : '') . '.' . $extension;
            $fullPath = $directory . '/' . $filename;
            $counter++;
        } while (file_exists($fullPath) && $counter < 1000);
        
        return $filename;
    }
    
    /**
     * Calculate file hash for integrity checking
     */
    private function calculateFileHash($filePath) {
        return hash_file('sha256', $filePath);
    }
    
    /**
     * Upload file to specified directory
     */
    public function uploadFile($file, $uploadType = 'course_material', $referenceId = null, $allowedExtensions = null, $maxSize = null) {
        if (!isLoggedIn()) {
            return array('success' => false, 'message' => 'Authentication required');
        }
        
        // Validate file
        $errors = $this->validateFile($file, $allowedExtensions, $maxSize);
        if (!empty($errors)) {
            return array('success' => false, 'message' => implode(' ', $errors));
        }
        
        // Determine upload directory
        $uploadDir = $this->uploadBasePath . $uploadType . 's'; // course_materials, user_avatars, etc.
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Generate unique filename
        $filename = $this->generateUniqueFilename($file['name'], $uploadDir);
        $filePath = $uploadDir . '/' . $filename;
        $relativePath = $uploadType . 's/' . $filename;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            return array('success' => false, 'message' => 'Failed to save uploaded file');
        }
        
        // Calculate file hash
        $fileHash = $this->calculateFileHash($filePath);
        
        // Get file info
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filePath);
        finfo_close($finfo);
        
        // Save file record to database
        $conn = connectDB(false);
        if (!$conn) {
            unlink($filePath); // Clean up uploaded file
            return array('success' => false, 'message' => 'Database connection failed');
        }
        
        $stmt = $conn->prepare("
            INSERT INTO file_uploads (original_filename, stored_filename, file_path, file_size, mime_type, file_hash, upload_type, reference_id, uploaded_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        if (!$stmt) {
            unlink($filePath);
            closeDB($conn);
            return array('success' => false, 'message' => 'Failed to prepare database statement');
        }
        
        $userId = getCurrentUser()['id'];
        $stmt->bind_param("sssississi", 
            $file['name'], 
            $filename, 
            $relativePath, 
            $file['size'], 
            $mimeType, 
            $fileHash, 
            $uploadType, 
            $referenceId, 
            $userId
        );
        
        if (!$stmt->execute()) {
            unlink($filePath);
            $stmt->close();
            closeDB($conn);
            return array('success' => false, 'message' => 'Failed to save file record');
        }
        
        $fileId = $conn->insert_id;
        $stmt->close();
        closeDB($conn);
        
        // Log activity
        logActivity('CREATE', 'file_uploads', $fileId, null, array(
            'filename' => $file['name'],
            'type' => $uploadType,
            'size' => $file['size']
        ));
        
        return array(
            'success' => true,
            'message' => 'File uploaded successfully',
            'file_id' => $fileId,
            'filename' => $filename,
            'original_name' => $file['name'],
            'file_path' => $relativePath,
            'file_size' => $file['size'],
            'mime_type' => $mimeType
        );
    }
    
    /**
     * Delete uploaded file
     */
    public function deleteFile($fileId) {
        if (!isLoggedIn()) {
            return array('success' => false, 'message' => 'Authentication required');
        }
        
        $conn = connectDB(false);
        if (!$conn) {
            return array('success' => false, 'message' => 'Database connection failed');
        }
        
        // Get file info
        $stmt = $conn->prepare("SELECT file_path, original_filename, uploaded_by FROM file_uploads WHERE id = ? AND is_active = 1");
        $stmt->bind_param("i", $fileId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $stmt->close();
            closeDB($conn);
            return array('success' => false, 'message' => 'File not found');
        }
        
        $fileData = $result->fetch_assoc();
        $stmt->close();
        
        // Check permissions
        $currentUser = getCurrentUser();
        if ($fileData['uploaded_by'] != $currentUser['id'] && !isAdmin()) {
            closeDB($conn);
            return array('success' => false, 'message' => 'Permission denied');
        }
        
        // Mark file as inactive
        $stmt = $conn->prepare("UPDATE file_uploads SET is_active = 0 WHERE id = ?");
        $stmt->bind_param("i", $fileId);
        
        if (!$stmt->execute()) {
            $stmt->close();
            closeDB($conn);
            return array('success' => false, 'message' => 'Failed to delete file record');
        }
        
        $stmt->close();
        closeDB($conn);
        
        // Delete physical file
        $fullPath = $this->uploadBasePath . $fileData['file_path'];
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
        
        // Log activity
        logActivity('DELETE', 'file_uploads', $fileId, $fileData, null);
        
        return array('success' => true, 'message' => 'File deleted successfully');
    }
    
    /**
     * Get file info by ID
     */
    public function getFileInfo($fileId) {
        $conn = connectDB(false);
        if (!$conn) {
            return null;
        }
        
        $stmt = $conn->prepare("
            SELECT fu.*, u.full_name as uploader_name 
            FROM file_uploads fu 
            LEFT JOIN users u ON fu.uploaded_by = u.id 
            WHERE fu.id = ? AND fu.is_active = 1
        ");
        
        $stmt->bind_param("i", $fileId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $stmt->close();
            closeDB($conn);
            return null;
        }
        
        $fileInfo = $result->fetch_assoc();
        $stmt->close();
        closeDB($conn);
        
        return $fileInfo;
    }
    
    /**
     * Format bytes to human readable format
     */
    private function formatBytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Get upload progress (for AJAX uploads)
     */
    public function getUploadProgress($uploadId) {
        if (function_exists('apc_fetch')) {
            $status = apc_fetch('upload_' . $uploadId);
            if ($status) {
                return array(
                    'uploaded' => $status['current'],
                    'total' => $status['total'],
                    'percentage' => round(($status['current'] / $status['total']) * 100)
                );
            }
        }
        
        return array('uploaded' => 0, 'total' => 0, 'percentage' => 0);
    }
}

/**
 * File download handler
 */
function downloadFile($fileId, $inline = false) {
    if (!isLoggedIn()) {
        http_response_code(401);
        exit('Authentication required');
    }
    
    $uploader = new FileUploader();
    $fileInfo = $uploader->getFileInfo($fileId);
    
    if (!$fileInfo) {
        http_response_code(404);
        exit('File not found');
    }
    
    $filePath = __DIR__ . '/../uploads/' . $fileInfo['file_path'];
    
    if (!file_exists($filePath)) {
        http_response_code(404);
        exit('File not found on disk');
    }
    
    // Set headers
    header('Content-Type: ' . $fileInfo['mime_type']);
    header('Content-Length: ' . $fileInfo['file_size']);
    
    if ($inline) {
        header('Content-Disposition: inline; filename="' . $fileInfo['original_filename'] . '"');
    } else {
        header('Content-Disposition: attachment; filename="' . $fileInfo['original_filename'] . '"');
    }
    
    // Prevent caching for sensitive files
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
    
    // Output file
    readfile($filePath);
    exit();
}

// Handle AJAX upload requests
if (isset($_POST['ajax_upload']) && $_POST['ajax_upload'] === '1') {
    header('Content-Type: application/json');
    
    if (!isLoggedIn()) {
        echo json_encode(array('success' => false, 'message' => 'Authentication required'));
        exit();
    }
    
    if (!isset($_FILES['file'])) {
        echo json_encode(array('success' => false, 'message' => 'No file uploaded'));
        exit();
    }
    
    $uploader = new FileUploader();
    $uploadType = isset($_POST['upload_type']) ? $_POST['upload_type'] : 'course_material';
    $referenceId = isset($_POST['reference_id']) ? (int)$_POST['reference_id'] : null;
    
    $result = $uploader->uploadFile($_FILES['file'], $uploadType, $referenceId);
    echo json_encode($result);
    exit();
}

// Handle file download requests
if (isset($_GET['download']) && isset($_GET['file_id'])) {
    $inline = isset($_GET['inline']) && $_GET['inline'] === '1';
    downloadFile((int)$_GET['file_id'], $inline);
}
?>
