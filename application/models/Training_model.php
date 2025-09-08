<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Training_model extends CI_Model {

    public function __construct() {
        parent::__construct();
        $this->load->database();
    }

    /**
     * Get all active trainings
     */
    public function get_all_trainings($limit = null, $offset = 0) {
        $this->db->select('t.*, u.full_name as instructor_name, cc.name as category_name');
        $this->db->from('trainings t');
        $this->db->join('users u', 'u.id = t.instructor_id', 'left');
        $this->db->join('course_categories cc', 'cc.id = t.category_id', 'left');
        $this->db->where('t.is_active', 1);
        
        if ($limit) {
            $this->db->limit($limit, $offset);
        }
        
        $this->db->order_by('t.start_date', 'DESC');
        return $this->db->get()->result_array();
    }

    /**
     * Get training by ID
     */
    public function get_training($id) {
        $this->db->select('t.*, u.full_name as instructor_name, u.email as instructor_email, cc.name as category_name');
        $this->db->from('trainings t');
        $this->db->join('users u', 'u.id = t.instructor_id', 'left');
        $this->db->join('course_categories cc', 'cc.id = t.category_id', 'left');
        $this->db->where('t.id', $id);
        return $this->db->get()->row_array();
    }

    /**
     * Create new training
     */
    public function create_training($data) {
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        $data['is_active'] = 1;
        
        return $this->db->insert('trainings', $data);
    }

    /**
     * Update training
     */
    public function update_training($id, $data) {
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        $this->db->where('id', $id);
        return $this->db->update('trainings', $data);
    }

    /**
     * Delete training (soft delete)
     */
    public function delete_training($id) {
        $data = array(
            'is_active' => 0,
            'updated_at' => date('Y-m-d H:i:s')
        );
        
        $this->db->where('id', $id);
        return $this->db->update('trainings', $data);
    }

    /**
     * Get trainings by instructor
     */
    public function get_trainings_by_instructor($instructor_id) {
        $this->db->select('t.*, cc.name as category_name');
        $this->db->from('trainings t');
        $this->db->join('course_categories cc', 'cc.id = t.category_id', 'left');
        $this->db->where('t.instructor_id', $instructor_id);
        $this->db->where('t.is_active', 1);
        $this->db->order_by('t.start_date', 'DESC');
        return $this->db->get()->result_array();
    }

    /**
     * Get upcoming trainings
     */
    public function get_upcoming_trainings($limit = 10) {
        $this->db->select('t.*, u.full_name as instructor_name');
        $this->db->from('trainings t');
        $this->db->join('users u', 'u.id = t.instructor_id', 'left');
        $this->db->where('t.start_date >', date('Y-m-d H:i:s'));
        $this->db->where('t.is_active', 1);
        $this->db->order_by('t.start_date', 'ASC');
        $this->db->limit($limit);
        return $this->db->get()->result_array();
    }

    /**
     * Get training enrollment count
     */
    public function get_enrollment_count($training_id) {
        $this->db->where('training_id', $training_id);
        return $this->db->count_all_results('enrollments');
    }

    /**
     * Check if training has available spots
     */
    public function has_available_spots($training_id) {
        $training = $this->get_training($training_id);
        
        if (!$training || !$training['max_participants']) {
            return true; // Unlimited spots
        }
        
        $enrolled_count = $this->get_enrollment_count($training_id);
        return $enrolled_count < $training['max_participants'];
    }

    /**
     * Get training statistics
     */
    public function get_training_stats() {
        $total = $this->db->where('is_active', 1)->count_all_results('trainings');
        
        $upcoming = $this->db->where('start_date >', date('Y-m-d H:i:s'))
                            ->where('is_active', 1)
                            ->count_all_results('trainings');
        
        $ongoing = $this->db->where('start_date <=', date('Y-m-d H:i:s'))
                           ->where('end_date >=', date('Y-m-d H:i:s'))
                           ->where('is_active', 1)
                           ->count_all_results('trainings');
        
        $completed = $this->db->where('end_date <', date('Y-m-d H:i:s'))
                             ->where('is_active', 1)
                             ->count_all_results('trainings');

        return array(
            'total' => $total,
            'upcoming' => $upcoming,
            'ongoing' => $ongoing,
            'completed' => $completed
        );
    }

    /**
     * Search trainings
     */
    public function search_trainings($search, $category_id = null) {
        $this->db->select('t.*, u.full_name as instructor_name, cc.name as category_name');
        $this->db->from('trainings t');
        $this->db->join('users u', 'u.id = t.instructor_id', 'left');
        $this->db->join('course_categories cc', 'cc.id = t.category_id', 'left');
        
        $this->db->group_start();
        $this->db->like('t.title', $search);
        $this->db->or_like('t.description', $search);
        $this->db->or_like('t.location', $search);
        $this->db->or_like('u.full_name', $search);
        $this->db->group_end();
        
        if ($category_id) {
            $this->db->where('t.category_id', $category_id);
        }
        
        $this->db->where('t.is_active', 1);
        $this->db->order_by('t.start_date', 'DESC');
        
        return $this->db->get()->result_array();
    }

    /**
     * Validate training data
     */
    public function validate_training_data($data) {
        $this->form_validation->set_data($data);
        
        $this->form_validation->set_rules('title', 'Title', 'required|min_length[3]|max_length[200]');
        $this->form_validation->set_rules('description', 'Description', 'required|min_length[10]');
        $this->form_validation->set_rules('start_date', 'Start Date', 'required');
        $this->form_validation->set_rules('end_date', 'End Date', 'required');
        $this->form_validation->set_rules('location', 'Location', 'required|max_length[200]');
        $this->form_validation->set_rules('instructor_id', 'Instructor', 'required|integer');
        $this->form_validation->set_rules('max_participants', 'Max Participants', 'permit_empty|integer|greater_than[0]');
        $this->form_validation->set_rules('difficulty_level', 'Difficulty Level', 'permit_empty|in_list[beginner,intermediate,advanced]');
        $this->form_validation->set_rules('duration_hours', 'Duration Hours', 'permit_empty|decimal|greater_than[0]');
        
        return $this->form_validation->run();
    }

    /**
     * Get training materials
     */
    public function get_training_materials($training_id) {
        if (!$this->db->table_exists('course_materials')) {
            return array();
        }
        
        $this->db->where('training_id', $training_id);
        $this->db->order_by('upload_date', 'DESC');
        return $this->db->get('course_materials')->result_array();
    }

    /**
     * Get training sessions
     */
    public function get_training_sessions($training_id) {
        if (!$this->db->table_exists('training_sessions')) {
            return array();
        }
        
        $this->db->where('training_id', $training_id);
        $this->db->order_by('session_date', 'ASC');
        return $this->db->get('training_sessions')->result_array();
    }

    /**
     * Get popular trainings (by enrollment count)
     */
    public function get_popular_trainings($limit = 5) {
        $this->db->select('t.*, u.full_name as instructor_name, COUNT(e.id) as enrollment_count');
        $this->db->from('trainings t');
        $this->db->join('users u', 'u.id = t.instructor_id', 'left');
        $this->db->join('enrollments e', 'e.training_id = t.id', 'left');
        $this->db->where('t.is_active', 1);
        $this->db->group_by('t.id');
        $this->db->order_by('enrollment_count', 'DESC');
        $this->db->limit($limit);
        return $this->db->get()->result_array();
    }
}
