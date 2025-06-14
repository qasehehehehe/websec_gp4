<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'db_connection.php';
include 'security_function.php';

// Initialize secure session
initializeSecureSession();

$conn = getDatabaseConnection();
$message = '';
$messageType = '';

// Handle claim submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'claim_item') {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = '❌ Security error: Invalid request. Please try again.';
        $messageType = 'error';
        logSecurityEvent('CSRF_TOKEN_INVALID', ['action' => 'claim_item']);
    } else {
        // Sanitize inputs
        $itemId = (int)sanitizeInput($_POST['item_id'] ?? 0);
        $claimantName = sanitizeInput($_POST['claimant_name'] ?? '');
        $claimantPhone = sanitizeInput($_POST['claimant_phone'] ?? '');
        $claimantEmail = sanitizeInput($_POST['claimant_email'] ?? '');
        $description = sanitizeInput($_POST['description'] ?? '');
        
        // Validation
        $validationErrors = [];
        
        if (empty($claimantName) || strlen($claimantName) < 2) {
            $validationErrors[] = 'Name must be at least 2 characters long';
        }
        
        if (empty($claimantPhone) || !preg_match('/^[0-9\-\+\s\(\)]{10,20}$/', $claimantPhone)) {
            $validationErrors[] = 'Please enter a valid phone number';
        }
        
        $emailValidation = validateEmail($claimantEmail);
        if (!$emailValidation['valid']) {
            $validationErrors[] = $emailValidation['message'];
        }
        
        if (empty($description) || strlen($description) < 10) {
            $validationErrors[] = 'Description must be at least 10 characters long';
        }
        
        if ($itemId <= 0) {
            $validationErrors[] = 'Invalid item selected';
        }
        
        if (empty($validationErrors)) {
            // Check if item exists and is unclaimed
            $itemCheckSql = "SELECT id, item_name, status FROM found_items WHERE id = ? AND status = 'unclaimed'";
            $itemCheckStmt = $conn->prepare($itemCheckSql);
            
            if ($itemCheckStmt) {
                $itemCheckStmt->bind_param('i', $itemId);
                $itemCheckStmt->execute();
                $itemResult = $itemCheckStmt->get_result();
                
                if ($itemResult->num_rows > 0) {
                    $item = $itemResult->fetch_assoc();
                    
                    // Check if user already claimed this item
                    $existingClaimSql = "SELECT id FROM claims WHERE user_id = ? AND item_id = ?";
                    $existingStmt = $conn->prepare($existingClaimSql);
                    
                    if ($existingStmt) {
                        $existingStmt->bind_param('ii', $_SESSION['user_id'], $itemId);
                        $existingStmt->execute();
                        $existingResult = $existingStmt->get_result();
                        
                        if ($existingResult->num_rows > 0) {
                            $validationErrors[] = 'You have already submitted a claim for this item';
                        } else {
                            // Generate claim reference
                            $claimReference = 'CLM' . date('Ymd') . sprintf('%04d', rand(1000, 9999));
                            
                            // Insert claim
                            $claimSql = "INSERT INTO claims (user_id, item_id, name, phone, email, description, claim_reference, submission_date) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
                            $claimStmt = $conn->prepare($claimSql);
                            
                            if ($claimStmt) {
                                $claimStmt->bind_param('iisssss', $_SESSION['user_id'], $itemId, $claimantName, $claimantPhone, $claimantEmail, $description, $claimReference);
                                
                                if ($claimStmt->execute()) {
                                    $message = "✅ Claim submitted successfully! Reference: $claimReference. You will be contacted if your claim is approved.";
                                    $messageType = 'success';
                                    
                                    logSecurityEvent('ITEM_CLAIM_SUBMITTED', [
                                        'user_id' => $_SESSION['user_id'],
                                        'item_id' => $itemId,
                                        'claim_reference' => $claimReference
                                    ]);
                                } else {
                                    $validationErrors[] = 'Failed to submit claim. Please try again.';
                                    error_log("Claim submission failed: " . $claimStmt->error);
                                }
                                $claimStmt->close();
                            }
                        }
                        $existingStmt->close();
                    }
                } else {
                    $validationErrors[] = 'Item not found or already claimed';
                }
                $itemCheckStmt->close();
            }
        }
        
        if (!empty($validationErrors)) {
            $message = implode('<br>', $validationErrors);
            $messageType = 'error';
        }
    }
}

