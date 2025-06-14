<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

include 'db_connection.php';
$conn = getDatabaseConnection();

// Image compression function
function compressImage($source, $quality = 80, $maxWidth = 800, $maxHeight = 600) {
    // Check if file exists
    if (!file_exists($source)) {
        return false;
    }
    
    $info = getimagesize($source);
    
    if (!$info) {
        return false;
    }
    
    $width = $info[0];
    $height = $info[1];
    $mime = $info['mime'];
    
    // Create image resource based on type
    switch ($mime) {
        case 'image/jpeg':
        case 'image/jpg':
            $image = imagecreatefromjpeg($source);
            break;
        case 'image/png':
            $image = imagecreatefrompng($source);
            break;
        case 'image/gif':
            $image = imagecreatefromgif($source);
            break;
        default:
            return false;
    }
    
    if (!$image) {
        return false;
    }
    
    // Calculate new dimensions
    $ratio = min($maxWidth / $width, $maxHeight / $height);
    if ($ratio < 1) {
        $newWidth = (int)($width * $ratio);
        $newHeight = (int)($height * $ratio);
        
        // Create new image with new dimensions
        $newImage = imagecreatetruecolor($newWidth, $newHeight);
        
        // Preserve transparency for PNG and GIF
        if ($mime === 'image/png' || $mime === 'image/gif') {
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
            $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
            imagefill($newImage, 0, 0, $transparent);
        }
        
        // Resize image
        imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($image);
        $image = $newImage;
    }
    
    // Capture output
    ob_start();
    
    // Always save as JPEG for consistency
    imagejpeg($image, null, $quality);
    $imageData = ob_get_clean();
    
    imagedestroy($image);
    
    // Return false if no data generated
    if (empty($imageData)) {
        return false;
    }
    
    return $imageData;
}

$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_item':
                $item_name = trim($_POST['item_name']);
                $description = trim($_POST['description']);
                $location_found = trim($_POST['location_found']);
                $date_found = $_POST['date_found'];
                $category = $_POST['category'];
                $color = trim($_POST['color']);
                $brand = trim($_POST['brand']);
                $found_by = trim($_POST['found_by']);
                $additional_notes = trim($_POST['additional_notes']);
                
                // Handle image upload with size and validation
                $image_data = null;
                if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] === UPLOAD_ERR_OK) {
                    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                    $max_size = 16 * 1024 * 1024; // 16MB limit
                    
                    // Debug: Log file info
                    error_log("File info: " . print_r($_FILES['item_image'], true));
                    
                    // Check file type
                    if (!in_array($_FILES['item_image']['type'], $allowed_types)) {
                        $message = "Error: Only JPEG, PNG, and GIF images are allowed. Detected: " . $_FILES['item_image']['type'];
                        $messageType = "error";
                        break;
                    }
                    
                    // Check file size
                    if ($_FILES['item_image']['size'] > $max_size) {
                        $message = "Error: Image too large. Maximum size is 16MB. Your file: " . round($_FILES['item_image']['size'] / 1024 / 1024, 2) . "MB";
                        $messageType = "error";
                        break;
                    }
                    
                    // Try to compress and resize image
                    $image_data = compressImage($_FILES['item_image']['tmp_name'], 80);
                    
                    if ($image_data === false || empty($image_data)) {
                        // If compression fails, try to use original file
                        $image_data = file_get_contents($_FILES['item_image']['tmp_name']);
                        
                        if ($image_data === false || empty($image_data)) {
                            $message = "Error: Failed to process image. Please try a different image.";
                            $messageType = "error";
                            break;
                        }
                        $message = "Warning: Used original image (compression failed). Size: " . round(strlen($image_data) / 1024, 2) . "KB";
                        $messageType = "success";
                    } else {
                        $message = "Image processed successfully. Compressed size: " . round(strlen($image_data) / 1024, 2) . "KB";
                        $messageType = "success";
                    }
                    
                    // Additional check for image data size
                    if (strlen($image_data) > 16 * 1024 * 1024) {
                        $message = "Error: Processed image is still too large. Please use a smaller image.";
                        $messageType = "error";
                        break;
                    }
                } else if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                    // Handle upload errors
                    $upload_errors = [
                        UPLOAD_ERR_INI_SIZE => 'File too large (PHP ini limit)',
                        UPLOAD_ERR_FORM_SIZE => 'File too large (form limit)',
                        UPLOAD_ERR_PARTIAL => 'File partially uploaded',
                        UPLOAD_ERR_NO_TMP_DIR => 'No temp directory',
                        UPLOAD_ERR_CANT_WRITE => 'Cannot write file',
                        UPLOAD_ERR_EXTENSION => 'PHP extension stopped upload'
                    ];
                    $message = "Upload error: " . ($upload_errors[$_FILES['item_image']['error']] ?? 'Unknown error');
                    $messageType = "error";
                    break;
                }
                
                $sql = "INSERT INTO found_items (item_name, description, location_found, date_found, category, color, brand, found_by, additional_notes, image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('ssssssssss', $item_name, $description, $location_found, $date_found, $category, $color, $brand, $found_by, $additional_notes, $image_data);
                
                if ($stmt->execute()) {
                    if ($image_data) {
                        $message = "Item added successfully with image! Image size: " . round(strlen($image_data) / 1024, 2) . "KB";
                    } else {
                        $message = "Item added successfully (no image)!";
                    }
                    $messageType = "success";
                } else {
                    $message = "Error adding item: " . $stmt->error;
                    $messageType = "error";
                }
                $stmt->close();
                break;
                
            case 'update_status':
                $item_id = (int)$_POST['item_id'];
                $new_status = $_POST['new_status'];
                
                $sql = "UPDATE found_items SET status = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('si', $new_status, $item_id);
                
                if ($stmt->execute()) {
                    $message = "Item status updated successfully!";
                    $messageType = "success";
                } else {
                    $message = "Error updating status: " . $stmt->error;
                    $messageType = "error";
                }
                $stmt->close();
                break;
                
            case 'delete_item':
                $item_id = (int)$_POST['item_id'];
                
                $sql = "DELETE FROM found_items WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('i', $item_id);
                
                if ($stmt->execute()) {
                    $message = "Item deleted successfully!";
                    $messageType = "success";
                } else {
                    $message = "Error deleting item: " . $stmt->error;
                    $messageType = "error";
                }
                $stmt->close();
                break;
        }
    }
}

