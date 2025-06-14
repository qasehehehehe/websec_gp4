<?php
ob_start();
session_start();
include 'db_connection.php';            
include 'config/email_config.php';
include __DIR__ . '/security_function.php';

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
$redirectToLogin = false;
$formData = []; // Store form data for repopulation on error

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Security error: Invalid request. Please try again.';
        $messageType = 'error';
        logSecurityEvent('CSRF_TOKEN_INVALID', ['action' => 'registration']);
    } else {
        // Sanitize all inputs
        $username = sanitizeInput($_POST['username'] ?? '');
        $firstName = sanitizeInput($_POST['first-name'] ?? '');
        $lastName = sanitizeInput($_POST['last-name'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $idNumber = sanitizeInput($_POST['id-number'] ?? '');
        $password = $_POST['password'] ?? '';
        $retypePassword = $_POST['retype-password'] ?? '';
        
        // Store form data for repopulation
        $formData = [
            'username' => $username,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'id_number' => $idNumber
        ];
        
        // Validation array to collect all errors
        $validationErrors = [];
        
        // Validate username
        $usernameValidation = validateUsername($username);
        if (!$usernameValidation['valid']) {
            $validationErrors[] = $usernameValidation['message'];
        }
        
        // Validate names
        if (empty($firstName) || strlen($firstName) < 2) {
            $validationErrors[] = 'First name must be at least 2 characters long';
        }
        if (empty($lastName) || strlen($lastName) < 2) {
            $validationErrors[] = 'Last name must be at least 2 characters long';
        }
        
        // Validate email
        $emailValidation = validateEmail($email);
        if (!$emailValidation['valid']) {
            $validationErrors[] = $emailValidation['message'];
        }
        
        // Validate ID Number (CRITICAL REQUIREMENT)
        $idValidation = validateIdNumber($idNumber);
        if (!$idValidation['valid']) {
            $validationErrors[] = $idValidation['message'];
        } else {
            // Store the hashed IC for database insertion
            $hashedICNumber = hashICNumber($idNumber);
        }
        
        // CRITICAL: Validate password strength using security function
        if (empty($password)) {
            $validationErrors[] = 'Password is required';
        } else {
            // Use the security function for validation
            $passwordValidation = validateStrongPassword($password);
            if (!$passwordValidation['valid']) {
                $validationErrors[] = $passwordValidation['message'];
            }
        }
        
        // Check password match
        if ($password !== $retypePassword) {
            $validationErrors[] = 'Passwords do not match';
        }
        
        // If validation passes, check database constraints
        if (empty($validationErrors)) {
            $conn = getDatabaseConnection();
            
            // Check for duplicate username or email
            $checkSql = "SELECT username, email FROM users WHERE username = ? OR email = ?";
            $checkStmt = $conn->prepare($checkSql);
            if ($checkStmt) {
                $checkStmt->bind_param('ss', $username, $email);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();
                
                if ($checkResult->num_rows > 0) {
                    $existing = $checkResult->fetch_assoc();
                    if ($existing['username'] === $username) {
                        $validationErrors[] = 'Username already exists. Please choose another.';
                    }
                    if ($existing['email'] === $email) {
                        $validationErrors[] = 'Email is already registered. Please use another email or try logging in.';
                    }
                }
                $checkStmt->close();
            }
            
            // If no validation errors, proceed with registration
            if (empty($validationErrors)) {
                // Generate enrollment ID
                $enrollmentResult = generateAndValidateEnrollmentID($conn);
                
                if (!$enrollmentResult['valid']) {
                    $validationErrors[] = $enrollmentResult['message'];
                } else {
                    $enrollmentID = $enrollmentResult['enrollment_id'];
                    
                    // Hash password with salt (built-in to password_hash)
                    $hashedPassword = hashPasswordWithSalt($password);
                    
                    // Insert new user with enrollment_id
                    $insertSql = "INSERT INTO users (username, first_name, last_name, email, id_number_hash, enrollment_id, password, role, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'user', 1, NOW())";
                    $insertStmt = $conn->prepare($insertSql);
                    
                    if ($insertStmt) {
                        $insertStmt->bind_param('sssssss', $username, $firstName, $lastName, $email, $hashedICNumber, $enrollmentID, $hashedPassword);
                        
                        if ($insertStmt->execute()) {
                            // Log successful registration
                            logSecurityEvent('USER_REGISTRATION_SUCCESS', [
                                'username' => $username,
                                'email' => $email
                            ]);
                            logUserActivity($username, 'registered successfully', "enrollment ID: {$enrollmentID}");
                            
                            // Send welcome email
                            if (EmailService\sendWelcomeEmail($email, $firstName)) {
                                $message = "✅ Registration successful! Your enrollment ID is: <strong>{$enrollmentID}</strong><br>Welcome email sent. Redirecting to login page...";
                            } else {
                                $message = "✅ Registration successful! Your enrollment ID is: <strong>{$enrollmentID}</strong><br>You can now login.";
                            }
                            $messageType = 'success';
                            $redirectToLogin = true;
                            
                            // Clear form data on success
                            $formData = [];
                        } else {
                            $validationErrors[] = 'Registration failed: Database error. Please try again.';
                            logSecurityEvent('USER_REGISTRATION_DB_ERROR', [
                                'username' => $username,
                                'error' => $insertStmt->error
                            ]);
                        }
                        $insertStmt->close();
                    } else {
                        $validationErrors[] = 'Registration failed: System error. Please try again.';
                    }
                }
            }
            
            if (isset($conn)) {
                $conn->close();
            }
        }
        
        // Display validation errors
        if (!empty($validationErrors)) {
            $message = implode('<br>', $validationErrors);
            $messageType = 'error';
        }
    }
}

// Generate CSRF token for form
$csrfToken = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="railway-styles.css" rel="stylesheet">
    <title>Secure Registration - Railway System</title>
    <style>
        .name-container {
            display: flex;
            gap: 10px;
        }

        .name-container .form-group {
            flex: 1;
        }

        .help-text {
            font-size: 12px;
            color: #666;
            margin-top: 3px;
        }

        .password-strength {
            margin-top: 5px;
            font-size: 12px;
        }

        .strength-weak { color: #dc3545; }
        .strength-medium { color: #ffc107; }
        .strength-strong { color: #28a745; }

        .required {
            color: #dc3545;
        }

        .form-control.error {
            border-color: #dc3545;
            box-shadow: 0 0 8px rgba(220, 53, 69, 0.2);
        }

        code {
            background: #e9ecef;
            padding: 2px 4px;
            border-radius: 3px;
            font-family: monospace;
        }

        @media (max-width: 480px) {
            .name-container {
                flex-direction: column;
                gap: 0;
            }
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <img src="img/logotest.jpg" alt="Railway Logo" style="width: 60px; height: 60px; border-radius: 12px; margin-bottom: 1rem;">
                <h2><i class="fas fa-user-shield"></i> Secure Registration</h2>
                <p>Create your Railway account with enhanced security</p>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" onsubmit="return validateForm()" id="registrationForm" class="auth-form">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                
                <div class="form-group">
                    <label for="username" class="form-label">Username <span class="required">*</span></label>
                    <input type="text" id="username" name="username" class="form-control" required minlength="3" 
                           value="<?php echo htmlspecialchars($formData['username'] ?? ''); ?>"
                           placeholder="Enter username (letters, numbers, underscore only)">
                    <div class="help-text">At least 3 characters, letters, numbers, and underscore only</div>
                </div>

                <div class="name-container">
                    <div class="form-group">
                        <label for="first-name" class="form-label">First Name <span class="required">*</span></label>
                        <input type="text" id="first-name" name="first-name" class="form-control" required minlength="2"
                               value="<?php echo htmlspecialchars($formData['first_name'] ?? ''); ?>"
                               placeholder="First Name">
                    </div>
                    <div class="form-group">
                        <label for="last-name" class="form-label">Last Name <span class="required">*</span></label>
                        <input type="text" id="last-name" name="last-name" class="form-control" required minlength="2"
                               value="<?php echo htmlspecialchars($formData['last_name'] ?? ''); ?>"
                               placeholder="Last Name">
                    </div>
                </div>

                <div class="form-group">
                    <label for="email" class="form-label">Email Address <span class="required">*</span></label>
                    <input type="email" id="email" name="email" class="form-control" required 
                           value="<?php echo htmlspecialchars($formData['email'] ?? ''); ?>"
                           placeholder="email@example.com">
                </div>

                <div class="form-group">
                    <label for="id-number" class="form-label">Malaysian ID Number <span class="required">*</span></label>
                    <input type="text" id="id-number" name="id-number" class="form-control" required 
                           pattern="[0-9]{12}" maxlength="12"
                           value="<?php echo htmlspecialchars($formData['id_number'] ?? ''); ?>"
                           placeholder="123456789012">
                    <div class="help-text">12-digit Malaysian IC number (only users enrolled from 2020 onwards)</div>
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Password <span class="required">*</span></label>
                    <input type="password" id="password" name="password" class="form-control" required minlength="8"
                           placeholder="Strong password">
                    <div id="passwordStrength" class="password-strength"></div>
                    <div class="help-text">Must contain uppercase, lowercase, numbers, and special characters</div>
                </div>

                <div class="form-group">
                    <label for="retype-password" class="form-label">Confirm Password <span class="required">*</span></label>
                    <input type="password" id="retype-password" name="retype-password" class="form-control" required minlength="8"
                           placeholder="Retype password">
                </div>

                <button type="submit" id="submitBtn" class="btn btn-primary">
                    <i class="fas fa-user-plus"></i> Create Secure Account
                </button>
            </form>

            <div class="auth-footer">
                <p>Already have an account? <a href="login.php"><i class="fas fa-sign-in-alt"></i> Login</a></p>
            </div>
        </div>
    </div>

    <script>
        // Real-time password strength checker
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthDiv = document.getElementById('passwordStrength');
            
            let score = 0;
            let feedback = [];
            
            if (password.length >= 8) score++;
            else feedback.push('At least 8 characters');
            
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

        // Real-time ID number validation
        document.getElementById('id-number').addEventListener('input', function() {
            let value = this.value.replace(/\D/g, ''); // Remove non-digits
            this.value = value;
            
            if (value.length === 12) {
                // Check birth year from first 2 digits
                const yearDigits = parseInt(value.substring(0, 2));
                const birthYear = yearDigits <= 30 ? 2000 + yearDigits : 1900 + yearDigits;
                
                if (birthYear < 2000) {
                    this.classList.add('error');
                    this.setCustomValidity('Only users enrolled from 2020 onwards are allowed');
                } else {
                    this.classList.remove('error');
                    this.setCustomValidity('');
                }
            }
        });

        // Form validation
        function validateForm() {
            const password = document.getElementById('password').value;
            const retypePassword = document.getElementById('retype-password').value;
            const idNumber = document.getElementById('id-number').value;
            
            // Password match validation
            if (password !== retypePassword) {
                alert('Passwords do not match');
                return false;
            }
            
            // ID number validation
            if (idNumber.length !== 12) {
                alert('ID number must be exactly 12 digits');
                return false;
            }
            
            // Submit button loading state
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating Account...';
            
            return true;
        }

        // Confirm password validation
        document.getElementById('retype-password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const retypePassword = this.value;
            
            if (password !== retypePassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });

        <?php if ($redirectToLogin): ?>
            setTimeout(() => {
                window.location.href = 'login.php';
            }, 3000);
        <?php endif; ?>
    </script>

</body>
</html>
<?php ob_end_flush(); ?>