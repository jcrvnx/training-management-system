<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Quiz_model extends CI_Model {

    public function __construct() {
        parent::__construct();
        $this->load->database();
    }

    /**
     * Get all active quizzes
     */
    public function get_all_quizzes($limit = null, $offset = 0) {
        $this->db->select('q.*, t.title as training_title, u.full_name as created_by_name');
        $this->db->from('quizzes q');
        $this->db->join('trainings t', 't.id = q.training_id', 'left');
        $this->db->join('users u', 'u.id = q.created_by', 'left');
        $this->db->where('q.is_active', 1);
        
        if ($limit) {
            $this->db->limit($limit, $offset);
        }
        
        $this->db->order_by('q.created_at', 'DESC');
        return $this->db->get()->result_array();
    }

    /**
     * Get quiz by ID
     */
    public function get_quiz($id) {
        $this->db->select('q.*, t.title as training_title');
        $this->db->from('quizzes q');
        $this->db->join('trainings t', 't.id = q.training_id', 'left');
        $this->db->where('q.id', $id);
        return $this->db->get()->row_array();
    }

    /**
     * Get quiz with questions and options
     */
    public function get_quiz_with_questions($quiz_id) {
        $quiz = $this->get_quiz($quiz_id);
        if (!$quiz) {
            return null;
        }

        // Get questions
        if ($this->db->table_exists('quiz_questions')) {
            $quiz['questions'] = $this->get_quiz_questions($quiz_id);
        }

        return $quiz;
    }

    /**
     * Get quiz questions with options
     */
    public function get_quiz_questions($quiz_id) {
        if (!$this->db->table_exists('quiz_questions')) {
            return array();
        }

        $this->db->where('quiz_id', $quiz_id);
        $this->db->order_by('question_order', 'ASC');
        $questions = $this->db->get('quiz_questions')->result_array();

        // Get options for each question
        foreach ($questions as &$question) {
            if ($this->db->table_exists('quiz_question_options')) {
                $this->db->where('question_id', $question['id']);
                $this->db->order_by('option_order', 'ASC');
                $question['options'] = $this->db->get('quiz_question_options')->result_array();
            } else {
                $question['options'] = array();
            }
        }

        return $questions;
    }

    /**
     * Create new quiz
     */
    public function create_quiz($data) {
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        $data['is_active'] = 1;
        
        return $this->db->insert('quizzes', $data);
    }

    /**
     * Update quiz
     */
    public function update_quiz($id, $data) {
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        $this->db->where('id', $id);
        return $this->db->update('quizzes', $data);
    }

    /**
     * Delete quiz (soft delete)
     */
    public function delete_quiz($id) {
        $data = array(
            'is_active' => 0,
            'updated_at' => date('Y-m-d H:i:s')
        );
        
        $this->db->where('id', $id);
        return $this->db->update('quizzes', $data);
    }

    /**
     * Get quizzes by training
     */
    public function get_quizzes_by_training($training_id) {
        $this->db->where('training_id', $training_id);
        $this->db->where('is_active', 1);
        $this->db->order_by('created_at', 'DESC');
        return $this->db->get('quizzes')->result_array();
    }

    /**
     * Get user quiz attempts
     */
    public function get_user_attempts($quiz_id, $user_id) {
        if (!$this->db->table_exists('quiz_attempts')) {
            return array();
        }

        $this->db->where('quiz_id', $quiz_id);
        $this->db->where('user_id', $user_id);
        $this->db->order_by('attempt_date', 'DESC');
        return $this->db->get('quiz_attempts')->result_array();
    }

    /**
     * Check if user can take quiz
     */
    public function can_user_take_quiz($quiz_id, $user_id) {
        $quiz = $this->get_quiz($quiz_id);
        if (!$quiz || !$quiz['is_active']) {
            return array('can_take' => false, 'reason' => 'Quiz not available');
        }

        $attempts = $this->get_user_attempts($quiz_id, $user_id);
        
        if ($quiz['max_attempts'] && count($attempts) >= $quiz['max_attempts']) {
            return array('can_take' => false, 'reason' => 'Maximum attempts reached');
        }

        // Check if user passed
        foreach ($attempts as $attempt) {
            if ($attempt['passed']) {
                return array('can_take' => false, 'reason' => 'Quiz already passed');
            }
        }

        return array(
            'can_take' => true, 
            'attempts_left' => $quiz['max_attempts'] ? $quiz['max_attempts'] - count($attempts) : null
        );
    }

    /**
     * Start quiz attempt
     */
    public function start_quiz_attempt($quiz_id, $user_id) {
        if (!$this->db->table_exists('quiz_attempts')) {
            return false;
        }

        $data = array(
            'quiz_id' => $quiz_id,
            'user_id' => $user_id,
            'status' => 'in_progress',
            'attempt_date' => date('Y-m-d H:i:s'),
            'started_at' => date('Y-m-d H:i:s')
        );

        return $this->db->insert('quiz_attempts', $data);
    }

    /**
     * Submit quiz attempt
     */
    public function submit_quiz_attempt($attempt_id, $answers, $score) {
        if (!$this->db->table_exists('quiz_attempts')) {
            return false;
        }

        $this->db->trans_start();

        // Update attempt
        $quiz_attempt = $this->db->where('id', $attempt_id)->get('quiz_attempts')->row_array();
        if (!$quiz_attempt) {
            return false;
        }

        $quiz = $this->get_quiz($quiz_attempt['quiz_id']);
        $passed = ($score >= $quiz['pass_percentage']);

        $update_data = array(
            'status' => 'submitted',
            'completed_at' => date('Y-m-d H:i:s'),
            'score' => $score,
            'passed' => $passed ? 1 : 0
        );

        $this->db->where('id', $attempt_id)->update('quiz_attempts', $update_data);

        // Save answers
        if ($this->db->table_exists('quiz_answers') && !empty($answers)) {
            foreach ($answers as $question_id => $answer) {
                $answer_data = array(
                    'attempt_id' => $attempt_id,
                    'question_id' => $question_id,
                    'selected_option_id' => $answer['selected_option_id'],
                    'answer_text' => isset($answer['answer_text']) ? $answer['answer_text'] : null,
                    'is_correct' => isset($answer['is_correct']) ? $answer['is_correct'] : 0
                );
                $this->db->insert('quiz_answers', $answer_data);
            }
        }

        $this->db->trans_complete();
        return $this->db->trans_status();
    }

    /**
     * Get quiz statistics
     */
    public function get_quiz_stats($quiz_id) {
        if (!$this->db->table_exists('quiz_attempts')) {
            return array(
                'total_attempts' => 0,
                'passed_attempts' => 0,
                'pass_rate' => 0,
                'average_score' => 0
            );
        }

        $total_attempts = $this->db->where('quiz_id', $quiz_id)
                                  ->where('status', 'submitted')
                                  ->count_all_results('quiz_attempts');
        
        $passed_attempts = $this->db->where('quiz_id', $quiz_id)
                                   ->where('status', 'submitted')
                                   ->where('passed', 1)
                                   ->count_all_results('quiz_attempts');
        
        $avg_score = $this->db->select_avg('score')
                             ->where('quiz_id', $quiz_id)
                             ->where('status', 'submitted')
                             ->get('quiz_attempts')
                             ->row()->score ?? 0;

        return array(
            'total_attempts' => $total_attempts,
            'passed_attempts' => $passed_attempts,
            'pass_rate' => $total_attempts > 0 ? round(($passed_attempts / $total_attempts) * 100, 2) : 0,
            'average_score' => round($avg_score, 2)
        );
    }

    /**
     * Validate quiz data
     */
    public function validate_quiz_data($data) {
        $this->form_validation->set_data($data);
        
        $this->form_validation->set_rules('title', 'Title', 'required|min_length[3]|max_length[200]');
        $this->form_validation->set_rules('training_id', 'Training', 'required|integer');
        $this->form_validation->set_rules('pass_percentage', 'Pass Percentage', 'required|integer|greater_than[0]|less_than_equal_to[100]');
        $this->form_validation->set_rules('time_limit', 'Time Limit', 'permit_empty|integer|greater_than[0]');
        $this->form_validation->set_rules('max_attempts', 'Max Attempts', 'permit_empty|integer|greater_than[0]');
        
        return $this->form_validation->run();
    }

    /**
     * Get quiz results for user
     */
    public function get_quiz_results($quiz_id, $user_id) {
        if (!$this->db->table_exists('quiz_attempts')) {
            return array();
        }

        $this->db->select('qa.*, q.title as quiz_title, q.pass_percentage');
        $this->db->from('quiz_attempts qa');
        $this->db->join('quizzes q', 'q.id = qa.quiz_id');
        $this->db->where('qa.quiz_id', $quiz_id);
        $this->db->where('qa.user_id', $user_id);
        $this->db->where('qa.status', 'submitted');
        $this->db->order_by('qa.attempt_date', 'DESC');
        return $this->db->get()->result_array();
    }
}
