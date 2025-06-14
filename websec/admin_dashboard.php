<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// ========== HTTPS SECURITY BLOCK (REQUIRED FOR ASSIGNMENT) ==========
function enforceHTTPS() {
    if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
        if ($_SERVER['HTTP_HOST'] !== 'localhost' && $_SERVER['HTTP_HOST'] !== '127.0.0.1') {
            $redirectURL = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            header("Location: $redirectURL", true, 301);
            exit();
        }
    }
}

function setSecurityHeaders() {
    if (!headers_sent()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }
}

enforceHTTPS();
setSecurityHeaders();
// ========== END HTTPS SECURITY BLOCK ==========

include 'db_connection.php';
$conn = getDatabaseConnection();

// Get dashboard statistics
$stats = [];

// Total users
$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'user'");
$stats['total_users'] = $result->fetch_assoc()['count'];

// Pending claims
$result = $conn->query("SELECT COUNT(*) as count FROM claims WHERE action = 'pending'");
$stats['pending_claims'] = $result->fetch_assoc()['count'];

// Unclaimed items
$result = $conn->query("SELECT COUNT(*) as count FROM found_items WHERE status = 'unclaimed'");
$stats['unclaimed_items'] = $result->fetch_assoc()['count'];

// Total feedback
$result = $conn->query("SELECT COUNT(*) as count FROM feedback WHERE status = 'pending'");
$stats['pending_feedback'] = $result->fetch_assoc()['count'];

// Active lost items
$result = $conn->query("SELECT COUNT(*) as count FROM lost_items WHERE status = 'active'");
$stats['active_lost_items'] = $result->fetch_assoc()['count'];

