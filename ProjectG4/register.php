<?php
ob_start();
session_start();
include 'db_connection.php';            
include 'config/email_config.php';

$message = '';
$messageType = '';
$redirectToLogin = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $firstName = trim($_POST['first-name']);
    $lastName = trim($_POST['last-name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $retypePassword = $_POST['retype-password'];

    $conn = getDatabaseConnection();

    // Check for duplicate username or email
    $checkSql = "SELECT * FROM users WHERE username = ? OR email = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param('ss', $username, $email);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if ($checkResult->num_rows > 0) {
        $existing = $checkResult->fetch_assoc();
        if ($existing['username'] === $username) {
            $message = 'Username already exists.';
        } else {
            $message = 'Email already registered.';
        }
        $messageType = 'error';
    } else {
        if ($password !== $retypePassword) {
            $message = 'Passwords do not match.';
            $messageType = 'error';
        } else {
            // Hash password
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

            $sql = "INSERT INTO users (username, first_name, last_name, email, password, role) VALUES (?, ?, ?, ?, ?, 'user')";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('sssss', $username, $firstName, $lastName, $email, $hashedPassword);

            if ($stmt->execute()) {
                // Send welcome email
                if (EmailService\sendWelcomeEmail($email, $firstName)) {
                    $message = 'Registration successful! Redirecting to login page...';
                } else {
                    $message = 'Registration successful! You can now login.';
                }
                $messageType = 'success';
                $redirectToLogin = true;
            } else {
                $message = 'Registration failed: ' . $stmt->error;
                $messageType = 'error';
            }
            $stmt->close();
        }
    }
    $checkStmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Railway Feedback & Lost and Found System - Sign Up</title>
    <link rel="stylesheet" href="assets/css/style.css" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
    <style>
        /* Inline overrides or additions to your main CSS */
        .signup-container {
            background-color: #e0e0e0;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
            margin: 40px auto;
            text-align: center;
        }

        .signup-container h2 {
            color: #003366;
            margin-bottom: 30px;
            font-size: 24px;
        }

        .signup-container input {
            width: 100%;
            padding: 12px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .signup-container input:focus {
            outline: none;
            border-color: #003366;
            box-shadow: 0 0 5px rgba(0, 51, 102, 0.2);
        }

        .name-container {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }

        .name-container input {
            margin-bottom: 0;
            flex: 1;
        }

        .signup-container button {
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

        .signup-container button:hover {
            background-color: #00509e;
        }

        .signup-container p {
            margin-top: 20px;
            color: #666;
        }

        .signup-container a {
            color: #003366;
            text-decoration: none;
            font-weight: 500;
        }

        .signup-container a:hover {
            text-decoration: underline;
        }

        .message {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: left;
            display: none;
        }

        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            display: block;
        }

        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            display: block;
        }

        @media (max-width: 480px) {
            .signup-container {
                padding: 20px;
            }
            .name-container {
                flex-direction: column;
                gap: 15px;
            }
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
    <div class="signup-container">
        <h2>Sign Up</h2>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" onsubmit="return validateForm()">
            <input type="text" id="username" name="username" placeholder="Username" required minlength="3" />

            <div class="name-container">
                <input type="text" id="first-name" name="first-name" placeholder="First Name" required />
                <input type="text" id="last-name" name="last-name" placeholder="Last Name" required />
            </div>

            <input type="email" id="email" name="email" placeholder="email@gmail.com" required />

            <input type="password" id="password" name="password" placeholder="Password" required />
            <input type="password" id="retype-password" name="retype-password" placeholder="Retype password" required />

            <button type="submit">Sign Up</button>
        </form>

        <p>Already have an account? <a href="login.php">Login</a></p>
    </div>
</main>

<footer>
    <p>&copy; 2025 Railway Feedback & Lost and Found . All rights reserved.</p>
</footer>

<script>
    function showMessage(message, type) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${type}`;
        messageDiv.textContent = message;
        const form = document.querySelector('form');
        form.parentNode.insertBefore(messageDiv, form);
        messageDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function validateForm() {
        const username = document.getElementById('username').value.trim();
        const firstName = document.getElementById('first-name').value.trim();
        const lastName = document.getElementById('last-name').value.trim();
        const email = document.getElementById('email').value.trim();
        const password = document.getElementById('password').value;
        const retypePassword = document.getElementById('retype-password').value;

        document.querySelectorAll('.message:not(.success)').forEach(msg => msg.remove());

        if (username.length < 3) {
            showMessage('Username must be at least 3 characters long', 'error');
            return false;
        }

        if (!firstName || !lastName) {
            showMessage('Please enter both first and last names', 'error');
            return false;
        }

        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            showMessage('Please enter a valid email address', 'error');
            return false;
        }

        if (password.length < 6) {
            showMessage('Password must be at least 6 characters long', 'error');
            return false;
        }

        if (password !== retypePassword) {
            showMessage('Passwords do not match', 'error');
            return false;
        }

        return true;
    }

    <?php if ($redirectToLogin): ?>
        setTimeout(() => {
            window.location.href = 'login.php';
        }, 3000);
    <?php endif; ?>
</script>

</body>
</html>
