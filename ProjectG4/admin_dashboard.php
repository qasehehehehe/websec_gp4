<?php
// Start the session to store and display messages
session_start();
// Check if the user is logged in and has the correct role 
if (!isset($_SESSION['user_id'])|| $_SESSION['role'] !== 'admin' ) { 
    header("Location: login.php"); 
    exit(); 
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Database connection
$servername = "localhost";
$username = "root"; 
$password = "";     
$dbname = "railway_pro";

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

/* Fetch statistics
$totalVisitors = $conn->query("SELECT COUNT(*) AS total FROM users")->fetch_assoc()['total'];
$reportedLostItems = $conn->query("SELECT COUNT(*) AS total FROM claims")->fetch_assoc()['total'];
$itemsFound = $conn->query("SELECT COUNT(*) AS total FROM found_items")->fetch_assoc()['total'];
$successfullyReturned = $conn->query("SELECT COUNT(*) AS total FROM found_items WHERE status='claimed'")->fetch_assoc()['total'];
$unclaimedItems = $conn->query("SELECT COUNT(*) AS total FROM found_items WHERE status='unclaimed'")->fetch_assoc()['total'];
*/
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/WebDev/Project/css/common.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <title>Admin Dashboard</title>
    <style>
        /* Advanced Table Styling */
        .container { display: flex; flex-wrap: wrap; justify-content: left; margin: 20px; }
        .card { background: #fff; margin: 10px; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.3); width: 250px; text-align: center; }
        .card h3 { margin: 0 0 10px; font-size: 1.2em; color: #333; }
        .card p { margin: 0; font-size: 2em; color: #007bff; }
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 20px;
            font-size: 14px;
        }

        table th, table td {
            text-align: left;
            padding: 12px 15px;
        }

        table thead {
            background-color: #003366;
            border-bottom: 2px solid #ccc;
        }

        table tbody tr {
            transition: background-color 0.3s ease;
        }

        table tbody tr:hover {
            background-color: #f1f1f1;
        }

        table tbody tr:nth-child(even) {
            background-color: #fafafa;
        }

        table th {
            background-color: #003366;
            color: #fff;
            font-weight: bold;
        }

        table td {
            border-bottom: 1px solid #ddd;
        }

        table tbody tr:last-child td {
            border-bottom: none;
        }
    </style>
</head>
<body>
<header>
    <div class="logo">
        <img src="img\logotest.jpg" alt=" Logo" style="max-width: 80px; height: auto;">
        <h1>Railway Feedback & Lost and Found </h1>
    </div>
    <nav>
        <ul>
        <?php if (isset($_SESSION['role'])) { 
            if ($_SESSION['role'] == 'admin') { 
                // Links for admin 
                echo '<li><a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>'; 

                echo '<li><a href="login.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>'; } 
 } 
            ?>
        </ul>
    </nav>
</header>
<main>
    <h2>Admin Dashboard</h2>

</main>

<footer>
    <p>&copy; 2025 Railway Feedback & Lost and Found. All rights reserved.</p>
</footer>
</body>
</html>
