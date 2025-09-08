<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Enrollment extends CI_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->model(['Enrollment_model', 'Training_model', 'User_model']);
        $this->load->library(['session', 'form_validation']);
        $this->load->helper(['url', 'form', 'security']);
        
        // Check authentication
        if (!$this->session->userdata('logged_in')) {
            redirect('auth/login');
        }
    }

    public function index() {
        $user_role = $this->session->userdata('user_role');
        $user_id = $this->session->userdata('user_id');

        $data = ['page_title' => 'Enrollment Management'];

        // Get enrollments based on role
        if ($user_role === 'admin') {
            $data['enrollments'] = $this->Enrollment_model->get_all_enrollments();
        } elseif ($user_role === 'trainer') {
            // Get enrollments for trainer's courses
            $data['enrollments'] = $this->Enrollment_model->get_all_enrollments();
            // Filter for trainer's courses in the view
        } else {
            // Regular users see their own enrollments
            $data['enrollments'] = $this->Enrollment_model->get_user_enrollments($user_id);
        }

        $data['user_role'] = $user_role;
        $data['enrollment_stats'] = $this->Enrollment_model->get_enrollment_stats();

        $this->load->view('templates/header', $data);
        $this->load->view('enrollment/index', $data);
        $this->load->view('templates/footer');
    }

    public function view($id = null) {
        if (!$id) {
            redirect('enrollment');
        }

        $enrollment = $this->Enrollment_model->get_enrollment($id);
        if (!$enrollment) {
            $this->session->set_flashdata('error', 'Enrollment not found.');
            redirect('enrollment');
        }

        $user_role = $this->session->userdata('user_role');
        $user_id = $this->session->userdata('user_id');

        // Check permissions
        if ($user_role === 'user' && $enrollment['user_id'] != $user_id) {
            redirect('auth/unauthorized');
        }

        $data = [
            'page_title' => 'Enrollment Details',
            'enrollment' => $enrollment,
            'user_role' => $user_role
        ];

        $this->load->view('templates/header', $data);
        $this->load->view('enrollment/view', $data);
        $this->load->view('templates/footer');
    }

    public function create() {
        $user_role = $this->session->userdata('user_role');
        if (!in_array($user_role, ['admin', 'trainer'])) {
            redirect('auth/unauthorized');
        }

        $data = [
            'page_title' => 'Create New Enrollment',
            'users' => $this->User_model->get_users_by_role('user'),
            'trainings' => $this->Training_model->get_all_trainings()
        ];

        if ($this->input->method() === 'post') {
            if ($this->Enrollment_model->validate_enrollment_data($this->input->post())) {
                $enrollment_data = [
                    'user_id' => $this->input->post('user_id'),
                    'training_id' => $this->input->post('training_id'),
                    'status' => $this->input->post('status'),
                    'progress_percentage' => $this->input->post('progress_percentage') ?: 0
                ];

                if ($this->Enrollment_model->create_enrollment($enrollment_data)) {
                    $this->session->set_flashdata('success', 'Enrollment created successfully!');
                    redirect('enrollment');
                } else {
                    $data['error'] = 'Failed to create enrollment. Please try again.';
                }
            }
        }

        $this->load->view('templates/header', $data);
        $this->load->view('enrollment/create', $data);
        $this->load->view('templates/footer');
    }

    public function edit($id = null) {
        if (!$id) {
            redirect('enrollment');
        }

        $enrollment = $this->Enrollment_model->get_enrollment($id);
        if (!$enrollment) {
            $this->session->set_flashdata('error', 'Enrollment not found.');
            redirect('enrollment');
        }

        $user_role = $this->session->userdata('user_role');
        if (!in_array($user_role, ['admin', 'trainer'])) {
            redirect('auth/unauthorized');
        }

        $data = [
            'page_title' => 'Edit Enrollment',
            'enrollment' => $enrollment,
            'users' => $this->User_model->get_users_by_role('user'),
            'trainings' => $this->Training_model->get_all_trainings()
        ];

        if ($this->input->method() === 'post') {
            if ($this->Enrollment_model->validate_enrollment_data($this->input->post())) {
                $enrollment_data = [
                    'user_id' => $this->input->post('user_id'),
                    'training_id' => $this->input->post('training_id'),
                    'status' => $this->input->post('status'),
                    'progress_percentage' => $this->input->post('progress_percentage')
                ];

                if ($this->Enrollment_model->update_enrollment($id, $enrollment_data)) {
                    $this->session->set_flashdata('success', 'Enrollment updated successfully!');
                    redirect('enrollment');
                } else {
                    $data['error'] = 'Failed to update enrollment. Please try again.';
                }
            }
        }

        $this->load->view('templates/header', $data);
        $this->load->view('enrollment/edit', $data);
        $this->load->view('templates/footer');
    }

    public function delete($id = null) {
        $user_role = $this->session->userdata('user_role');
        if ($user_role !== 'admin' || !$id) {
            redirect('auth/unauthorized');
        }

        if ($this->Enrollment_model->delete_enrollment($id)) {
            $this->session->set_flashdata('success', 'Enrollment deleted successfully!');
        } else {
            $this->session->set_flashdata('error', 'Failed to delete enrollment.');
        }

        redirect('enrollment');
    }

    public function update_progress($id = null) {
        if (!$id) {
            redirect('enrollment');
        }

        $user_role = $this->session->userdata('user_role');
        if (!in_array($user_role, ['admin', 'trainer'])) {
            redirect('auth/unauthorized');
        }

        if ($this->input->method() === 'post') {
            $progress = $this->input->post('progress_percentage');
            $status = $this->input->post('status');

            if ($this->Enrollment_model->update_progress($id, $progress, $status)) {
                $this->session->set_flashdata('success', 'Progress updated successfully!');
            } else {
                $this->session->set_flashdata('error', 'Failed to update progress.');
            }
        }

        redirect('enrollment/view/' . $id);
    }
}
