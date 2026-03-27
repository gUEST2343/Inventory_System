<?php
/**
 * User Class
 * Handles user-related operations
 */

require_once __DIR__ . '/Database.php';

class User {
    private $db;
    private $table = 'users';
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Create new user
     */
    public function create($data) {
        $required = ['username', 'email', 'password', 'full_name'];
        
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }
        
        // Check if username exists
        if ($this->usernameExists($data['username'])) {
            throw new Exception("Username already exists");
        }
        
        // Check if email exists
        if ($this->emailExists($data['email'])) {
            throw new Exception("Email already exists");
        }
        
        $userData = [
            'username' => $data['username'],
            'email' => $data['email'],
            'full_name' => $data['full_name'],
            'password' => password_hash($data['password'], PASSWORD_BCRYPT),
            'role' => $data['role'] ?? 'staff',
            'is_active' => $data['is_active'] ?? 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        return $this->db->insert($this->table, $userData);
    }
    
    /**
     * Get user by ID
     */
    public function getById($id) {
        return $this->db->fetchOne(
            "SELECT * FROM {$this->table} WHERE id = ?",
            [$id]
        );
    }
    
    /**
     * Get user by username
     */
    public function getByUsername($username) {
        return $this->db->fetchOne(
            "SELECT * FROM {$this->table} WHERE username = ?",
            [$username]
        );
    }
    
    /**
     * Get user by email
     */
    public function getByEmail($email) {
        return $this->db->fetchOne(
            "SELECT * FROM {$this->table} WHERE email = ?",
            [$email]
        );
    }
    
    /**
     * Get all users
     */
    public function getAll($limit = 100, $offset = 0) {
        return $this->db->fetchAll(
            "SELECT id, username, email, full_name, role, is_active, created_at, updated_at 
             FROM {$this->table} 
             ORDER BY created_at DESC 
             LIMIT ? OFFSET ?",
            [$limit, $offset]
        );
    }
    
    /**
     * Get users by role
     */
    public function getByRole($role) {
        return $this->db->fetchAll(
            "SELECT * FROM {$this->table} WHERE role = ? ORDER BY created_at DESC",
            [$role]
        );
    }
    
    /**
     * Get active users
     */
    public function getActiveUsers() {
        return $this->db->fetchAll(
            "SELECT * FROM {$this->table} WHERE is_active = 1 ORDER BY full_name"
        );
    }
    
    /**
     * Update user
     */
    public function update($id, $data) {
        $allowedFields = ['username', 'email', 'full_name', 'role', 'is_active'];
        $updateData = [];
        
        foreach ($data as $key => $value) {
            if (in_array($key, $allowedFields)) {
                $updateData[$key] = $value;
            }
        }
        
        if (isset($data['password']) && !empty($data['password'])) {
            $updateData['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
        }
        
        $updateData['updated_at'] = date('Y-m-d H:i:s');
        
        return $this->db->update($this->table, $updateData, 'id = ?', [$id]);
    }
    
    /**
     * Delete user
     */
    public function delete($id) {
        return $this->db->delete($this->table, 'id = ?', [$id]);
    }
    
    /**
     * Soft delete (deactivate) user
     */
    public function deactivate($id) {
        return $this->db->update(
            $this->table,
            ['is_active' => 0, 'updated_at' => date('Y-m-d H:i:s')],
            'id = ?',
            [$id]
        );
    }
    
    /**
     * Activate user
     */
    public function activate($id) {
        return $this->db->update(
            $this->table,
            ['is_active' => 1, 'updated_at' => date('Y-m-d H:i:s')],
            'id = ?',
            [$id]
        );
    }
    
    /**
     * Check if username exists
     */
    public function usernameExists($username, $excludeId = null) {
        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE username = ?";
        $params = [$username];
        
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        return $this->db->fetchColumn($sql, $params) > 0;
    }
    
    /**
     * Check if email exists
     */
    public function emailExists($email, $excludeId = null) {
        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE email = ?";
        $params = [$email];
        
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        return $this->db->fetchColumn($sql, $params) > 0;
    }
    
    /**
     * Verify password
     */
    public function verifyPassword($id, $password) {
        $user = $this->getById($id);
        
        if (!$user) {
            return false;
        }
        
        return password_verify($password, $user['password']);
    }
    
    /**
     * Change password
     */
    public function changePassword($id, $newPassword) {
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
        
        return $this->db->update(
            $this->table,
            ['password' => $hashedPassword, 'updated_at' => date('Y-m-d H:i:s')],
            'id = ?',
            [$id]
        );
    }
    
    /**
     * Reset password (admin function)
     */
    public function resetPassword($id, $newPassword) {
        return $this->changePassword($id, $newPassword);
    }
    
    /**
     * Get user count
     */
    public function count($activeOnly = false) {
        $sql = "SELECT COUNT(*) FROM {$this->table}";
        
        if ($activeOnly) {
            $sql .= " WHERE is_active = 1";
        }
        
        return $this->db->fetchColumn($sql);
    }
    
    /**
     * Search users
     */
    public function search($keyword) {
        $keyword = "%{$keyword}%";
        
        return $this->db->fetchAll(
            "SELECT * FROM {$this->table} 
             WHERE username LIKE ? OR email LIKE ? OR full_name LIKE ?
             ORDER BY full_name",
            [$keyword, $keyword, $keyword]
        );
    }
    
    /**
     * Get users with pagination
     */
    public function getPaginated($page = 1, $perPage = 25, $search = '') {
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT * FROM {$this->table}";
        $countSql = "SELECT COUNT(*) FROM {$this->table}";
        $params = [];
        
        if (!empty($search)) {
            $keyword = "%{$search}%";
            $where = " WHERE username LIKE ? OR email LIKE ? OR full_name LIKE ?";
            $sql .= $where;
            $countSql .= $where;
            $params = [$keyword, $keyword, $keyword];
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        
        $total = $this->db->fetchColumn($countSql, $params);
        $users = $this->db->fetchAll($sql, array_merge($params, [$perPage, $offset]));
        
        return [
            'users' => $users,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage)
        ];
    }
    
    /**
     * Update last login
     */
    public function updateLastLogin($id) {
        return $this->db->update(
            $this->table,
            ['updated_at' => date('Y-m-d H:i:s')],
            'id = ?',
            [$id]
        );
    }
    
    /**
     * Get user roles
     */
    public function getRoles() {
        return [
            'admin' => 'Administrator',
            'manager' => 'Manager',
            'staff' => 'Staff',
            'customer' => 'Customer'
        ];
    }
    
    /**
     * Check permission
     */
    public function hasPermission($userId, $permission) {
        $user = $this->getById($userId);
        
        if (!$user || !$user['is_active']) {
            return false;
        }
        
        // Admin has all permissions
        if ($user['role'] === 'admin') {
            return true;
        }
        
        // Define role permissions
        $permissions = [
            'admin' => ['*'],
            'manager' => ['users.view', 'users.edit', 'products.manage', 'reports.view', 'reports.export'],
            'staff' => ['products.view', 'products.edit', 'products.add', 'stock.manage'],
            'customer' => ['products.view', 'orders.create']
        ];
        
        $userPermissions = $permissions[$user['role']] ?? [];
        
        // Check if user has the permission
        return in_array('*', $userPermissions) || in_array($permission, $userPermissions);
    }
}
