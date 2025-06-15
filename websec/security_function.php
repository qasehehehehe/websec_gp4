<?php
// security_functions.php - Centralized security functions
include_once 'db_connection.php' ;

/**
 * Sanitize input data to prevent XSS and other injection attacks
 */
function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate strong password requirements
 * - At least 8 characters
 * - Contains uppercase letters
 * - Contains lowercase letters
 * - Contains numbers
 * - Contains special characters
 */

function validateStrongPassword($password) {
    $errors = [];
    
    // Check minimum length
    if (strlen($password) < 12) {
        $errors[] = 'Password must be at least 12 characters long';
    }
    
    // Check for uppercase letter
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter (A-Z)';
    }
    
    // Check for lowercase letter
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password must contain at least one lowercase letter (a-z)';
    }
    
    // Check for number
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain at least one number (0-9)';
    }
    
    // Check for special character
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = 'Password must contain at least one special character (!@#$%^&*()_+-=[]{}|;:,.<>?)';
    }
    
    if (!empty($errors)) {
        return ['valid' => false, 'message' => implode('. ', $errors)];
    }
    
    return ['valid' => true, 'message' => 'Password meets all requirements'];
}

/**
 * Validate ID Number and check enrollment year
 * Block users enrolled before 2020
 */
/**
 * Validate ID Number and check enrollment year
 * Returns validation result and hashed IC
 */
function validateIdNumber($idNumber) {
    // Remove any non-digit characters
    $idNumber = preg_replace('/\D/', '', $idNumber);
    
    // Check if ID number is exactly 12 digits
    if (strlen($idNumber) !== 12) {
        return ['valid' => false, 'message' => 'ID Number must be exactly 12 digits'];
    }
    
    // Extract birth year from ID number (first 2 digits represent year)
    $yearDigits = substr($idNumber, 0, 2);
    $birthYear = intval($yearDigits);
    
    // Determine full year (Malaysian IC format)
    if ($birthYear <= 30) {
        $fullYear = 2000 + $birthYear;
    } else {
        $fullYear = 1900 + $birthYear;
    }
    
    // Calculate age (current year 2025)
    $currentYear = 2025;
    $age = $currentYear - $fullYear;
    
    // Must be 18 or older to register
    if ($age < 18) {
        return ['valid' => false, 'message' => 'You must be at least 18 years old to register'];
    }
    
    // Optional: Set upper age limit (e.g., 100 years)
    if ($age > 100) {
        return ['valid' => false, 'message' => 'Please contact customer service for assistance'];
    }
    
    return [
        'valid' => true, 
        'message' => 'Valid ID Number - Age: ' . $age . ' years',
        'plain_ic' => $idNumber  // Keep plain for any processing needed
    ];
}
/**
 * Validate email address
 */
function validateEmail($email) {
    $email = sanitizeInput($email);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['valid' => false, 'message' => 'Invalid email format'];
    }
    return ['valid' => true, 'message' => 'Valid email'];
}

/**
 * Validate username
 */
function validateUsername($username) {
    $username = sanitizeInput($username);
    if (strlen($username) < 3) {
        return ['valid' => false, 'message' => 'Username must be at least 3 characters long'];
    }
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        return ['valid' => false, 'message' => 'Username can only contain letters, numbers, and underscores'];
    }
    return ['valid' => true, 'message' => 'Valid username'];
}

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Generate secure random salt for password hashing
 */
function generateSalt() {
    return bin2hex(random_bytes(16));
}

/**
 * Hash password with salt using password_hash (built-in salting)
 */
function hashPasswordWithSalt($password) {
    // Using PASSWORD_DEFAULT which automatically includes salt
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Display appropriate error message for password, ID number, email validation
 */
function displayValidationError($field, $value) {
    switch ($field) {
        case 'password':
            $result = validateStrongPassword($value);
            break;
        case 'id_number':
            $result = validateIdNumber($value);
            break;
        case 'email':
            $result = validateEmail($value);
            break;
        case 'username':
            $result = validateUsername($value);
            break;
        default:
            return ['valid' => false, 'message' => 'Unknown field validation'];
    }
    
    return $result;
}

/**
 * Secure session initialization
 */
/**
 * Secure session initialization
 */
function initializeSecureSession() {
    // Only regenerate session ID to prevent session fixation
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
    
    // Generate CSRF token if not exists
    generateCSRFToken();

        // Add 30-minute session timeout
    checkSessionTimeout(30);

}

/**
 * Rate limiting for login attempts
 */
function checkRateLimit($identifier, $maxAttempts = 5, $timeWindow = 300) { // 15 minutes
    $filename = sys_get_temp_dir() . '/login_attempts_' . md5($identifier);
    
    $attempts = [];
    if (file_exists($filename)) {
        $attempts = json_decode(file_get_contents($filename), true) ?: [];
    }
    
    // Remove old attempts outside time window
    $currentTime = time();
    $attempts = array_filter($attempts, function($timestamp) use ($currentTime, $timeWindow) {
        return ($currentTime - $timestamp) < $timeWindow;
    });
    
    // Check if max attempts exceeded
    if (count($attempts) >= $maxAttempts) {
        return ['allowed' => false, 'message' => 'Too many login attempts. Please try again later.'];
    }
    
    // Add current attempt
    $attempts[] = $currentTime;
    file_put_contents($filename, json_encode($attempts));
    
    return ['allowed' => true, 'remaining' => $maxAttempts - count($attempts)];
}

/**
 * Clear rate limit for successful login
 */
function clearRateLimit($identifier) {
    $filename = sys_get_temp_dir() . '/login_attempts_' . md5($identifier);
    if (file_exists($filename)) {
        unlink($filename);
    }
}

/**
 * Secure file upload validation
 */
function validateFileUpload($file, $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'], $maxSize = 5242880) { // 5MB
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['valid' => false, 'message' => 'File upload error'];
    }
    
    // Check file size
    if ($file['size'] > $maxSize) {
        return ['valid' => false, 'message' => 'File size too large (max 5MB)'];
    }
    
    // Check file type
    if (!in_array($file['type'], $allowedTypes)) {
        return ['valid' => false, 'message' => 'Invalid file type. Only JPEG, PNG, and GIF allowed'];
    }
    
    // Additional security: Check file content
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        return ['valid' => false, 'message' => 'File content does not match extension'];
    }
    
    return ['valid' => true, 'message' => 'File is valid'];
}

