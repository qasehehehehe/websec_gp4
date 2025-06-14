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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $train_number = trim($_POST['train_number']);
    $route = trim($_POST['route']);
    $travel_date = $_POST['travel_date'];
    $feedback_type = $_POST['feedback_type'];
    $category = $_POST['category'];
    $subject = trim($_POST['subject']);
    $feedback_message = trim($_POST['message']);
    $rating = $_POST['rating'] ?? null;
    $priority = $_POST['priority'] ?? 'medium';
    
    $sql = "INSERT INTO feedback (user_id, train_number, route, travel_date, feedback_type, category, subject, message, rating, priority) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('isssssssss', $_SESSION['user_id'], $train_number, $route, $travel_date, $feedback_type, $category, $subject, $feedback_message, $rating, $priority);
    
    if ($stmt->execute()) {
        $message = "Feedback submitted successfully! We'll review it soon.";
        $messageType = "success";
    } else {
        $message = "An error occurred. Please try again.";
        $messageType = "error";
    }
    $stmt->close();
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
    <title>Submit Feedback - Railway System</title>
    <style>
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .rating-stars {
            display: flex;
            gap: 0.5rem;
            margin-top: 0.5rem;
            align-items: center;
        }
        
        .rating-stars i {
            font-size: 1.5rem;
            color: #e9ecef;
            cursor: pointer;
            transition: color 0.3s ease;
        }
        
        .rating-stars i.active,
        .rating-stars i:hover {
            color: #ffc107;
        }
        
        .rating-display {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .rating-display span {
            font-size: 0.9rem;
            color: #666;
            margin-left: 0.5rem;
        }
        
        @media (max-width: 768px) {
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
                    <p>Submit Feedback</p>
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
                <i class="fas fa-comments"></i>
                Submit Feedback
            </h2>
            
            <div class="card-description text-center mb-4">
                Help us improve our railway services by sharing your experience.
            </div>
            
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="train_number">Train Number</label>
                        <input type="text" id="train_number" name="train_number" class="form-control" placeholder="e.g., KL001">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="travel_date">Travel Date</label>
                        <input type="date" id="travel_date" name="travel_date" class="form-control">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="route">Route</label>
                    <input type="text" id="route" name="route" class="form-control" placeholder="e.g., KL Sentral to Ipoh">
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="feedback_type">Feedback Type *</label>
                        <select id="feedback_type" name="feedback_type" class="form-control" required>
                            <option value="">Select type</option>
                            <option value="complaint">Complaint</option>
                            <option value="suggestion">Suggestion</option>
                            <option value="compliment">Compliment</option>
                            <option value="general">General</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="category">Category *</label>
                        <select id="category" name="category" class="form-control" required>
                            <option value="">Select category</option>
                            <option value="cleanliness">Cleanliness</option>
                            <option value="punctuality">Punctuality</option>
                            <option value="staff_behavior">Staff Behavior</option>
                            <option value="facilities">Facilities</option>
                            <option value="safety">Safety</option>
                            <option value="ticketing">Ticketing</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="subject">Subject *</label>
                    <input type="text" id="subject" name="subject" class="form-control" required placeholder="Brief description of your feedback">
                </div>

                <div class="form-group">
                    <label class="form-label" for="message">Message *</label>
                    <textarea id="message" name="message" rows="5" class="form-control" required placeholder="Please provide detailed feedback..."></textarea>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Overall Rating</label>
                        <div class="rating-display">
                            <div class="rating-stars">
                                <i class="fas fa-star" data-rating="1"></i>
                                <i class="fas fa-star" data-rating="2"></i>
                                <i class="fas fa-star" data-rating="3"></i>
                                <i class="fas fa-star" data-rating="4"></i>
                                <i class="fas fa-star" data-rating="5"></i>
                            </div>
                            <span id="rating-text">Click to rate</span>
                        </div>
                        <input type="hidden" id="rating" name="rating" value="">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="priority">Priority</label>
                        <select id="priority" name="priority" class="form-control">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 15px; font-size: 16px;">
                    <i class="fas fa-paper-plane"></i> Submit Feedback
                </button>
            </form>
        </div>
    </main>

    <footer class="mt-5 text-center p-3" style="background: rgba(255,255,255,0.1); color: #666;">
        <p>&copy; 2025 Railway Feedback & Lost and Found. All rights reserved.</p>
    </footer>

    <script>
        // Rating system
        const stars = document.querySelectorAll('.rating-stars i');
        const ratingInput = document.getElementById('rating');
        const ratingText = document.getElementById('rating-text');
        
        stars.forEach(star => {
            star.addEventListener('click', function() {
                const rating = this.getAttribute('data-rating');
                ratingInput.value = rating;
                
                // Update star display
                stars.forEach((s, index) => {
                    if (index < rating) {
                        s.classList.add('active');
                    } else {
                        s.classList.remove('active');
                    }
                });
                
                // Update rating text
                const ratingTexts = ['', 'Poor', 'Fair', 'Good', 'Very Good', 'Excellent'];
                ratingText.textContent = ratingTexts[rating] + ' (' + rating + '/5)';
            });
            
            star.addEventListener('mouseover', function() {
                const rating = this.getAttribute('data-rating');
                stars.forEach((s, index) => {
                    if (index < rating) {
                        s.style.color = '#ffc107';
                    } else {
                        s.style.color = '#e9ecef';
                    }
                });
            });
        });
        
        document.querySelector('.rating-stars').addEventListener('mouseleave', function() {
            const currentRating = ratingInput.value;
            stars.forEach((s, index) => {
                if (index < currentRating) {
                    s.style.color = '#ffc107';
                    s.classList.add('active');
                } else {
                    s.style.color = '#e9ecef';
                    s.classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>

<?php $conn->close(); ?>