<?php
session_start();
require 'db_connection.php';

$conn = getDatabaseConnection();

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    // Optional fallback if not logged in
    header("Location: login.php");
    exit;
}

$message = '';
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $new_email = $_POST['email'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $message = "Invalid email format.";
    } else {
        if (!empty($new_password)) {
            if ($new_password !== $confirm_password) {
                $message = "Passwords do not match.";
            } else {
                $salt = bin2hex(random_bytes(16));
                $salted_password = $new_password . $salt;
                $hashed_password = password_hash($salted_password, PASSWORD_DEFAULT);

                $stmt = $conn->prepare("UPDATE users SET email = ?, password = ?, salt = ? WHERE id = ?");
                $stmt->bind_param("sssi", $new_email, $hashed_password, $salt, $user_id);
            }
        } else {
            $stmt = $conn->prepare("UPDATE users SET email = ? WHERE id = ?");
            $stmt->bind_param("si", $new_email, $user_id);
        }

        if (isset($stmt) && $stmt->execute()) {
            $message = "Profile updated successfully!";
        } else {
            $message = "Update failed: " . $stmt->error;
        }

        if (isset($stmt)) {
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Update Profile</title>
    <link rel="stylesheet" href="css/common.css">
    <style>
        .update-container {
            background-color: #f9f9f9;
            padding: 40px;
            border-radius: 12px;
            width: 100%;
            max-width: 500px;
            margin: 50px auto;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        h2 {
            text-align: center;
            color: #003366;
        }

        form label {
            display: block;
            margin-top: 15px;
            color: #333;
            font-weight: bold;
        }

        form input {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            border-radius: 5px;
            border: 1px solid #ccc;
        }

        form button {
            margin-top: 20px;
            background-color: #003366;
            color: #fff;
            padding: 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            width: 100%;
            font-size: 16px;
        }

        form button:hover {
            background-color: #00509e;
        }

        .message {
            margin-top: 20px;
            padding: 12px;
            border-radius: 5px;
            text-align: center;
        }

        .message.success {
            background-color: #d4edda;
            color: #155724;
        }

        .message.error {
            background-color: #f8d7da;
            color: #721c24;
        }

        .back-button {
            display: inline-block;
            margin-top: 20px;
            background-color: #6c757d;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 6px;
        }

        .back-button:hover {
            background-color: #5a6268;
        }
    </style>
</head>
<body>
    <header>
        <div class="logo">
            <img src="img/logotest.jpg" alt="Logo" style="max-width: 80px; height: auto;">
            <h1>Railway Feedback & Lost and Found System</h1>
        </div>
    </header>

    <main>
        <div class="update-container">
            <h2>Update Profile</h2>

            <?php if (!empty($message)): ?>
                <div class="message <?= strpos($message, 'successfully') !== false ? 'success' : 'error' ?>">
                    <?= $message ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <label for="email">New Email:</label>
                <input type="email" id="email" name="email" required>

                <label for="new_password">New Password (optional):</label>
                <input type="password" id="new_password" name="new_password">

                <label for="confirm_password">Confirm New Password:</label>
                <input type="password" id="confirm_password" name="confirm_password">

                <button type="submit">Update Profile</button>
            </form>

            <a href="user_dashboard.php" class="back-button">← Back to Dashboard</a>
        </div>
    </main>

    <footer>
        <p>&copy; 2025 Railway Feedback & Lost and Found System. All rights reserved.</p>
    </footer>
</body>
</html>

