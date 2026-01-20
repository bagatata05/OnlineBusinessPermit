<?php
require_once __DIR__ . '/../config.php';

class Auth {
    private $conn;
    
    public function __construct() {
        $this->conn = getDBConnection();
    }
    
    public function login($username, $password, $remember = false) {
        $username = sanitize($username);
        
        $stmt = $this->conn->prepare("SELECT user_id, username, email, password_hash, first_name, last_name, contact_number, role, is_active FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if (!$user['is_active']) {
                return ['success' => false, 'message' => 'Account is deactivated.'];
            }
            
            if (password_verify($password, $user['password_hash'])) {
                // Set session variables
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];
                $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
                $_SESSION['contact_number'] = $user['contact_number'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['logged_in'] = true;
                $_SESSION['last_activity'] = time();
                
                // Update last login
                $this->updateLastLogin($user['user_id']);
                
                // Log activity
                logActivity($user['user_id'], 'login', 'User logged in from ' . $_SERVER['REMOTE_ADDR']);
                
                return ['success' => true, 'user' => $user];
            }
        }
        
        return ['success' => false, 'message' => 'Invalid username or password.'];
    }
    
    public function logout() {
        if (isset($_SESSION['user_id'])) {
            logActivity($_SESSION['user_id'], 'logout', 'User logged out');
        }
        
        // Clear session
        session_unset();
        session_destroy();
        
        // Clear remember token
        if (isset($_COOKIE['remember_token'])) {
            $this->clearRememberToken($_COOKIE['remember_token']);
            setcookie('remember_token', '', time() - 3600, '/', '', false, true);
        }
    }
    
    public function register($userData) {
        // Validate required fields
        $required = ['username', 'email', 'password', 'first_name', 'last_name', 'contact_number'];
        foreach ($required as $field) {
            if (empty($userData[$field])) {
                return ['success' => false, 'message' => ucfirst($field) . ' is required.'];
            }
        }
        
        // Check if username or email already exists
        if ($this->userExists($userData['username'], $userData['email'])) {
            return ['success' => false, 'message' => 'Username or email already exists.'];
        }
        
        // Validate email
        if (!filter_var($userData['email'], FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid email format.'];
        }
        
        // Validate password strength
        if (strlen($userData['password']) < 8) {
            return ['success' => false, 'message' => 'Password must be at least 8 characters long.'];
        }
        
        // Hash password
        $password_hash = password_hash($userData['password'], HASH_ALGO);
        
        // Insert user
        $stmt = $this->conn->prepare("INSERT INTO users (username, email, password_hash, first_name, last_name, contact_number, role) VALUES (?, ?, ?, ?, ?, ?, 'applicant')");
        $stmt->bind_param("ssssss", 
            $userData['username'], 
            $userData['email'], 
            $password_hash, 
            $userData['first_name'], 
            $userData['last_name'], 
            $userData['contact_number']
        );
        
        if ($stmt->execute()) {
            $user_id = $this->conn->insert_id;
            logActivity($user_id, 'register', 'New user registration');
            
            return ['success' => true, 'user_id' => $user_id, 'message' => 'Registration successful!'];
        } else {
            return ['success' => false, 'message' => 'Registration failed. Please try again.'];
        }
    }
    
    public function checkAuth() {
        if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
            // Check session timeout
            if (time() - $_SESSION['last_activity'] > SESSION_LIFETIME) {
                $this->logout();
                return false;
            }
            $_SESSION['last_activity'] = time();
            return true;
        }
        
        return false;
    }
    
    public function requireRole($required_role) {
        if (!$this->checkAuth()) {
            header('Location: index.php?page=login');
            exit();
        }
        
        $user_role = $_SESSION['role'];
        $role_hierarchy = ['applicant' => 1, 'staff' => 2, 'admin' => 3];
        
        if ($role_hierarchy[$user_role] < $role_hierarchy[$required_role]) {
            header('HTTP/1.0 403 Forbidden');
            echo 'Access denied. Insufficient privileges.';
            exit();
        }
    }
    
    private function userExists($username, $email) {
        $stmt = $this->conn->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->num_rows > 0;
    }
    
    private function updateLastLogin($user_id) {
        $stmt = $this->conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
    }
    
    private function clearRememberToken($token) {
        $stmt = $this->conn->prepare("DELETE FROM remember_tokens WHERE token = ?");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $stmt->close();
    }
    
    public function getCurrentUser() {
        if ($this->checkAuth()) {
            return [
                'user_id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'email' => $_SESSION['email'],
                'first_name' => $_SESSION['first_name'],
                'last_name' => $_SESSION['last_name'],
                'full_name' => $_SESSION['full_name'],
                'contact_number' => $_SESSION['contact_number'],
                'role' => $_SESSION['role']
            ];
        }
        return null;
    }
    
    public function updateProfile($user_id, $data) {
        $allowed_fields = ['first_name', 'last_name', 'contact_number', 'email'];
        $updates = [];
        $types = '';
        $values = [];
        
        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = ?";
                $types .= 's';
                $values[] = $data[$field];
            }
        }
        
        if (empty($updates)) {
            return ['success' => false, 'message' => 'No valid fields to update.'];
        }
        
        $types .= 'i';
        $values[] = $user_id;
        
        $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE user_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$values);
        
        if ($stmt->execute()) {
            logActivity($user_id, 'profile_update', 'User profile updated');
            return ['success' => true, 'message' => 'Profile updated successfully.'];
        } else {
            return ['success' => false, 'message' => 'Failed to update profile.'];
        }
    }
    
    public function changePassword($user_id, $current_password, $new_password) {
        // Verify current password
        $stmt = $this->conn->prepare("SELECT password_hash FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if (password_verify($current_password, $user['password_hash'])) {
                if (strlen($new_password) < 8) {
                    return ['success' => false, 'message' => 'New password must be at least 8 characters long.'];
                }
                
                $new_hash = password_hash($new_password, HASH_ALGO);
                $stmt = $this->conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
                $stmt->bind_param("si", $new_hash, $user_id);
                
                if ($stmt->execute()) {
                    logActivity($user_id, 'password_change', 'Password changed successfully');
                    return ['success' => true, 'message' => 'Password changed successfully.'];
                }
            } else {
                return ['success' => false, 'message' => 'Current password is incorrect.'];
            }
        }
        
        return ['success' => false, 'message' => 'Password change failed.'];
    }
}
?>
