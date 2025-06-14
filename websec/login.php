<?php
ob_start();
session_start();
include 'db_connection.php';
include 'config/email_config.php';
include 'security_function.php';

// ========== HTTPS SECURITY BLOCK (REQUIRED FOR ASSIGNMENT) ==========
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
    }
}

enforceHTTPS();
setSecurityHeaders();
// ========== END HTTPS SECURITY BLOCK ==========

// Initialize secure session
initializeSecureSession();

$message = '';
$messageType = '';
$redirect = false;
$redirectUrl = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = '❌ Security error: Invalid request. Please try again.';
        $messageType = 'error';
        logSecurityEvent('CSRF_TOKEN_INVALID', ['action' => 'login']);
    } else {
        // Sanitize inputs
        $username = sanitizeInput($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        // Check rate limiting
        $clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $rateLimitCheck = checkRateLimit($clientIP . '_' . $username);
        
        if (!$rateLimitCheck['allowed']) {
            $message = '❌ ' . $rateLimitCheck['message'];
            $messageType = 'error';
            logSecurityEvent('RATE_LIMIT_EXCEEDED', [
                'username' => $username,
                'ip' => $clientIP
            ]);
        } else {
            // Validate inputs
            if (empty($username) || empty($password)) {
                $message = '❌ Please enter both username and password.';
                $messageType = 'error';
            } else {
                $conn = getDatabaseConnection();
                
                // Use prepared statement to prevent SQL injection
                $sql = "SELECT id, username, password, role, first_name, last_name, email, is_active, enrollment_id FROM users WHERE username = ?";
                $stmt = $conn->prepare($sql);
                
                if ($stmt) {
                    $stmt->bind_param('s', $username);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows > 0) {
                        $user = $result->fetch_assoc();
                        
                        // CRITICAL: Check enrollment year blocking FIRST
                        if (!empty($user['enrollment_id'])) {
                            $enrollmentYear = intval(substr($user['enrollment_id'], 1, 4));
                            if ($enrollmentYear <= 2020) {
                                $yearsAgo = 2025 - $enrollmentYear;
                                $message = "❌ Access denied. Users enrolled in {$enrollmentYear} ({$yearsAgo} years ago) are blocked. Only users enrolled from 2021 onwards are allowed.";
                                $messageType = 'error';
                                logSecurityEvent('LOGIN_ENROLLMENT_BLOCKED', [
                                    'username' => $username,
                                    'enrollment_id' => $user['enrollment_id'],
                                    'enrollment_year' => $enrollmentYear,
                                    'ip' => $clientIP
                                ]);
                                logUserActivity($username, 'login blocked', "enrolled in {$enrollmentYear} - policy violation");
                                $stmt->close();
                                $conn->close();
                            } else {
                                // Enrollment year is valid, continue with login checks
                                proceedWithLogin($user, $password, $username, $clientIP, $conn, $stmt);
                            }
                        } else {
                            // No enrollment_id (legacy users like admin), continue with login
                            proceedWithLogin($user, $password, $username, $clientIP, $conn, $stmt);
                        }
                    } else {
                        $message = "❌ User not found.";
                        $messageType = "error";
                        
                        logSecurityEvent('LOGIN_USER_NOT_FOUND', [
                            'username' => $username,
                            'ip' => $clientIP
                        ]);
                        $stmt->close();
                        $conn->close();
                    }
                } else {
                    $message = "❌ System error. Please try again.";
                    $messageType = "error";
                    $conn->close();
                }
            }
        }
    }
}

