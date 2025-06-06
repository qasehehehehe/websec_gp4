<?php
session_start();

// Redirect to login if user is not authenticated
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: login.php");
    exit();
}

// Optional: fetch user data from database if needed
// Example: $user_id = $_SESSION['user_id'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Dashboard</title>
    <link rel="stylesheet" href="/WebDev/Project/css/common.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f6f9;
        }

        .dashboard-container {
            max-width: 900px;
            margin: 40px auto;
            background-color: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .dashboard-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .dashboard-header h2 {
            color: #003366;
        }

        .dashboard-actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-around;
            gap: 20px;
        }

        .action-card {
            flex: 1 1 40%;
            background-color: #f0f0f0;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            transition: 0.3s;
            cursor: pointer;
        }

        .action-card:hover {
            background-color: #e0ecff;
            box-shadow: 0 0 8px rgba(0, 51, 102, 0.3);
        }

        .action-card i {
            font-size: 36px;
            margin-bottom: 10px;
            color: #003366;
        }

        .logout-btn {
            margin-top: 30px;
            text-align: center;
        }

        .logout-btn a {
            background-color: #dc3545;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 8px;
            transition: 0.3s;
        }

        .logout-btn a:hover {
            background-color: #c82333;
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

    <header>
        <div class="logo">
            <img src="img\logotest.jpg" alt="Logo"style="max-width: 80px; height: auto;">
            <h1>Railway Feedback & Lost and Found System</h1>
        </div>
    </header>

    <main>
        <div class="dashboard-container">
            <div class="dashboard-header">
                <h2>Welcome, User</h2>
                <p>What would you like to do today?</p>
            </div>
            <div class="dashboard-actions">
                <div class="action-card" onclick="location.href='submit_feedback.php'">
                    <i class="fas fa-comments"></i>
                    <h3>Submit Feedback</h3>
                    <p>Let us know about your recent train experience.</p>
                </div>
                <div class="action-card" onclick="location.href='report_lost_item.php'">
                    <i class="fas fa-box-open"></i>
                    <h3>Report Lost Item</h3>
                    <p>Lost something on the train? File a report here.</p>
                </div>
                <div class="action-card" onclick="location.href='view_my_reports.php'">
                    <i class="fas fa-history"></i>
                    <h3>My Reports</h3>
                    <p>Track your feedback and lost item reports.</p>
                </div>
                <div class="action-card" onclick="location.href='update_profile.php'">
                    <i class="fas fa-user-edit"></i>
                    <h3>Update Profile</h3>
                    <p>Manage your account details.</p>
                </div>
            </div>

            <div class="logout-btn">
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </main>

    <footer>
        <p>&copy; 2025 Railway Feedback & Lost and Found System. All rights reserved.</p>
    </footer>

</body>
</html>
