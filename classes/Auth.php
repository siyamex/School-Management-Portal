<?php
/**
 * Authentication Class
 * Handles user authentication, registration, and session management
 */

class Auth {
    /**
     * Register a new user
     * 
     * @param array $userData User data
     * @return array Result with success status and message
     */
    public static function register($userData) {
        global $pdo;
        
        try {
            // Validate required fields
            $required = ['email', 'full_name', 'password'];
            foreach ($required as $field) {
                if (empty($userData[$field])) {
                    return ['success' => false, 'message' => ucfirst($field) . ' is required'];
                }
            }
            
            // Validate email
            if (!isValidEmail($userData['email'])) {
                return ['success' => false, 'message' => 'Invalid email address'];
            }
            
            // Check if email already exists
            $existing = getRow("SELECT id FROM users WHERE email = ?", [$userData['email']]);
            if ($existing) {
                return ['success' => false, 'message' => 'Email already registered'];
            }
            
            // Hash password
            $hashedPassword = hashPassword($userData['password']);
            
            beginTransaction();
            
            // Insert user
            $sql = "INSERT INTO users (email, password, full_name, phone, is_active) 
                    VALUES (?, ?, ?, ?, 1)";
            
            $userId = insert($sql, [
                $userData['email'],
                $hashedPassword,
                $userData['full_name'],
                $userData['phone'] ?? null
            ]);
            
            // Assign role if specified
            if (isset($userData['role'])) {
                $roleId = getRoleId($userData['role']);
                if ($roleId) {
                    query("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)", [$userId, $roleId]);
                }
            }
            
            commit();
            
            return ['success' => true, 'message' => 'Registration successful', 'user_id' => $userId];
            
        } catch (Exception $e) {
            rollback();
            error_log("Registration error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Registration failed'];
        }
    }
    
    /**
     * Login user with email and password
     * 
     * @param string $email User email
     * @param string $password User password
     * @param bool $rememberMe Remember user (optional)
     * @return array Result with success status and message
     */
    public static function login($email, $password, $rememberMe = false) {
        // Validate input
        if (empty($email) || empty($password)) {
            return ['success' => false, 'message' => 'Email and password are required'];
        }
        
        // Get user by email
        $user = getRow("SELECT * FROM users WHERE email = ? AND is_active = 1", [$email]);
        
        if (!$user) {
            return ['success' => false, 'message' => 'Invalid email or password'];
        }
        
        // Verify password
        if (!verifyPassword($password, $user['password'])) {
            return ['success' => false, 'message' => 'Invalid email or password'];
        }
        
        // Create session
        self::createSession($user['id']);
        
        // Update last login
        query("UPDATE users SET last_login = NOW() WHERE id = ?", [$user['id']]);
        
        return ['success' => true, 'message' => 'Login successful'];
    }
    
    /**
     * Login with Google OAuth
     * 
     * @param array $googleUser Google user data
     * @return array Result with success status and message
     */
    public static function loginWithGoogle($googleUser) {
        try {
            // Check if user exists by Google ID
            $user = getRow("SELECT * FROM users WHERE google_id = ?", [$googleUser['id']]);
            
            // If not found, check by email
            if (!$user) {
                $user = getRow("SELECT * FROM users WHERE email = ?", [$googleUser['email']]);
                
                // If user exists with this email, link Google account
                if ($user) {
                    query("UPDATE users SET google_id = ? WHERE id = ?", [$googleUser['id'], $user['id']]);
                }
            }
            
            // If user still doesn't exist, create new user
            if (!$user) {
                beginTransaction();
                
                $sql = "INSERT INTO users (email, full_name, google_id, is_active, email_verified, photo) 
                        VALUES (?, ?, ?, 1, 1, ?)";
                
                $userId = insert($sql, [
                    $googleUser['email'],
                    $googleUser['name'],
                    $googleUser['id'],
                    $googleUser['picture'] ?? null
                ]);
                
                // Automatically detect role based on email format
                $role = self::detectRoleFromEmail($googleUser['email']);
                
                $roleId = getRoleId($role);
                if ($roleId) {
                    query("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)", [$userId, $roleId]);
                    
                    // Create corresponding teacher or student profile
                    if ($role === 'teacher') {
                        insert("INSERT INTO teachers (user_id) VALUES (?)", [$userId]);
                    } elseif ($role === 'student') {
                        insert("INSERT INTO students (user_id) VALUES (?)", [$userId]);
                    }
                }
                
                commit();
                
                $user = getRow("SELECT * FROM users WHERE id = ?", [$userId]);
            }
            
            // Store OAuth tokens
            self::storeOAuthTokens($user['id'], $googleUser['access_token'] ?? null, $googleUser['refresh_token'] ?? null);
            
            // Create session
            self::createSession($user['id']);
            
            // Update last login
            query("UPDATE users SET last_login = NOW() WHERE id = ?", [$user['id']]);
            
            return ['success' => true, 'message' => 'Login successful'];
            
        } catch (Exception $e) {
            if (isset($userId)) {
                rollback();
            }
            error_log("Google login error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Login failed'];
        }
    }
    
    /**
     * Create user session
     * 
     * @param int $userId User ID
     */
    private static function createSession($userId) {
        // Clear existing session data
        $_SESSION = [];
        
        // Set user session data
        $_SESSION['user_id'] = $userId;
        $_SESSION['created'] = time();
        $_SESSION['last_activity'] = time();
        
        // Generate session ID and store in database
        $sessionId = session_id();
        $expiresAt = date('Y-m-d H:i:s', time() + SESSION_LIFETIME);
        
        // Delete old sessions for this user
        query("DELETE FROM sessions WHERE user_id = ?", [$userId]);
        
        // Insert new session
        $sql = "INSERT INTO sessions (id, user_id, ip_address, user_agent, expires_at) 
                VALUES (?, ?, ?, ?, ?)";
        
        query($sql, [
            $sessionId,
            $userId,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $expiresAt
        ]);
    }
    
    /**
     * Store OAuth tokens
     * 
     * @param int $userId User ID
     * @param string|null $accessToken Access token
     * @param string|null $refreshToken Refresh token
     */
    private static function storeOAuthTokens($userId, $accessToken, $refreshToken) {
        if (!$accessToken) {
            return;
        }
        
        // Delete old tokens
        query("DELETE FROM google_oauth_tokens WHERE user_id = ?", [$userId]);
        
        // Store new tokens
        $sql = "INSERT INTO google_oauth_tokens (user_id, access_token, refresh_token, expires_at) 
                VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))";
        
        query($sql, [$userId, $accessToken, $refreshToken]);
    }
    
