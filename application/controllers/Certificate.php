<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Certificate extends CI_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->model(['Training_model', 'User_model', 'Enrollment_model']);
        $this->load->library(['session', 'form_validation']);
        $this->load->helper(['url', 'form', 'security', 'download']);
        
        // Check authentication
        if (!$this->session->userdata('logged_in')) {
            redirect('auth/login');
        }
    }

    public function index() {
        $user_role = $this->session->userdata('user_role');
        $user_id = $this->session->userdata('user_id');

        $data = ['page_title' => 'Certificate Management'];

        // Check if certificates table exists
        if (!$this->db->table_exists('certificates')) {
            $data['certificates'] = [];
            $data['message'] = 'Certificate system not yet configured.';
        } else {
            // Get certificates based on role
            if ($user_role === 'admin') {
                $this->db->select('c.*, u.full_name as user_name, t.title as training_title');
                $this->db->from('certificates c');
                $this->db->join('users u', 'u.id = c.user_id');
                $this->db->join('trainings t', 't.id = c.training_id');
                $this->db->where('c.status', 'active');
                $this->db->order_by('c.issued_date', 'DESC');
                $data['certificates'] = $this->db->get()->result_array();
            } elseif ($user_role === 'trainer') {
                // Get certificates for trainer's courses
                $this->db->select('c.*, u.full_name as user_name, t.title as training_title');
                $this->db->from('certificates c');
                $this->db->join('users u', 'u.id = c.user_id');
                $this->db->join('trainings t', 't.id = c.training_id');
                $this->db->where('t.instructor_id', $user_id);
                $this->db->where('c.status', 'active');
                $this->db->order_by('c.issued_date', 'DESC');
                $data['certificates'] = $this->db->get()->result_array();
            } else {
                // Regular users see their own certificates
                $this->db->select('c.*, t.title as training_title, t.description');
                $this->db->from('certificates c');
                $this->db->join('trainings t', 't.id = c.training_id');
                $this->db->where('c.user_id', $user_id);
                $this->db->where('c.status', 'active');
                $this->db->order_by('c.issued_date', 'DESC');
                $data['certificates'] = $this->db->get()->result_array();
            }
        }

        $data['user_role'] = $user_role;

        $this->load->view('templates/header', $data);
        $this->load->view('certificate/index', $data);
        $this->load->view('templates/footer');
    }

    public function view($id = null) {
        if (!$id || !$this->db->table_exists('certificates')) {
            redirect('certificate');
        }

        $this->db->select('c.*, u.full_name as user_name, u.email as user_email, 
                          t.title as training_title, t.description, t.start_date, t.end_date,
                          i.full_name as instructor_name');
        $this->db->from('certificates c');
        $this->db->join('users u', 'u.id = c.user_id');
        $this->db->join('trainings t', 't.id = c.training_id');
        $this->db->join('users i', 'i.id = t.instructor_id', 'left');
        $this->db->where('c.id', $id);
        $certificate = $this->db->get()->row_array();

        if (!$certificate) {
            $this->session->set_flashdata('error', 'Certificate not found.');
            redirect('certificate');
        }

        $user_role = $this->session->userdata('user_role');
        $user_id = $this->session->userdata('user_id');

        // Check permissions
        if ($user_role === 'user' && $certificate['user_id'] != $user_id) {
            redirect('auth/unauthorized');
        }

        $data = [
            'page_title' => 'Certificate - ' . $certificate['training_title'],
            'certificate' => $certificate,
            'user_role' => $user_role
        ];

        $this->load->view('templates/header', $data);
        $this->load->view('certificate/view', $data);
        $this->load->view('templates/footer');
    }

    public function download($id = null) {
        if (!$id || !$this->db->table_exists('certificates')) {
            redirect('certificate');
        }

        $this->db->select('c.*, u.full_name as user_name, t.title as training_title, 
                          t.description, i.full_name as instructor_name');
        $this->db->from('certificates c');
        $this->db->join('users u', 'u.id = c.user_id');
        $this->db->join('trainings t', 't.id = c.training_id');
        $this->db->join('users i', 'i.id = t.instructor_id', 'left');
        $this->db->where('c.id', $id);
        $certificate = $this->db->get()->row_array();

        if (!$certificate) {
            $this->session->set_flashdata('error', 'Certificate not found.');
            redirect('certificate');
        }

        $user_role = $this->session->userdata('user_role');
        $user_id = $this->session->userdata('user_id');

        // Check permissions
        if ($user_role === 'user' && $certificate['user_id'] != $user_id) {
            redirect('auth/unauthorized');
        }

        // Generate PDF certificate (simplified version)
        $this->load->library('pdf');
        
        // Create certificate HTML
        $html = $this->generate_certificate_html($certificate);
        
        // Generate PDF
        $filename = 'certificate_' . $certificate['certificate_number'] . '.pdf';
        
        // In a real implementation, you would use a proper PDF library
        // For now, we'll just force download the HTML version
        force_download($filename, $html);
    }

    public function my_certificates() {
        $user_id = $this->session->userdata('user_id');
        
        $data = ['page_title' => 'My Certificates'];

        if (!$this->db->table_exists('certificates')) {
            $data['certificates'] = [];
            $data['message'] = 'Certificate system not yet configured.';
        } else {
            $this->db->select('c.*, t.title as training_title, t.description, t.completion_date');
            $this->db->from('certificates c');
            $this->db->join('trainings t', 't.id = c.training_id');
            $this->db->where('c.user_id', $user_id);
            $this->db->where('c.status', 'active');
            $this->db->order_by('c.issued_date', 'DESC');
            $data['certificates'] = $this->db->get()->result_array();
        }

        $this->load->view('templates/header', $data);
        $this->load->view('certificate/my_certificates', $data);
        $this->load->view('templates/footer');
    }

    public function generate($training_id = null, $user_id = null) {
        $current_user_role = $this->session->userdata('user_role');
        if (!in_array($current_user_role, ['admin', 'trainer']) || !$training_id || !$user_id) {
            redirect('auth/unauthorized');
        }

        if (!$this->db->table_exists('certificates')) {
            $this->session->set_flashdata('error', 'Certificate system not configured.');
            redirect('training/view/' . $training_id);
        }

        // Check if user completed the training
        $this->db->where('user_id', $user_id);
        $this->db->where('training_id', $training_id);
        $this->db->where('status', 'completed');
        $enrollment = $this->db->get('enrollments')->row_array();

        if (!$enrollment) {
            $this->session->set_flashdata('error', 'User has not completed this training.');
            redirect('training/view/' . $training_id);
        }

        // Check if certificate already exists
        $this->db->where('user_id', $user_id);
        $this->db->where('training_id', $training_id);
        $existing_certificate = $this->db->get('certificates')->row_array();

        if ($existing_certificate) {
            $this->session->set_flashdata('warning', 'Certificate already exists for this user.');
            redirect('certificate/view/' . $existing_certificate['id']);
        }

        // Generate certificate
        $certificate_data = [
            'user_id' => $user_id,
            'training_id' => $training_id,
            'certificate_number' => $this->generate_certificate_number(),
            'issued_date' => date('Y-m-d'),
            'status' => 'active',
            'template_id' => 1, // Default template
            'issued_by' => $this->session->userdata('user_id')
        ];

        if ($this->db->insert('certificates', $certificate_data)) {
            $certificate_id = $this->db->insert_id();
            $this->session->set_flashdata('success', 'Certificate generated successfully!');
            redirect('certificate/view/' . $certificate_id);
        } else {
            $this->session->set_flashdata('error', 'Failed to generate certificate.');
            redirect('training/view/' . $training_id);
        }
    }

    private function generate_certificate_number() {
        return 'CERT-' . date('Y') . '-' . strtoupper(substr(md5(uniqid()), 0, 8));
    }

    private function generate_certificate_html($certificate) {
        $html = '<!DOCTYPE html>
        <html>
        <head>
            <title>Certificate of Completion</title>
            <style>
                body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
                .certificate { border: 10px solid #4CAF50; padding: 50px; margin: 20px; }
                .header { font-size: 48px; color: #4CAF50; margin-bottom: 20px; }
                .title { font-size: 24px; margin: 20px 0; }
                .name { font-size: 36px; color: #333; margin: 30px 0; }
                .course { font-size: 28px; color: #4CAF50; margin: 20px 0; }
                .date { font-size: 18px; margin: 30px 0; }
                .signature { margin-top: 50px; }
            </style>
        </head>
        <body>
            <div class="certificate">
                <div class="header">Certificate of Completion</div>
                <div class="title">This is to certify that</div>
                <div class="name">' . $certificate['user_name'] . '</div>
                <div class="title">has successfully completed the training course</div>
                <div class="course">' . $certificate['training_title'] . '</div>
                <div class="date">on ' . date('F j, Y', strtotime($certificate['issued_date'])) . '</div>
                <div class="signature">
                    <div>' . ($certificate['instructor_name'] ?? 'Training Administrator') . '</div>
                    <div>Instructor</div>
                </div>
                <div style="margin-top: 30px; font-size: 12px; color: #666;">
                    Certificate #: ' . $certificate['certificate_number'] . '
                </div>
            </div>
        </body>
        </html>';
        
        return $html;
    }
}