// Search and filter functionality
$searchTerm = sanitizeInput($_GET['search'] ?? '');
$categoryFilter = sanitizeInput($_GET['category'] ?? '');
$dateFilter = sanitizeInput($_GET['date_from'] ?? '');

// Build query with filters
$whereClauses = ["status = 'unclaimed'"];
$params = [];
$types = '';

if (!empty($searchTerm)) {
    $whereClauses[] = "(item_name LIKE ? OR description LIKE ? OR location_found LIKE ?)";
    $searchPattern = "%$searchTerm%";
    $params = array_merge($params, [$searchPattern, $searchPattern, $searchPattern]);
    $types .= 'sss';
}

if (!empty($categoryFilter)) {
    $whereClauses[] = "category = ?";
    $params[] = $categoryFilter;
    $types .= 's';
}

if (!empty($dateFilter)) {
    $whereClauses[] = "date_found >= ?";
    $params[] = $dateFilter;
    $types .= 's';
}

$whereClause = implode(' AND ', $whereClauses);

// Get found items with pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$itemsPerPage = 12;
$offset = ($page - 1) * $itemsPerPage;

// Count total items
$countSql = "SELECT COUNT(*) as total FROM found_items WHERE $whereClause";
$countStmt = $conn->prepare($countSql);
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$totalItems = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalItems / $itemsPerPage);
$countStmt->close();

// Get items for current page
$itemsSql = "SELECT * FROM found_items WHERE $whereClause ORDER BY created_at DESC LIMIT ? OFFSET ?";
$finalParams = array_merge($params, [$itemsPerPage, $offset]);
$finalTypes = $types . 'ii';

$itemsStmt = $conn->prepare($itemsSql);
$itemsStmt->bind_param($finalTypes, ...$finalParams);
$itemsStmt->execute();
$itemsResult = $itemsStmt->get_result();

$conn->close();

