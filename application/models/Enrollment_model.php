<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Enrollment_model extends CI_Model {

    public function __construct() {
        parent::__construct();
        $this->load->database();
    }

    /**
     * Get all enrollments with details
     */
    public function get_all_enrollments($limit = null, $offset = 0) {
        $this->db->select('e.*, u.full_name as user_name, u.email as user_email, 
                          t.title as training_title, t.start_date, t.end_date');
        $this->db->from('enrollments e');
        $this->db->join('users u', 'u.id = e.user_id');
        $this->db->join('trainings t', 't.id = e.training_id');
        
        if ($limit) {
            $this->db->limit($limit, $offset);
        }
        
        $this->db->order_by('e.enrolled_at', 'DESC');
        return $this->db->get()->result_array();
    }

    /**
     * Get enrollment by ID
     */
    public function get_enrollment($id) {
        $this->db->select('e.*, u.full_name as user_name, u.email as user_email, 
                          t.title as training_title, t.start_date, t.end_date, t.location');
        $this->db->from('enrollments e');
        $this->db->join('users u', 'u.id = e.user_id');
        $this->db->join('trainings t', 't.id = e.training_id');
        $this->db->where('e.id', $id);
        return $this->db->get()->row_array();
    }

    /**
     * Get user enrollments
     */
    public function get_user_enrollments($user_id) {
        $this->db->select('e.*, t.title, t.description, t.start_date, t.end_date, 
                          t.location, u.full_name as instructor_name');
        $this->db->from('enrollments e');
        $this->db->join('trainings t', 't.id = e.training_id');
        $this->db->join('users u', 'u.id = t.instructor_id', 'left');
        $this->db->where('e.user_id', $user_id);
        $this->db->order_by('e.enrolled_at', 'DESC');
        return $this->db->get()->result_array();
    }

    /**
     * Get training enrollments
     */
    public function get_training_enrollments($training_id) {
        $this->db->select('e.*, u.full_name, u.email, u.phone');
        $this->db->from('enrollments e');
        $this->db->join('users u', 'u.id = e.user_id');
        $this->db->where('e.training_id', $training_id);
        $this->db->order_by('e.enrolled_at', 'DESC');
        return $this->db->get()->result_array();
    }

    /**
     * Check if user is enrolled in training
     */
    public function is_user_enrolled($user_id, $training_id) {
        $this->db->where('user_id', $user_id);
        $this->db->where('training_id', $training_id);
        return $this->db->count_all_results('enrollments') > 0;
    }

    /**
     * Enroll user in training
     */
    public function enroll_user($user_id, $training_id) {
        // Check if already enrolled
        if ($this->is_user_enrolled($user_id, $training_id)) {
            return false;
        }

        $data = array(
            'user_id' => $user_id,
            'training_id' => $training_id,
            'status' => 'enrolled',
            'progress_percentage' => 0,
            'enrolled_at' => date('Y-m-d H:i:s')
        );

        return $this->db->insert('enrollments', $data);
    }

    /**
     * Create enrollment
     */
    public function create_enrollment($data) {
        $data['enrolled_at'] = date('Y-m-d H:i:s');
        if (!isset($data['status'])) {
            $data['status'] = 'enrolled';
        }
        if (!isset($data['progress_percentage'])) {
            $data['progress_percentage'] = 0;
        }
        
        return $this->db->insert('enrollments', $data);
    }

    /**
     * Update enrollment
     */
    public function update_enrollment($id, $data) {
        $this->db->where('id', $id);
        return $this->db->update('enrollments', $data);
    }

    /**
     * Update enrollment progress
     */
    public function update_progress($enrollment_id, $progress, $status = null) {
        $data = array('progress_percentage' => $progress);
        
        if ($status) {
            $data['status'] = $status;
        }

        if ($progress >= 100 && $status === 'completed') {
            $data['completed_at'] = date('Y-m-d H:i:s');
        }

        $this->db->where('id', $enrollment_id);
        return $this->db->update('enrollments', $data);
    }

    /**
     * Delete enrollment
     */
    public function delete_enrollment($id) {
        $this->db->where('id', $id);
        return $this->db->delete('enrollments');
    }

    /**
     * Get enrollment statistics
     */
    public function get_enrollment_stats() {
        $total = $this->db->count_all_results('enrollments');
        $completed = $this->db->where('status', 'completed')->count_all_results('enrollments');
        $in_progress = $this->db->where('status', 'in_progress')->count_all_results('enrollments');
        $enrolled = $this->db->where('status', 'enrolled')->count_all_results('enrollments');

        return array(
            'total' => $total,
            'completed' => $completed,
            'in_progress' => $in_progress,
            'enrolled' => $enrolled,
            'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 2) : 0
        );
    }

    /**
     * Get recent enrollments
     */
    public function get_recent_enrollments($limit = 10) {
        $this->db->select('e.*, u.full_name as user_name, t.title as training_title');
        $this->db->from('enrollments e');
        $this->db->join('users u', 'u.id = e.user_id');
        $this->db->join('trainings t', 't.id = e.training_id');
        $this->db->order_by('e.enrolled_at', 'DESC');
        $this->db->limit($limit);
        return $this->db->get()->result_array();
    }

    /**
     * Get enrollments by status
     */
    public function get_enrollments_by_status($status) {
        $this->db->select('e.*, u.full_name as user_name, t.title as training_title');
        $this->db->from('enrollments e');
        $this->db->join('users u', 'u.id = e.user_id');
        $this->db->join('trainings t', 't.id = e.training_id');
        $this->db->where('e.status', $status);
        $this->db->order_by('e.enrolled_at', 'DESC');
        return $this->db->get()->result_array();
    }

    /**
     * Validate enrollment data
     */
    public function validate_enrollment_data($data) {
        $this->form_validation->set_data($data);
        
        $this->form_validation->set_rules('user_id', 'User', 'required|integer');
        $this->form_validation->set_rules('training_id', 'Training', 'required|integer');
        $this->form_validation->set_rules('status', 'Status', 'required|in_list[enrolled,in_progress,completed,withdrawn]');
        $this->form_validation->set_rules('progress_percentage', 'Progress', 'permit_empty|integer|greater_than_equal_to[0]|less_than_equal_to[100]');
        
        return $this->form_validation->run();
    }

    /**
     * Get completion statistics for a training
     */
    public function get_training_completion_stats($training_id) {
        $total = $this->db->where('training_id', $training_id)->count_all_results('enrollments');
        $completed = $this->db->where('training_id', $training_id)
                             ->where('status', 'completed')
                             ->count_all_results('enrollments');
        
        $avg_progress = $this->db->select_avg('progress_percentage')
                               ->where('training_id', $training_id)
                               ->get('enrollments')
                               ->row()->progress_percentage;

        return array(
            'total_enrolled' => $total,
            'completed' => $completed,
            'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 2) : 0,
            'average_progress' => round($avg_progress, 2)
        );
    }
}