// Recent activities - latest claims
$recent_claims = $conn->query("
    SELECT c.*, fi.item_name, u.username 
    FROM claims c 
    JOIN found_items fi ON c.item_id = fi.id 
    JOIN users u ON c.user_id = u.id 
    ORDER BY c.submission_date DESC 
    LIMIT 5
");

// Recent found items
$recent_items = $conn->query("
    SELECT * FROM found_items 
    ORDER BY created_at DESC 
    LIMIT 5
");

// Recent lost items
$recent_lost = $conn->query("
    SELECT li.*, u.first_name, u.last_name 
    FROM lost_items li 
    JOIN users u ON li.user_id = u.id 
    ORDER BY li.created_at DESC 
    LIMIT 5
");

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <title>Admin Dashboard - Railway System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }

        /* Modern Header */
        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 1rem 2rem;
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo-section img {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .logo-text h1 {
            font-size: 1.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .logo-text p {
            font-size: 0.875rem;
            color: #666;
            font-weight: 500;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 16px;
            background: rgba(102, 126, 234, 0.1);
            border-radius: 50px;
            border: 1px solid rgba(102, 126, 234, 0.2);
        }

        .user-avatar {
            width: 35px;
            height: 35px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }

        .logout-btn {
            background: linear-gradient(135deg, #ff6b6b, #ee5a5a);
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 107, 107, 0.3);
            text-decoration: none;
            color: white;
        }

        /* Main Content */
        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        /* Welcome Section */
        .welcome-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 40px rgba(0, 0, 0, 0.1);
        }

        .welcome-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .welcome-text h2 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .welcome-text p {
            color: #666;
            font-size: 1.1rem;
        }

        .welcome-stats {
            display: flex;
            gap: 2rem;
            align-items: center;
        }

        .quick-stat {
            text-align: center;
        }

        .quick-stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: #333;
        }

        .quick-stat-label {
            font-size: 0.875rem;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Statistics Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            padding: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 40px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: var(--gradient);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.15);
        }

        .stat-card.users::before {
            background: linear-gradient(135deg, #4facfe, #00f2fe);
        }

        .stat-card.claims::before {
            background: linear-gradient(135deg, #43e97b, #38f9d7);
        }

        .stat-card.items::before {
            background: linear-gradient(135deg, #fa709a, #fee140);
        }

        .stat-card.feedback::before {
            background: linear-gradient(135deg, #a8edea, #fed6e3);
        }

        .stat-card.lost::before {
            background: linear-gradient(135deg, #ff9a9e, #fecfef);
        }

        .stat-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .stat-info h3 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: #333;
        }

        .stat-info p {
            color: #666;
            font-weight: 500;
            font-size: 0.95rem;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .stat-icon.users {
            background: linear-gradient(135deg, #4facfe, #00f2fe);
        }

        .stat-icon.claims {
            background: linear-gradient(135deg, #43e97b, #38f9d7);
        }

        .stat-icon.items {
            background: linear-gradient(135deg, #fa709a, #fee140);
        }

        .stat-icon.feedback {
            background: linear-gradient(135deg, #a8edea, #fed6e3);
        }

        .stat-icon.lost {
            background: linear-gradient(135deg, #ff9a9e, #fecfef);
        }

        /* Management Cards */
        .management-section {
            margin-bottom: 2rem;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title::before {
            content: '';
            width: 4px;
            height: 30px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 2px;
        }

        .management-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 1.5rem;
        }

        .management-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            padding: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 40px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .management-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--hover-gradient);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .management-card:hover::before {
            opacity: 0.05;
        }

        .management-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.15);
        }

        .card-header {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
            position: relative;
            z-index: 1;
        }

        .card-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: white;
            margin-right: 1rem;
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 0.25rem;
        }

        .card-description {
            color: #666;
            font-size: 0.875rem;
            line-height: 1.5;
            margin-bottom: 1.5rem;
            position: relative;
            z-index: 1;
        }

        .card-action {
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
            z-index: 1;
        }

        .action-btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .action-btn:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
            text-decoration: none;
            color: white;
        }

        /* Recent Activities */
        .recent-activities {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 1.5rem;
        }

        .activity-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            padding: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 40px rgba(0, 0, 0, 0.1);
        }

        .activity-header {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }

        .activity-header h3 {
            font-size: 1.125rem;
            font-weight: 600;
            color: #333;
            margin-left: 0.5rem;
        }

        .activity-item {
            display: flex;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 35px;
            height: 35px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.75rem;
            font-size: 0.875rem;
        }

        .activity-content {
            flex: 1;
        }

        .activity-title {
            font-weight: 500;
            color: #333;
            margin-bottom: 0.25rem;
        }

        .activity-time {
            font-size: 0.75rem;
            color: #666;
        }

        .activity-status {
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
        }

        .status-pending {
            background: rgba(255, 193, 7, 0.1);
            color: #856404;
        }

        .status-active {
            background: rgba(23, 162, 184, 0.1);
            color: #0c5460;
        }

        .status-unclaimed {
            background: rgba(108, 117, 125, 0.1);
            color: #495057;
        }

        .no-activity {
            text-align: center;
            color: #666;
            font-style: italic;
            padding: 2rem;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 1rem;
            }

            .welcome-content {
                flex-direction: column;
                text-align: center;
                gap: 1.5rem;
            }

            .welcome-stats {
                justify-content: center;
            }

            .main-container {
                padding: 1rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .management-grid {
                grid-template-columns: 1fr;
            }

            .recent-activities {
                grid-template-columns: 1fr;
            }
        }

        /* Color scheme variables */
        .management-card.claims {
            --hover-gradient: linear-gradient(135deg, #667eea, #764ba2);
        }

        .management-card.items {
            --hover-gradient: linear-gradient(135deg, #43e97b, #38f9d7);
        }

        .management-card.users {
            --hover-gradient: linear-gradient(135deg, #4facfe, #00f2fe);
        }

        .management-card.feedback {
            --hover-gradient: linear-gradient(135deg, #fa709a, #fee140);
        }

        .management-card.lost {
            --hover-gradient: linear-gradient(135deg, #ff9a9e, #fecfef);
        }

        /* Card icons */
        .card-icon.claims {
            background: linear-gradient(135deg, #667eea, #764ba2);
        }

        .card-icon.items {
            background: linear-gradient(135deg, #43e97b, #38f9d7);
        }

        .card-icon.users {
            background: linear-gradient(135deg, #4facfe, #00f2fe);
        }

        .card-icon.feedback {
            background: linear-gradient(135deg, #fa709a, #fee140);
        }

        .card-icon.lost {
            background: linear-gradient(135deg, #ff9a9e, #fecfef);
        }

        /* Activity icons */
        .activity-icon.claims {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .activity-icon.items {
            background: linear-gradient(135deg, #43e97b, #38f9d7);
            color: white;
        }

        .activity-icon.lost {
            background: linear-gradient(135deg, #ff9a9e, #fecfef);
            color: white;
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
                    <p>Management Dashboard</p>
                </div>
            </div>
            <div class="header-actions">
                <div class="user-info">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($_SESSION['first_name'] ?? 'A', 0, 1)); ?>
                    </div>
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['first_name'] ?? 'Admin'); ?></span>
                </div>
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </div>
        </div>
    </header>

    <main class="main-container">
        <!-- Welcome Section -->
        <div class="welcome-card">
            <div class="welcome-content">
                <div class="welcome-text">
                    <h2>Good <?php echo date('H') < 12 ? 'Morning' : (date('H') < 18 ? 'Afternoon' : 'Evening'); ?>!</h2>
                    <p>Here's what's happening with your railway system today.</p>
                </div>
                <div class="welcome-stats">
                    <div class="quick-stat">
                        <div class="quick-stat-number"><?php echo $stats['total_users']; ?></div>
                        <div class="quick-stat-label">Users</div>
                    </div>
                    <div class="quick-stat">
                        <div class="quick-stat-number"><?php echo $stats['pending_claims']; ?></div>
                        <div class="quick-stat-label">Pending</div>
                    </div>
                    <div class="quick-stat">
                        <div class="quick-stat-number"><?php echo $stats['unclaimed_items']; ?></div>
                        <div class="quick-stat-label">Unclaimed</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Grid -->
        <div class="stats-grid">
            <div class="stat-card users">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3><?php echo $stats['total_users']; ?></h3>
                        <p>Registered Users</p>
                    </div>
                    <div class="stat-icon users">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card claims">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3><?php echo $stats['pending_claims']; ?></h3>
                        <p>Pending Claims</p>
                    </div>
                    <div class="stat-icon claims">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card items">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3><?php echo $stats['unclaimed_items']; ?></h3>
                        <p>Unclaimed Items</p>
                    </div>
                    <div class="stat-icon items">
                        <i class="fas fa-box-open"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card feedback">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3><?php echo $stats['pending_feedback']; ?></h3>
                        <p>Pending Feedback</p>
                    </div>
                    <div class="stat-icon feedback">
                        <i class="fas fa-comments"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card lost">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3><?php echo $stats['active_lost_items']; ?></h3>
                        <p>Lost Items Reports</p>
                    </div>
                    <div class="stat-icon lost">
                        <i class="fas fa-search"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Management Section -->
        <div class="management-section">
            <h2 class="section-title">
                <i class="fas fa-cogs"></i>
                Quick Management
            </h2>
            <div class="management-grid">
                <div class="management-card claims" onclick="location.href='manage_claims.php'">
                    <div class="card-header">
                        <div class="card-icon claims">
                            <i class="fas fa-gavel"></i>
                        </div>
                        <div>
                            <div class="card-title">Manage Claims</div>
                        </div>
                    </div>
                    <p class="card-description">Review and process user claims for found items. Approve or reject claims with detailed notes and track claim history.</p>
                    <div class="card-action">
                        <span style="color: #666; font-size: 0.875rem;"><?php echo $stats['pending_claims']; ?> pending</span>
                        <span class="action-btn">
                            Manage <i class="fas fa-arrow-right"></i>
                        </span>
                    </div>
                </div>

                <div class="management-card items" onclick="location.href='manage_items.php'">
                    <div class="card-header">
                        <div class="card-icon items">
                            <i class="fas fa-boxes"></i>
                        </div>
                        <div>
                            <div class="card-title">Found Items</div>
                        </div>
                    </div>
                    <p class="card-description">Add new found items, edit existing entries, and manage the complete lost and found inventory system.</p>
                    <div class="card-action">
                        <span style="color: #666; font-size: 0.875rem;"><?php echo $stats['unclaimed_items']; ?> unclaimed</span>
                        <span class="action-btn">
                            Manage <i class="fas fa-arrow-right"></i>
                        </span>
                    </div>
                </div>

                <div class="management-card users" onclick="location.href='manage_users.php'">
                    <div class="card-header">
                        <div class="card-icon users">
                            <i class="fas fa-user-cog"></i>
                        </div>
                        <div>
                            <div class="card-title">User Management</div>
                        </div>
                    </div>
                    <p class="card-description">View registered users, manage accounts, monitor activity, and handle user permissions across the system.</p>
                    <div class="card-action">
                        <span style="color: #666; font-size: 0.875rem;"><?php echo $stats['total_users']; ?> users</span>
                        <span class="action-btn">
                            Manage <i class="fas fa-arrow-right"></i>
                        </span>
                    </div>
                </div>

                <div class="management-card feedback" onclick="location.href='feedback_management.php'">
                    <div class="card-header">
                        <div class="card-icon feedback">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <div>
                            <div class="card-title">Feedback & Reports</div>
                        </div>
                    </div>
                    <p class="card-description">Review user feedback, generate system reports, and analyze operational metrics for service improvement.</p>
                    <div class="card-action">
                        <span style="color: #666; font-size: 0.875rem;"><?php echo $stats['pending_feedback']; ?> pending</span>
                        <span class="action-btn">
                            View <i class="fas fa-arrow-right"></i>
                        </span>
                    </div>
                </div>

                <div class="management-card lost" onclick="location.href='manage_lost_items.php'">
                    <div class="card-header">
                        <div class="card-icon lost">
                            <i class="fas fa-search"></i>
                        </div>
                        <div>
                            <div class="card-title">Lost Items Reports</div>
                        </div>
                    </div>
                    <p class="card-description">View and manage lost item reports submitted by users. Mark items as found or close cases efficiently.</p>
                    <div class="card-action">
                        <span style="color: #666; font-size: 0.875rem;"><?php echo $stats['active_lost_items']; ?> active</span>
                        <span class="action-btn">
                            View <i class="fas fa-arrow-right"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activities -->
        <div class="management-section">
            <h2 class="section-title">
                <i class="fas fa-clock"></i>
                Recent Activities
            </h2>
            <div class="recent-activities">
                <div class="activity-card">
                    <div class="activity-header">
                        <i class="fas fa-clipboard-list"></i>
                        <h3>Latest Claims</h3>
                    </div>
                    <?php if ($recent_claims && $recent_claims->num_rows > 0): ?>
                        <?php while ($claim = $recent_claims->fetch_assoc()): ?>
                            <div class="activity-item">
                                <div class="activity-icon claims">
                                    <i class="fas fa-hand-holding"></i>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-title"><?php echo htmlspecialchars($claim['item_name']); ?></div>
                                    <div class="activity-time">
                                        Claimed by <?php echo htmlspecialchars($claim['username']); ?> • 
                                        <?php echo date('M j, g:i A', strtotime($claim['submission_date'])); ?>
                                    </div>
                                </div>
                                <div class="activity-status status-pending">
                                    <?php echo ucfirst($claim['action']); ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="no-activity">
                            <i class="fas fa-inbox"></i>
                            <p>No recent claims</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="activity-card">
                    <div class="activity-header">
                        <i class="fas fa-plus-circle"></i>
                        <h3>Recent Found Items</h3>
                    </div>
                    <?php if ($recent_items && $recent_items->num_rows > 0): ?>
                        <?php while ($item = $recent_items->fetch_assoc()): ?>
                            <div class="activity-item">
                                <div class="activity-icon items">
                                    <i class="fas fa-box"></i>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-title"><?php echo htmlspecialchars($item['item_name']); ?></div>
                                    <div class="activity-time">
                                        Found at <?php echo htmlspecialchars($item['location_found']); ?> • 
                                        <?php echo date('M j, g:i A', strtotime($item['created_at'])); ?>
                                    </div>
                                </div>
                                <div class="activity-status status-unclaimed">
                                    <?php echo ucfirst($item['status']); ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="no-activity">
                            <i class="fas fa-inbox"></i>
                            <p>No recent items found</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="activity-card">
                    <div class="activity-header">
                        <i class="fas fa-search"></i>
                        <h3>Recent Lost Reports</h3>
                    </div>
                    <?php if ($recent_lost && $recent_lost->num_rows > 0): ?>
                        <?php while ($lost = $recent_lost->fetch_assoc()): ?>
                            <div class="activity-item">
                                <div class="activity-icon lost">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-title"><?php echo htmlspecialchars($lost['item_name']); ?></div>
                                    <div class="activity-time">
                                        Reported by <?php echo htmlspecialchars($lost['first_name'] . ' ' . $lost['last_name']); ?> • 
                                        <?php echo date('M j, g:i A', strtotime($lost['created_at'])); ?>
                                    </div>
                                </div>
                                <div class="activity-status status-active">
                                    <?php echo ucfirst($lost['status']); ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                        <?php else: ?>
                        <div class="no-activity">
                            <i class="fas fa-inbox"></i>
                            <p>No recent lost reports</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- User Activities Card -->
                <div class="activity-card">
                    <div class="activity-header">
                        <i class="fas fa-history"></i>
                        <h3>User Activities</h3>
                    </div>
                    <?php
                    // Get recent user activities
                    $conn = getDatabaseConnection();
                    $recent_activities = $conn->query("
                        SELECT sl.*, u.username, u.first_name, u.last_name 
                        FROM system_logs sl 
                        LEFT JOIN users u ON sl.user_id = u.id 
                        WHERE sl.description LIKE '%logged in%' 
                        OR sl.description LIKE '%logged out%'
                        OR sl.description LIKE '%registered%'
                        OR sl.description LIKE '%password reset%'
                        ORDER BY sl.created_at DESC 
                        LIMIT 5
                    ");
                    
                    if ($recent_activities && $recent_activities->num_rows > 0): ?>
                        <?php while ($activity = $recent_activities->fetch_assoc()): ?>
                            <div class="activity-item">
                                <div class="activity-icon 
                                    <?php 
                                    if (strpos($activity['description'], 'logged in') !== false) echo 'claims';
                                    elseif (strpos($activity['description'], 'logged out') !== false) echo 'lost';
                                    elseif (strpos($activity['description'], 'registered') !== false) echo 'items';
                                    else echo 'users';
                                    ?>">
                                    <i class="fas 
                                        <?php 
                                        if (strpos($activity['description'], 'logged in') !== false) echo 'fa-sign-in-alt';
                                        elseif (strpos($activity['description'], 'logged out') !== false) echo 'fa-sign-out-alt';
                                        elseif (strpos($activity['description'], 'registered') !== false) echo 'fa-user-plus';
                                        else echo 'fa-key';
                                        ?>">
                                    </i>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-title"><?php echo htmlspecialchars($activity['description']); ?></div>
                                    <div class="activity-time">
                                        IP: <?php echo htmlspecialchars($activity['ip_address'] ?? 'unknown'); ?> • 
                                        <?php echo date('M j, g:i A', strtotime($activity['created_at'])); ?>
                                    </div>
                                </div>
                                <div class="activity-status <?php echo strpos($activity['description'], 'failed') !== false ? 'status-pending' : 'status-active'; ?>">
                                    <?php echo strpos($activity['description'], 'failed') !== false ? 'Failed' : 'Success'; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="no-activity">
                            <i class="fas fa-inbox"></i>
                            <p>No recent user activities</p>
                        </div>
                    <?php endif; ?>
                    <?php $conn->close(); ?>
                </div>
            </div>
        </div>    
             
    </main>

    <script>
        // Modern animations and interactions
        document.addEventListener('DOMContentLoaded', function() {
            // Animate numbers on load
            const animateNumbers = () => {
                const numbers = document.querySelectorAll('.stat-info h3, .quick-stat-number');
                numbers.forEach(number => {
                    const target = parseInt(number.textContent);
                    const duration = 1500;
                    const start = 0;
                    const startTime = Date.now();

                    const updateNumber = () => {
                        const elapsed = Date.now() - startTime;
                        const progress = Math.min(elapsed / duration, 1);
                        
                        // Easing function for smooth animation
                        const easeOutQuart = 1 - Math.pow(1 - progress, 4);
                        const current = Math.floor(start + (target - start) * easeOutQuart);
                        
                        number.textContent = current;
                        
                        if (progress < 1) {
                            requestAnimationFrame(updateNumber);
                        } else {
                            number.textContent = target;
                        }
                    };
                    
                    requestAnimationFrame(updateNumber);
                });
            };

            // Intersection Observer for animations
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, observerOptions);

            // Add entrance animations
            const cards = document.querySelectorAll('.stat-card, .management-card, .activity-card, .welcome-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(30px)';
                card.style.transition = `opacity 0.6s ease ${index * 0.1}s, transform 0.6s ease ${index * 0.1}s`;
                observer.observe(card);
            });

            // Trigger number animation after a short delay
            setTimeout(animateNumbers, 500);

            // Enhanced card hover effects
            const managementCards = document.querySelectorAll('.management-card');
            managementCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-8px) scale(1.02)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0) scale(1)';
                });
            });

            // Stat card click effects
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach(card => {
                card.addEventListener('click', function() {
                    this.style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        this.style.transform = 'scale(1)';
                    }, 150);
                });
            });

            // Real-time clock in welcome message
            const updateClock = () => {
                const now = new Date();
                const hour = now.getHours();
                let greeting = 'Good ';
                
                if (hour < 12) greeting += 'Morning';
                else if (hour < 18) greeting += 'Afternoon';
                else greeting += 'Evening';
                
                const welcomeTitle = document.querySelector('.welcome-text h2');
                if (welcomeTitle) {
                    welcomeTitle.textContent = greeting + '!';
                }
            };

            // Update every minute
            setInterval(updateClock, 60000);

            // Smooth scrolling for any anchor links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });

            // Add loading states for management cards
            managementCards.forEach(card => {
                card.addEventListener('click', function() {
                    const actionBtn = this.querySelector('.action-btn');
                    if (actionBtn) {
                        const originalText = actionBtn.innerHTML;
                        actionBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
                        
                        // Reset after navigation (fallback)
                        setTimeout(() => {
                            actionBtn.innerHTML = originalText;
                        }, 2000);
                    }
                });
            });

            // Keyboard navigation support
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Tab') {
                    document.body.classList.add('keyboard-navigation');
                }
            });

            document.addEventListener('mousedown', function() {
                document.body.classList.remove('keyboard-navigation');
            });

            // Add focus styles for keyboard navigation
            const style = document.createElement('style');
            style.textContent = `
                .keyboard-navigation .management-card:focus,
                .keyboard-navigation .stat-card:focus {
                    outline: 3px solid #667eea;
                    outline-offset: 2px;
                }
            `;
            document.head.appendChild(style);
        });

        // Progressive loading for better UX
        window.addEventListener('load', function() {
            // Add loaded class for any final animations
            document.body.classList.add('loaded');
            
            // Add subtle breathing animation to stat icons
            const statIcons = document.querySelectorAll('.stat-icon');
            statIcons.forEach((icon, index) => {
                setTimeout(() => {
                    icon.style.animation = 'breathe 3s ease-in-out infinite';
                }, index * 200);
            });
        });

        // Add CSS for breathing animation
        const breatheStyle = document.createElement('style');
        breatheStyle.textContent = `
            @keyframes breathe {
                0%, 100% { transform: scale(1); }
                50% { transform: scale(1.05); }
            }
            
            .loaded .stat-card {
                animation: slideInUp 0.6s ease forwards;
            }
            
            @keyframes slideInUp {
                from {
                    opacity: 0;
                    transform: translateY(30px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
        `;
        document.head.appendChild(breatheStyle);
    </script>
</body>
</html>