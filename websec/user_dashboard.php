<?php
session_start();
// FIXED: Changed from 'admin' to 'user' - this was the main issue!
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: login.php");
    exit();
}

include 'db_connection.php';
$conn = getDatabaseConnection();

// Get user-specific dashboard statistics
$stats = [];
$userId = $_SESSION['user_id'];

// User's total claims
$result = $conn->query("SELECT COUNT(*) as count FROM claims WHERE user_id = $userId");
$stats['user_claims'] = $result->fetch_assoc()['count'];

// User's pending claims
$result = $conn->query("SELECT COUNT(*) as count FROM claims WHERE user_id = $userId AND action = 'pending'");
$stats['pending_claims'] = $result->fetch_assoc()['count'];

// User's approved claims
$result = $conn->query("SELECT COUNT(*) as count FROM claims WHERE user_id = $userId AND action = 'approved'");
$stats['approved_claims'] = $result->fetch_assoc()['count'];

// Available unclaimed items (for browsing)
$result = $conn->query("SELECT COUNT(*) as count FROM found_items WHERE status = 'unclaimed'");
$stats['unclaimed_items'] = $result->fetch_assoc()['count'];

// User's lost item reports
$result = $conn->query("SELECT COUNT(*) as count FROM lost_items WHERE user_id = $userId AND status = 'active'");
$stats['active_lost_items'] = $result->fetch_assoc()['count'];

// User's feedback submissions
$result = $conn->query("SELECT COUNT(*) as count FROM feedback WHERE user_id = $userId");
$stats['user_feedback'] = $result->fetch_assoc()['count'];

