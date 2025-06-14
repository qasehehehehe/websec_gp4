<?php
ob_start();
session_start();
include 'db_connection.php';
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

// Check if user has temp session data (came from login)
if (!isset($_SESSION['temp_user_id']) || !isset($_SESSION['verification_code'])) {
    header('Location: login.php');
    exit();
}

// Check if verification code has expired
if (time() > $_SESSION['code_expiry']) {
    // Clear temp session data
    unset($_SESSION['temp_user_id'], $_SESSION['temp_user_role'], $_SESSION['temp_user_email']);
    unset($_SESSION['verification_code'], $_SESSION['code_expiry']);
    
    $message = 'Verification code has expired. Please login again.';
    $messageType = 'error';
    
    // Redirect to login after 3 seconds
    $redirect = true;
    $redirectUrl = 'login.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$redirect) {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Security error: Invalid request. Please try again.';
        $messageType = 'error';
        logSecurityEvent('CSRF_TOKEN_INVALID', ['action' => 'verification']);
    } else {
        $enteredCode = sanitizeInput($_POST['verification_code'] ?? '');
        
        if (empty($enteredCode)) {
            $message = 'Please enter the verification code.';
            $messageType = 'error';
        } else {
            // Check rate limiting for verification attempts
            $clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $rateLimitCheck = checkRateLimit($clientIP . '_verification_' . $_SESSION['temp_user_id'], 5, 300);
            
            if (!$rateLimitCheck['allowed']) {
                $message = 'Too many verification attempts. Please try again later.';
                $messageType = 'error';
                logSecurityEvent('VERIFICATION_RATE_LIMIT_EXCEEDED', [
                    'user_id' => $_SESSION['temp_user_id'],
                    'ip' => $clientIP
                ]);
            } else {
                // Verify the code
                if ($enteredCode === $_SESSION['verification_code']) {
                    // Code is correct - complete the login process
                    $conn = getDatabaseConnection();
                    
                    // Update verification attempt as used
                    $updateSql = "UPDATE verification_attempts SET is_used = 1 WHERE user_id = ? AND verification_code = ? AND is_used = 0";
                    $updateStmt = $conn->prepare($updateSql);
                    
                    if ($updateStmt) {
                        $updateStmt->bind_param('is', $_SESSION['temp_user_id'], $_SESSION['verification_code']);
                        $updateStmt->execute();
                        $updateStmt->close();
                    }
                    
                    // Update last login time
                    $loginUpdateSql = "UPDATE users SET last_login = NOW() WHERE id = ?";
                    $loginStmt = $conn->prepare($loginUpdateSql);
                    if ($loginStmt) {
                        $loginStmt->bind_param('i', $_SESSION['temp_user_id']);
                        $loginStmt->execute();
                        $loginStmt->close();
                    }
                    
                    $conn->close();
                    
                    // Set permanent session variables
                    $_SESSION['user_id'] = $_SESSION['temp_user_id'];
                    $_SESSION['role'] = $_SESSION['temp_user_role'];
                    $_SESSION['email'] = $_SESSION['temp_user_email'];
                    $_SESSION['username'] = $_SESSION['temp_username'];
                    $_SESSION['first_name'] = $_SESSION['temp_first_name'];
                    $_SESSION['last_name'] = $_SESSION['temp_last_name'];
                    $_SESSION['logged_in'] = true;
                    $_SESSION['login_time'] = time();
                    
                    // Clear temporary session data
                    unset($_SESSION['temp_user_id'], $_SESSION['temp_user_role'], $_SESSION['temp_user_email']);
                    unset($_SESSION['temp_username'], $_SESSION['temp_first_name'], $_SESSION['temp_last_name']);
                    unset($_SESSION['verification_code'], $_SESSION['code_expiry']);
                    
                    // Clear rate limit on successful verification
                    clearRateLimit($clientIP . '_verification_' . $_SESSION['user_id']);
                    
                    // Log successful verification
                    logSecurityEvent('LOGIN_2FA_SUCCESS', [
                        'username' => $_SESSION['username'],
                        'ip' => $clientIP
                    ]);
                    logUserActivity($_SESSION['username'], 'completed 2FA verification', 'login successful');
                    
                    // Role-based redirect
                    if ($_SESSION['role'] === 'admin') {
                        $message = 'Verification successful! Redirecting to Admin Dashboard...';
                        $messageType = 'success';
                        $redirect = true;
                        $redirectUrl = 'admin_dashboard.php';
                    } else {
                        $message = 'Verification successful! Redirecting to User Dashboard...';
                        $messageType = 'success';
                        $redirect = true;
                        $redirectUrl = 'user_dashboard.php';
                    }
                } else {
                    // Incorrect verification code
                    $message = 'Invalid verification code. Please check your email and try again.';
                    $messageType = 'error';
                    
                    logSecurityEvent('LOGIN_2FA_INVALID_CODE', [
                        'user_id' => $_SESSION['temp_user_id'],
                        'attempted_code' => $enteredCode,
                        'ip' => $clientIP
                    ]);
                    
                    // Store failed attempt
                    $conn = getDatabaseConnection();
                    $failSql = "INSERT INTO verification_failures (user_id, ip_address, attempted_code, user_agent) VALUES (?, ?, ?, ?)";
                    $failStmt = $conn->prepare($failSql);
                    if ($failStmt) {
                        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
                        $failStmt->bind_param('isss', $_SESSION['temp_user_id'], $clientIP, $enteredCode, $userAgent);
                        $failStmt->execute();
                        $failStmt->close();
                    }
                    $conn->close();
                }
            }
        }
    }
}