// Get all found items
$items_query = "SELECT * FROM found_items ORDER BY created_at DESC";
$items_result = $conn->query($items_query);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="railway-styles.css" rel="stylesheet">
    <title>Manage Found Items - Railway System</title>
    <style>
        .items-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }

        .item-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            padding: 1.5rem;
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
            background-color: #f8f9fa;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            overflow: hidden;
        }

        .item-image img {
            max-width: 100%;
            max-height: 100%;
            object-fit: cover;
            border-radius: 12px;
        }

        .item-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .item-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
            margin: 0;
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

        .item-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
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
            margin: 3% auto;
            padding: 2rem;
            border-radius: 20px;
            width: 90%;
            max-width: 600px;
            max-height: 85vh;
            overflow-y: auto;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 40px rgba(0, 0, 0, 0.1);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f8f9fa;
        }

        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            border: none;
            background: none;
            padding: 0;
            line-height: 1;
        }

        .close:hover {
            color: #000;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        @media (max-width: 768px) {
            .header-section {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .items-grid {
                grid-template-columns: 1fr;
            }
            
            .item-actions {
                flex-direction: column;
            }
            
            .modal-content {
                margin: 10% auto;
                padding: 1.5rem;
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
                    <p>Items Management</p>
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

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?>">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="header-section">
            <h2 class="section-title">
                <i class="fas fa-boxes"></i>
                Manage Found Items
            </h2>
            <button class="btn btn-primary" onclick="openModal()">
                <i class="fas fa-plus"></i> Add New Item
            </button>
        </div>

        <!-- Items Grid -->
        <?php if ($items_result && $items_result->num_rows > 0): ?>
            <div class="items-grid">
                <?php while ($item = $items_result->fetch_assoc()): ?>
                    <div class="item-card">
                        <div class="item-image">
                            <?php if (!empty($item['image']) && strlen($item['image']) > 0): ?>
                                <?php
                                $imageData = base64_encode($item['image']);
                                $imageSrc = 'data:image/jpeg;base64,' . $imageData;
                                ?>
                                <img src="<?php echo $imageSrc; ?>" 
                                     alt="<?php echo htmlspecialchars($item['item_name']); ?>"
                                     style="max-width: 100%; max-height: 100%; object-fit: cover;"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <div style="display: none; align-items: center; justify-content: center; height: 100%;">
                                    <i class="fas fa-exclamation-triangle" style="font-size: 2rem; color: #dc3545;"></i>
                                    <span style="margin-left: 10px; color: #dc3545;">Image Error</span>
                                </div>
                            <?php else: ?>
                                <i class="fas fa-image" style="font-size: 3rem; color: #ddd;"></i>
                            <?php endif; ?>
                        </div>

                        <div class="item-header">
                            <h3 class="item-title"><?php echo htmlspecialchars($item['item_name']); ?></h3>
                            <span class="activity-status status-<?php echo $item['status']; ?>">
                                <?php echo $item['status']; ?>
                            </span>
                        </div>

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
                                <span><?php echo htmlspecialchars($item['category']); ?></span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-user"></i>
                                <span><?php echo htmlspecialchars($item['found_by'] ?? 'Unknown'); ?></span>
                            </div>
                        </div>

                        <div class="item-actions">
                            <button class="btn btn-info btn-sm" onclick="toggleStatus(<?php echo $item['id']; ?>, '<?php echo $item['status']; ?>')">
                                <i class="fas fa-exchange-alt"></i> Toggle Status
                            </button>
                            <button class="btn btn-danger btn-sm" onclick="deleteItem(<?php echo $item['id']; ?>)">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="card text-center p-5">
                <i class="fas fa-box-open" style="font-size: 3rem; color: #ddd; margin-bottom: 1rem;"></i>
                <h3>No Found Items</h3>
                <p class="text-muted">Start by adding found items to the system.</p>
            </div>
        <?php endif; ?>
    </main>

    <!-- Add Item Modal -->
    <div id="addItemModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-plus-circle"></i> Add New Found Item</h3>
                <button class="close" onclick="closeModal()">&times;</button>
            </div>

            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add_item">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="item_name" class="form-label">Item Name *</label>
                        <input type="text" id="item_name" name="item_name" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="category" class="form-label">Category</label>
                        <select id="category" name="category" class="form-control">
                            <option value="electronics">Electronics</option>
                            <option value="clothing">Clothing</option>
                            <option value="documents">Documents</option>
                            <option value="jewelry">Jewelry</option>
                            <option value="bags">Bags</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="color" class="form-label">Color</label>
                        <input type="text" id="color" name="color" class="form-control" placeholder="e.g. Black, Blue">
                    </div>

                    <div class="form-group">
                        <label for="brand" class="form-label">Brand</label>
                        <input type="text" id="brand" name="brand" class="form-control" placeholder="e.g. Apple, Nike">
                    </div>

                    <div class="form-group">
                        <label for="location_found" class="form-label">Location Found *</label>
                        <input type="text" id="location_found" name="location_found" class="form-control" required 
                               placeholder="e.g. Platform 3, KL Sentral">
                    </div>

                    <div class="form-group">
                        <label for="date_found" class="form-label">Date Found *</label>
                        <input type="date" id="date_found" name="date_found" class="form-control" required 
                               value="<?php echo date('Y-m-d'); ?>">
                    </div>

                    <div class="form-group">
                        <label for="found_by" class="form-label">Found By</label>
                        <input type="text" id="found_by" name="found_by" class="form-control" 
                               placeholder="e.g. Security Officer Ahmad">
                    </div>

                    <div class="form-group">
                        <label for="item_image" class="form-label">Item Image</label>
                        <input type="file" id="item_image" name="item_image" class="form-control" 
                               accept="image/jpeg,image/png,image/gif">
                    </div>

                    <div class="form-group full-width">
                        <label for="description" class="form-label">Description *</label>
                        <textarea id="description" name="description" class="form-control" required 
                                  placeholder="Detailed description of the item..." rows="4"></textarea>
                    </div>

                    <div class="form-group full-width">
                        <label for="additional_notes" class="form-label">Additional Notes</label>
                        <textarea id="additional_notes" name="additional_notes" class="form-control" 
                                  placeholder="Any additional information..." rows="3"></textarea>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">
                    <i class="fas fa-plus"></i> Add Item
                </button>
            </form>
        </div>
    </div>

    <footer class="mt-5 text-center p-3" style="background: rgba(255,255,255,0.1); color: #666;">
        <p>&copy; 2025 Railway Feedback & Lost and Found. All rights reserved.</p>
    </footer>

    <script>
        function openModal() {
            document.getElementById('addItemModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('addItemModal').style.display = 'none';
            // Reset form
            document.querySelector('#addItemModal form').reset();
        }

        function toggleStatus(itemId, currentStatus) {
            const newStatus = currentStatus === 'unclaimed' ? 'claimed' : 'unclaimed';
            
            if (confirm(`Are you sure you want to mark this item as ${newStatus}?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'update_status';
                
                const itemIdInput = document.createElement('input');
                itemIdInput.type = 'hidden';
                itemIdInput.name = 'item_id';
                itemIdInput.value = itemId;
                
                const statusInput = document.createElement('input');
                statusInput.type = 'hidden';
                statusInput.name = 'new_status';
                statusInput.value = newStatus;
                
                form.appendChild(actionInput);
                form.appendChild(itemIdInput);
                form.appendChild(statusInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }

        function deleteItem(itemId) {
            if (confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete_item';
                
                const itemIdInput = document.createElement('input');
                itemIdInput.type = 'hidden';
                itemIdInput.name = 'item_id';
                itemIdInput.value = itemId;
                
                form.appendChild(actionInput);
                form.appendChild(itemIdInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('addItemModal');
            if (event.target === modal) {
                closeModal();
            }
        }

        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeModal();
            }
        });
    </script>
</body>
</html>