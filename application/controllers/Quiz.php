<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Quiz extends CI_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->model(['Quiz_model', 'Training_model', 'User_model']);
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

        $data = ['page_title' => 'Quiz Management'];

        // Get quizzes based on role
        if (in_array($user_role, ['admin', 'trainer'])) {
            $data['quizzes'] = $this->Quiz_model->get_all_quizzes();
        } else {
            // For regular users, show available quizzes from their enrollments
            $data['quizzes'] = $this->Quiz_model->get_all_quizzes();
        }

        $data['user_role'] = $user_role;

        $this->load->view('templates/header', $data);
        $this->load->view('quiz/index', $data);
        $this->load->view('templates/footer');
    }

    public function view($id = null) {
        if (!$id) {
            redirect('quiz');
        }

        $quiz = $this->Quiz_model->get_quiz($id);
        if (!$quiz) {
            $this->session->set_flashdata('error', 'Quiz not found.');
            redirect('quiz');
        }

        $user_id = $this->session->userdata('user_id');
        $user_role = $this->session->userdata('user_role');

        $data = [
            'page_title' => $quiz['title'],
            'quiz' => $quiz,
            'user_role' => $user_role,
            'can_take' => $this->Quiz_model->can_user_take_quiz($id, $user_id),
            'user_attempts' => $this->Quiz_model->get_user_attempts($id, $user_id),
            'quiz_stats' => $this->Quiz_model->get_quiz_stats($id)
        ];

        $this->load->view('templates/header', $data);
        $this->load->view('quiz/view', $data);
        $this->load->view('templates/footer');
    }

    public function create() {
        $user_role = $this->session->userdata('user_role');
        if (!in_array($user_role, ['admin', 'trainer'])) {
            redirect('auth/unauthorized');
        }

        $data = [
            'page_title' => 'Create New Quiz',
            'trainings' => $this->Training_model->get_all_trainings()
        ];

        if ($this->input->method() === 'post') {
            if ($this->Quiz_model->validate_quiz_data($this->input->post())) {
                $quiz_data = [
                    'title' => $this->input->post('title'),
                    'description' => $this->input->post('description'),
                    'training_id' => $this->input->post('training_id'),
                    'pass_percentage' => $this->input->post('pass_percentage'),
                    'time_limit' => $this->input->post('time_limit'),
                    'max_attempts' => $this->input->post('max_attempts'),
                    'shuffle_questions' => $this->input->post('shuffle_questions') ? 1 : 0,
                    'show_results' => $this->input->post('show_results') ? 1 : 0,
                    'created_by' => $this->session->userdata('user_id')
                ];

                if ($this->Quiz_model->create_quiz($quiz_data)) {
                    $quiz_id = $this->db->insert_id();
                    $this->session->set_flashdata('success', 'Quiz created successfully!');
                    redirect('quiz/manage/' . $quiz_id);
                } else {
                    $data['error'] = 'Failed to create quiz. Please try again.';
                }
            }
        }

        $this->load->view('templates/header', $data);
        $this->load->view('quiz/create', $data);
        $this->load->view('templates/footer');
    }

    public function edit($id = null) {
        if (!$id) {
            redirect('quiz');
        }

        $quiz = $this->Quiz_model->get_quiz($id);
        if (!$quiz) {
            $this->session->set_flashdata('error', 'Quiz not found.');
            redirect('quiz');
        }

        $user_role = $this->session->userdata('user_role');
        $user_id = $this->session->userdata('user_id');

        // Check permissions
        if ($user_role !== 'admin' && $quiz['created_by'] != $user_id) {
            redirect('auth/unauthorized');
        }

        $data = [
            'page_title' => 'Edit Quiz',
            'quiz' => $quiz,
            'trainings' => $this->Training_model->get_all_trainings()
        ];

        if ($this->input->method() === 'post') {
            if ($this->Quiz_model->validate_quiz_data($this->input->post())) {
                $quiz_data = [
                    'title' => $this->input->post('title'),
                    'description' => $this->input->post('description'),
                    'training_id' => $this->input->post('training_id'),
                    'pass_percentage' => $this->input->post('pass_percentage'),
                    'time_limit' => $this->input->post('time_limit'),
                    'max_attempts' => $this->input->post('max_attempts'),
                    'shuffle_questions' => $this->input->post('shuffle_questions') ? 1 : 0,
                    'show_results' => $this->input->post('show_results') ? 1 : 0
                ];

                if ($this->Quiz_model->update_quiz($id, $quiz_data)) {
                    $this->session->set_flashdata('success', 'Quiz updated successfully!');
                    redirect('quiz');
                } else {
                    $data['error'] = 'Failed to update quiz. Please try again.';
                }
            }
        }

        $this->load->view('templates/header', $data);
        $this->load->view('quiz/edit', $data);
        $this->load->view('templates/footer');
    }

    public function delete($id = null) {
        $user_role = $this->session->userdata('user_role');
        if ($user_role !== 'admin' || !$id) {
            redirect('auth/unauthorized');
        }

        if ($this->Quiz_model->delete_quiz($id)) {
            $this->session->set_flashdata('success', 'Quiz deleted successfully!');
        } else {
            $this->session->set_flashdata('error', 'Failed to delete quiz.');
        }

        redirect('quiz');
    }

    public function attempt($id = null) {
        if (!$id) {
            redirect('quiz');
        }

        $user_id = $this->session->userdata('user_id');
        $can_take = $this->Quiz_model->can_user_take_quiz($id, $user_id);

        if (!$can_take['can_take']) {
            $this->session->set_flashdata('error', $can_take['reason']);
            redirect('quiz/view/' . $id);
        }

        $quiz = $this->Quiz_model->get_quiz_with_questions($id);
        if (!$quiz) {
            $this->session->set_flashdata('error', 'Quiz not found.');
            redirect('quiz');
        }

        // Start attempt
        $attempt_id = $this->Quiz_model->start_quiz_attempt($id, $user_id);
        if (!$attempt_id) {
            $this->session->set_flashdata('error', 'Failed to start quiz attempt.');
            redirect('quiz/view/' . $id);
        }

        $data = [
            'page_title' => 'Taking Quiz: ' . $quiz['title'],
            'quiz' => $quiz,
            'attempt_id' => $attempt_id
        ];

        $this->load->view('templates/header', $data);
        $this->load->view('quiz/attempt', $data);
        $this->load->view('templates/footer');
    }

    public function submit($attempt_id = null) {
        if (!$attempt_id || $this->input->method() !== 'post') {
            redirect('quiz');
        }

        $user_id = $this->session->userdata('user_id');
        $answers = $this->input->post('answers');
        
        // Calculate score (simplified - in real implementation, you'd check against correct answers)
        $score = 0; // This would be calculated based on correct answers
        
        if ($this->Quiz_model->submit_quiz_attempt($attempt_id, $answers, $score)) {
            $this->session->set_flashdata('success', 'Quiz submitted successfully!');
            redirect('quiz/results/' . $attempt_id);
        } else {
            $this->session->set_flashdata('error', 'Failed to submit quiz.');
            redirect('quiz');
        }
    }

    public function results($attempt_id = null) {
        if (!$attempt_id) {
            redirect('quiz');
        }

        // Get attempt results
        $data = [
            'page_title' => 'Quiz Results',
            'attempt_id' => $attempt_id
        ];

        $this->load->view('templates/header', $data);
        $this->load->view('quiz/results', $data);
        $this->load->view('templates/footer');
    }

    public function manage($id = null) {
        if (!$id) {
            redirect('quiz');
        }

        $user_role = $this->session->userdata('user_role');
        if (!in_array($user_role, ['admin', 'trainer'])) {
            redirect('auth/unauthorized');
        }

        $quiz = $this->Quiz_model->get_quiz_with_questions($id);
        if (!$quiz) {
            $this->session->set_flashdata('error', 'Quiz not found.');
            redirect('quiz');
        }

        $data = [
            'page_title' => 'Manage Quiz: ' . $quiz['title'],
            'quiz' => $quiz,
            'quiz_stats' => $this->Quiz_model->get_quiz_stats($id)
        ];

        $this->load->view('templates/header', $data);
        $this->load->view('quiz/manage', $data);
        $this->load->view('templates/footer');
    }
}
