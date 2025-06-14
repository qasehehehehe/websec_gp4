<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

include 'db_connection.php';
$conn = getDatabaseConnection();

$message = '';
$messageType = '';

// Debug information (remove this after testing)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("POST data received: " . print_r($_POST, true));
}

// Handle suspend/activate action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
    $user_id = (int)$_POST['user_id'];
    
    // Make sure user_id is valid and not the current admin
    if ($user_id > 0 && $user_id != $_SESSION['user_id']) {
        // First check if user exists
        $checkSql = "SELECT id, is_active, username FROM users WHERE id = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param('i', $user_id);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            $currentUser = $checkResult->fetch_assoc();
            
            // Toggle the status
            $sql = "UPDATE users SET is_active = NOT is_active WHERE id = ?";
            $stmt = $conn->prepare($sql);
            
            if ($stmt && $stmt->bind_param('i', $user_id) && $stmt->execute()) {
                $action_word = $currentUser['is_active'] ? 'suspended' : 'activated';
                $message = "User '{$currentUser['username']}' has been {$action_word} successfully!";
                $messageType = "success";
                $stmt->close();
            } else {
                $message = "Error updating user status: " . $conn->error;
                $messageType = "error";
            }
        } else {
            $message = "User not found!";
            $messageType = "error";
        }
        $checkStmt->close();
    } else {
        $message = "Invalid user ID or cannot modify your own account!";
        $messageType = "error";
    }
}

// Get all users
$users_query = "SELECT * FROM users ORDER BY created_at DESC";
$users_result = $conn->query($users_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="railway-styles.css" rel="stylesheet">
    <title>Manage Users - Railway System</title>
    <style>
        .users-table-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-top: 1.5rem;
        }
        
        .users-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .users-table th {
            background: rgba(102, 126, 234, 0.1);
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
            font-weight: 600;
            color: #333;
        }
        
        .users-table td {
            padding: 1rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            vertical-align: top;
        }
        
        .users-table tbody tr:hover {
            background: rgba(102, 126, 234, 0.05);
        }
        
        .user-info {
            display: flex;
            flex-direction: column;
        }
        
        .user-name {
            font-weight: 500;
            margin-bottom: 0.25rem;
            color: #333;
        }
        
        .username {
            color: #666;
            font-size: 0.85rem;
        }
        
        .role-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .role-admin {
            background: rgba(220, 53, 69, 0.1);
            color: #721c24;
        }
        
        .role-user {
            background: rgba(23, 162, 184, 0.1);
            color: #0c5460;
        }
        
        .current-user-label {
            color: #666;
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        @media (max-width: 768px) {
            .users-table-container {
                overflow-x: auto;
            }
            
            .users-table {
                min-width: 800px;
            }
            
            .users-table th,
            .users-table td {
                padding: 0.75rem 0.5rem;
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
                    <h1>Railway Admin</h1>
                    <p>User Management</p>
                </div>
            </div>
            <div class="header-actions">
                <nav class="nav-menu">
                    <a href="admin_dashboard.php" class="nav-link">
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
        <a href="admin_dashboard.php" class="btn btn-secondary mb-3">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>

        <h2 class="section-title">
            <i class="fas fa-users"></i>
            Manage Users
        </h2>
        
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if ($users_result && $users_result->num_rows > 0): ?>
            <div class="users-table-container">
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($user = $users_result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div class="user-info">
                                        <div class="user-name">
                                            <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                        </div>
                                        <div class="username">
                                            @<?php echo htmlspecialchars($user['username']); ?>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <span class="role-badge role-<?php echo $user['role']; ?>">
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="activity-status status-<?php echo $user['is_active'] ? 'approved' : 'rejected'; ?>">
                                        <?php echo $user['is_active'] ? 'Active' : 'Suspended'; ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <form method="POST" style="display: inline;" onsubmit="return confirmAction(this)">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <button type="submit" 
                                                    class="btn <?php echo $user['is_active'] ? 'btn-danger' : 'btn-success'; ?> btn-sm"
                                                    data-action="<?php echo $user['is_active'] ? 'suspend' : 'activate'; ?>"
                                                    data-username="<?php echo htmlspecialchars($user['username']); ?>">
                                                <i class="fas <?php echo $user['is_active'] ? 'fa-ban' : 'fa-check'; ?>"></i>
                                                <?php echo $user['is_active'] ? 'Suspend' : 'Activate'; ?>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="current-user-label">
                                            <i class="fas fa-user"></i> Current User
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="card text-center p-5">
                <i class="fas fa-users" style="font-size: 3rem; color: #ddd; margin-bottom: 1rem;"></i>
                <h3>No Users Found</h3>
                <p class="text-muted">No users are registered in the system yet.</p>
            </div>
        <?php endif; ?>
    </main>

    <footer class="mt-5 text-center p-3" style="background: rgba(255,255,255,0.1); color: #666;">
        <p>&copy; 2025 Railway Feedback & Lost and Found. All rights reserved.</p>
    </footer>

    <script>
        // Confirmation function
        function confirmAction(form) {
            const button = form.querySelector('button[type="submit"]');
            const action = button.getAttribute('data-action');
            const username = button.getAttribute('data-username');
            
            const confirmed = confirm(`Are you sure you want to ${action} user "${username}"?`);
            
            if (confirmed) {
                // Show loading state
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                button.disabled = true;
                
                // Allow form submission
                return true;
            }
            
            return false;
        }

        // Auto-hide success messages after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const successMessages = document.querySelectorAll('.alert-success');
            successMessages.forEach(message => {
                setTimeout(() => {
                    message.style.opacity = '0';
                    message.style.transition = 'opacity 0.3s';
                    setTimeout(() => {
                        message.style.display = 'none';
                    }, 300);
                }, 5000);
            });

            // Reset button state if there's an error (page reloads with error)
            const errorMessages = document.querySelectorAll('.alert-danger');
            if (errorMessages.length > 0) {
                const buttons = document.querySelectorAll('button[type="submit"]');
                buttons.forEach(button => {
                    button.disabled = false;
                    const icon = button.querySelector('i');
                    if (icon && icon.classList.contains('fa-spinner')) {
                        // Reset button text based on current state
                        const isActive = button.classList.contains('btn-danger');
                        button.innerHTML = isActive ? '<i class="fas fa-ban"></i> Suspend' : '<i class="fas fa-check"></i> Activate';
                    }
                });
            }
        });
    </script>
</body>
</html>

<?php $conn->close(); ?>