    /**
     * Logout user
     */
    public static function logout() {
        if (isLoggedIn()) {
            // Delete session from database
            $sessionId = session_id();
            query("DELETE FROM sessions WHERE id = ?", [$sessionId]);
            
            // Clear session data
            $_SESSION = [];
            
            // Destroy session
            session_destroy();
        }
    }
    
    /**
     * Update user profile
     * 
     * @param int $userId User ID
     * @param array $data Profile data
     * @return array Result
     */
    public static function updateProfile($userId, $data) {
        try {
            $updates = [];
            $params = [];
            
            // Build update query dynamically
            $allowedFields = ['full_name', 'phone', 'photo'];
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }
            
            if (empty($updates)) {
                return ['success' => false, 'message' => 'No data to update'];
            }
            
            $params[] = $userId;
            $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
            
            query($sql, $params);
            
            // Clear cached user data
            unset($_SESSION['user_data']);
            
            return ['success' => true, 'message' => 'Profile updated successfully'];
            
        } catch (Exception $e) {
            error_log("Profile update error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Update failed'];
        }
    }
    
    /**
     * Change password
     * 
     * @param int $userId User ID
     * @param string $currentPassword Current password
     * @param string $newPassword New password
     * @return array Result
     */
    public static function changePassword($userId, $currentPassword, $newPassword) {
        // Get user
        $user = getRow("SELECT password FROM users WHERE id = ?", [$userId]);
        
        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }
        
        // Verify current password
        if (!verifyPassword($currentPassword, $user['password'])) {
            return ['success' => false, 'message' => 'Current password is incorrect'];
        }
        
        // Hash new password
        $hashedPassword = hashPassword($newPassword);
        
        // Update password
        query("UPDATE users SET password = ? WHERE id = ?", [$hashedPassword, $userId]);
        
        return ['success' => true, 'message' => 'Password changed successfully'];
    }
    
    /**
     * Detect user role based on email pattern
     * 
     * @param string $email User email address
     * @return string Role name ('teacher' or 'student')
     */
    private static function detectRoleFromEmail($email) {
        // Extract username part (before @)
        $username = strtolower(substr($email, 0, strpos($email, '@')));
        
        // Teacher pattern: firstname.lastname (contains a dot and alphabetic characters)
        // Example: mohamed.ahmed@fainuschool.edu.mv
        if (preg_match('/^[a-z]+\.[a-z]+$/', $username)) {
            return 'teacher';
        }
        
        // Student pattern: starts with 'a' followed by digits
        // Example: a45678@fainuschool.edu.mv
        if (preg_match('/^a\d+$/', $username)) {
            return 'student';
        }
        
        // Default to student if no pattern matches
        return 'student';
    }
}
