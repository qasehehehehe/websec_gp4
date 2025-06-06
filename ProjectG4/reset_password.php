<?php
ob_start();
session_start();
include 'db_connection.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$message = '';
$messageType = '';
$validToken = false;  // Initialize this at the start

// First, verify the token from URL
if (isset($_GET['token'])) {
    $token = trim($_GET['token']);
    $conn = getDatabaseConnection();

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
            $validToken = true;  // Set to true only if token is valid
        } else {
            $message = "Invalid or expired reset link. Please request a new one.";
            $messageType = "error";
        }
        $stmt->close();
    } else {
        $message = "An error occurred. Please try again.";
        $messageType = "error";
    }
    $conn->close();
}

// Handle password reset form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['reset_user_id'])) {
    $newPassword = $_POST['new-password'];
    $confirmPassword = $_POST['confirm-password'];
    
    // Validate password
    if (strlen($newPassword) < 6) {
        $message = "Password must be at least 6 characters long.";
        $messageType = "error";
    } elseif ($newPassword !== $confirmPassword) {
        $message = "Passwords do not match.";
        $messageType = "error";
    } else {
                // Database connection
                $conn = new mysqli('localhost', 'root', '', 'railway_pro');

                // Check the connection
                if ($conn->connect_error) {
                    die("Connection failed: " . $conn->connect_error);
                }
        
        // Begin transaction
        $conn->begin_transaction();
        try {
            // Update user's password
            $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
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
        }
        
        if (isset($updateStmt)) $updateStmt->close();
        if (isset($tokenStmt)) $tokenStmt->close();
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">  
    <link rel="stylesheet" href="/WebDev/Project/css/common.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <title>Reset Password</title>
    <style>
        .reset-container {
            background-color: #e0e0e0;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            text-align: center;
            margin-top: 65px;
            margin-left: 525px;
        }

        .reset-container h2 {
            color: #003366;
            margin-bottom: 30px;
            font-size: 24px;
            text-align: center;
        }

        .reset-container p {
            margin-bottom: 20px;
            color: #666;
            text-align: center;
        }

        .reset-container input {
            width: 100%;
            padding: 12px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .reset-container input:focus {
            outline: none;
            border-color: #003366;
            box-shadow: 0 0 5px rgba(0, 51, 102, 0.2);
        }

        .reset-container button {
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

        .reset-container button:hover {
            background-color: #00509e;
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

        .back-link {
            margin-top: 20px;
            display: block;
            color: #003366;
            text-decoration: none;
        }

        .back-link:hover {
            text-decoration: underline;
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
            <div class="reset-container">
                <h2>Reset Password</h2>

                <?php if (!empty($message)): ?>
                    <div class="message <?php echo $messageType; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <?php if ($validToken): ?>
                    <form method="POST" action="" onsubmit="return validateForm()">
                        <input type="password" id="new-password" name="new-password" placeholder="New Password" required>
                        <input type="password" id="confirm-password" name="confirm-password" placeholder="Confirm Password" required>
                        <button type="submit">Reset Password</button>
                    </form>
                <?php else: ?>
                    <p>Invalid or expired reset link. Please request a new password reset.</p>
                    <a href="forgot_password.php" class="back-link">Back to Forgot Password</a>
                <?php endif; ?>
            </div>
        </main>

    <footer>
        <p>&copy; 2025 Railway Feedback & Lost and Found. All rights reserved.</p>
    </footer>

    <script>
        function validateForm() {
            const password = document.getElementById('new-password').value;
            const confirmPassword = document.getElementById('confirm-password').value;

            if (password.length < 6) {
                alert('Password must be at least 6 characters long');
                return false;
            }

            if (!/[A-Z]/.test(password)) {
                alert('Password must include at least one uppercase letter');
                return false;
            }

            if (!/\d/.test(password)) {
                alert('Password must include at least one number');
                return false;
            }

            if (!/[!@#$%^&*]/.test(password)) {
                alert('Password must include at least one special character (!@#$%^&*)');
                return false;
            }

            if (password !== confirmPassword) {
                alert('Passwords do not match');
                return false;
            }

            return true;
        }
    </script>
</body>
</html>
<?php
ob_end_flush();
?>