// Generate CSRF token
$csrfToken = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="railway-styles.css" rel="stylesheet">
    <title>Two-Factor Verification - Railway System</title>
    <style>
        .verify-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .verify-card {
            width: 100%;
            max-width: 450px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2.5rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 40px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .verify-header {
            margin-bottom: 2rem;
        }

        .verify-header .logo-section {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 15px;
            margin-bottom: 1.5rem;
        }

        .verify-header .logo-section img {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .verify-header .logo-text h1 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .verify-header h2 {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #333;
        }

        .security-notice {
            background: rgba(79, 172, 254, 0.1);
            border: 1px solid rgba(79, 172, 254, 0.2);
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            color: #0c5460;
            text-align: left;
        }

        .timer {
            background: rgba(255, 193, 7, 0.1);
            border: 1px solid rgba(255, 193, 7, 0.2);
            padding: 0.75rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            color: #856404;
            text-align: center;
        }

        .timer.expired {
            background: rgba(220, 53, 69, 0.1);
            border-color: rgba(220, 53, 69, 0.2);
            color: #721c24;
        }

        .verification-input {
            font-size: 1.5rem !important;
            text-align: center !important;
            letter-spacing: 0.5rem !important;
            font-weight: 600 !important;
            padding: 1rem !important;
            margin-bottom: 1.5rem !important;
        }

        .auth-form {
            text-align: left;
            margin-bottom: 1.5rem;
        }

        .auth-footer {
            text-align: center;
            font-size: 0.9rem;
            color: #666;
        }

        .auth-footer a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }

        .auth-footer a:hover {
            text-decoration: underline;
        }

        .loading-state {
            opacity: 0.7;
            pointer-events: none;
        }

        @media (max-width: 480px) {
            .verify-card {
                padding: 2rem 1.5rem;
                margin: 1rem;
            }
            
            .verify-header .logo-section {
                flex-direction: column;
                gap: 10px;
            }
            
            .verification-input {
                font-size: 1.25rem !important;
                letter-spacing: 0.25rem !important;
            }
        }
    </style>
</head>
<body>
    <div class="verify-container">
        <div class="verify-card">
            <div class="verify-header">
                <div class="logo-section">
                    <img src="img/logotest.jpg" alt="Railway Logo">
                    <div class="logo-text">
                        <h1>Railway System</h1>
                    </div>
                </div>
                <h2><i class="fas fa-shield-alt"></i> Two-Factor Verification</h2>
            </div>
            
            <div class="security-notice">
                <i class="fas fa-envelope"></i> <strong>Check Your Email:</strong><br>
                We've sent a 6-digit verification code to your email address. Enter it below to complete your login.
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?> mb-4">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if (!$redirect): ?>
                <div class="timer" id="timer">
                    <i class="fas fa-clock"></i> Code expires in: <span id="countdown"></span>
                </div>

                <form method="POST" action="" onsubmit="return validateVerificationForm()" id="verificationForm" class="auth-form">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    
                    <div class="form-group mb-4">
                        <label class="form-label" for="verification_code">
                            <i class="fas fa-key"></i> Verification Code
                        </label>
                        <input type="text" id="verification_code" name="verification_code" 
                               class="form-control verification-input" placeholder="000000" 
                               maxlength="6" pattern="[0-9]{6}" required autocomplete="off">
                    </div>
                    
                    <button type="submit" id="verifyBtn" class="btn btn-primary" style="width: 100%; padding: 12px; font-size: 1rem;">
                        <i class="fas fa-check-circle"></i> Verify & Login
                    </button>
                </form>

                <div class="auth-footer">
                    <a href="login.php">
                        <i class="fas fa-arrow-left"></i> Back to Login
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <footer class="mt-5 text-center p-3" style="background: rgba(255,255,255,0.1); color: #666; position: fixed; bottom: 0; width: 100%;">
        <p>&copy; 2025 Railway Feedback & Lost and Found. All rights reserved.</p>
    </footer>

    <script>
        // Countdown timer
        function startCountdown() {
            const expiryTime = <?php echo isset($_SESSION['code_expiry']) ? $_SESSION['code_expiry'] : 'null'; ?>;
            
            if (!expiryTime) return;
            
            const countdownElement = document.getElementById('countdown');
            const timerElement = document.getElementById('timer');
            
            function updateCountdown() {
                const now = Math.floor(Date.now() / 1000);
                const timeLeft = expiryTime - now;
                
                if (timeLeft <= 0) {
                    countdownElement.textContent = 'Expired';
                    timerElement.classList.add('expired');
                    timerElement.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Code has expired';
                    
                    const verifyBtn = document.getElementById('verifyBtn');
                    verifyBtn.disabled = true;
                    verifyBtn.innerHTML = '<i class="fas fa-clock"></i> Code Expired';
                    verifyBtn.classList.add('btn-secondary');
                    verifyBtn.classList.remove('btn-primary');
                    
                    setTimeout(() => {
                        window.location.href = 'login.php';
                    }, 3000);
                    return;
                }
                
                const minutes = Math.floor(timeLeft / 60);
                const seconds = timeLeft % 60;
                countdownElement.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
            }
            
            updateCountdown();
            setInterval(updateCountdown, 1000);
        }

        function validateVerificationForm() {
            const code = document.getElementById('verification_code').value;
            
            if (!/^\d{6}$/.test(code)) {
                alert('Please enter a valid 6-digit code');
                return false;
            }
            
            // Show loading state
            const verifyBtn = document.getElementById('verifyBtn');
            const form = document.getElementById('verificationForm');
            
            verifyBtn.disabled = true;
            verifyBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying...';
            form.classList.add('loading-state');
            
            return true;
        }

        // Auto-format input (numbers only)
        document.getElementById('verification_code').addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '');
        });

        // Auto-submit when 6 digits entered
        document.getElementById('verification_code').addEventListener('input', function() {
            if (this.value.length === 6) {
                const submitBtn = document.getElementById('verifyBtn');
                submitBtn.style.background = 'linear-gradient(135deg, #43e97b, #38f9d7)';
                submitBtn.innerHTML = '<i class="fas fa-rocket"></i> Auto-submitting...';
                
                setTimeout(() => {
                    if (validateVerificationForm()) {
                        document.getElementById('verificationForm').submit();
                    }
                }, 800);
            }
        });

        // Add focus effect to input
        document.getElementById('verification_code').addEventListener('focus', function() {
            this.style.transform = 'scale(1.02)';
            this.style.boxShadow = '0 0 20px rgba(102, 126, 234, 0.3)';
        });

        document.getElementById('verification_code').addEventListener('blur', function() {
            this.style.transform = 'scale(1)';
            this.style.boxShadow = '';
        });

        // Start countdown when page loads
        <?php if (!$redirect): ?>
        startCountdown();
        
        // Auto-focus on input
        document.getElementById('verification_code').focus();
        <?php endif; ?>
    </script>

    <?php if ($redirect): ?>
    <script>
        // Show success animation
        const card = document.querySelector('.verify-card');
        card.style.transform = 'scale(1.05)';
        card.style.boxShadow = '0 20px 60px rgba(67, 233, 123, 0.3)';
        
        setTimeout(() => {
            card.style.transform = 'scale(1)';
            window.location.href = '<?php echo $redirectUrl; ?>';
        }, 2000);
    </script>
    <?php endif; ?>
</body>
</html>

<?php ob_end_flush(); ?>