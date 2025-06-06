<?php
ob_start();
session_start();
include 'db_connection.php';
include 'config/email_config.php';

$message = '';
$messageType = '';
$redirect = false;
$redirectUrl = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $conn = getDatabaseConnection();

    $sql = "SELECT * FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            // No role check here — get role from DB
            $role = $user['role'];

            // Generate 6-digit verification code
            $verificationCode = sprintf("%06d", mt_rand(0, 999999));
            
            // Store user data and verification code in session
            $_SESSION['temp_user_id'] = $user['id'];
            $_SESSION['temp_user_role'] = $role;
            $_SESSION['temp_user_email'] = $user['email'];
            $_SESSION['verification_code'] = $verificationCode;
            $_SESSION['code_expiry'] = time() + (10 * 60); // 10 minutes
            
            if (EmailService\sendVerificationCode($user['email'], $verificationCode)) {
                // Store verification attempt
                $storeTwoFASql = "INSERT INTO verification_attempts (user_id, verification_code, expiry) VALUES (?, ?, ?)";
                $twoFAStmt = $conn->prepare($storeTwoFASql);
                $expiryTime = date('Y-m-d H:i:s', $_SESSION['code_expiry']);
                $twoFAStmt->bind_param('iss', $user['id'], $verificationCode, $expiryTime);
                
                if ($twoFAStmt->execute()) {
                    $message = "Verification code sent to " . substr($user['email'], 0, 3) . "***" . 
                            substr($user['email'], strpos($user['email'], '@'));
                    $messageType = "success";
                    $redirect = true;

                    // Redirect based on role after 2FA
                    if ($role === 'admin') {
                        $redirectUrl = 'verify.php?redirect=admin_dashboard.php';
                    } else {
                        $redirectUrl = 'verify.php?redirect=user_dashboard.php';
                    }
                } else {
                    $message = "Error storing verification. Please try again.";
                    $messageType = "error";
                }
                $twoFAStmt->close();
            } else {
                $message = "Failed to send verification code. Please try again.";
                $messageType = "error";
            }
        } else {
            $message = "Invalid credentials.";
            $messageType = "error";
        }
    } else {
        $message = "User not found.";
        $messageType = "error";
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
    <link rel="stylesheet" href="/WebDev/Project/css/common.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <title>Login</title>
    <style>
        .login-container {
            background-color: #e0e0e0;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            text-align: center;
            margin-left: 565px;
        }

        .login-container h2 {
            color: #003366;
            margin-bottom: 30px;
            font-size: 24px;
            text-align: center;
        }

        .login-container input,
        .login-container select {
            width: 100%;
            padding: 12px;
            padding-left: 5px;
            padding-right: 6px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .login-container input:focus,
        .login-container select:focus {
            outline: none;
            border-color: #003366;
            box-shadow: 0 0 5px rgba(0, 51, 102, 0.2);
        }

        .login-container label {
            display: block;
            text-align: left;
            margin-bottom: 5px;
            color: #333;
            font-weight: 500;
        }

        .login-container button {
            width: 100%;
            padding: 12px;
            background-color: #003366;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 10px;
            transition: background-color 0.3s;
        }

        .login-container button:hover {
            background-color: #00509e;
        }

        .login-container p {
            margin-top: 20px;
            color: #666;
        }

        .login-container a {
            color: #003366;
            text-decoration: none;
            font-weight: 500;
        }

        .login-container a:hover {
            text-decoration: underline;
        }

        .message {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: left;
        }

        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .forgot {
            display: block;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <header>
        <div class="logo">
<img src="img/logotest.jpg" alt="Logo" style="max-width: 80px; height: auto;">
            <h1>Railway Feedback & Lost and Found System </h1>
        </div>
    </header>
    <main>
        <div class="login-container">
            <h2>Login</h2>
            <?php if (!empty($message)): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" placeholder="Username" required>
                
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Password" required>
                
                <button type="submit">Login</button>
            </form>
            
            <p>Don't have an account? <a href="register.php">SignUp</a></p>
            <a href="forgot_password.php" class="forgot">Forgot Password?</a>
        </div>
    </main>
    <footer>
        <p>&copy; 2025 Railway Feedback & Lost and Found . All rights reserved.</p>
    </footer>

    <?php if ($redirect): ?>
    <script>
        setTimeout(() => {
            window.location.href = '<?php echo $redirectUrl; ?>';
        }, 3000);
    </script>
    <?php endif; ?>
</body>
</html>

<?php
ob_end_flush();
?>