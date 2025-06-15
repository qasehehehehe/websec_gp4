<?php
ob_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
include 'db_connection.php';
include 'config/email_config.php';
include 'security_function.php';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $conn = getDatabaseConnection();

    // Check if email exists
    $sql = "SELECT * FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $token = bin2hex(random_bytes(32));
        
        $currentTime = new DateTime('now', new DateTimeZone('Asia/Kuala_Lumpur'));
        $expiryTime = clone $currentTime;
        $expiryTime->modify('+1 hour');
        $expiry = $expiryTime->format('Y-m-d H:i:s');

        // Mark existing tokens as used
        $updateSql = "UPDATE password_resets SET is_used = TRUE WHERE user_id = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param('i', $user['id']);
        $updateStmt->execute();
        $updateStmt->close();

        // Insert new token
        $insertSql = "INSERT INTO password_resets (user_id, token, expiry) VALUES (?, ?, ?)";
        $insertStmt = $conn->prepare($insertSql);
        $insertStmt->bind_param('iss', $user['id'], $token, $expiry);
        
        if ($insertStmt->execute()) {
            if (EmailService\sendPasswordResetLink($email, $token)) {
                $message = "Password reset link has been sent to your email";
                $messageType = "success";

                logUserActivity($user['username'], 'requested password reset', 'reset link sent to email');
            }
            else {
                $message = "Failed to send reset link. Please try again.";
                $messageType = "error";
            }
        } else {
            $message = "An error occurred. Please try again.";
            $messageType = "error";
        }
        $insertStmt->close();
    } else {
        // For security, show same message even if email doesn't exist
        $message = "If your email is registered, you will receive a password reset link";
        $messageType = "success";
    }
    $stmt->close();
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="railway-styles.css" rel="stylesheet">
    <title>Forgot Password - Railway System</title>
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
                <h2><i class="fas fa-key"></i> Trouble logging in?</h2>
                <p>Enter your email and we'll send you a link to reset your password.</p>
            </div>
                
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?> mb-4">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" class="auth-form">
                <div class="form-group mb-4">
                    <label class="form-label" for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control" 
                           placeholder="Enter your email address" required>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; font-size: 1rem;">
                    <i class="fas fa-paper-plane"></i> Send Reset Link
                </button>
            </form>

            <div class="divider">
                <span>or</span>
            </div>
                
            <div class="auth-footer">
                <a href="register.php">
                    <i class="fas fa-user-plus"></i> Create new account
                </a>
                <a href="login.php">
                    <i class="fas fa-arrow-left"></i> Back to login
                </a>
            </div>
        </div>
    </div>

    <footer class="mt-5 text-center p-3" style="background: rgba(255,255,255,0.1); color: #666; position: fixed; bottom: 0; width: 100%;">
        <p>&copy; 2025 Railway Feedback & Lost and Found. All rights reserved.</p>
    </footer>
</body>
</html>
<?php ob_end_flush(); ?>