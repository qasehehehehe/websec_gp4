<?php
session_start();
include 'db_connection.php';

// Check if the user has a pending verification
if (!isset($_SESSION['temp_user_id']) || !isset($_SESSION['verification_code'])) {
    header('Location: login.php');
    exit();
}

$message = '';
$messageType = '';
$redirect = false;
$redirectUrl = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedCode = $_POST['verification_code'];
    $storedCode = $_SESSION['verification_code'];
    $codeExpiry = $_SESSION['code_expiry'];

    // Check if the code is expired
    if (time() > $codeExpiry) {
        $message = "Verification code has expired. Please login again.";
        $messageType = "error";

        // Clear session and redirect after 3 seconds
        session_destroy();
        header("refresh:3;url=login.php");
        exit();
    }

    // Verify the code
    elseif ($submittedCode === $storedCode) {
        // Set full session variables
        $_SESSION['user_id'] = $_SESSION['temp_user_id'];
        $_SESSION['role'] = $_SESSION['temp_user_role'];

        // Update verification attempt in the database
        $conn = getDatabaseConnection();
        $updateSql = "UPDATE verification_attempts SET is_used = TRUE WHERE user_id = ? AND verification_code = ?";
        $stmt = $conn->prepare($updateSql);
        $stmt->bind_param('is', $_SESSION['user_id'], $storedCode);
        $stmt->execute();
        $stmt->close();
        $conn->close();

        // Determine dashboard based on role
        $role = $_SESSION['role'];
        switch ($role) {
            case 'admin':
                $redirectUrl = 'admin_dashboard.php';
                break;
            case 'user':
                $redirectUrl = 'user_dashboard.php';
                break;
            default:
                $redirectUrl = 'login.php';
        }


        // Clear temporary session variables
        unset($_SESSION['temp_user_id'], $_SESSION['temp_user_role'], $_SESSION['verification_code'], $_SESSION['code_expiry'], $_SESSION['temp_user_email']);

        $message = "Verification successful! Redirecting to dashboard...";
        $messageType = "success";
        $redirect = true;

    header("Location: $redirectUrl");
    exit();
        

    } else {
        // Log the failed attempt
        $conn = getDatabaseConnection();
        $logSql = "INSERT INTO verification_failures (user_id, ip_address, attempted_code) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($logSql);
        $ipAddress = $_SERVER['REMOTE_ADDR'];
        $stmt->bind_param('iss', $_SESSION['temp_user_id'], $ipAddress, $submittedCode);
        $stmt->execute();
        $stmt->close();

        // Check the number of failed attempts
        $checkSql = "SELECT COUNT(*) as fail_count FROM verification_failures WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)";
        $stmt = $conn->prepare($checkSql);
        $stmt->bind_param('i', $_SESSION['temp_user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $failCount = $result->fetch_assoc()['fail_count'];
        $stmt->close();
        $conn->close();

        if ($failCount >= 5) {
            $message = "Too many failed attempts. Please try logging in again after 15 minutes.";
            $messageType = "error";
            session_destroy();
            header("refresh:3;url=login.php");
            exit();
        } else {
            $message = "Invalid verification code. Please try again. (" . (5 - $failCount) . " attempts remaining)";
            $messageType = "error";
        }
    }
}
?>


<!DOCTYPE html> 
<html lang="en"> 
    <head> 
        <meta charset="UTF-8"> 
        <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
        <link rel="stylesheet" href="/WebDev/Project/css/common.css"> 
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet"> 
        <title>Verify Code</title>

    <style>       
        .verify-container {
            background-color: #e0e0e0;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            width: 300px;
            text-align: center;
            margin-top: 45px;
            margin-left: 575px;
        }
        
        .verify-container h2 {
            margin-bottom: 20px;
            color: #003366;
            text-align: center;
        }
        
        .verify-container p {
            margin-bottom: 20px;
            color: #666;
            font-size: 0.9em;
            line-height: 1.5;
            text-align: center;
        }
        
        .code-input {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .code-input input {
            width: 40px;
            height: 40px;
            text-align: center;
            font-size: 20px;
            border: 2px solid #ccc;
            border-radius: 5px;
            margin: 0 2px;
        }
        
        .code-input input:focus {
            border-color: #003366;
            outline: none;
        }
        
        .verify-container button {
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
        
        .verify-container button:hover {
            background-color: #00509e;
        }
        
        .verify-container .resend {
            margin-top: 20px;
            color: #00509e;
            text-decoration: none;
            font-size: 0.9em;
            display: block;
        }
        
        .verify-container .resend:hover {
            text-decoration: underline;
        }
        
        .message {
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: left;
            font-size: 0.9em;
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

        .timer {
            margin-top: 15px;
            font-size: 0.9em;
            color: #666;
        }

        .timer.expiring {
            color: #dc3545;
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
        <div class="verify-container">
            <h2>Verify Your Identity</h2>
            <?php if (!empty($message)): ?>
                <div class="message <?php echo htmlspecialchars($messageType); ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            <p>
                Please enter the 6-digit verification code sent to your email 
                <?php 
                    $email = isset($_SESSION['temp_user_email']) ? $_SESSION['temp_user_email'] : ''; 
                    if ($email) {
                        echo htmlspecialchars(substr($email, 0, 3) . '***' . substr($email, strpos($email, '@')));
                    }
                ?>
            </p>
            <form method="POST" action="">
                <div class="code-input">
                    <input type="text" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                    <input type="text" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                    <input type="text" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                    <input type="text" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                    <input type="text" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                    <input type="text" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                </div>
                <input type="hidden" id="verification_code" name="verification_code">
                <button type="submit">Verify Code</button>
            </form>
            <div id="timer" class="timer">Time remaining: <span>10:00</span></div>
            <a href="login.php" class="resend">Didn't receive the code? Try again</a>
        </div>
    </main>
    <footer>
        <p>&copy; 2025 Railway Feedback & Lost and Found. All rights reserved.</p>
    </footer>
    <script>
        // Handle auto-focus and movement between code inputs
        const inputs = document.querySelectorAll('.code-input input');
        inputs.forEach((input, index) => {
            input.addEventListener('keypress', function (e) {
                if (e.key < '0' || e.key > '9') e.preventDefault();
            });

            input.addEventListener('paste', function (e) {
                e.preventDefault();
                const paste = e.clipboardData.getData('text');
                if (/^\d+$/.test(paste)) {
                    const digits = paste.split('');
                    inputs.forEach((input, i) => {
                        if (digits[i]) {
                            input.value = digits[i];
                            if (i < inputs.length - 1) inputs[i + 1].focus();
                        }
                    });
                }
            });

            input.addEventListener('input', function () {
                if (this.value.length === 1 && index < inputs.length - 1) {
                    inputs[index + 1].focus();
                }
            });

            input.addEventListener('keydown', function (e) {
                if (e.key === 'Backspace' && !this.value && index > 0) {
                    inputs[index - 1].focus();
                }
            });
        });

        // Combine inputs into hidden field before submit
        document.querySelector('form').addEventListener('submit', function () {
            const code = Array.from(inputs).map(input => input.value).join('');
            document.getElementById('verification_code').value = code;
        });

        // Timer functionality
        function startTimer(duration, display) {
            let timer = duration;
            const interval = setInterval(function () {
                const minutes = Math.floor(timer / 60);
                const seconds = timer % 60;
                display.textContent = `${minutes}:${seconds < 10 ? '0' : ''}${seconds}`;
                if (--timer < 0) {
                    clearInterval(interval);
                    display.textContent = "Code expired";
                    window.location.href = 'login.php';
                } else if (timer < 60) {
                    document.querySelector('.timer').classList.add('expiring');
                }
            }, 1000);
        }

        // Start 10-minute countdown
        startTimer(600, document.querySelector('#timer span'));

        <?php if (isset($redirect) && $redirect): ?>
            setTimeout(() => {
                window.location.href = '<?php echo htmlspecialchars($redirectUrl); ?>';
            }, 2000);
        <?php endif; ?>
    </script>
</body>
</html>