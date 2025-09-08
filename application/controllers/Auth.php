<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Auth extends CI_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->model('User_model');
        $this->load->library(['session', 'form_validation']);
        $this->load->helper(['url', 'form', 'security']);
    }

    public function login() {
        // If already logged in, redirect to dashboard
        if ($this->session->userdata('logged_in')) {
            redirect('dashboard');
        }

        $data = ['page_title' => 'Login'];

        if ($this->input->method() === 'post') {
            $this->form_validation->set_rules('email', 'Email', 'required|valid_email');
            $this->form_validation->set_rules('password', 'Password', 'required|min_length[6]');

            if ($this->form_validation->run()) {
                $email = $this->input->post('email');
                $password = $this->input->post('password');

                if ($this->User_model->verify_password($email, $password)) {
                    $user = $this->User_model->get_user_by_email($email);
                    
                    if ($user && $user['is_active']) {
                        // Set session data
                        $session_data = [
                            'user_id' => $user['id'],
                            'user_email' => $user['email'],
                            'user_name' => $user['full_name'],
                            'user_role' => $user['role'],
                            'logged_in' => true
                        ];
                        
                        $this->session->set_userdata($session_data);

                        // Update last login
                        $this->User_model->update_last_login($user['id']);

                        // Set success message and redirect
                        $this->session->set_flashdata('success', 'Welcome back, ' . $user['full_name'] . '!');
                        
                        $redirect_to = $this->input->post('redirect') ?: 'dashboard';
                        redirect($redirect_to);
                    } else {
                        $data['error'] = 'Account is inactive. Please contact administrator.';
                    }
                } else {
                    $data['error'] = 'Invalid email or password.';
                }
            }
        }

        $this->load->view('auth/login', $data);
    }

    public function register() {
        $data = ['page_title' => 'Register'];

        if ($this->input->method() === 'post') {
            $this->form_validation->set_rules('full_name', 'Full Name', 'required|min_length[2]|max_length[100]');
            $this->form_validation->set_rules('email', 'Email', 'required|valid_email|is_unique[users.email]');
            $this->form_validation->set_rules('password', 'Password', 'required|min_length[6]');
            $this->form_validation->set_rules('password_confirm', 'Password Confirmation', 'required|matches[password]');
            $this->form_validation->set_rules('department', 'Department', 'max_length[100]');
            $this->form_validation->set_rules('position', 'Position', 'max_length[100]');
            $this->form_validation->set_rules('phone', 'Phone', 'min_length[10]|max_length[15]');

            if ($this->form_validation->run()) {
                $user_data = [
                    'full_name' => $this->input->post('full_name'),
                    'email' => $this->input->post('email'),
                    'password' => $this->input->post('password'),
                    'role' => 'user', // Default role
                    'department' => $this->input->post('department'),
                    'position' => $this->input->post('position'),
                    'phone' => $this->input->post('phone'),
                    'is_active' => 1,
                    'email_verified' => 0
                ];

                if ($this->User_model->create_user($user_data)) {
                    $this->session->set_flashdata('success', 'Registration successful! Please log in.');
                    redirect('auth/login');
                } else {
                    $data['error'] = 'Registration failed. Please try again.';
                }
            }
        }

        $this->load->view('auth/register', $data);
    }

    public function logout() {
        $this->session->sess_destroy();
        $this->session->set_flashdata('success', 'You have been logged out successfully.');
        redirect('auth/login');
    }

    public function unauthorized() {
        $data = ['page_title' => 'Unauthorized Access'];
        $this->load->view('auth/unauthorized', $data);
    }

    public function forgot_password() {
        $data = ['page_title' => 'Forgot Password'];

        if ($this->input->method() === 'post') {
            $this->form_validation->set_rules('email', 'Email', 'required|valid_email');

            if ($this->form_validation->run()) {
                $email = $this->input->post('email');
                $user = $this->User_model->get_user_by_email($email);

                if ($user) {
                    // Generate password reset token
                    $token = bin2hex(random_bytes(32));
                    // In a real application, you'd store this token and send an email
                    $this->session->set_flashdata('success', 'Password reset instructions sent to your email.');
                    redirect('auth/login');
                } else {
                    $data['error'] = 'No account found with this email address.';
                }
            }
        }

        $this->load->view('auth/forgot_password', $data);
    }

    // Helper methods for checking authentication and roles
    public function is_logged_in() {
        return $this->session->userdata('logged_in') ? true : false;
    }

    public function get_current_user() {
        if ($this->is_logged_in()) {
            return $this->User_model->get_user($this->session->userdata('user_id'));
        }
        return null;
    }

    public function has_role($role) {
        return $this->session->userdata('user_role') === $role;
    }

    public function require_login() {
        if (!$this->is_logged_in()) {
            redirect('auth/login');
        }
    }

    public function require_role($role) {
        $this->require_login();
        if (!$this->has_role($role)) {
            redirect('auth/unauthorized');
        }
    }
}
