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

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $item_id = (int)$_POST['item_id'];
    $new_status = $_POST['new_status'];
    
    $sql = "UPDATE lost_items SET status = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('si', $new_status, $item_id);
    
    if ($stmt->execute()) {
        $message = "Lost item status updated successfully!";
        $messageType = "success";
    } else {
        $message = "Error updating status.";
        $messageType = "error";
    }
    $stmt->close();
}

// Get all lost items with user details
$lost_items_query = "
    SELECT li.*, u.username, u.first_name, u.last_name 
    FROM lost_items li 
    JOIN users u ON li.user_id = u.id 
    ORDER BY li.created_at DESC
";
$lost_items_result = $conn->query($lost_items_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="railway-styles.css" rel="stylesheet">
    <title>Manage Lost Items - Railway System</title>
    <style>
        .lost-item-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            padding: 1.5rem;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 40px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
            border-left: 4px solid #dc3545;
            transition: all 0.3s ease;
        }
        
        .lost-item-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.15);
        }
        
        .item-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        
        .item-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #333;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .item-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
        }
        
        .detail-label {
            font-weight: 600;
            color: #666;
            font-size: 0.75rem;
            margin-bottom: 0.25rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .detail-value {
            color: #333;
            font-size: 0.9rem;
        }
        
        .description-box {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            margin-top: 0.5rem;
            border-left: 3px solid #dc3545;
        }
        
        .notes-box {
            background: #fff3cd;
            padding: 1rem;
            border-radius: 8px;
            margin-top: 0.5rem;
            border-left: 3px solid #ffc107;
        }
        
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-box {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            padding: 1.5rem;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 40px rgba(0, 0, 0, 0.1);
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .stat-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.15);
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #dc3545;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 500;
        }
        
        .status-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #eee;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
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
                    <p>Lost Items Management</p>
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
            <i class="fas fa-search"></i>
            Lost Items Reports
        </h2>
        
        <!-- Statistics -->
        <div class="stats-row">
            <?php
            $active_count = $conn->query("SELECT COUNT(*) as count FROM lost_items WHERE status = 'active'")->fetch_assoc()['count'];
            $found_count = $conn->query("SELECT COUNT(*) as count FROM lost_items WHERE status = 'found'")->fetch_assoc()['count'];
            $closed_count = $conn->query("SELECT COUNT(*) as count FROM lost_items WHERE status = 'closed'")->fetch_assoc()['count'];
            $total_count = $conn->query("SELECT COUNT(*) as count FROM lost_items")->fetch_assoc()['count'];
            ?>
            <div class="stat-box">
                <div class="stat-number"><?php echo $total_count; ?></div>
                <div class="stat-label">Total Reports</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?php echo $active_count; ?></div>
                <div class="stat-label">Active Cases</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?php echo $found_count; ?></div>
                <div class="stat-label">Found Items</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?php echo $closed_count; ?></div>
                <div class="stat-label">Closed Cases</div>
            </div>
        </div>
        
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?>">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if ($lost_items_result && $lost_items_result->num_rows > 0): ?>
            <?php while ($item = $lost_items_result->fetch_assoc()): ?>
                <div class="lost-item-card">
                    <div class="item-header">
                        <h3 class="item-title">
                            <i class="fas fa-box-open"></i>
                            <?php echo htmlspecialchars($item['item_name']); ?>
                        </h3>
                        <span class="activity-status status-<?php echo $item['status']; ?>">
                            <?php echo ucfirst($item['status']); ?>
                        </span>
                    </div>

                    <div class="item-details">
                        <div class="detail-item">
                            <span class="detail-label">Reported By</span>
                            <span class="detail-value">
                                <i class="fas fa-user"></i> 
                                <?php echo htmlspecialchars($item['first_name'] . ' ' . $item['last_name']); ?>
                                <br><small>@<?php echo htmlspecialchars($item['username']); ?></small>
                            </span>
                        </div>
                        
                        <div class="detail-item">
                            <span class="detail-label">Category</span>
                            <span class="detail-value">
                                <i class="fas fa-tag"></i> 
                                <?php echo ucfirst($item['category']); ?>
                            </span>
                        </div>
                        
                        <div class="detail-item">
                            <span class="detail-label">Lost Location</span>
                            <span class="detail-value">
                                <i class="fas fa-map-marker-alt"></i> 
                                <?php echo htmlspecialchars($item['lost_location']); ?>
                            </span>
                        </div>
                        
                        <div class="detail-item">
                            <span class="detail-label">Lost Date</span>
                            <span class="detail-value">
                                <i class="fas fa-calendar"></i> 
                                <?php echo date('M j, Y', strtotime($item['lost_date'])); ?>
                            </span>
                        </div>
                        
                        <?php if (!empty($item['train_number'])): ?>
                        <div class="detail-item">
                            <span class="detail-label">Train Number</span>
                            <span class="detail-value">
                                <i class="fas fa-train"></i> 
                                <?php echo htmlspecialchars($item['train_number']); ?>
                            </span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="detail-item">
                            <span class="detail-label">Contact Info</span>
                            <span class="detail-value">
                                <i class="fas fa-phone"></i> <?php echo htmlspecialchars($item['contact_phone']); ?><br>
                                <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($item['contact_email']); ?>
                            </span>
                        </div>
                        
                        <?php if ($item['reward_offered'] > 0): ?>
                        <div class="detail-item">
                            <span class="detail-label">Reward Offered</span>
                            <span class="detail-value">
                                <i class="fas fa-money-bill"></i> 
                                RM <?php echo number_format($item['reward_offered'], 2); ?>
                            </span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="detail-item">
                            <span class="detail-label">Reference Number</span>
                            <span class="detail-value">
                                <i class="fas fa-hashtag"></i> 
                                <?php echo htmlspecialchars($item['reference_number'] ?? 'Not assigned'); ?>
                            </span>
                        </div>
                    </div>

                    <div style="margin-bottom: 1rem;">
                        <span class="detail-label">Item Description</span>
                        <div class="description-box">
                            <?php echo nl2br(htmlspecialchars($item['description'])); ?>
                        </div>
                    </div>

                    <?php if (!empty($item['additional_notes'])): ?>
                    <div style="margin-bottom: 1rem;">
                        <span class="detail-label">Additional Notes</span>
                        <div class="notes-box">
                            <?php echo nl2br(htmlspecialchars($item['additional_notes'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="status-actions">
                        <small style="color: #666;">
                            <i class="fas fa-clock"></i> 
                            Reported: <?php echo date('M j, Y g:i A', strtotime($item['created_at'])); ?>
                        </small>
                        
                        <?php if ($item['status'] === 'active'): ?>
                        <div class="action-buttons">
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                <input type="hidden" name="action" value="update_status">
                                <button type="submit" name="new_status" value="found" class="btn btn-success btn-sm" 
                                        onclick="return confirm('Mark this item as found?')">
                                    <i class="fas fa-check"></i> Mark as Found
                                </button>
                                <button type="submit" name="new_status" value="closed" class="btn btn-secondary btn-sm"
                                        onclick="return confirm('Close this case?')">
                                    <i class="fas fa-times"></i> Close Case
                                </button>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="card text-center p-5">
                <i class="fas fa-search" style="font-size: 3rem; color: #ddd; margin-bottom: 1rem;"></i>
                <h3>No Lost Items Reported</h3>
                <p class="text-muted">No users have reported lost items yet.</p>
            </div>
        <?php endif; ?>
    </main>

    <footer class="mt-5 text-center p-3" style="background: rgba(255,255,255,0.1); color: #666;">
        <p>&copy; 2025 Railway Feedback & Lost and Found. All rights reserved.</p>
    </footer>
</body>
</html>

<?php $conn->close(); ?>