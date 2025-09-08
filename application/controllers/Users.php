<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Users extends CI_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->model('User_model');
        $this->load->library(['session', 'form_validation', 'upload']);
        $this->load->helper(['url', 'form', 'security']);
        
        // Check authentication and admin role
        if (!$this->session->userdata('logged_in')) {
            redirect('auth/login');
        }
        
        if ($this->session->userdata('user_role') !== 'admin') {
            redirect('auth/unauthorized');
        }
    }

    public function index() {
        $data = [
            'page_title' => 'User Management',
            'users' => $this->User_model->get_all_users(),
            'user_stats' => $this->User_model->get_user_stats()
        ];

        $this->load->view('templates/header', $data);
        $this->load->view('users/index', $data);
        $this->load->view('templates/footer');
    }

    public function view($id = null) {
        if (!$id) {
            redirect('users');
        }

        $user = $this->User_model->get_user($id);
        if (!$user) {
            $this->session->set_flashdata('error', 'User not found.');
            redirect('users');
        }

        $data = [
            'page_title' => 'User Details - ' . $user['full_name'],
            'user' => $user
        ];

        // Get user's enrollments
        $this->load->model('Enrollment_model');
        $data['enrollments'] = $this->Enrollment_model->get_user_enrollments($id);

        // Get user's quiz attempts if table exists
        if ($this->db->table_exists('quiz_attempts')) {
            $this->db->select('qa.*, q.title as quiz_title, t.title as training_title');
            $this->db->from('quiz_attempts qa');
            $this->db->join('quizzes q', 'q.id = qa.quiz_id', 'left');
            $this->db->join('trainings t', 't.id = q.training_id', 'left');
            $this->db->where('qa.user_id', $id);
            $this->db->where('qa.status', 'submitted');
            $this->db->order_by('qa.attempt_date', 'DESC');
            $data['quiz_attempts'] = $this->db->get()->result_array();
        } else {
            $data['quiz_attempts'] = [];
        }

        // Get user's certificates if table exists
        if ($this->db->table_exists('certificates')) {
            $this->db->select('c.*, t.title as training_title');
            $this->db->from('certificates c');
            $this->db->join('trainings t', 't.id = c.training_id', 'left');
            $this->db->where('c.user_id', $id);
            $this->db->where('c.status', 'active');
            $this->db->order_by('c.issued_date', 'DESC');
            $data['certificates'] = $this->db->get()->result_array();
        } else {
            $data['certificates'] = [];
        }

        $this->load->view('templates/header', $data);
        $this->load->view('users/view', $data);
        $this->load->view('templates/footer');
    }

    public function create() {
        $data = ['page_title' => 'Create New User'];

        if ($this->input->method() === 'post') {
            if ($this->User_model->validate_user_data($this->input->post())) {
                $user_data = [
                    'full_name' => $this->input->post('full_name'),
                    'email' => $this->input->post('email'),
                    'password' => $this->input->post('password'),
                    'role' => $this->input->post('role'),
                    'department' => $this->input->post('department'),
                    'position' => $this->input->post('position'),
                    'phone' => $this->input->post('phone'),
                    'is_active' => $this->input->post('is_active') ? 1 : 0,
                    'email_verified' => $this->input->post('email_verified') ? 1 : 0
                ];

                if ($this->User_model->create_user($user_data)) {
                    $this->session->set_flashdata('success', 'User created successfully!');
                    redirect('users');
                } else {
                    $data['error'] = 'Failed to create user. Please try again.';
                }
            }
        }

        $this->load->view('templates/header', $data);
        $this->load->view('users/create', $data);
        $this->load->view('templates/footer');
    }

    public function edit($id = null) {
        if (!$id) {
            redirect('users');
        }

        $user = $this->User_model->get_user($id);
        if (!$user) {
            $this->session->set_flashdata('error', 'User not found.');
            redirect('users');
        }

        $data = [
            'page_title' => 'Edit User - ' . $user['full_name'],
            'user' => $user
        ];

        if ($this->input->method() === 'post') {
            if ($this->User_model->validate_user_data($this->input->post(), $id)) {
                $user_data = [
                    'full_name' => $this->input->post('full_name'),
                    'email' => $this->input->post('email'),
                    'role' => $this->input->post('role'),
                    'department' => $this->input->post('department'),
                    'position' => $this->input->post('position'),
                    'phone' => $this->input->post('phone'),
                    'is_active' => $this->input->post('is_active') ? 1 : 0,
                    'email_verified' => $this->input->post('email_verified') ? 1 : 0
                ];

                // Only update password if provided
                if (!empty($this->input->post('password'))) {
                    $user_data['password'] = $this->input->post('password');
                }

                if ($this->User_model->update_user($id, $user_data)) {
                    $this->session->set_flashdata('success', 'User updated successfully!');
                    redirect('users');
                } else {
                    $data['error'] = 'Failed to update user. Please try again.';
                }
            }
        }

        $this->load->view('templates/header', $data);
        $this->load->view('users/edit', $data);
        $this->load->view('templates/footer');
    }

    public function delete($id = null) {
        if (!$id) {
            redirect('users');
        }

        // Prevent deleting self
        if ($id == $this->session->userdata('user_id')) {
            $this->session->set_flashdata('error', 'You cannot delete your own account.');
            redirect('users');
        }

        if ($this->User_model->delete_user($id)) {
            $this->session->set_flashdata('success', 'User deleted successfully!');
        } else {
            $this->session->set_flashdata('error', 'Failed to delete user.');
        }

        redirect('users');
    }

    public function search() {
        $search = $this->input->get('search');
        $role = $this->input->get('role');

        $data = [
            'page_title' => 'Search Users',
            'users' => [],
            'search_query' => $search,
            'selected_role' => $role
        ];

        if ($search) {
            $data['users'] = $this->User_model->search_users($search, $role);
        }

        $this->load->view('templates/header', $data);
        $this->load->view('users/search', $data);
        $this->load->view('templates/footer');
    }

    public function profile() {
        $user_id = $this->session->userdata('user_id');
        $user = $this->User_model->get_user($user_id);

        $data = [
            'page_title' => 'My Profile',
            'user' => $user
        ];

        if ($this->input->method() === 'post') {
            $this->form_validation->set_rules('full_name', 'Full Name', 'required|min_length[2]|max_length[100]');
            $this->form_validation->set_rules('phone', 'Phone', 'permit_empty|min_length[10]|max_length[15]');
            $this->form_validation->set_rules('department', 'Department', 'permit_empty|max_length[100]');
            $this->form_validation->set_rules('position', 'Position', 'permit_empty|max_length[100]');

            if ($this->form_validation->run()) {
                $user_data = [
                    'full_name' => $this->input->post('full_name'),
                    'department' => $this->input->post('department'),
                    'position' => $this->input->post('position'),
                    'phone' => $this->input->post('phone')
                ];

                // Handle password change
                if (!empty($this->input->post('current_password'))) {
                    if ($this->User_model->verify_password($user['email'], $this->input->post('current_password'))) {
                        if ($this->input->post('new_password') === $this->input->post('confirm_password')) {
                            $user_data['password'] = $this->input->post('new_password');
                        } else {
                            $data['error'] = 'New password and confirmation do not match.';
                            $this->load->view('templates/header', $data);
                            $this->load->view('users/profile', $data);
                            $this->load->view('templates/footer');
                            return;
                        }
                    } else {
                        $data['error'] = 'Current password is incorrect.';
                        $this->load->view('templates/header', $data);
                        $this->load->view('users/profile', $data);
                        $this->load->view('templates/footer');
                        return;
                    }
                }

                if ($this->User_model->update_user($user_id, $user_data)) {
                    $this->session->set_flashdata('success', 'Profile updated successfully!');
                    redirect('users/profile');
                } else {
                    $data['error'] = 'Failed to update profile. Please try again.';
                }
            }
        }

        $this->load->view('templates/header', $data);
        $this->load->view('users/profile', $data);
        $this->load->view('templates/footer');
    }
}
