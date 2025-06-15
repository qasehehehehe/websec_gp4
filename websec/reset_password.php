<?php
ob_start();
session_start();
include_once 'security_function.php';    // ADDED
initializeSecureSession();              // ADDED

$conn = getDatabaseConnection();        // CHANGED: Remove duplicate include

$message = '';
$messageType = '';
$validToken = false;

// First, verify the token from URL
if (isset($_GET['token'])) {
    // CHANGED: Added sanitization
    $token = sanitizeInput($_GET['token']);

    // Check if token exists and is not expired or used
    $sql = "SELECT pr.*, u.email 
            FROM password_resets pr 
            JOIN users u ON pr.user_id = u.id 
            WHERE pr.token = ? 
            AND pr.expiry > NOW() 
            AND pr.is_used = FALSE";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $reset_info = $result->fetch_assoc();
            $_SESSION['reset_user_id'] = $reset_info['user_id'];
            $_SESSION['reset_token'] = $token;
            $validToken = true;
        } else {
            $message = "Invalid or expired reset link. Please request a new one.";
            $messageType = "error";
            
            // ADDED: Log invalid token attempt
            logSecurityEvent('PASSWORD_RESET_INVALID_TOKEN', [
                'token' => $token,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        }
        $stmt->close();
    } else {
        $message = "An error occurred. Please try again.";
        $messageType = "error";
    }
}

// Handle password reset form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['reset_user_id'])) {
    // ADDED: CSRF Protection
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Security error: Invalid request. Please try again.';
        $messageType = 'error';
        logSecurityEvent('CSRF_TOKEN_INVALID', ['action' => 'password_reset']);
    } else {
        $newPassword = $_POST['new-password'];
        $confirmPassword = $_POST['confirm-password'];
        
        // CHANGED: Enhanced password validation
        if (strlen($newPassword) < 12) {  // Changed from 6 to 12
            $message = "Password must be at least 12 characters long.";
            $messageType = "error";
        } elseif ($newPassword !== $confirmPassword) {
            $message = "Passwords do not match.";
            $messageType = "error";
        } else {
            // ADDED: Password strength validation
            $passwordValidation = validateStrongPassword($newPassword);
            if (!$passwordValidation['valid']) {
                $message = $passwordValidation['message'];
                $messageType = "error";
            } else {
                // Begin transaction
                $conn->begin_transaction();
                try {
                    // CHANGED: Use system's hash function
                    $hashedPassword = hashPasswordWithSalt($newPassword);
                    $updateSql = "UPDATE users SET password = ? WHERE id = ?";
                    $updateStmt = $conn->prepare($updateSql);
                    $updateStmt->bind_param('si', $hashedPassword, $_SESSION['reset_user_id']);
                    $updateStmt->execute();

                    // Mark reset token as used
                    $tokenSql = "UPDATE password_resets SET is_used = TRUE WHERE token = ?";
                    $tokenStmt = $conn->prepare($tokenSql);
                    $tokenStmt->bind_param('s', $_SESSION['reset_token']);
                    $tokenStmt->execute();

                    // Commit transaction
                    $conn->commit();

                    $message = "Password successfully reset! Redirecting to login page...";
                    $messageType = "success";
                    
                    // ADDED: Log successful reset
                    logSecurityEvent('PASSWORD_RESET_SUCCESS', [
                        'user_id' => $_SESSION['reset_user_id'],
                        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                    ]);
                    
                    // Clear reset session data
                    unset($_SESSION['reset_user_id']);
                    unset($_SESSION['reset_token']);
                    
                    // Redirect after showing message
                    header("refresh:3;url=login.php");
                    
                } catch (Exception $e) {
                    // Rollback on error
                    $conn->rollback();
                    $message = "An error occurred. Please try again.";
                    $messageType = "error";
                    
                    // ADDED: Log error
                    logSecurityEvent('PASSWORD_RESET_ERROR', [
                        'error' => $e->getMessage(),
                        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                    ]);
                }
                
                if (isset($updateStmt)) $updateStmt->close();
                if (isset($tokenStmt)) $tokenStmt->close();
            }
        }
    }
}

