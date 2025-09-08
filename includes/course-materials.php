<?php
/**
 * Course Materials Management System
 * Compatible with PHP 5.6+ and XAMPP 2015
 */

require_once 'config.php';
require_once 'functions.php';
require_once 'db.php';
require_once 'upload.php';

class CourseMaterialsManager {
    private $conn;
    
    public function __construct() {
        $this->conn = connectDB(false);
        if (!$this->conn) {
            throw new Exception('Database connection failed');
        }
    }
    
    /**
     * Add material to a training course
     */
    public function addMaterial($trainingId, $title, $description, $fileId, $sortOrder = 0, $isDownloadable = true) {
        if (!hasPermission('trainings.upload_materials')) {
            return array('success' => false, 'message' => 'Permission denied');
        }
        
        // Verify training exists and user has access
        if (!$this->canManageTraining($trainingId)) {
            return array('success' => false, 'message' => 'Training not found or access denied');
        }
        
        // Get file information
        $uploader = new FileUploader();
        $fileInfo = $uploader->getFileInfo($fileId);
        
        if (!$fileInfo) {
            return array('success' => false, 'message' => 'File not found');
        }
        
        $stmt = $this->conn->prepare("
            INSERT INTO course_materials (training_id, title, description, file_name, file_path, file_size, file_type, mime_type, sort_order, is_downloadable, uploaded_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        if (!$stmt) {
            return array('success' => false, 'message' => 'Database error');
        }
        
        $extension = pathinfo($fileInfo['original_filename'], PATHINFO_EXTENSION);
        $userId = getCurrentUser()['id'];
        
        $stmt->bind_param("isssssissii", 
            $trainingId, 
            $title, 
            $description, 
            $fileInfo['original_filename'],
            $fileInfo['file_path'],
            $fileInfo['file_size'],
            $extension,
            $fileInfo['mime_type'],
            $sortOrder,
            $isDownloadable,
            $userId
        );
        
        if ($stmt->execute()) {
            $materialId = $this->conn->insert_id;
            $stmt->close();
            
            // Log activity
            logActivity('CREATE', 'course_materials', $materialId, null, array(
                'training_id' => $trainingId,
                'title' => $title,
                'filename' => $fileInfo['original_filename']
            ));
            
            return array('success' => true, 'message' => 'Material added successfully', 'material_id' => $materialId);
        } else {
            $stmt->close();
            return array('success' => false, 'message' => 'Failed to add material');
        }
    }
    
    /**
     * Get materials for a training course
     */
    public function getTrainingMaterials($trainingId, $userAccess = true) {
        if ($userAccess && !$this->canAccessTraining($trainingId)) {
            return array();
        }
        
        $stmt = $this->conn->prepare("
            SELECT cm.*, u.full_name as uploader_name 
            FROM course_materials cm 
            LEFT JOIN users u ON cm.uploaded_by = u.id 
            WHERE cm.training_id = ? 
            ORDER BY cm.sort_order ASC, cm.created_at ASC
        ");
        
        $stmt->bind_param("i", $trainingId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $materials = array();
        while ($row = $result->fetch_assoc()) {
            $materials[] = $row;
        }
        
        $stmt->close();
        return $materials;
    }
    
    /**
     * Update material information
     */
    public function updateMaterial($materialId, $title, $description, $sortOrder, $isDownloadable) {
        if (!hasPermission('trainings.edit')) {
            return array('success' => false, 'message' => 'Permission denied');
        }
        
        // Get material info and check permissions
        $material = $this->getMaterialInfo($materialId);
        if (!$material) {
            return array('success' => false, 'message' => 'Material not found');
        }
        
        if (!$this->canManageTraining($material['training_id'])) {
            return array('success' => false, 'message' => 'Access denied');
        }
        
        $stmt = $this->conn->prepare("
            UPDATE course_materials 
            SET title = ?, description = ?, sort_order = ?, is_downloadable = ?, updated_at = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        
        $stmt->bind_param("ssiii", $title, $description, $sortOrder, $isDownloadable, $materialId);
        
        if ($stmt->execute()) {
            $stmt->close();
            
            // Log activity
            logActivity('UPDATE', 'course_materials', $materialId, $material, array(
                'title' => $title,
                'description' => $description,
                'sort_order' => $sortOrder,
                'is_downloadable' => $isDownloadable
            ));
            
            return array('success' => true, 'message' => 'Material updated successfully');
        } else {
            $stmt->close();
            return array('success' => false, 'message' => 'Failed to update material');
        }
    }
    
    /**
     * Delete a material
     */
    public function deleteMaterial($materialId) {
        if (!hasPermission('trainings.edit')) {
            return array('success' => false, 'message' => 'Permission denied');
        }
        
        // Get material info and check permissions
        $material = $this->getMaterialInfo($materialId);
        if (!$material) {
            return array('success' => false, 'message' => 'Material not found');
        }
        
        if (!$this->canManageTraining($material['training_id'])) {
            return array('success' => false, 'message' => 'Access denied');
        }
        
        $stmt = $this->conn->prepare("DELETE FROM course_materials WHERE id = ?");
        $stmt->bind_param("i", $materialId);
        
        if ($stmt->execute()) {
            $stmt->close();
            
            // Delete associated file
            $uploader = new FileUploader();
            $files = $this->getFilesByPath($material['file_path']);
            foreach ($files as $file) {
                $uploader->deleteFile($file['id']);
            }
            
            // Log activity
            logActivity('DELETE', 'course_materials', $materialId, $material, null);
            
            return array('success' => true, 'message' => 'Material deleted successfully');
        } else {
            $stmt->close();
            return array('success' => false, 'message' => 'Failed to delete material');
        }
    }
    
    /**
     * Get material information by ID
     */
    public function getMaterialInfo($materialId) {
        $stmt = $this->conn->prepare("
            SELECT cm.*, t.title as training_title 
            FROM course_materials cm 
            LEFT JOIN trainings t ON cm.training_id = t.id 
            WHERE cm.id = ?
        ");
        
        $stmt->bind_param("i", $materialId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $stmt->close();
            return null;
        }
        
        $material = $result->fetch_assoc();
        $stmt->close();
        return $material;
    }
    
    /**
     * Check if user can manage a training
     */
    private function canManageTraining($trainingId) {
        if (isAdmin()) {
            return true;
        }
        
        if (isTrainer()) {
            // Check if trainer is assigned to this training
            $stmt = $this->conn->prepare("SELECT id FROM trainings WHERE id = ? AND instructor_id = ?");
            $userId = getCurrentUser()['id'];
            $stmt->bind_param("ii", $trainingId, $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $canManage = $result->num_rows > 0;
            $stmt->close();
            return $canManage;
        }
        
        return false;
    }
    
    /**
     * Check if user can access a training
     */
    private function canAccessTraining($trainingId) {
        if (isAdmin() || isTrainer()) {
            return true;
        }
        
        // Check if user is enrolled
        $stmt = $this->conn->prepare("SELECT id FROM enrollments WHERE training_id = ? AND user_id = ?");
        $userId = getCurrentUser()['id'];
        $stmt->bind_param("ii", $trainingId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $canAccess = $result->num_rows > 0;
        $stmt->close();
        return $canAccess;
    }
    
    /**
     * Get files by path (for cleanup)
     */
    private function getFilesByPath($filePath) {
        $stmt = $this->conn->prepare("SELECT id FROM file_uploads WHERE file_path = ?");
        $stmt->bind_param("s", $filePath);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $files = array();
        while ($row = $result->fetch_assoc()) {
            $files[] = $row;
        }
        
        $stmt->close();
        return $files;
    }
    
    /**
     * Reorder materials
     */
    public function reorderMaterials($trainingId, $materialIds) {
        if (!$this->canManageTraining($trainingId)) {
            return array('success' => false, 'message' => 'Access denied');
        }
        
        $this->conn->autocommit(false);
        
        try {
            $stmt = $this->conn->prepare("UPDATE course_materials SET sort_order = ? WHERE id = ? AND training_id = ?");
            
            foreach ($materialIds as $index => $materialId) {
                $sortOrder = $index + 1;
                $stmt->bind_param("iii", $sortOrder, $materialId, $trainingId);
                $stmt->execute();
            }
            
            $stmt->close();
            $this->conn->commit();
            $this->conn->autocommit(true);
            
            return array('success' => true, 'message' => 'Materials reordered successfully');
            
        } catch (Exception $e) {
            $this->conn->rollback();
            $this->conn->autocommit(true);
            return array('success' => false, 'message' => 'Failed to reorder materials');
        }
    }
    
    /**
     * Get material statistics for a training
     */
    public function getMaterialStats($trainingId) {
        $stmt = $this->conn->prepare("
            SELECT 
                COUNT(*) as total_materials,
                SUM(file_size) as total_size,
                COUNT(CASE WHEN is_downloadable = 1 THEN 1 END) as downloadable_count
            FROM course_materials 
            WHERE training_id = ?
        ");
        
        $stmt->bind_param("i", $trainingId);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats = $result->fetch_assoc();
        $stmt->close();
        
        return $stats;
    }
    
    public function __destruct() {
        if ($this->conn) {
            closeDB($this->conn);
        }
    }
}

// Handle AJAX requests
if (isset($_POST['action']) && $_POST['action'] === 'course_materials') {
    header('Content-Type: application/json');
    
    if (!isLoggedIn()) {
        echo json_encode(array('success' => false, 'message' => 'Authentication required'));
        exit();
    }
    
    $manager = new CourseMaterialsManager();
    $response = array('success' => false, 'message' => 'Invalid request');
    
    switch ($_POST['operation']) {
        case 'add':
            $response = $manager->addMaterial(
                (int)$_POST['training_id'],
                sanitize($_POST['title']),
                sanitize($_POST['description']),
                (int)$_POST['file_id'],
                (int)$_POST['sort_order'],
                isset($_POST['is_downloadable']) ? (bool)$_POST['is_downloadable'] : true
            );
            break;
            
        case 'update':
            $response = $manager->updateMaterial(
                (int)$_POST['material_id'],
                sanitize($_POST['title']),
                sanitize($_POST['description']),
                (int)$_POST['sort_order'],
                isset($_POST['is_downloadable']) ? (bool)$_POST['is_downloadable'] : true
            );
            break;
            
        case 'delete':
            $response = $manager->deleteMaterial((int)$_POST['material_id']);
            break;
            
        case 'reorder':
            $materialIds = json_decode($_POST['material_ids'], true);
            $response = $manager->reorderMaterials((int)$_POST['training_id'], $materialIds);
            break;
            
        case 'get_materials':
            $materials = $manager->getTrainingMaterials((int)$_POST['training_id']);
            $response = array('success' => true, 'materials' => $materials);
            break;
    }
    
    echo json_encode($response);
    exit();
}
?>