/**
 * Log security events
 */
function logSecurityEvent($event, $details = []) {
    $logFile = __DIR__ . '/logs/security.log';
    $logDir = dirname($logFile);
    
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'event' => $event,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'details' => $details
    ];
    
    file_put_contents($logFile, json_encode($logEntry) . "\n", FILE_APPEND | LOCK_EX);
}
/**
 * Generate enrollment ID automatically and check 5-year rule
 */
function generateAndValidateEnrollmentID($conn) {
    $currentYear = 2025; // Current year
    
    // Block rule: 2020 and below are blocked  
    // Since we're in 2025, all new registrations get 2025 IDs (which are allowed)
    
    // Count existing users with 2025 IDs to get next number
    $sql = "SELECT COUNT(*) as count FROM users WHERE enrollment_id LIKE 'A2025%'";
    $result = $conn->query($sql);
    $count = $result->fetch_assoc()['count'];
    $newNumber = $count + 1;
    
    // Format: A2025001, A2025002, etc.
    $enrollmentID = sprintf("A2025%03d", $newNumber);
    
    return [
        'valid' => true, 
        'enrollment_id' => $enrollmentID,
        'message' => "Enrollment ID generated: $enrollmentID"
    ];
}
/**
 * Hash IC number with salt for secure storage
 * Uses PHP's built-in password_hash which includes salt automatically
 */
function hashICNumber($icNumber) {
    // Clean the IC number (remove any spaces or dashes)
    $cleanIC = preg_replace('/[^0-9]/', '', $icNumber);
    
    // Use PHP's built-in password hashing (includes salt automatically)
    return password_hash($cleanIC, PASSWORD_DEFAULT);
}

/**
 * Verify IC number against stored hash
 */
function verifyICNumber($icNumber, $hashedIC) {
    // Clean the input IC number
    $cleanIC = preg_replace('/[^0-9]/', '', $icNumber);
    
    // Verify against stored hash
    return password_verify($cleanIC, $hashedIC);
}

/**
 * Check if user's IC number matches (for verification purposes)
 */
function verifyUserICNumber($userId, $providedIC) {
    $conn = getDatabaseConnection();
    $sql = "SELECT id_number_hash FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $isValid = verifyICNumber($providedIC, $user['id_number_hash']);
            $stmt->close();
            $conn->close();
            return $isValid;
        }
        $stmt->close();
    }
    $conn->close();
    return false;
}
/**
 * Simple user activity logging function
 * Logs user activities to system_logs table
 */
function logUserActivity($username, $action, $details = '') {
    try {
        $conn = getDatabaseConnection();
        
        // Create description with username
        $description = "User '{$username}' {$action}";
        if ($details) {
            $description .= " - {$details}";
        }
        
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $user_id = $_SESSION['user_id'] ?? null;
        
        $sql = "INSERT INTO system_logs (user_id, action, description, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('issss', $user_id, $action, $description, $ip_address, $user_agent);
        $stmt->execute();
        $stmt->close();
        $conn->close();
    } catch (Exception $e) {
        // Fail silently to not break the system
        error_log("User activity logging failed: " . $e->getMessage());
    }
}
/**
 * Check session timeout and auto-logout inactive users
 */
function checkSessionTimeout($timeoutMinutes = 30) {
    // Skip timeout check for public pages
    $currentPage = basename($_SERVER['PHP_SELF']);
    $publicPages = ['login.php', 'register.php', 'index.php', 'forgot_password.php'];
    
    if (in_array($currentPage, $publicPages)) {
        return; // No timeout on public pages
    }
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id']) && !isset($_SESSION['temp_user_id'])) {
        return; // No timeout for non-logged-in users
    }
    
    $timeout = $timeoutMinutes * 60; // Convert minutes to seconds (30 min = 1800 sec)
    
    if (isset($_SESSION['last_activity'])) {
        if (time() - $_SESSION['last_activity'] > $timeout) {
            // Session expired after 30 minutes - destroy and redirect
            session_destroy();
            header('Location: login.php?timeout=1');
            exit();
        }
    }
    
    // Update last activity time
    $_SESSION['last_activity'] = time();
}
?>