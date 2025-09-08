<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Training extends CI_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->model(['Training_model', 'User_model', 'Enrollment_model']);
        $this->load->library(['session', 'form_validation', 'upload']);
        $this->load->helper(['url', 'form', 'security']);
        
        // Check authentication
        if (!$this->session->userdata('logged_in')) {
            redirect('auth/login');
        }
    }

    public function index() {
        $user_role = $this->session->userdata('user_role');
        $user_id = $this->session->userdata('user_id');

        $data = ['page_title' => 'Training Management'];

        // Get trainings based on role
        if ($user_role === 'admin') {
            $data['trainings'] = $this->Training_model->get_all_trainings();
        } elseif ($user_role === 'trainer') {
            $data['trainings'] = $this->Training_model->get_trainings_by_instructor($user_id);
        } else {
            // For regular users, show available trainings
            $data['trainings'] = $this->Training_model->get_all_trainings();
        }

        $data['user_role'] = $user_role;
        $data['training_stats'] = $this->Training_model->get_training_stats();

        $this->load->view('templates/header', $data);
        $this->load->view('training/index', $data);
        $this->load->view('templates/footer');
    }

    public function view($id = null) {
        if (!$id) {
            redirect('training');
        }

        $training = $this->Training_model->get_training($id);
        if (!$training) {
            $this->session->set_flashdata('error', 'Training not found.');
            redirect('training');
        }

        $user_id = $this->session->userdata('user_id');
        $user_role = $this->session->userdata('user_role');

        $data = [
            'page_title' => $training['title'],
            'training' => $training,
            'is_enrolled' => $this->Enrollment_model->is_user_enrolled($user_id, $id),
            'enrollment_count' => $this->Training_model->get_enrollment_count($id),
            'has_available_spots' => $this->Training_model->has_available_spots($id),
            'user_role' => $user_role
        ];

        // Get training materials and sessions if tables exist
        $data['materials'] = $this->Training_model->get_training_materials($id);
        $data['sessions'] = $this->Training_model->get_training_sessions($id);

        // Get enrollments for admin/trainer
        if (in_array($user_role, ['admin', 'trainer'])) {
            $data['enrollments'] = $this->Enrollment_model->get_training_enrollments($id);
        }

        $this->load->view('templates/header', $data);
        $this->load->view('training/view', $data);
        $this->load->view('templates/footer');
    }

    public function create() {
        $user_role = $this->session->userdata('user_role');
        if (!in_array($user_role, ['admin', 'trainer'])) {
            redirect('auth/unauthorized');
        }

        $data = [
            'page_title' => 'Create New Training',
            'instructors' => $this->User_model->get_users_by_role('trainer'),
            'categories' => []
        ];

        // Get categories if table exists
        if ($this->db->table_exists('course_categories')) {
            $data['categories'] = $this->db->get('course_categories')->result_array();
        }

        if ($this->input->method() === 'post') {
            if ($this->Training_model->validate_training_data($this->input->post())) {
                $training_data = [
                    'title' => $this->input->post('title'),
                    'description' => $this->input->post('description'),
                    'start_date' => $this->input->post('start_date'),
                    'end_date' => $this->input->post('end_date'),
                    'location' => $this->input->post('location'),
                    'max_participants' => $this->input->post('max_participants'),
                    'instructor_id' => $this->input->post('instructor_id'),
                    'category_id' => $this->input->post('category_id'),
                    'difficulty_level' => $this->input->post('difficulty_level'),
                    'duration_hours' => $this->input->post('duration_hours'),
                    'prerequisites' => $this->input->post('prerequisites'),
                    'learning_objectives' => $this->input->post('learning_objectives'),
                    'materials_required' => $this->input->post('materials_required')
                ];

                if ($this->Training_model->create_training($training_data)) {
                    $this->session->set_flashdata('success', 'Training created successfully!');
                    redirect('training');
                } else {
                    $data['error'] = 'Failed to create training. Please try again.';
                }
            }
        }

        $this->load->view('templates/header', $data);
        $this->load->view('training/create', $data);
        $this->load->view('templates/footer');
    }

    public function edit($id = null) {
        if (!$id) {
            redirect('training');
        }

        $training = $this->Training_model->get_training($id);
        if (!$training) {
            $this->session->set_flashdata('error', 'Training not found.');
            redirect('training');
        }

        $user_role = $this->session->userdata('user_role');
        $user_id = $this->session->userdata('user_id');

        // Check permissions
        if ($user_role !== 'admin' && $training['instructor_id'] != $user_id) {
            redirect('auth/unauthorized');
        }

        $data = [
            'page_title' => 'Edit Training',
            'training' => $training,
            'instructors' => $this->User_model->get_users_by_role('trainer'),
            'categories' => []
        ];

        // Get categories if table exists
        if ($this->db->table_exists('course_categories')) {
            $data['categories'] = $this->db->get('course_categories')->result_array();
        }

        if ($this->input->method() === 'post') {
            if ($this->Training_model->validate_training_data($this->input->post())) {
                $training_data = [
                    'title' => $this->input->post('title'),
                    'description' => $this->input->post('description'),
                    'start_date' => $this->input->post('start_date'),
                    'end_date' => $this->input->post('end_date'),
                    'location' => $this->input->post('location'),
                    'max_participants' => $this->input->post('max_participants'),
                    'instructor_id' => $this->input->post('instructor_id'),
                    'category_id' => $this->input->post('category_id'),
                    'difficulty_level' => $this->input->post('difficulty_level'),
                    'duration_hours' => $this->input->post('duration_hours'),
                    'prerequisites' => $this->input->post('prerequisites'),
                    'learning_objectives' => $this->input->post('learning_objectives'),
                    'materials_required' => $this->input->post('materials_required')
                ];

                if ($this->Training_model->update_training($id, $training_data)) {
                    $this->session->set_flashdata('success', 'Training updated successfully!');
                    redirect('training');
                } else {
                    $data['error'] = 'Failed to update training. Please try again.';
                }
            }
        }

        $this->load->view('templates/header', $data);
        $this->load->view('training/edit', $data);
        $this->load->view('templates/footer');
    }

    public function delete($id = null) {
        $user_role = $this->session->userdata('user_role');
        if ($user_role !== 'admin' || !$id) {
            redirect('auth/unauthorized');
        }

        if ($this->Training_model->delete_training($id)) {
            $this->session->set_flashdata('success', 'Training deleted successfully!');
        } else {
            $this->session->set_flashdata('error', 'Failed to delete training.');
        }

        redirect('training');
    }

    public function enroll($id = null) {
        if (!$id) {
            redirect('training');
        }

        $user_id = $this->session->userdata('user_id');
        $training = $this->Training_model->get_training($id);

        if (!$training || !$training['is_active']) {
            $this->session->set_flashdata('error', 'Training not available.');
            redirect('training');
        }

        if ($this->Enrollment_model->is_user_enrolled($user_id, $id)) {
            $this->session->set_flashdata('error', 'You are already enrolled in this training.');
            redirect('training/view/' . $id);
        }

        if (!$this->Training_model->has_available_spots($id)) {
            $this->session->set_flashdata('error', 'No spots available for this training.');
            redirect('training/view/' . $id);
        }

        if ($this->Enrollment_model->enroll_user($user_id, $id)) {
            $this->session->set_flashdata('success', 'Successfully enrolled in training!');
        } else {
            $this->session->set_flashdata('error', 'Failed to enroll. Please try again.');
        }

        redirect('training/view/' . $id);
    }

    public function search() {
        $search = $this->input->get('search');
        $category_id = $this->input->get('category_id');

        $data = [
            'page_title' => 'Search Trainings',
            'trainings' => [],
            'search_query' => $search,
            'selected_category' => $category_id,
            'categories' => []
        ];

        if ($search) {
            $data['trainings'] = $this->Training_model->search_trainings($search, $category_id);
        }

        // Get categories if table exists
        if ($this->db->table_exists('course_categories')) {
            $data['categories'] = $this->db->get('course_categories')->result_array();
        }

        $this->load->view('templates/header', $data);
        $this->load->view('training/search', $data);
        $this->load->view('templates/footer');
    }
}
