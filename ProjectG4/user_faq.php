<?php
session_start();

// Redirect to login if user is not authenticated
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User FAQ - Railway System</title>
    <link rel="stylesheet" href="/WebDev/Project/css/common.css">
    <link rel="stylesheet" href="/WebDev/Project/css/style.css">
    <style>
        .faq-container {
            max-width: 1000px;
            margin: 50px auto;
            padding: 30px;
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }

        .faq-container h2 {
            text-align: center;
            color: #003366;
            margin-bottom: 30px;
        }

        .faq-item {
            background: #003366;
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s ease-in-out;
        }

        .faq-item:hover {
            background-color: #005599;
        }

        .faq-item h3 {
            margin: 0 0 10px;
            font-size: 20px;
        }

        .faq-item p {
            margin: 0;
            font-size: 16px;
            line-height: 1.5;
        }

        .back-btn {
            text-align: center;
            margin-top: 30px;
        }

        .back-btn a {
            background-color: #003366;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 8px;
            transition: background-color 0.3s;
        }

        .back-btn a:hover {
            background-color: #005599;
        }
    </style>
</head>
<body>

<header>
    <div class="logo">
        <img src="img/logotest.jpg" alt="Logo" style="max-width: 80px;">
        <h1>Railway Feedback & Lost and Found System</h1>
    </div>
</header>

<main>
    <div class="faq-container">
        <h2>Frequently Asked Questions (FAQ)</h2>

        <div class="faq-item">
            <h3>1. How do I submit feedback about my train journey?</h3>
            <p>Click on "Submit Feedback" on your dashboard. Fill out the form and provide details about your experience.</p>
        </div>

        <div class="faq-item">
            <h3>2. How can I report a lost item?</h3>
            <p>Select "Report Lost Item" on your dashboard and enter details like item description, train number, and date.</p>
        </div>

        <div class="faq-item">
            <h3>3. What happens after I report a lost item?</h3>
            <p>Our staff will review your report. If a matching item is found, you will be contacted via your registered email or phone.</p>
        </div>

        <div class="faq-item">
            <h3>4. How does Multi-Factor Authentication (MFA) work in this system?</h3>
            <p>MFA adds an extra security layer. After logging in with your password, you’ll need to verify via email or mobile OTP.</p>
        </div>

        <div class="faq-item">
            <h3>5. Why is my password salted and hashed?</h3>
            <p>Salting and hashing protect your password from being stolen even if the database is compromised. It's a key security measure.</p>
        </div>

        <div class="faq-item">
            <h3>6. Can I update my profile information?</h3>
            <p>Yes. Click on "Update Profile" to change your name, contact details, or password.</p>
        </div>

        <div class="faq-item">
            <h3>7. How do I view my previous feedback or lost item reports?</h3>
            <p>Click on "My Reports" to view a list of all your submitted feedback and lost item requests.</p>
        </div>

        <div class="faq-item">
            <h3>8. Who can I contact for further help?</h3>
            <p>You can contact our support team via the Help section or email us at support@railwayfeedback.my</p>
        </div>

        <div class="back-btn">
            <a href="user_dashboard.php"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        </div>
    </div>
</main>

<footer>
    <p>&copy; 2025 Railway Feedback & Lost and Found System. All rights reserved.</p>
</footer>

</body>
</html>
