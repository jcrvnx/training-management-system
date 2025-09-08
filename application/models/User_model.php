<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class User_model extends CI_Model {

    public function __construct() {
        parent::__construct();
        $this->load->database();
    }

    /**
     * Get all active users
     */
    public function get_all_users($limit = null, $offset = 0) {
        $this->db->where('is_active', 1);
        if ($limit) {
            $this->db->limit($limit, $offset);
        }
        $this->db->order_by('created_at', 'DESC');
        return $this->db->get('users')->result_array();
    }

    /**
     * Get user by ID
     */
    public function get_user($id) {
        $this->db->where('id', $id);
        $this->db->where('is_active', 1);
        return $this->db->get('users')->row_array();
    }

    /**
     * Get user by email
     */
    public function get_user_by_email($email) {
        $this->db->where('email', $email);
        $this->db->where('is_active', 1);
        return $this->db->get('users')->row_array();
    }

    /**
     * Create new user
     */
    public function create_user($data) {
        // Hash password if provided
        if (isset($data['password'])) {
            $data['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
            unset($data['password']);
        }
        
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        return $this->db->insert('users', $data);
    }

    /**
     * Update user
     */
    public function update_user($id, $data) {
        // Hash password if provided
        if (isset($data['password'])) {
            $data['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
            unset($data['password']);
        }
        
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        $this->db->where('id', $id);
        return $this->db->update('users', $data);
    }

    /**
     * Delete user (soft delete)
     */
    public function delete_user($id) {
        $data = array(
            'is_active' => 0,
            'updated_at' => date('Y-m-d H:i:s')
        );
        
        $this->db->where('id', $id);
        return $this->db->update('users', $data);
    }

    /**
     * Verify user password
     */
    public function verify_password($email, $password) {
        $user = $this->get_user_by_email($email);
        
        if (!$user) {
            return false;
        }
        
        return password_verify($password, $user['password_hash']);
    }

    /**
     * Update last login
     */
    public function update_last_login($user_id) {
        $data = array(
            'last_login' => date('Y-m-d H:i:s')
        );
        
        $this->db->where('id', $user_id);
        return $this->db->update('users', $data);
    }

    /**
     * Get users by role
     */
    public function get_users_by_role($role) {
        $this->db->where('role', $role);
        $this->db->where('is_active', 1);
        $this->db->order_by('full_name', 'ASC');
        return $this->db->get('users')->result_array();
    }

    /**
     * Get user with permissions
     */
    public function get_user_with_permissions($user_id) {
        // Check if permission tables exist
        if (!$this->db->table_exists('user_permissions') || !$this->db->table_exists('role_permissions')) {
            return $this->get_user($user_id);
        }

        $this->db->select('u.*, 
                          GROUP_CONCAT(DISTINCT up.permission_name) as user_permissions,
                          GROUP_CONCAT(DISTINCT rp_perm.permission_name) as role_permissions');
        $this->db->from('users u');
        $this->db->join('user_permissions up', 'u.id = up.user_id', 'left');
        $this->db->join('role_permissions rp', 'u.role = rp.role', 'left');
        $this->db->join('user_permissions rp_perm', 'rp.permission_id = rp_perm.id', 'left');
        $this->db->where('u.id', $user_id);
        $this->db->where('u.is_active', 1);
        $this->db->group_by('u.id');
        
        return $this->db->get()->row_array();
    }

    /**
     * Check if user has permission
     */
    public function has_permission($user_id, $permission) {
        $user = $this->get_user_with_permissions($user_id);
        
        if (!$user) {
            return false;
        }

        // Check user-specific permissions
        if (!empty($user['user_permissions'])) {
            $user_permissions = explode(',', $user['user_permissions']);
            if (in_array($permission, $user_permissions)) {
                return true;
            }
        }

        // Check role-based permissions
        if (!empty($user['role_permissions'])) {
            $role_permissions = explode(',', $user['role_permissions']);
            if (in_array($permission, $role_permissions)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get user statistics
     */
    public function get_user_stats() {
        $total = $this->db->where('is_active', 1)->count_all_results('users');
        $admins = $this->db->where('role', 'admin')->where('is_active', 1)->count_all_results('users');
        $trainers = $this->db->where('role', 'trainer')->where('is_active', 1)->count_all_results('users');
        $students = $this->db->where('role', 'user')->where('is_active', 1)->count_all_results('users');

        return array(
            'total' => $total,
            'admins' => $admins,
            'trainers' => $trainers,
            'students' => $students
        );
    }

    /**
     * Search users
     */
    public function search_users($search, $role = null) {
        $this->db->like('full_name', $search);
        $this->db->or_like('email', $search);
        $this->db->or_like('department', $search);
        $this->db->or_like('position', $search);
        
        if ($role) {
            $this->db->where('role', $role);
        }
        
        $this->db->where('is_active', 1);
        $this->db->order_by('full_name', 'ASC');
        
        return $this->db->get('users')->result_array();
    }

    /**
     * Validate user data
     */
    public function validate_user_data($data, $user_id = null) {
        $this->form_validation->set_data($data);
        
        $this->form_validation->set_rules('full_name', 'Full Name', 'required|min_length[2]|max_length[100]');
        
        if ($user_id) {
            // For updates, check email uniqueness excluding current user
            $this->form_validation->set_rules('email', 'Email', 'required|valid_email|callback_check_email_unique[' . $user_id . ']');
        } else {
            // For new users, check email uniqueness
            $this->form_validation->set_rules('email', 'Email', 'required|valid_email|is_unique[users.email]');
            $this->form_validation->set_rules('password', 'Password', 'required|min_length[6]');
        }
        
        $this->form_validation->set_rules('role', 'Role', 'required|in_list[admin,trainer,user]');
        $this->form_validation->set_rules('phone', 'Phone', 'permit_empty|min_length[10]|max_length[15]');
        $this->form_validation->set_rules('department', 'Department', 'permit_empty|max_length[100]');
        $this->form_validation->set_rules('position', 'Position', 'permit_empty|max_length[100]');
        
        return $this->form_validation->run();
    }

    /**
     * Check email uniqueness for updates
     */
    public function check_email_unique($email, $user_id) {
        $this->db->where('email', $email);
        $this->db->where('id !=', $user_id);
        $query = $this->db->get('users');
        
        if ($query->num_rows() > 0) {
            $this->form_validation->set_message('check_email_unique', 'The {field} field must contain a unique value.');
            return false;
        }
        
        return true;
    }
}