// Function to handle the login process after enrollment check
function proceedWithLogin($user, $password, $username, $clientIP, $conn, $stmt) {
    global $message, $messageType, $redirect, $redirectUrl;
    
    // Check if account is active
    if (!$user['is_active']) {
        $message = '❌ Account is deactivated. Please contact administrator.';
        $messageType = 'error';
        logSecurityEvent('LOGIN_INACTIVE_ACCOUNT', [
            'username' => $username,
            'ip' => $clientIP
        ]);
    } elseif (password_verify($password, $user['password'])) {
        // Clear rate limit on successful login
        clearRateLimit($clientIP . '_' . $username);
        
        $role = $user['role'];
        
        // Generate 6-digit verification code for multifactor authentication
        $verificationCode = sprintf("%06d", mt_rand(100000, 999999));
        
        // Store user data and verification code in session
        $_SESSION['temp_user_id'] = $user['id'];
        $_SESSION['temp_user_role'] = $role;
        $_SESSION['temp_user_email'] = $user['email'];
        $_SESSION['temp_username'] = $user['username'];
        $_SESSION['temp_first_name'] = $user['first_name'];
        $_SESSION['temp_last_name'] = $user['last_name'];
        $_SESSION['verification_code'] = $verificationCode;
        $_SESSION['code_expiry'] = time() + (10 * 60); // 10 minutes
        
        // Try to send verification email
        if (EmailService\sendVerificationCode($user['email'], $verificationCode)) {
            // Store verification attempt in database
            $storeTwoFASql = "INSERT INTO verification_attempts (user_id, verification_code, expiry, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
            $twoFAStmt = $conn->prepare($storeTwoFASql);
            
            if ($twoFAStmt) {
                $expiryTime = date('Y-m-d H:i:s', $_SESSION['code_expiry']);
                $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
                $twoFAStmt->bind_param('issss', $user['id'], $verificationCode, $expiryTime, $ipAddress, $userAgent);
                
                if ($twoFAStmt->execute()) {
                    $maskedEmail = substr($user['email'], 0, 3) . '***' . substr($user['email'], strpos($user['email'], '@'));
                    $message = "✅ Verification code sent to $maskedEmail. Please check your email and enter the 6-digit code.";
                    $messageType = "success";
                    $redirect = true;
                    $redirectUrl = 'verify.php';
                    
                    logSecurityEvent('LOGIN_2FA_SENT', [
                        'username' => $username,
                        'email' => $maskedEmail,
                        'ip' => $clientIP
                    ]);
                    logUserActivity($username, 'logged in successfully', '2FA verification sent');
                } else {
                    $message = "❌ Error storing verification. Please try again.";
                    $messageType = "error";
                    
                    // Clear temp session data on error
                    unset($_SESSION['temp_user_id'], $_SESSION['temp_user_role'], $_SESSION['temp_user_email']);
                    unset($_SESSION['verification_code'], $_SESSION['code_expiry']);
                }
                $twoFAStmt->close();
            }
        } else {
            $message = "❌ Failed to send verification code. Please check your email configuration or try again.";
            $messageType = "error";
            
            // Clear temporary session data on email failure
            unset($_SESSION['temp_user_id'], $_SESSION['temp_user_role'], $_SESSION['temp_user_email']);
            unset($_SESSION['verification_code'], $_SESSION['code_expiry']);
            
            logSecurityEvent('LOGIN_2FA_EMAIL_FAILED', [
                'username' => $username,
                'ip' => $clientIP
            ]);
        }
    } else {
        $message = "❌ Invalid password.";
        $messageType = "error";
        
        logSecurityEvent('LOGIN_INVALID_PASSWORD', [
            'username' => $username,
            'ip' => $clientIP
        ]);
        logUserActivity($username, 'failed to login', 'invalid password');
    }
    
    $stmt->close();
    $conn->close();
}

// Generate CSRF token for form
$csrfToken = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="railway-styles.css" rel="stylesheet">
    <title>Secure Login - Railway System</title>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <img src="img/logotest.jpg" alt="Railway Logo" style="width: 60px; height: 60px; border-radius: 12px; margin-bottom: 1rem;">
                <h2><i class="fas fa-shield-alt"></i> Secure Login</h2>
                <p>Access your Railway account securely</p>
            </div>
            
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i> <strong>Security Policy:</strong> Users enrolled in 2020 and below are automatically blocked due to 5-year security policy.
            </div>

            <form method="POST" action="" onsubmit="return validateLoginForm()" id="loginForm" class="auth-form">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                
                <div class="form-group">
                    <label for="username" class="form-label"><i class="fas fa-user"></i> Username</label>
                    <input type="text" id="username" name="username" class="form-control" placeholder="Enter your username" required 
                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="password" class="form-label"><i class="fas fa-lock"></i> Password</label>
                    <input type="password" id="password" name="password" class="form-control" placeholder="Enter your password" required>
                </div>
                
                <button type="submit" id="loginBtn" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt"></i> Login Securely
                </button>
            </form>
            
            <div class="auth-footer">
                <p>Don't have an account? <a href="register.php"><i class="fas fa-user-plus"></i> Sign Up</a></p>
                <p><a href="forgot_password.php"><i class="fas fa-key"></i> Forgot Password?</a></p>
            </div>
        </div>
    </div>

    <script>
        function validateLoginForm() {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            
            if (!username || !password) {
                alert('Please enter both username and password');
                return false;
            }
            
            // Show loading state
            const loginBtn = document.getElementById('loginBtn');
            loginBtn.disabled = true;
            loginBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Authenticating...';
            
            return true;
        }

        // Auto-clear sensitive data on page unload
        window.addEventListener('beforeunload', function() {
            document.getElementById('password').value = '';
        });

        // Prevent form resubmission on refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>

    <?php if ($redirect): ?>
    <script>
        setTimeout(() => {
            window.location.href = '<?php echo $redirectUrl; ?>';
        }, 3000);
    </script>
    <?php endif; ?>
</body>
</html>

<?php ob_end_flush(); ?>