// ADDED: Generate CSRF token
$csrfToken = generateCSRFToken();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="railway-styles.css" rel="stylesheet">
    <title>Reset Password - Railway System</title>
    <style>
        .auth-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .auth-card {
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

        .auth-header {
            margin-bottom: 2rem;
        }

        .auth-header .logo-section {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 15px;
            margin-bottom: 1.5rem;
        }

        .auth-header .logo-section img {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .auth-header .logo-text h1 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .auth-header h2 {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #333;
        }

        .auth-header p {
            color: #666;
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .auth-form {
            text-align: left;
            margin-bottom: 2rem;
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
            margin: 0 0.75rem;
        }

        .auth-footer a:hover {
            text-decoration: underline;
        }

        .divider {
            margin: 1.5rem 0;
            text-align: center;
            position: relative;
        }

        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: rgba(0, 0, 0, 0.1);
        }

        .divider span {
            background: rgba(255, 255, 255, 0.95);
            padding: 0 1rem;
            color: #666;
            font-size: 0.875rem;
        }

        .password-strength {
            margin-top: 5px;
            font-size: 12px;
        }

        .strength-weak { color: #dc3545; }
        .strength-medium { color: #ffc107; }
        .strength-strong { color: #28a745; }

        @media (max-width: 480px) {
            .auth-card {
                padding: 2rem 1.5rem;
                margin: 1rem;
            }
            
            .auth-header .logo-section {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="logo-section">
                    <img src="img/logotest.jpg" alt="Railway Logo">
                    <div class="logo-text">
                        <h1>Railway System</h1>
                    </div>
                </div>
                <h2><i class="fas fa-lock"></i> Reset Your Password</h2>
                <p>Enter your new password below. Make sure it's strong and secure.</p>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($validToken): ?>
                <form method="POST" action="" onsubmit="return validateForm()" class="auth-form">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    
                    <div class="form-group">
                        <label class="form-label" for="new-password">New Password</label>
                        <input type="password" id="new-password" name="new-password" class="form-control" 
                               placeholder="Enter new password" required minlength="12">
                        <div id="passwordStrength" class="password-strength"></div>
                        <small class="text-muted">Must be at least 12 characters with uppercase, lowercase, numbers, and special characters</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="confirm-password">Confirm Password</label>
                        <input type="password" id="confirm-password" name="confirm-password" class="form-control" 
                               placeholder="Confirm new password" required minlength="12">
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; font-size: 1rem;">
                        <i class="fas fa-lock"></i> Reset Password
                    </button>
                </form>
            <?php else: ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    Invalid or expired reset link. Please request a new password reset.
                </div>
                
                <div class="auth-footer">
                    <a href="forgot_password.php">
                        <i class="fas fa-arrow-left"></i> Back to Forgot Password
                    </a>
                    <a href="login.php">
                        <i class="fas fa-sign-in-alt"></i> Back to Login
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <footer class="mt-5 text-center p-3" style="background: rgba(255,255,255,0.1); color: #666; position: fixed; bottom: 0; width: 100%;">
        <p>&copy; 2025 Railway Feedback & Lost and Found. All rights reserved.</p>
    </footer>

    <script>
        // Real-time password strength checker
        document.getElementById('new-password').addEventListener('input', function() {
            const password = this.value;
            const strengthDiv = document.getElementById('passwordStrength');
            
            let score = 0;
            let feedback = [];
            
            if (password.length >= 12) score++;
            else feedback.push('At least 12 characters');
            
            if (/[A-Z]/.test(password)) score++;
            else feedback.push('Uppercase letter');
            
            if (/[a-z]/.test(password)) score++;
            else feedback.push('Lowercase letter');
            
            if (/[0-9]/.test(password)) score++;
            else feedback.push('Number');
            
            if (/[^A-Za-z0-9]/.test(password)) score++;
            else feedback.push('Special character');
            
            if (score < 3) {
                strengthDiv.className = 'password-strength strength-weak';
                strengthDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Weak - Missing: ' + feedback.join(', ');
            } else if (score < 5) {
                strengthDiv.className = 'password-strength strength-medium';
                strengthDiv.innerHTML = '<i class="fas fa-shield-alt"></i> Medium - Missing: ' + feedback.join(', ');
            } else {
                strengthDiv.className = 'password-strength strength-strong';
                strengthDiv.innerHTML = '<i class="fas fa-check-circle"></i> Strong password';
            }
        });

        // Password confirmation validation
        document.getElementById('confirm-password').addEventListener('input', function() {
            const newPassword = document.getElementById('new-password').value;
            const confirmPassword = this.value;
            
            if (newPassword !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });

        function validateForm() {
            const password = document.getElementById('new-password').value;
            const confirmPassword = document.getElementById('confirm-password').value;

            if (password.length < 12) {
                alert('Password must be at least 12 characters long');
                return false;
            }

            if (password !== confirmPassword) {
                alert('Passwords do not match');
                return false;
            }

            // Check password strength
            let score = 0;
            if (/[A-Z]/.test(password)) score++;
            if (/[a-z]/.test(password)) score++;
            if (/[0-9]/.test(password)) score++;
            if (/[^A-Za-z0-9]/.test(password)) score++;

            if (score < 4) {
                alert('Password must contain uppercase, lowercase, numbers, and special characters');
                return false;
            }

            return true;
        }
    </script>
</body>
</html>
<?php ob_end_flush(); ?>