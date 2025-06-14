<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'db_connection.php';
$conn = getDatabaseConnection();

// Get user's feedback
$feedback_query = "SELECT * FROM feedback WHERE user_id = ? ORDER BY created_at DESC";
$feedback_stmt = $conn->prepare($feedback_query);
$feedback_stmt->bind_param('i', $_SESSION['user_id']);
$feedback_stmt->execute();
$feedback_result = $feedback_stmt->get_result();

// Get user's lost item reports
$lost_items_query = "SELECT * FROM lost_items WHERE user_id = ? ORDER BY created_at DESC";
$lost_items_stmt = $conn->prepare($lost_items_query);
$lost_items_stmt->bind_param('i', $_SESSION['user_id']);
$lost_items_stmt->execute();
$lost_items_result = $lost_items_stmt->get_result();

// Get user's claims
$claims_query = "SELECT c.*, fi.item_name, fi.description as item_description FROM claims c JOIN found_items fi ON c.item_id = fi.id WHERE c.user_id = ? ORDER BY c.submission_date DESC";
$claims_stmt = $conn->prepare($claims_query);
$claims_stmt->bind_param('i', $_SESSION['user_id']);
$claims_stmt->execute();
$claims_result = $claims_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="railway-styles.css" rel="stylesheet">
    <title>My Reports - Railway System</title>
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
        
        .report-card {
            background: rgba(248, 249, 250, 0.8);
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            border-left: 4px solid #667eea;
            transition: all 0.3s ease;
        }
        
        .report-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }
        
        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        
        .report-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
            margin: 0;
        }
        
        .report-info {
            margin-bottom: 1rem;
            line-height: 1.6;
            color: #666;
        }
        
        .report-info p {
            margin-bottom: 0.5rem;
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
        
        .admin-response, .admin-notes {
            background: rgba(102, 126, 234, 0.1);
            padding: 1rem;
            border-radius: 8px;
            margin-top: 1rem;
            border-left: 3px solid #667eea;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: #666;
        }
        
        .empty-state i {
            font-size: 3rem;
            color: #ddd;
            margin-bottom: 1rem;
        }
        
        .empty-state h3 {
            margin-bottom: 1rem;
            color: #333;
        }
        
        @media (max-width: 768px) {
            .tabs {
                flex-direction: column;
            }
            
            .tab {
                padding: 0.75rem 1rem;
            }
            
            .report-header {
                flex-direction: column;
                gap: 0.5rem;
                align-items: flex-start;
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
                    <p>My Reports</p>
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

        <h2 class="section-title">
            <i class="fas fa-history"></i>
            My Reports
        </h2>

        <div class="tabs">
            <button class="tab active" onclick="showTab('feedback')">
                <i class="fas fa-comments"></i> Feedback (<?php echo $feedback_result->num_rows; ?>)
            </button>
            <button class="tab" onclick="showTab('lost-items')">
                <i class="fas fa-box-open"></i> Lost Items (<?php echo $lost_items_result->num_rows; ?>)
            </button>
            <button class="tab" onclick="showTab('claims')">
                <i class="fas fa-hand-holding"></i> Claims (<?php echo $claims_result->num_rows; ?>)
            </button>
        </div>

        <!-- Feedback Tab -->
        <div id="feedback" class="tab-content active">
            <?php if ($feedback_result->num_rows > 0): ?>
                <?php while ($feedback = $feedback_result->fetch_assoc()): ?>
                    <div class="report-card">
                        <div class="report-header">
                            <h4 class="report-title"><?php echo htmlspecialchars($feedback['subject']); ?></h4>
                            <span class="activity-status status-<?php echo $feedback['status']; ?>">
                                <?php echo ucfirst($feedback['status']); ?>
                            </span>
                        </div>
                        
                        <div class="report-info">
                            <p><strong>Type:</strong> <?php echo ucfirst($feedback['feedback_type']); ?> | 
                               <strong>Category:</strong> <?php echo ucfirst(str_replace('_', ' ', $feedback['category'])); ?></p>
                            
                            <?php if (!empty($feedback['train_number'])): ?>
                                <p><strong>Train:</strong> <?php echo htmlspecialchars($feedback['train_number']); ?>
                                <?php if (!empty($feedback['route'])): ?>
                                    | <strong>Route:</strong> <?php echo htmlspecialchars($feedback['route']); ?>
                                <?php endif; ?>
                                </p>
                            <?php endif; ?>
                            
                            <?php if (!empty($feedback['rating'])): ?>
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
                            
                            <p><?php echo nl2br(htmlspecialchars($feedback['message'])); ?></p>
                            
                            <?php if (!empty($feedback['admin_response'])): ?>
                                <div class="admin-response">
                                    <strong><i class="fas fa-reply"></i> Admin Response:</strong><br>
                                    <?php echo nl2br(htmlspecialchars($feedback['admin_response'])); ?>
                                </div>
                            <?php endif; ?>
                            
                            <small style="color: #666;">Submitted: <?php echo date('M j, Y g:i A', strtotime($feedback['created_at'])); ?></small>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-comments"></i>
                    <h3>No Feedback Submitted</h3>
                    <p>You haven't submitted any feedback yet.</p>
                    <a href="submit_feedback.php" class="btn btn-primary">Submit Feedback</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Lost Items Tab -->
        <div id="lost-items" class="tab-content">
            <?php if ($lost_items_result->num_rows > 0): ?>
                <?php while ($item = $lost_items_result->fetch_assoc()): ?>
                    <div class="report-card">
                        <div class="report-header">
                            <h4 class="report-title"><?php echo htmlspecialchars($item['item_name']); ?></h4>
                            <span class="activity-status status-<?php echo $item['status']; ?>">
                                <?php echo ucfirst($item['status']); ?>
                            </span>
                        </div>
                        
                        <div class="report-info">
                            <?php if (!empty($item['reference_number'])): ?>
                                <p><strong>Reference:</strong> <?php echo htmlspecialchars($item['reference_number']); ?></p>
                            <?php endif; ?>
                            
                            <p><strong>Category:</strong> <?php echo ucfirst($item['category']); ?></p>
                            <p><strong>Lost Location:</strong> <?php echo htmlspecialchars($item['lost_location']); ?></p>
                            <p><strong>Lost Date:</strong> <?php echo date('M j, Y', strtotime($item['lost_date'])); ?></p>
                            
                            <?php if (!empty($item['train_number'])): ?>
                                <p><strong>Train Number:</strong> <?php echo htmlspecialchars($item['train_number']); ?></p>
                            <?php endif; ?>
                            
                            <p><?php echo nl2br(htmlspecialchars($item['description'])); ?></p>
                            
                            <?php if ($item['reward_offered'] > 0): ?>
                                <p><strong>Reward Offered:</strong> RM <?php echo number_format($item['reward_offered'], 2); ?></p>
                            <?php endif; ?>
                            
                            <small style="color: #666;">Reported: <?php echo date('M j, Y g:i A', strtotime($item['created_at'])); ?></small>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-box-open"></i>
                    <h3>No Lost Items Reported</h3>
                    <p>You haven't reported any lost items yet.</p>
                    <a href="report_lost_item.php" class="btn btn-primary">Report Lost Item</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Claims Tab -->
        <div id="claims" class="tab-content">
            <?php if ($claims_result->num_rows > 0): ?>
                <?php while ($claim = $claims_result->fetch_assoc()): ?>
                    <div class="report-card">
                        <div class="report-header">
                            <h4 class="report-title"><?php echo htmlspecialchars($claim['item_name']); ?></h4>
                            <span class="activity-status status-<?php echo $claim['action']; ?>">
                                <?php echo ucfirst($claim['action']); ?>
                            </span>
                        </div>
                        
                        <div class="report-info">
                            <?php if (!empty($claim['claim_reference'])): ?>
                                <p><strong>Reference:</strong> <?php echo htmlspecialchars($claim['claim_reference']); ?></p>
                            <?php endif; ?>
                            
                            <p><strong>Item Description:</strong> <?php echo htmlspecialchars($claim['item_description']); ?></p>
                            <p><strong>Your Description:</strong> <?php echo nl2br(htmlspecialchars($claim['description'])); ?></p>
                            <p><strong>Contact:</strong> <?php echo htmlspecialchars($claim['phone']); ?> | <?php echo htmlspecialchars($claim['email']); ?></p>
                            
                            <?php if (!empty($claim['admin_notes'])): ?>
                                <div class="admin-notes">
                                    <strong><i class="fas fa-sticky-note"></i> Admin Notes:</strong><br>
                                    <?php echo nl2br(htmlspecialchars($claim['admin_notes'])); ?>
                                </div>
                            <?php endif; ?>
                            
                            <small style="color: #666;">Submitted: <?php echo date('M j, Y g:i A', strtotime($claim['submission_date'])); ?></small>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-hand-holding"></i>
                    <h3>No Claims Submitted</h3>
                    <p>You haven't claimed any items yet.</p>
                    <a href="view_found_items.php" class="btn btn-primary">View Found Items</a>
                </div>
            <?php endif; ?>
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
    </script>
</body>
</html>

<?php 
$feedback_stmt->close();
$lost_items_stmt->close();
$claims_stmt->close();
$conn->close(); 
?>