// Recent claims by this user
$recent_claims = $conn->query("
    SELECT c.*, fi.item_name, fi.location_found, fi.date_found 
    FROM claims c 
    JOIN found_items fi ON c.item_id = fi.id 
    WHERE c.user_id = $userId 
    ORDER BY c.submission_date DESC 
    LIMIT 5
");

// Recent found items (available for claiming)
$recent_items = $conn->query("
    SELECT * FROM found_items 
    WHERE status = 'unclaimed' 
    ORDER BY created_at DESC 
    LIMIT 6
");

// Recent lost items by this user
$recent_lost = $conn->query("
    SELECT * FROM lost_items 
    WHERE user_id = $userId 
    ORDER BY created_at DESC 
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
    <link href="railway-styles.css" rel="stylesheet">
    <title>User Dashboard - Railway System</title>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <div class="logo-section">
                <img src="img/logotest.jpg" alt="Railway Logo">
                <div class="logo-text">
                    <h1>Railway User</h1>
                    <p>Personal Dashboard</p>
                </div>
            </div>
            <div class="header-actions">
                <div class="user-info">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($_SESSION['first_name'] ?? 'U', 0, 1)); ?>
                    </div>
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['first_name'] ?? 'User'); ?></span>
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
        <div class="welcome-card user-welcome">
            <div class="welcome-text">
                <h2>Good <?php echo date('H') < 12 ? 'Morning' : (date('H') < 18 ? 'Afternoon' : 'Evening'); ?>!</h2>
                <p>Welcome to your personal dashboard. Manage your lost and found items, submit claims, and track your activity all in one place.</p>
            </div>
            
            <div class="quick-stats">
                <div class="quick-stat">
                    <div class="quick-stat-number"><?php echo $stats['user_claims']; ?></div>
                    <div class="quick-stat-label">Total Claims</div>
                </div>
                <div class="quick-stat">
                    <div class="quick-stat-number"><?php echo $stats['pending_claims']; ?></div>
                    <div class="quick-stat-label">Pending</div>
                </div>
                <div class="quick-stat">
                    <div class="quick-stat-number"><?php echo $stats['approved_claims']; ?></div>
                    <div class="quick-stat-label">Approved</div>
                </div>
                <div class="quick-stat">
                    <div class="quick-stat-number"><?php echo $stats['unclaimed_items']; ?></div>
                    <div class="quick-stat-label">Available Items</div>
                </div>
                <div class="quick-stat">
                    <div class="quick-stat-number"><?php echo $stats['active_lost_items']; ?></div>
                    <div class="quick-stat-label">Lost Reports</div>
                </div>
                <div class="quick-stat">
                    <div class="quick-stat-number"><?php echo $stats['user_feedback']; ?></div>
                    <div class="quick-stat-label">Feedback</div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="actions-section">
            <h2 class="section-title">
                <i class="fas fa-bolt"></i>
                Quick Actions
            </h2>
            <div class="actions-grid">
                <div class="action-card browse" onclick="location.href='view_found_items.php'">
                    <div class="action-icon browse">
                        <i class="fas fa-search"></i>
                    </div>
                    <h3 class="action-title">Browse Found Items</h3>
                    <p class="action-description">Search through items found by our staff and claim yours if you find a match.</p>
                    <button class="action-btn">
                        Browse Items <i class="fas fa-arrow-right"></i>
                    </button>
                </div>

                <div class="action-card report" onclick="location.href='report_lost_item.php'">
                    <div class="action-icon report">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h3 class="action-title">Report Lost Item</h3>
                    <p class="action-description">Lost something on the railway? Report it here and we'll help you find it.</p>
                    <button class="action-btn">
                        Report Item <i class="fas fa-arrow-right"></i>
                    </button>
                </div>

                <div class="action-card claims" onclick="location.href='view_my_reports.php'">
                    <div class="action-icon claims">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <h3 class="action-title">My Claims & Reports</h3>
                    <p class="action-description">Track the status of your claims, lost reports, and feedback submissions.</p>
                    <button class="action-btn">
                        View Reports <i class="fas fa-arrow-right"></i>
                    </button>
                </div>

                <div class="action-card feedback" onclick="location.href='submit_feedback.php'">
                    <div class="action-icon feedback">
                        <i class="fas fa-comments"></i>
                    </div>
                    <h3 class="action-title">Submit Feedback</h3>
                    <p class="action-description">Help us improve our railway services by sharing your feedback and suggestions.</p>
                    <button class="action-btn">
                        Give Feedback <i class="fas fa-arrow-right"></i>
                    </button>
                </div>

                <div class="action-card profile" onclick="location.href='update_profile.php'">
                    <div class="action-icon profile">
                        <i class="fas fa-user-cog"></i>
                    </div>
                    <h3 class="action-title">My Profile</h3>
                    <p class="action-description">Update your account information, change password, and manage settings.</p>
                    <button class="action-btn">
                        Edit Profile <i class="fas fa-arrow-right"></i>
                    </button>
                </div>

                <div class="action-card reports" onclick="location.href='view_my_reports.php'">
                    <div class="action-icon reports">
                        <i class="fas fa-history"></i>
                    </div>
                    <h3 class="action-title">Activity History</h3>
                    <p class="action-description">View your complete history of claims, reports, and feedback submissions.</p>
                    <button class="action-btn">
                        View History <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Recently Found Items Preview -->
        <div class="browse-preview">
            <div class="activity-header">
                <i class="fas fa-eye"></i>
                <h3>Recently Found Items</h3>
                <div style="margin-left: auto;">
                    <a href="view_found_items.php" style="color: #667eea; text-decoration: none; font-size: 0.9rem;">
                        View All <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
            <?php if ($recent_items && $recent_items->num_rows > 0): ?>
                <div class="items-preview-grid">
                    <?php while ($item = $recent_items->fetch_assoc()): ?>
                        <div class="preview-item" onclick="location.href='view_found_items.php'">
                            <div class="preview-item-name"><?php echo htmlspecialchars($item['item_name']); ?></div>
                            <div class="preview-item-location"><?php echo htmlspecialchars($item['location_found']); ?></div>
                            <div class="preview-item-location"><?php echo date('M j', strtotime($item['date_found'])); ?></div>
                            <button class="preview-claim-btn">
                                <i class="fas fa-hand-paper"></i> View
                            </button>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="no-activity">
                    <i class="fas fa-inbox"></i>
                    <p>No items available at the moment</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Recent Activities -->
        <div class="recent-section">
            <h2 class="section-title">
                <i class="fas fa-clock"></i>
                Recent Activities
            </h2>
            <div class="recent-grid">
                <div class="activity-card">
                    <div class="activity-header">
                        <i class="fas fa-clipboard-list"></i>
                        <h3>My Latest Claims</h3>
                    </div>
                    <?php if ($recent_claims && $recent_claims->num_rows > 0): ?>
                        <?php while ($claim = $recent_claims->fetch_assoc()): ?>
                            <div class="activity-item">
                                <div class="activity-item-icon claims">
                                    <i class="fas fa-hand-holding"></i>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-title"><?php echo htmlspecialchars($claim['item_name']); ?></div>
                                    <div class="activity-time">
                                        <?php echo date('M j, g:i A', strtotime($claim['submission_date'])); ?>
                                    </div>
                                </div>
                                <div class="activity-status status-<?php echo $claim['action']; ?>">
                                    <?php echo ucfirst($claim['action']); ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="no-activity">
                            <i class="fas fa-inbox"></i>
                            <p>No claims submitted yet</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="activity-card">
                    <div class="activity-header">
                        <i class="fas fa-search"></i>
                        <h3>My Lost Reports</h3>
                    </div>
                    <?php if ($recent_lost && $recent_lost->num_rows > 0): ?>
                        <?php while ($lost = $recent_lost->fetch_assoc()): ?>
                            <div class="activity-item">
                                <div class="activity-item-icon lost">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-title"><?php echo htmlspecialchars($lost['item_name']); ?></div>
                                    <div class="activity-time">
                                        <?php echo date('M j, g:i A', strtotime($lost['created_at'])); ?>
                                    </div>
                                </div>
                                <div class="activity-status status-<?php echo $lost['status']; ?>">
                                    <?php echo ucfirst($lost['status']); ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="no-activity">
                            <i class="fas fa-inbox"></i>
                            <p>No lost reports submitted yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Modern animations and interactions
        document.addEventListener('DOMContentLoaded', function() {
            // Animate numbers on load
            const animateNumbers = () => {
                const numbers = document.querySelectorAll('.quick-stat-number');
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
            const cards = document.querySelectorAll('.action-card, .activity-card, .welcome-card, .browse-preview');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(30px)';
                card.style.transition = `opacity 0.6s ease ${index * 0.1}s, transform 0.6s ease ${index * 0.1}s`;
                observer.observe(card);
            });

            // Trigger number animation after a short delay
            setTimeout(animateNumbers, 500);

            // Enhanced card hover effects
            const actionCards = document.querySelectorAll('.action-card');
            actionCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-10px) scale(1.02)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0) scale(1)';
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

            // Add loading states for action cards
            actionCards.forEach(card => {
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
                .keyboard-navigation .action-card:focus,
                .keyboard-navigation .preview-item:focus {
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
            
            // Add subtle breathing animation to action icons
            const actionIcons = document.querySelectorAll('.action-icon');
            actionIcons.forEach((icon, index) => {
                setTimeout(() => {
                    icon.style.animation = 'breathe 4s ease-in-out infinite';
                }, index * 300);
            });
        });

        // Add CSS for breathing animation
        const breatheStyle = document.createElement('style');
        breatheStyle.textContent = `
            @keyframes breathe {
                0%, 100% { transform: scale(1); }
                50% { transform: scale(1.05); }
            }
            
            .loaded .action-card {
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