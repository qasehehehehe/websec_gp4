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

// Handle feedback actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $feedback_id = (int)$_POST['feedback_id'];
    $action = $_POST['action'];
    
    if ($action === 'update_status') {
        $new_status = $_POST['new_status'];
        $admin_response = trim($_POST['admin_response'] ?? '');
        
        $sql = "UPDATE feedback SET status = ?, admin_response = ?, responded_by = ?, response_date = NOW() WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ssii', $new_status, $admin_response, $_SESSION['user_id'], $feedback_id);
        
        if ($stmt->execute()) {
            $message = "Feedback status updated successfully!";
            $messageType = "success";
        } else {
            $message = "Error updating feedback.";
            $messageType = "error";
        }
        $stmt->close();
    }
}

// Get all feedback
$feedback_query = "
    SELECT f.*, u.username, u.first_name, u.last_name 
    FROM feedback f 
    JOIN users u ON f.user_id = u.id 
    ORDER BY f.created_at DESC
";
$feedback_result = $conn->query($feedback_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="railway-styles.css" rel="stylesheet">
    <title>Feedback Management - Railway System</title>
    <style>
        .feedback-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            padding: 1.5rem;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 40px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
        }
        
        .feedback-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.15);
        }
        
        .feedback-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        
        .feedback-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #333;
            margin: 0;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .status-pending {
            background: rgba(255, 193, 7, 0.1);
            color: #856404;
        }
        
        .status-resolved {
            background: rgba(40, 167, 69, 0.1);
            color: #155724;
        }
        
        .status-in_progress {
            background: rgba(23, 162, 184, 0.1);
            color: #0c5460;
        }
        
        .feedback-info {
            margin-bottom: 1rem;
            line-height: 1.6;
        }
        
        .feedback-info p {
            margin-bottom: 0.5rem;
            color: #666;
        }
        
        .feedback-message {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
            border-left: 4px solid #667eea;
        }
        
        .admin-response {
            background: rgba(102, 126, 234, 0.1);
            padding: 1rem;
            border-radius: 8px;
            margin-top: 1rem;
            border-left: 4px solid #667eea;
        }
        
        .response-form {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #eee;
        }
        
        .response-form textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-family: inherit;
            resize: vertical;
        }
        
        .response-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .rating-display {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            margin: 0.5rem 0;
        }
        
        .star {
            color: #ffc107;
        }
        
        .star.empty {
            color: #e9ecef;
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
                    <p>Feedback Management</p>
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
            <i class="fas fa-comments"></i>
            Feedback Management
        </h2>
        
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if ($feedback_result && $feedback_result->num_rows > 0): ?>
            <?php while ($feedback = $feedback_result->fetch_assoc()): ?>
                <div class="feedback-card">
                    <div class="feedback-header">
                        <h3 class="feedback-title"><?php echo htmlspecialchars($feedback['subject']); ?></h3>
                        <span class="status-badge status-<?php echo $feedback['status']; ?>">
                            <?php echo ucfirst($feedback['status']); ?>
                        </span>
                    </div>
                    
                    <div class="feedback-info">
                        <p><strong>From:</strong> <?php echo htmlspecialchars($feedback['first_name'] . ' ' . $feedback['last_name']); ?> 
                           (@<?php echo htmlspecialchars($feedback['username']); ?>)</p>
                        <p><strong>Type:</strong> <?php echo ucfirst($feedback['feedback_type']); ?> | 
                           <strong>Category:</strong> <?php echo ucfirst(str_replace('_', ' ', $feedback['category'])); ?></p>
                        
                        <?php if ($feedback['train_number']): ?>
                            <p><strong>Train:</strong> <?php echo htmlspecialchars($feedback['train_number']); ?> | 
                               <strong>Route:</strong> <?php echo htmlspecialchars($feedback['route']); ?></p>
                        <?php endif; ?>
                        
                        <?php if ($feedback['travel_date']): ?>
                            <p><strong>Travel Date:</strong> <?php echo date('M j, Y', strtotime($feedback['travel_date'])); ?></p>
                        <?php endif; ?>
                        
                        <?php if ($feedback['rating']): ?>
                            <div>
                                <strong>Rating:</strong>
                                <div class="rating-display">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star <?php echo $i <= $feedback['rating'] ? 'star' : 'star empty'; ?>"></i>
                                    <?php endfor; ?>
                                    <span>(<?php echo $feedback['rating']; ?>/5)</span>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <p><strong>Submitted:</strong> <?php echo date('M j, Y g:i A', strtotime($feedback['created_at'])); ?></p>
                    </div>
                    
                    <div>
                        <strong>Message:</strong>
                        <div class="feedback-message">
                            <?php echo nl2br(htmlspecialchars($feedback['message'])); ?>
                        </div>
                    </div>
                    
                    <?php if ($feedback['admin_response']): ?>
                        <div class="admin-response">
                            <strong><i class="fas fa-reply"></i> Admin Response:</strong><br>
                            <?php echo nl2br(htmlspecialchars($feedback['admin_response'])); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($feedback['status'] === 'pending'): ?>
                        <form method="POST" class="response-form">
                            <input type="hidden" name="feedback_id" value="<?php echo $feedback['id']; ?>">
                            <input type="hidden" name="action" value="update_status">
                            
                            <div class="form-group">
                                <label class="form-label">Admin Response:</label>
                                <textarea name="admin_response" rows="3" class="form-control" 
                                          placeholder="Enter your response..."></textarea>
                            </div>
                            
                            <div class="response-buttons">
                                <button type="submit" name="new_status" value="in_progress" class="btn btn-warning btn-sm">
                                    <i class="fas fa-clock"></i> In Progress
                                </button>
                                <button type="submit" name="new_status" value="resolved" class="btn btn-success btn-sm">
                                    <i class="fas fa-check"></i> Resolved
                                </button>
                                <button type="submit" name="new_status" value="closed" class="btn btn-secondary btn-sm">
                                    <i class="fas fa-times"></i> Close
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="card text-center p-5">
                <i class="fas fa-comments" style="font-size: 3rem; color: #ddd; margin-bottom: 1rem;"></i>
                <h3>No Feedback Found</h3>
                <p class="text-muted">No feedback has been submitted yet.</p>
            </div>
        <?php endif; ?>
    </main>

    <footer class="mt-5 text-center p-3" style="background: rgba(255,255,255,0.1); color: #666;">
        <p>&copy; 2025 Railway Feedback & Lost and Found. All rights reserved.</p>
    </footer>
</body>
</html>

<?php $conn->close(); ?>