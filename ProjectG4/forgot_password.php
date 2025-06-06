<?php
ob_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
include 'db_connection.php';
include 'config/email_config.php';

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
            } else {
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
<html lang="en">>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">  
    <link rel="stylesheet" href="/WebDev/Project/css/common.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <title>Forgot Password</title>
    <style>
        .forgot-container {
            background-color: #e0e0e0;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            text-align: center;
            margin-top: 45px;
            margin-left: 525px;
        }

        .forgot-container h2 {
            color: #003366;
            margin-bottom: 30px;
            font-size: 24px;
            text-align: center;
        }

        .forgot-container p {
            margin-bottom: 20px;
            color: #666;
            font-size: 14px;
            text-align: center;
        }

        .forgot-container input[type="email"] {
            width: 100%;
            padding: 12px;
            margin-bottom: 20px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .forgot-container input[type="email"]:focus {
            outline: none;
            border-color: #003366;
            box-shadow: 0 0 5px rgba(0, 51, 102, 0.2);
        }

        .forgot-container button {
            width: 100%;
            padding: 12px;
            background-color: #003366;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s;
        }

        .forgot-container button:hover {
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

        .links-container {
            margin-top: 20px;
        }

        .links-container a {
            color: #003366;
            text-decoration: none;
            margin: 0 10px;
            font-weight: 500;
        }

        .links-container a:hover {
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
    <div class="forgot-container">
        <h2>Trouble logging in?</h2>
            
        <?php if (!empty($message)): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <p>Enter your email and we'll send you a link to reset your password.</p>
            
        <form method="POST" action="">
            <input type="email" name="email" placeholder="Enter your email" required>
            <button type="submit">Send Reset Link</button>
        </form>
            
        <div class="links-container">
            <a href="register.php">Create new account</a>
            <a href="login.php">Back to login</a>
        </div>
    </div>
</main>

<footer>
        <p>&copy; 2025 Railway Feedback & Lost and Found. All rights reserved.</p>
    </footer>
</body>
</html>
<?php
ob_end_flush();
?>