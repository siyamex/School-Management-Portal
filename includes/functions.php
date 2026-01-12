<?php
/**
 * Common Utility Functions
 * Helper functions used throughout the application
 */

/**
 * Sanitize input data
 * 
 * @param mixed $data Data to sanitize
 * @return mixed
 */
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    
    // Handle null/empty values for PHP 8.1+ compatibility
    if ($data === null || $data === '') {
        return '';
    }
    
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email address
 * 
 * @param string $email Email to validate
 * @return bool
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Hash password
 * 
 * @param string $password Plain text password
 * @return string Hashed password
 */
function hashPassword($password) {
    return password_hash($password, HASH_ALGO, ['cost' => HASH_COST]);
}

/**
 * Verify password
 * 
 * @param string $password Plain text password
 * @param string $hash Hashed password
 * @return bool
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Generate random string
 * 
 * @param int $length Length of string
 * @return string
 */
function generateRandomString($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Redirect to URL
 * 
 * @param string $url URL to redirect to
 */
function redirect($url) {
    // Check if headers already sent, if so use JavaScript redirect
    if (headers_sent()) {
        echo '<script>window.location.href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '";</script>';
        echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"></noscript>';
        exit;
    }
    
    header("Location: $url");
    exit;
}

/**
 * Get role ID by name
 * 
 * @param string $roleName Role name
 * @return int|null
 */
function getRoleId($roleName) {
    $role = getRow("SELECT id FROM roles WHERE role_name = ?", [$roleName]);
    return $role ? $role['id'] : null;
}

/**
 * Format date for display
 * 
 * @param string $date Date string
 * @param string $format Format string
 * @return string
 */
function formatDate($date, $format = 'Y-m-d') {
    if (!$date) return '';
    
    $dt = new DateTime($date);
    return $dt->format($format);
}

/**
 * Format date and time for display
 * 
 * @param string $datetime DateTime string
 * @param string $format Format string
 * @return string
 */
function formatDateTime($datetime, $format = 'Y-m-d H:i:s') {
    if (!$datetime) return '';
    
    $dt = new DateTime($datetime);
    return $dt->format($format);
}

/**
 * Time ago format
 * 
 * @param string $datetime DateTime string
 * @return string
 */
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return formatDate($datetime, 'M d, Y');
    }
}

/**
 * Upload file
 * 
 * @param array $file $_FILES array element
 * @param string $destination Destination folder
 * @param array $allowedTypes Allowed file extensions
 * @return array ['success' => bool, 'path' => string, 'error' => string]
 */
function uploadFile($file, $destination, $allowedTypes = null) {
    $result = ['success' => false, 'path' => '', 'error' => ''];
    
    if (!isset($file['error']) || is_array($file['error'])) {
        $result['error'] = 'Invalid file upload';
        return $result;
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $result['error'] = 'Upload failed with error code: ' . $file['error'];
        return $result;
    }
    
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        $result['error'] = 'File size exceeds maximum allowed size';
        return $result;
    }
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedTypes = $allowedTypes ?? ALLOWED_EXTENSIONS;
    
    if (!in_array($extension, $allowedTypes)) {
        $result['error'] = 'File type not allowed';
        return $result;
    }
    
    // Create destination directory if it doesn't exist
    if (!is_dir($destination)) {
        mkdir($destination, 0755, true);
    }
    
    // Generate unique filename
    $filename = uniqid() . '_' . time() . '.' . $extension;
    $filepath = $destination . '/' . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        $result['success'] = true;
        $result['path'] = $filepath;
    } else {
        $result['error'] = 'Failed to move uploaded file';
    }
    
    return $result;
}

/**
 * Delete file
 * 
 * @param string $filepath Path to file
 * @return bool
 */
function deleteFile($filepath) {
    if (file_exists($filepath)) {
        return unlink($filepath);
    }
    return false;
}

/**
 * Get file extension
 * 
 * @param string $filename Filename
 * @return string
 */
function getFileExtension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

/**
 * Format file size
 * 
 * @param int $bytes File size in bytes
 * @return string
 */
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    
    return round($bytes, 2) . ' ' . $units[$pow];
}

/**
 * Generate pagination HTML
 * 
 * @param int $currentPage Current page number
 * @param int $totalPages Total number of pages
 * @param string $baseUrl Base URL for pagination links
 * @return string HTML for pagination
 */
function getPagination($currentPage, $totalPages, $baseUrl) {
    if ($totalPages <= 1) {
        return '';
    }
    
    $html = '<div class="join">';
    
    // Previous button
    if ($currentPage > 1) {
        $html .= '<a href="' . $baseUrl . '&page=' . ($currentPage - 1) . '" class="join-item btn btn-sm">«</a>';
    }
    
    // Page numbers
    $start = max(1, $currentPage - 2);
    $end = min($totalPages, $currentPage + 2);
    
    for ($i = $start; $i <= $end; $i++) {
        $active = $i === $currentPage ? 'btn-active' : '';
        $html .= '<a href="' . $baseUrl . '&page=' . $i . '" class="join-item btn btn-sm ' . $active . '">' . $i . '</a>';
    }
    
    // Next button
    if ($currentPage < $totalPages) {
        $html .= '<a href="' . $baseUrl . '&page=' . ($currentPage + 1) . '" class="join-item btn btn-sm">»</a>';
    }
    
    $html .= '</div>';
    
    return $html;
}

/**
 * Get student's current enrollment
 * 
 * @param int $studentId Student ID
 * @return array|null
 */
function getCurrentEnrollment($studentId) {
    $sql = "SELECT e.*, c.class_name, s.section_name, ay.year_name
            FROM enrollments e
            JOIN sections s ON e.section_id = s.id
            JOIN classes c ON s.class_id = c.id
            JOIN academic_years ay ON e.academic_year_id = ay.id
            WHERE e.student_id = ? AND e.status = 'active'
            ORDER BY e.created_at DESC
            LIMIT 1";
    
    return getRow($sql, [$studentId]);
}

/**
 * Calculate grade from percentage
 * 
 * @param float $percentage Percentage scored
 * @return array Grade details
 */
function calculateGrade($percentage) {
    $sql = "SELECT * FROM grading_scales 
            WHERE ? BETWEEN min_percentage AND max_percentage 
            ORDER BY min_percentage DESC 
            LIMIT 1";
    
    $grade = getRow($sql, [$percentage]);
    
    return $grade ?: ['grade_letter' => 'N/A', 'grade_point' => 0];
}

/**
 * Send notification to user
 * 
 * @param int $userId User ID
 * @param string $type Notification type
 * @param string $title Notification title
 * @param string $message Notification message
 * @param string|null $link Optional link
 * @return bool
 */
function sendNotification($userId, $type, $title, $message, $link = null) {
    $sql = "INSERT INTO notifications (user_id, notification_type, title, message, link) 
            VALUES (?, ?, ?, ?, ?)";
    
    try {
        query($sql, [$userId, $type, $title, $message, $link]);
        return true;
    } catch (Exception $e) {
        error_log("Failed to send notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Get unread notification count
 * 
 * @param int $userId User ID
 * @return int
 */
function getUnreadNotificationCount($userId) {
    $sql = "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0";
    return (int) getValue($sql, [$userId]);
}

/**
 * CSRF Token functions
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * JSON response helper
 * 
 * @param bool $success Success status
 * @param mixed $data Data to return
 * @param string $message Message
 */
function jsonResponse($success, $data = null, $message = '') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'message' => $message
    ]);
    exit;
}
