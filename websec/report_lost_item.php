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
    $item_name = trim($_POST['item_name']);
    $description = trim($_POST['description']);
    $category = $_POST['category'];
    $color = trim($_POST['color']);
    $brand = trim($_POST['brand']);
    $lost_location = trim($_POST['lost_location']);
    $lost_date = $_POST['lost_date'];
    $train_number = trim($_POST['train_number']); // Optional now
    $contact_phone = trim($_POST['contact_phone']);
    $contact_email = trim($_POST['contact_email']);
    $reward_offered = $_POST['reward_offered'] ?? 0;
    $additional_notes = trim($_POST['additional_notes']);
    
    $sql = "INSERT INTO lost_items (user_id, item_name, description, category, color, brand, lost_location, lost_date, train_number, contact_phone, contact_email, reward_offered, additional_notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('issssssssssds', $_SESSION['user_id'], $item_name, $description, $category, $color, $brand, $lost_location, $lost_date, $train_number, $contact_phone, $contact_email, $reward_offered, $additional_notes);
    
    if ($stmt->execute()) {
        $message = "Lost item report submitted successfully! We'll contact you if we find your item.";
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
    <title>Report Lost Item - Railway System</title>
    <style>
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .optional-label {
            color: #43e97b;
            font-size: 0.75rem;
            font-weight: normal;
            text-transform: uppercase;
            margin-left: 0.5rem;
        }
        
        .info-box {
            background: rgba(79, 172, 254, 0.1);
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            border-left: 3px solid #4facfe;
        }
        
        .info-box p {
            margin: 0;
            color: #0c5460;
            font-size: 0.9rem;
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
                    <p>Report Lost Item</p>
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
                <i class="fas fa-box-open"></i>
                Report Lost Item
            </h2>
            
            <div class="card-description text-center mb-4">
                Lost something during your railway journey? Fill out this form and we'll help you find it. 
                <strong>Don't worry if you don't remember all the details</strong> - we can still help!
            </div>
            
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="item_name">Item Name *</label>
                        <input type="text" id="item_name" name="item_name" class="form-control" required placeholder="e.g., iPhone 13, Wallet, Laptop">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="category">Category *</label>
                        <select id="category" name="category" class="form-control" required>
                            <option value="">Select category</option>
                            <option value="electronics">Electronics</option>
                            <option value="clothing">Clothing</option>
                            <option value="documents">Documents</option>
                            <option value="jewelry">Jewelry</option>
                            <option value="bags">Bags</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="description">Description *</label>
                    <textarea id="description" name="description" rows="3" class="form-control" required placeholder="Detailed description of the item (color, size, brand, distinctive features, etc.)"></textarea>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="color">Color <span class="optional-label">Optional</span></label>
                        <input type="text" id="color" name="color" class="form-control" placeholder="e.g., Black, Blue, Red">
                        <small class="text-muted">If you remember the color</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="brand">Brand <span class="optional-label">Optional</span></label>
                        <input type="text" id="brand" name="brand" class="form-control" placeholder="e.g., Apple, Samsung, Nike">
                        <small class="text-muted">If you remember the brand</small>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="lost_location">Where did you lose it? *</label>
                        <input type="text" id="lost_location" name="lost_location" class="form-control" required placeholder="e.g., Train Coach A, Platform 2, Waiting area">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="lost_date">When did you lose it? *</label>
                        <input type="date" id="lost_date" name="lost_date" class="form-control" required max="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="train_number">Train Number <span class="optional-label">Optional</span></label>
                    <input type="text" id="train_number" name="train_number" class="form-control" placeholder="e.g., KL001, ETS9108 (if you remember)">
                    <small class="text-muted">Don't worry if you can't remember the train number - this is completely optional</small>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="contact_phone">Contact Phone *</label>
                        <input type="tel" id="contact_phone" name="contact_phone" class="form-control" required placeholder="Your phone number">
                        <small class="text-muted">We'll call you if we find your item</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="contact_email">Contact Email *</label>
                        <input type="email" id="contact_email" name="contact_email" class="form-control" required placeholder="Your email address">
                        <small class="text-muted">We'll also send updates via email</small>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="reward_offered">Reward Offered (RM) <span class="optional-label">Optional</span></label>
                    <input type="number" id="reward_offered" name="reward_offered" class="form-control" min="0" step="0.01" placeholder="0.00">
                    <small class="text-muted">Optional reward amount you're willing to offer</small>
                </div>

                <div class="form-group">
                    <label class="form-label" for="additional_notes">Additional Notes <span class="optional-label">Optional</span></label>
                    <textarea id="additional_notes" name="additional_notes" rows="3" class="form-control" placeholder="Any additional information that might help us find your item (e.g., seat number, time of travel, circumstances of loss, etc.)"></textarea>
                    <small class="text-muted">The more details you provide, the better we can help</small>
                </div>

                <div class="info-box">
                    <p>
                        <i class="fas fa-info-circle"></i> <strong>What happens next?</strong><br>
                        We'll search our found items database and contact you within 24-48 hours if we find a match. Our staff will also be notified to look out for your item.
                    </p>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 15px; font-size: 16px;">
                    <i class="fas fa-paper-plane"></i> Submit Lost Item Report
                </button>
            </form>
        </div>
    </main>

    <footer class="mt-5 text-center p-3" style="background: rgba(255,255,255,0.1); color: #666;">
        <p>&copy; 2025 Railway Feedback & Lost and Found. All rights reserved.</p>
    </footer>

    <script>
        // Set max date to today
        document.getElementById('lost_date').max = new Date().toISOString().split('T')[0];
        
        // Auto-populate email if available
        document.addEventListener('DOMContentLoaded', function() {
            // You can add auto-population logic here if needed
        });
    </script>
</body>
</html>

<?php $conn->close(); ?>