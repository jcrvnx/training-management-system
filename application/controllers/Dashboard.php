<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Dashboard extends CI_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->model(['User_model', 'Training_model', 'Enrollment_model', 'Quiz_model']);
        $this->load->library(['session', 'form_validation']);
        $this->load->helper(['url', 'security']);
        
        // Check authentication
        if (!$this->session->userdata('logged_in')) {
            redirect('auth/login');
        }
    }

    public function index() {
        $user_id = $this->session->userdata('user_id');
        $user_role = $this->session->userdata('user_role');
        
        $data = [
            'page_title' => 'Dashboard',
            'current_user' => $this->User_model->get_user($user_id),
            'user_role' => $user_role
        ];

        try {
            // Get basic statistics
            $data['training_count'] = $this->db->where('is_active', 1)->count_all_results('trainings');
            $data['user_count'] = $this->db->where('is_active', 1)->count_all_results('users');
            $data['enrollment_count'] = $this->db->count_all_results('enrollments');

            // Initialize quiz and certificate stats
            $data['quiz_count'] = 0;
            $data['certificate_count'] = 0;
            $data['quiz_attempts'] = 0;
            $data['passed_quizzes'] = 0;

            // Check if advanced tables exist
            if ($this->db->table_exists('quizzes')) {
                $data['quiz_count'] = $this->db->where('is_active', 1)->count_all_results('quizzes');
                
                if ($this->db->table_exists('quiz_attempts')) {
                    $data['quiz_attempts'] = $this->db->where('status', 'submitted')->count_all_results('quiz_attempts');
                    $data['passed_quizzes'] = $this->db->where('status', 'submitted')->where('passed', 1)->count_all_results('quiz_attempts');
                }
                
                if ($this->db->table_exists('certificates')) {
                    $data['certificate_count'] = $this->db->where('status', 'active')->count_all_results('certificates');
                }
            }

            // Role-based data
            if ($user_role === 'admin') {
                $data['my_trainings'] = $data['training_count'];
                $data['my_enrollments'] = $data['enrollment_count'];
                $data['my_certificates'] = $data['certificate_count'];
                
                // Get recent activities
                $data['recent_activities'] = $this->Enrollment_model->get_recent_enrollments(10);
                
            } elseif ($user_role === 'trainer') {
                $data['my_trainings'] = $this->db->where('instructor_id', $user_id)->count_all_results('trainings');
                
                $this->db->select('COUNT(*) as count');
                $this->db->from('enrollments e');
                $this->db->join('trainings t', 't.id = e.training_id');
                $this->db->where('t.instructor_id', $user_id);
                $result = $this->db->get()->row();
                $data['my_enrollments'] = $result->count;
                
                $data['my_certificates'] = 0;
                if ($this->db->table_exists('certificates')) {
                    $this->db->select('COUNT(*) as count');
                    $this->db->from('certificates c');
                    $this->db->join('trainings t', 't.id = c.training_id');
                    $this->db->where('t.instructor_id', $user_id);
                    $this->db->where('c.status', 'active');
                    $result = $this->db->get()->row();
                    $data['my_certificates'] = $result->count;
                }
                
                // Get activities for trainer's trainings
                $data['recent_activities'] = $this->Enrollment_model->get_all_enrollments(10);
                
            } else {
                // Regular user
                $data['my_trainings'] = $this->db->where('user_id', $user_id)->count_all_results('enrollments');
                $data['my_enrollments'] = $data['my_trainings'];
                
                $data['my_certificates'] = 0;
                $data['my_quiz_attempts'] = 0;
                $data['my_passed_quizzes'] = 0;
                
                if ($this->db->table_exists('certificates')) {
                    $data['my_certificates'] = $this->db->where('user_id', $user_id)->where('status', 'active')->count_all_results('certificates');
                }
                
                if ($this->db->table_exists('quiz_attempts')) {
                    $data['my_quiz_attempts'] = $this->db->where('user_id', $user_id)->where('status', 'submitted')->count_all_results('quiz_attempts');
                    $data['my_passed_quizzes'] = $this->db->where('user_id', $user_id)->where('status', 'submitted')->where('passed', 1)->count_all_results('quiz_attempts');
                }
                
                // Get user's recent activities
                $data['recent_activities'] = $this->Enrollment_model->get_user_enrollments($user_id);
                $data['recent_activities'] = array_slice($data['recent_activities'], 0, 5);
            }

            // Get notifications
            $data['unread_notifications'] = 0;
            $data['recent_notifications'] = [];
            
            if ($this->db->table_exists('notifications')) {
                $data['unread_notifications'] = $this->db->where('user_id', $user_id)->where('status', 'unread')->count_all_results('notifications');
                $this->db->where('user_id', $user_id)->order_by('created_at', 'DESC')->limit(5);
                $data['recent_notifications'] = $this->db->get('notifications')->result_array();
            }

        } catch (Exception $e) {
            log_message('error', 'Dashboard error: ' . $e->getMessage());
            redirect('install');
        }

        $this->load->view('templates/header', $data);
        $this->load->view('dashboard/index', $data);
        $this->load->view('templates/footer');
    }
}