// Generate CSRF token for forms
$csrfToken = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="railway-styles.css" rel="stylesheet">
    <title>Found Items - Railway System</title>
    <style>
        .search-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            padding: 2rem;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 40px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .search-form {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr auto;
            gap: 1rem;
            align-items: end;
        }

        .items-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .item-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 40px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .item-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.15);
        }

        .item-image {
            width: 100%;
            height: 200px;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .item-image img {
            max-width: 100%;
            max-height: 100%;
            object-fit: cover;
        }

        .item-image .no-image {
            color: #6c757d;
            font-size: 3rem;
        }

        .item-content {
            padding: 1.5rem;
        }

        .item-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 0.75rem;
        }

        .item-details {
            color: #666;
            margin-bottom: 1rem;
            line-height: 1.5;
        }

        .item-meta {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
            margin-bottom: 1rem;
            font-size: 0.85rem;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #666;
        }

        .meta-item i {
            color: #667eea;
            width: 12px;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            margin: 5% auto;
            padding: 2rem;
            border-radius: 20px;
            width: 90%;
            max-width: 500px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 40px rgba(0, 0, 0, 0.1);
            position: relative;
        }

        .close {
            position: absolute;
            right: 15px;
            top: 15px;
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            border: none;
            background: none;
        }

        .close:hover {
            color: #000;
        }

        .stats-info {
            background: rgba(231, 243, 255, 0.8);
            border: 1px solid rgba(190, 229, 235, 0.8);
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            text-align: center;
            color: #0c5460;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
            flex-wrap: wrap;
        }

        .pagination a, .pagination span {
            padding: 0.75rem 1rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            text-decoration: none;
            color: #333;
            transition: all 0.3s ease;
        }

        .pagination a:hover {
            background: rgba(102, 126, 234, 0.1);
            border-color: #667eea;
            text-decoration: none;
            color: #667eea;
        }

        .pagination .current {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        .no-items {
            text-align: center;
            padding: 4rem 2rem;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 40px rgba(0, 0, 0, 0.1);
            color: #666;
        }

        .no-items i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #ddd;
        }

        @media (max-width: 768px) {
            .search-form {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .items-grid {
                grid-template-columns: 1fr;
            }
            
            .pagination {
                gap: 0.25rem;
            }
            
            .pagination a, .pagination span {
                padding: 0.5rem 0.75rem;
                font-size: 0.85rem;
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
                    <p>Browse Found Items</p>
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
            <i class="fas fa-search"></i>
            Browse Found Items
        </h2>

        <div class="stats-info">
            <i class="fas fa-info-circle"></i> 
            <strong><?php echo $totalItems; ?></strong> unclaimed items available for claiming
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- Search and Filter Section -->
        <div class="search-section">
            <h3><i class="fas fa-filter"></i> Search & Filter Items</h3>
            <form method="GET" action="" class="search-form">
                <input type="text" name="search" class="form-control" placeholder="Search by item name, description, or location..." 
                       value="<?php echo htmlspecialchars($searchTerm); ?>">
                
                <select name="category" class="form-control">
                    <option value="">All Categories</option>
                    <option value="electronics" <?php echo $categoryFilter === 'electronics' ? 'selected' : ''; ?>>Electronics</option>
                    <option value="clothing" <?php echo $categoryFilter === 'clothing' ? 'selected' : ''; ?>>Clothing</option>
                    <option value="documents" <?php echo $categoryFilter === 'documents' ? 'selected' : ''; ?>>Documents</option>
                    <option value="jewelry" <?php echo $categoryFilter === 'jewelry' ? 'selected' : ''; ?>>Jewelry</option>
                    <option value="bags" <?php echo $categoryFilter === 'bags' ? 'selected' : ''; ?>>Bags</option>
                    <option value="other" <?php echo $categoryFilter === 'other' ? 'selected' : ''; ?>>Other</option>
                </select>
                
                <input type="date" name="date_from" class="form-control" placeholder="From date" 
                       value="<?php echo htmlspecialchars($dateFilter); ?>">
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Search
                </button>
            </form>
        </div>

        <!-- Items Grid -->
        <?php if ($itemsResult && $itemsResult->num_rows > 0): ?>
            <div class="items-grid">
                <?php while ($item = $itemsResult->fetch_assoc()): ?>
                    <div class="item-card">
                        <div class="item-image">
                            <?php if (!empty($item['image'])): ?>
                                <img src="data:image/jpeg;base64,<?php echo base64_encode($item['image']); ?>" 
                                     alt="<?php echo htmlspecialchars($item['item_name']); ?>">
                            <?php else: ?>
                                <i class="fas fa-image no-image"></i>
                            <?php endif; ?>
                        </div>

                        <div class="item-content">
                            <h3 class="item-title"><?php echo htmlspecialchars($item['item_name']); ?></h3>
                            
                            <div class="item-details">
                                <?php echo htmlspecialchars($item['description']); ?>
                            </div>

                            <div class="item-meta">
                                <div class="meta-item">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span><?php echo htmlspecialchars($item['location_found']); ?></span>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-calendar"></i>
                                    <span><?php echo date('M j, Y', strtotime($item['date_found'])); ?></span>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-tag"></i>
                                    <span><?php echo ucfirst($item['category']); ?></span>
                                </div>
                                <?php if (!empty($item['color'])): ?>
                                <div class="meta-item">
                                    <i class="fas fa-palette"></i>
                                    <span><?php echo htmlspecialchars($item['color']); ?></span>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($item['brand'])): ?>
                                <div class="meta-item">
                                    <i class="fas fa-copyright"></i>
                                    <span><?php echo htmlspecialchars($item['brand']); ?></span>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($item['found_by'])): ?>
                                <div class="meta-item">
                                    <i class="fas fa-user"></i>
                                    <span>Found by: <?php echo htmlspecialchars($item['found_by']); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>

                            <button class="btn btn-success" style="width: 100%;" onclick="openClaimModal(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['item_name']); ?>')">
                                <i class="fas fa-hand-holding"></i> Claim This Item
                            </button>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($searchTerm); ?>&category=<?php echo urlencode($categoryFilter); ?>&date_from=<?php echo urlencode($dateFilter); ?>">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php endif; ?>

                    <?php for ($i = max(1, $page-2); $i <= min($totalPages, $page+2); $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($searchTerm); ?>&category=<?php echo urlencode($categoryFilter); ?>&date_from=<?php echo urlencode($dateFilter); ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($searchTerm); ?>&category=<?php echo urlencode($categoryFilter); ?>&date_from=<?php echo urlencode($dateFilter); ?>">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="no-items">
                <i class="fas fa-search"></i>
                <h3>No Items Found</h3>
                <p>No items match your search criteria. Try adjusting your filters or check back later.</p>
            </div>
        <?php endif; ?>
    </main>

    <!-- Claim Modal -->
    <div id="claimModal" class="modal">
        <div class="modal-content">
            <button class="close" onclick="closeClaimModal()">&times;</button>
            <h3><i class="fas fa-hand-holding"></i> Claim Item: <span id="modalItemName"></span></h3>
            
            <form method="POST" action="" onsubmit="return validateClaimForm()" id="claimForm">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                <input type="hidden" name="action" value="claim_item">
                <input type="hidden" id="modalItemId" name="item_id" value="">
                
                <div class="form-group">
                    <label for="claimant_name" class="form-label">Your Full Name <span class="required">*</span></label>
                    <input type="text" id="claimant_name" name="claimant_name" class="form-control" required 
                           value="<?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?>">
                </div>

                <div class="form-group">
                    <label for="claimant_phone" class="form-label">Contact Phone <span class="required">*</span></label>
                    <input type="tel" id="claimant_phone" name="claimant_phone" class="form-control" required 
                           placeholder="e.g., +60123456789">
                </div>

                <div class="form-group">
                    <label for="claimant_email" class="form-label">Contact Email <span class="required">*</span></label>
                    <input type="email" id="claimant_email" name="claimant_email" class="form-control" required 
                           value="<?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="description" class="form-label">Describe the item and why you believe it's yours <span class="required">*</span></label>
                    <textarea id="description" name="description" class="form-control" required rows="4"
                              placeholder="Please provide detailed description including any distinguishing features, where and when you lost it, etc."></textarea>
                </div>

                <div class="alert alert-warning" style="margin-bottom: 1rem;">
                    <i class="fas fa-info-circle"></i> <strong>Important:</strong> Your claim will be reviewed by our staff. You will be contacted only if your claim is approved. Please provide accurate information.
                </div>

                <button type="submit" class="btn btn-primary" id="submitClaimBtn" style="width: 100%;">
                    <i class="fas fa-paper-plane"></i> Submit Claim
                </button>
            </form>
        </div>
    </div>

    <footer class="mt-5 text-center p-3" style="background: rgba(255,255,255,0.1); color: #666;">
        <p>&copy; 2025 Railway Feedback & Lost and Found. All rights reserved.</p>
    </footer>

    <script>
        function openClaimModal(itemId, itemName) {
            document.getElementById('modalItemId').value = itemId;
            document.getElementById('modalItemName').textContent = itemName;
            document.getElementById('claimModal').style.display = 'block';
            
            // Focus on first input
            document.getElementById('claimant_name').focus();
        }

        function closeClaimModal() {
            document.getElementById('claimModal').style.display = 'none';
            // Reset form
            document.getElementById('claimForm').reset();
        }

        function validateClaimForm() {
            const name = document.getElementById('claimant_name').value.trim();
            const phone = document.getElementById('claimant_phone').value.trim();
            const email = document.getElementById('claimant_email').value.trim();
            const description = document.getElementById('description').value.trim();
            
            if (name.length < 2) {
                alert('Please enter your full name');
                return false;
            }
            
            if (phone.length < 10) {
                alert('Please enter a valid phone number');
                return false;
            }
            
            if (!email.includes('@')) {
                alert('Please enter a valid email address');
                return false;
            }
            
            if (description.length < 10) {
                alert('Please provide a detailed description (at least 10 characters)');
                return false;
            }
            
            // Show loading state
            const submitBtn = document.getElementById('submitClaimBtn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting Claim...';
            
            return true;
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('claimModal');
            if (event.target === modal) {
                closeClaimModal();
            }
        }

        // Auto-clear search when no results
        document.addEventListener('DOMContentLoaded', function() {
            const noItems = document.querySelector('.no-items');
            if (noItems) {
                const clearBtn = document.createElement('button');
                clearBtn.innerHTML = '<i class="fas fa-refresh"></i> Clear Filters';
                clearBtn.className = 'btn btn-primary';
                clearBtn.style.marginTop = '1rem';
                clearBtn.onclick = function() {
                    window.location.href = 'view_found_items.php';
                };
                noItems.appendChild(clearBtn);
            }
        });
    </script>
</body>
</html>

<?php 
$itemsStmt->close();
?>