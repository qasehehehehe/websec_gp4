<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'db_connection.php';
$conn = getDatabaseConnection();

$message = '';
$messageType = '';

// Initialize user data with default values
$user = array(
    'first_name' => '',
    'last_name' => '',
    'email' => '',
    'contact_number' => '',
    'address' => '',
    'password' => ''
);

// Get current user data
$user_query = "SELECT * FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_query);
if ($user_stmt) {
    $user_stmt->bind_param('i', $_SESSION['user_id']);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    
    if ($user_result->num_rows > 0) {
        $user = $user_result->fetch_assoc();
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $contact_number = trim($_POST['contact_number']);
        $address = trim($_POST['address']);
        
        // Check if email is already used by another user
        $email_check = "SELECT id FROM users WHERE email = ? AND id != ?";
        $email_stmt = $conn->prepare($email_check);
        if ($email_stmt) {
            $email_stmt->bind_param('si', $email, $_SESSION['user_id']);
            $email_stmt->execute();
            $email_result = $email_stmt->get_result();
            
            if ($email_result->num_rows > 0) {
                $message = "Email is already in use by another account.";
                $messageType = "error";
            } else {
                $update_sql = "UPDATE users SET first_name = ?, last_name = ?, email = ?, contact_number = ?, address = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                if ($update_stmt) {
                    $update_stmt->bind_param('sssssi', $first_name, $last_name, $email, $contact_number, $address, $_SESSION['user_id']);
                    
                    if ($update_stmt->execute()) {
                        $message = "Profile updated successfully!";
                        $messageType = "success";
                        
                        // Update session data
                        $_SESSION['first_name'] = $first_name;
                        $_SESSION['last_name'] = $last_name;
                        
                        // Update user array
                        $user['first_name'] = $first_name;
                        $user['last_name'] = $last_name;
                        $user['email'] = $email;
                        $user['contact_number'] = $contact_number;
                        $user['address'] = $address;
                    } else {
                        $message = "Error updating profile.";
                        $messageType = "error";
                    }
                    $update_stmt->close();
                }
            }
            $email_stmt->close();
        }
    }
    
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (!password_verify($current_password, $user['password'])) {
            $message = "Current password is incorrect.";
            $messageType = "error";
        } elseif ($new_password !== $confirm_password) {
            $message = "New passwords do not match.";
            $messageType = "error";
        } elseif (strlen($new_password) < 6) {
            $message = "New password must be at least 6 characters long.";
            $messageType = "error";
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
            $password_sql = "UPDATE users SET password = ? WHERE id = ?";
            $password_stmt = $conn->prepare($password_sql);
            if ($password_stmt) {
                $password_stmt->bind_param('si', $hashed_password, $_SESSION['user_id']);
                
                if ($password_stmt->execute()) {
                    $message = "Password changed successfully!";
                    $messageType = "success";
                } else {
                    $message = "Error changing password.";
                    $messageType = "error";
                }
                $password_stmt->close();
            }
        }
    }
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
    <title>Update Profile - Railway System</title>
    <style>
        .tabs {
            display: flex;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 16px 16px 0 0;
            overflow: hidden;
            box-shadow: 0 8px 40px rgba(0, 0, 0, 0.1);
            margin-top: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .tab {
            flex: 1;
            padding: 1rem 1.5rem;
            background: rgba(248, 249, 250, 0.8);
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
            color: #666;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .tab.active {
            background: rgba(255, 255, 255, 0.95);
            color: #667eea;
            border-bottom: 3px solid #667eea;
        }
        
        .tab:hover {
            background: rgba(233, 236, 239, 0.8);
        }
        
        .tab-content {
            display: none;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            padding: 2rem;
            border-radius: 0 0 16px 16px;
            box-shadow: 0 8px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-top: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        @media (max-width: 768px) {
            .tabs {
                flex-direction: column;
            }
            
            .tab {
                padding: 0.75rem 1rem;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <div class="logo-section">
                <img src="img/logotest.jpg" alt="Railway Logo">
                <div class="logo-text">
                    <h1>Railway User</h1>
                    <p>Update Profile</p>
                </div>
            </div>
            <div class="header-actions">
                <nav class="nav-menu">
                    <a href="user_dashboard.php" class="nav-link">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                    <a href="logout.php" class="nav-link">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </nav>
            </div>
        </div>
    </header>

    <main class="container">
        <a href="user_dashboard.php" class="btn btn-secondary mb-3">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>

        <div class="form-container">
            <h2 class="section-title">
                <i class="fas fa-user-edit"></i>
                Update Profile
            </h2>
            
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="tabs">
                <button class="tab active" onclick="showTab('profile')">
                    <i class="fas fa-user"></i> Profile Information
                </button>
                <button class="tab" onclick="showTab('password')">
                    <i class="fas fa-lock"></i> Change Password
                </button>
            </div>

            <!-- Profile Tab -->
            <div id="profile" class="tab-content active">
                <form method="POST" action="">
                    <input type="hidden" name="update_profile" value="1">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="first_name">First Name *</label>
                            <input type="text" id="first_name" name="first_name" class="form-control" required 
                                   value="<?php echo htmlspecialchars($user['first_name']); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="last_name">Last Name *</label>
                            <input type="text" id="last_name" name="last_name" class="form-control" required 
                                   value="<?php echo htmlspecialchars($user['last_name']); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="email">Email Address *</label>
                        <input type="email" id="email" name="email" class="form-control" required 
                               value="<?php echo htmlspecialchars($user['email']); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="contact_number">Contact Number</label>
                        <input type="tel" id="contact_number" name="contact_number" class="form-control" 
                               value="<?php echo htmlspecialchars($user['contact_number']); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="address">Address</label>
                        <textarea id="address" name="address" rows="3" class="form-control"><?php echo htmlspecialchars($user['address']); ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px;">
                        <i class="fas fa-save"></i> Update Profile
                    </button>
                </form>
            </div>

            <!-- Password Tab -->
            <div id="password" class="tab-content">
                <form method="POST" action="">
                    <input type="hidden" name="change_password" value="1">
                    
                    <div class="form-group">
                        <label class="form-label" for="current_password">Current Password *</label>
                        <input type="password" id="current_password" name="current_password" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="new_password">New Password *</label>
                        <input type="password" id="new_password" name="new_password" class="form-control" required minlength="6">
                        <small class="text-muted">Password must be at least 6 characters long.</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="confirm_password">Confirm New Password *</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required minlength="6">
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px;">
                        <i class="fas fa-lock"></i> Change Password
                    </button>
                </form>
            </div>
        </div>
    </main>

    <footer class="mt-5 text-center p-3" style="background: rgba(255,255,255,0.1); color: #666;">
        <p>&copy; 2025 Railway Feedback & Lost and Found. All rights reserved.</p>
    </footer>

    <script>
        function showTab(tabName) {
            // Hide all tab contents
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(content => content.classList.remove('active'));
            
            // Remove active class from all tabs
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => tab.classList.remove('active'));
            
            // Show selected tab content
            document.getElementById(tabName).classList.add('active');
            
            // Add active class to clicked tab
            event.target.classList.add('active');
        }

        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (newPassword !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>

<?php 
if (isset($user_stmt)) $user_stmt->close();
$conn->close(); 
?>