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

// Handle claim actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $claim_id = (int)$_POST['claim_id'];
    $action = $_POST['action'];
    $admin_notes = trim($_POST['admin_notes'] ?? '');
    
    $sql = "UPDATE claims SET action = ?, admin_notes = ?, processed_by = ?, processed_date = NOW() WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssii', $action, $admin_notes, $_SESSION['user_id'], $claim_id);
    
    if ($stmt->execute()) {
        $message = "Claim has been $action successfully!";
        $messageType = "success";
    } else {
        $message = "Error updating claim: " . $stmt->error;
        $messageType = "error";
    }
    $stmt->close();
}

// Get all claims
$claims_query = "
    SELECT c.*, fi.item_name, u.username, u.first_name, u.last_name 
    FROM claims c 
    JOIN found_items fi ON c.item_id = fi.id 
    JOIN users u ON c.user_id = u.id 
    ORDER BY c.submission_date DESC
";
$claims_result = $conn->query($claims_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="railway-styles.css" rel="stylesheet">
    <title>Manage Claims - Railway System</title>
    <style>
        .claim-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            padding: 1.5rem;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 40px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
        }
        
        .claim-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.15);
        }
        
        .claim-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        
        .claim-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #333;
            margin: 0;
        }
        
        .claim-info {
            margin-bottom: 1rem;
            line-height: 1.6;
        }
        
        .claim-info p {
            margin-bottom: 0.5rem;
            color: #666;
        }
        
        .claim-actions {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #eee;
        }
        
        .claim-actions textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-family: inherit;
            resize: vertical;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .admin-notes {
            background: rgba(102, 126, 234, 0.1);
            padding: 1rem;
            border-radius: 8px;
            margin-top: 1rem;
            border-left: 4px solid #667eea;
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
                    <p>Claims Management</p>
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
            <i class="fas fa-gavel"></i>
            Manage Claims
        </h2>
        
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if ($claims_result && $claims_result->num_rows > 0): ?>
            <?php while ($claim = $claims_result->fetch_assoc()): ?>
                <div class="claim-card">
                    <div class="claim-header">
                        <h3 class="claim-title"><?php echo htmlspecialchars($claim['item_name']); ?></h3>
                        <span class="activity-status status-<?php echo $claim['action']; ?>">
                            <?php echo ucfirst($claim['action']); ?>
                        </span>
                    </div>
                    
                    <div class="claim-info">
                        <p><strong>Claimant:</strong> <?php echo htmlspecialchars($claim['name']); ?> (<?php echo htmlspecialchars($claim['username']); ?>)</p>
                        <p><strong>Contact:</strong> <?php echo htmlspecialchars($claim['phone']); ?> | <?php echo htmlspecialchars($claim['email']); ?></p>
                        <p><strong>Description:</strong> <?php echo htmlspecialchars($claim['description']); ?></p>
                        <p><strong>Submitted:</strong> <?php echo date('M j, Y g:i A', strtotime($claim['submission_date'])); ?></p>
                    </div>
                    
                    <?php if ($claim['admin_notes']): ?>
                        <div class="admin-notes">
                            <strong><i class="fas fa-sticky-note"></i> Admin Notes:</strong><br>
                            <?php echo nl2br(htmlspecialchars($claim['admin_notes'])); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($claim['action'] === 'pending'): ?>
                        <form method="POST" class="claim-actions">
                            <input type="hidden" name="claim_id" value="<?php echo $claim['id']; ?>">
                            <div class="form-group">
                                <label class="form-label">Admin Notes:</label>
                                <textarea name="admin_notes" rows="2" class="form-control" 
                                          placeholder="Add your notes about this claim..."></textarea>
                            </div>
                            <div class="action-buttons">
                                <button type="submit" name="action" value="approved" class="btn btn-success btn-sm">
                                    <i class="fas fa-check"></i> Approve
                                </button>
                                <button type="submit" name="action" value="rejected" class="btn btn-danger btn-sm">
                                    <i class="fas fa-times"></i> Reject
                                </button>
                                <button type="submit" name="action" value="under_review" class="btn btn-warning btn-sm">
                                    <i class="fas fa-eye"></i> Under Review
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="card text-center p-5">
                <i class="fas fa-clipboard-list" style="font-size: 3rem; color: #ddd; margin-bottom: 1rem;"></i>
                <h3>No Claims Found</h3>
                <p class="text-muted">No claims have been submitted yet.</p>
            </div>
        <?php endif; ?>
    </main>

    <footer class="mt-5 text-center p-3" style="background: rgba(255,255,255,0.1); color: #666;">
        <p>&copy; 2025 Railway Feedback & Lost and Found. All rights reserved.</p>
    </footer>
</body>
</html>

<?php $conn->close(); ?>