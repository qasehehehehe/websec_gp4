<?php
session_start();

// SECURITY BLOCK - Force HTTPS and security headers
function enforceHTTPS() {
    if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
        if ($_SERVER['HTTP_HOST'] !== 'localhost' && $_SERVER['HTTP_HOST'] !== '127.0.0.1') {
            $redirectURL = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            header("Location: $redirectURL", true, 301);
            exit();
        }
    }
}

function setSecurityHeaders() {
    if (!headers_sent()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
    }
}

enforceHTTPS();
setSecurityHeaders();
// END SECURITY BLOCK

include 'db_connection.php';
include 'security_function.php';

$message = '';
$username = '';

// Enhanced logout logging BEFORE destroying session
if (isset($_SESSION['username'])) {
    $username = $_SESSION['username'];
    $userId = $_SESSION['user_id'] ?? null;
    $loginTime = $_SESSION['login_time'] ?? null;
    
    // Calculate session duration
    $sessionDuration = $loginTime ? (time() - $loginTime) : 0;
    $durationText = $sessionDuration > 0 ? gmdate("H:i:s", $sessionDuration) : 'unknown';
    
    // Enhanced logging with more details
    logUserActivity($username, 'logged out', "user initiated logout - session duration: {$durationText}");
    
    // Log comprehensive security event
    logSecurityEvent('USER_LOGOUT', [
        'username' => $username,
        'user_id' => $userId,
        'session_duration_seconds' => $sessionDuration,
        'session_duration_formatted' => $durationText,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'logout_method' => 'manual'
    ]);
    
    // Update database to track logout time
    $conn = getDatabaseConnection();
    if ($conn && $userId) {
        $updateSql = "UPDATE users SET last_login = NOW() WHERE id = ?";
        $stmt = $conn->prepare($updateSql);
        if ($stmt) {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $stmt->close();
        }
        
        // Also log to system_logs table
        $logSql = "INSERT INTO system_logs (user_id, action, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)";
        $logStmt = $conn->prepare($logSql);
        if ($logStmt) {
            $action = 'logged out';
            $description = "User '{$username}' logged out - session duration: {$durationText}";
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            
            $logStmt->bind_param('issss', $userId, $action, $description, $ip, $userAgent);
            $logStmt->execute();
            $logStmt->close();
        }
        
        $conn->close();
    }
    
    $message = "Goodbye, {$username}! You have been logged out successfully.";
}

// Comprehensive session destruction (Assignment Requirement: Session Management)
function destroyUserSession() {
    // 1. Clear all session variables
    $_SESSION = array();
    
    // 2. Delete the session cookie completely
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], 
            $params["domain"],
            $params["secure"], 
            $params["httponly"]
        );
    }
    
    // 3. Clear any remember me cookies
    if (isset($_COOKIE['remember_me'])) {
        setcookie('remember_me', '', time() - 3600, '/', '', true, true);
    }
    
    // 4. Clear any authentication tokens
    if (isset($_COOKIE['auth_token'])) {
        setcookie('auth_token', '', time() - 3600, '/', '', true, true);
    }
    
    // 5. Clear any 2FA cookies
    if (isset($_COOKIE['2fa_verified'])) {
        setcookie('2fa_verified', '', time() - 3600, '/', '', true, true);
    }
    
    // 6. Destroy the session
    session_destroy();
    
    // 7. Start a new clean session and regenerate ID
    session_start();
    session_regenerate_id(true);
}

// Execute comprehensive session destruction
destroyUserSession();

// Set success message in new clean session
$_SESSION['logout_message'] = $message ?: 'You have been logged out successfully.';
$_SESSION['logout_timestamp'] = time();

// Clear sensitive variables from memory (if sodium available)
if (function_exists('sodium_memzero') && $username) {
    sodium_memzero($username);
}

// Check if this is an AJAX request
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
    exit();
}

// For regular requests, redirect to login with success message
header('Location: login.php?logout=success');
exit();
?>