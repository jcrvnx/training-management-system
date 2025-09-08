<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Training Management System Helper Functions
 */

if (!function_exists('is_logged_in')) {
    /**
     * Check if user is logged in
     */
    function is_logged_in() {
        $CI =& get_instance();
        return $CI->session->userdata('logged_in') ? true : false;
    }
}

if (!function_exists('get_current_user')) {
    /**
     * Get current logged in user
     */
    function get_current_user() {
        $CI =& get_instance();
        if (is_logged_in()) {
            $CI->load->model('User_model');
            return $CI->User_model->get_user($CI->session->userdata('user_id'));
        }
        return null;
    }
}

if (!function_exists('get_current_user_role')) {
    /**
     * Get current user role
     */
    function get_current_user_role() {
        $CI =& get_instance();
        return $CI->session->userdata('user_role');
    }
}

if (!function_exists('has_role')) {
    /**
     * Check if current user has specific role
     */
    function has_role($role) {
        return get_current_user_role() === $role;
    }
}

if (!function_exists('is_admin')) {
    /**
     * Check if current user is admin
     */
    function is_admin() {
        return has_role('admin');
    }
}

if (!function_exists('is_trainer')) {
    /**
     * Check if current user is trainer
     */
    function is_trainer() {
        return has_role('trainer');
    }
}

if (!function_exists('is_user')) {
    /**
     * Check if current user is regular user
     */
    function is_user() {
        return has_role('user');
    }
}

if (!function_exists('require_login')) {
    /**
     * Require user to be logged in
     */
    function require_login() {
        if (!is_logged_in()) {
            $CI =& get_instance();
            redirect('auth/login');
        }
    }
}

if (!function_exists('require_role')) {
    /**
     * Require specific role
     */
    function require_role($role) {
        require_login();
        if (!has_role($role)) {
            $CI =& get_instance();
            redirect('auth/unauthorized');
        }
    }
}

if (!function_exists('format_date')) {
    /**
     * Format date for display
     */
    function format_date($date, $format = 'M j, Y g:i A') {
        if (empty($date) || $date === '0000-00-00 00:00:00') {
            return 'N/A';
        }
        return date($format, strtotime($date));
    }
}

if (!function_exists('time_ago')) {
    /**
     * Get time ago string
     */
    function time_ago($datetime) {
        $time = time() - strtotime($datetime);
        
        if ($time < 60) return 'just now';
        if ($time < 3600) return floor($time/60).' minutes ago';
        if ($time < 86400) return floor($time/3600).' hours ago';
        if ($time < 2592000) return floor($time/86400).' days ago';
        if ($time < 31536000) return floor($time/2592000).' months ago';
        
        return floor($time/31536000).' years ago';
    }
}

if (!function_exists('get_status_badge')) {
    /**
     * Get bootstrap badge class for status
     */
    function get_status_badge($status) {
        $badges = [
            'enrolled' => 'badge-primary',
            'in_progress' => 'badge-warning',
            'completed' => 'badge-success',
            'withdrawn' => 'badge-danger',
            'active' => 'badge-success',
            'inactive' => 'badge-secondary',
            'pending' => 'badge-warning',
            'approved' => 'badge-success',
            'rejected' => 'badge-danger'
        ];
        
        return isset($badges[$status]) ? $badges[$status] : 'badge-secondary';
    }
}

if (!function_exists('get_difficulty_badge')) {
    /**
     * Get bootstrap badge class for difficulty level
     */
    function get_difficulty_badge($level) {
        $badges = [
            'beginner' => 'badge-success',
            'intermediate' => 'badge-warning',
            'advanced' => 'badge-danger'
        ];
        
        return isset($badges[$level]) ? $badges[$level] : 'badge-secondary';
    }
}

if (!function_exists('truncate_text')) {
    /**
     * Truncate text to specified length
     */
    function truncate_text($text, $length = 100, $suffix = '...') {
        if (strlen($text) <= $length) {
            return $text;
        }
        
        return substr($text, 0, $length) . $suffix;
    }
}

if (!function_exists('generate_csrf_token')) {
    /**
     * Generate CSRF token
     */
    function generate_csrf_token() {
        $CI =& get_instance();
        return $CI->security->get_csrf_hash();
    }
}

if (!function_exists('csrf_field')) {
    /**
     * Generate CSRF hidden field
     */
    function csrf_field() {
        $CI =& get_instance();
        return '<input type="hidden" name="'.$CI->security->get_csrf_token_name().'" value="'.$CI->security->get_csrf_hash().'" />';
    }
}

if (!function_exists('flash_message')) {
    /**
     * Display flash messages
     */
    function flash_message($type = '') {
        $CI =& get_instance();
        $output = '';
        
        if ($type) {
            $message = $CI->session->flashdata($type);
            if ($message) {
                $alert_class = ($type === 'error') ? 'alert-danger' : 'alert-'.$type;
                $output = '<div class="alert '.$alert_class.' alert-dismissible fade show" role="alert">';
                $output .= htmlspecialchars($message);
                $output .= '<button type="button" class="close" data-dismiss="alert" aria-label="Close">';
                $output .= '<span aria-hidden="true">&times;</span>';
                $output .= '</button>';
                $output .= '</div>';
            }
        } else {
            // Display all flash messages
            $types = ['success', 'error', 'warning', 'info'];
            foreach ($types as $msg_type) {
                $output .= flash_message($msg_type);
            }
        }
        
        return $output;
    }
}

if (!function_exists('sanitize_filename')) {
    /**
     * Sanitize filename for uploads
     */
    function sanitize_filename($filename) {
        // Remove special characters
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        // Remove multiple underscores
        $filename = preg_replace('/_+/', '_', $filename);
        // Remove leading/trailing underscores
        $filename = trim($filename, '_');
        
        return $filename;
    }
}

if (!function_exists('format_bytes')) {
    /**
     * Format bytes to human readable format
     */
    function format_bytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}

if (!function_exists('calculate_percentage')) {
    /**
     * Calculate percentage
     */
    function calculate_percentage($part, $total) {
        if ($total == 0) return 0;
        return round(($part / $total) * 100, 2);
    }
}

if (!function_exists('get_progress_bar_class')) {
    /**
     * Get progress bar CSS class based on percentage
     */
    function get_progress_bar_class($percentage) {
        if ($percentage >= 100) return 'bg-success';
        if ($percentage >= 75) return 'bg-info';
        if ($percentage >= 50) return 'bg-warning';
        return 'bg-danger';
    }
}
