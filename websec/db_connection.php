<?php
// db_connection.php - Secure Database Connection with Error Handling

function getDatabaseConnection() {
    // Database configuration
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "railway_pro";
    
    try {
        // Create connection with additional security options
        $conn = new mysqli($servername, $username, $password, $dbname);
        
        // Check connection
        if ($conn->connect_error) {
            // Log the error securely (don't expose to user)
            error_log("Database connection failed: " . $conn->connect_error);
            
            // Show generic error to user
            die("Database connection failed. Please try again later or contact support.");
        }
        
        // Set charset to prevent character set confusion attacks
        $conn->set_charset("utf8mb4");
        
        // Set SQL mode for strict validation
        $conn->query("SET sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE'");
        
        // Disable autocommit for better transaction control
        //$conn->autocommit(FALSE);
        
        return $conn;
        
    } catch (Exception $e) {
        // Log the error securely
        error_log("Database connection exception: " . $e->getMessage());
        
        // Show generic error to user
        die("Database connection failed. Please try again later or contact support.");
    }
}

/**
 * Execute a prepared statement safely with proper error handling
 */
function executePreparedStatement($conn, $sql, $types = '', $params = []) {
    try {
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            error_log("Prepare failed: " . $conn->error);
            return ['success' => false, 'error' => 'Database prepare error'];
        }
        
        if (!empty($types) && !empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            $stmt->close();
            return ['success' => true, 'result' => $result];
        } else {
            error_log("Execute failed: " . $stmt->error);
            $stmt->close();
            return ['success' => false, 'error' => 'Database execution error'];
        }
        
    } catch (Exception $e) {
        error_log("Database operation exception: " . $e->getMessage());
        return ['success' => false, 'error' => 'Database operation failed'];
    }
}

/**
 * Safely close database connection
 */
function closeDatabaseConnection($conn) {
    if ($conn && !$conn->connect_error) {
        $conn->close();
    }
}

/**
 * Begin database transaction
 */
function beginTransaction($conn) {
    try {
        return $conn->begin_transaction();
    } catch (Exception $e) {
        error_log("Transaction begin failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Commit database transaction
 */
function commitTransaction($conn) {
    try {
        return $conn->commit();
    } catch (Exception $e) {
        error_log("Transaction commit failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Rollback database transaction
 */
function rollbackTransaction($conn) {
    try {
        return $conn->rollback();
    } catch (Exception $e) {
        error_log("Transaction rollback failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if required database tables exist
 */
function checkDatabaseTables() {
    $conn = getDatabaseConnection();
    
    $requiredTables = [
        'users',
        'feedback',
        'found_items',
        'lost_items',
        'claims',
        'password_resets',
        'verification_attempts'
    ];
    
    $missingTables = [];
    
    foreach ($requiredTables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result->num_rows === 0) {
            $missingTables[] = $table;
        }
    }
    
    closeDatabaseConnection($conn);
    
    if (!empty($missingTables)) {
        error_log("Missing database tables: " . implode(', ', $missingTables));
        return ['valid' => false, 'missing_tables' => $missingTables];
    }
    
    return ['valid' => true];
}

/**
 * Create database tables if they don't exist (for setup)
 */
function createDatabaseTables() {
    $conn = getDatabaseConnection();
    
    // Users table with security enhancements
    $userTableSQL = "
    CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        first_name VARCHAR(100) NOT NULL,
        last_name VARCHAR(100) NOT NULL,
        email VARCHAR(255) UNIQUE NOT NULL,
        id_number VARCHAR(12) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin', 'user') DEFAULT 'user',
        is_active BOOLEAN DEFAULT TRUE,
        contact_number VARCHAR(20),
        address TEXT,
        last_login TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_username (username),
        INDEX idx_email (email),
        INDEX idx_id_number (id_number),
        INDEX idx_role (role),
        INDEX idx_active (is_active)
    ) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    // Password resets table
    $passwordResetSQL = "
    CREATE TABLE IF NOT EXISTS password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token VARCHAR(255) NOT NULL,
        expiry TIMESTAMP NOT NULL,
        is_used BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_token (token),
        INDEX idx_expiry (expiry),
        INDEX idx_user_id (user_id)
    ) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    // Verification attempts table for 2FA
    $verificationSQL = "
    CREATE TABLE IF NOT EXISTS verification_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        verification_code VARCHAR(10) NOT NULL,
        expiry TIMESTAMP NOT NULL,
        is_used BOOLEAN DEFAULT FALSE,
        ip_address VARCHAR(45),
        user_agent TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user_id (user_id),
        INDEX idx_code (verification_code),
        INDEX idx_expiry (expiry)
    ) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    // Feedback table with enhanced security
    $feedbackSQL = "
    CREATE TABLE IF NOT EXISTS feedback (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        train_number VARCHAR(20),
        route VARCHAR(255),
        travel_date DATE,
        feedback_type ENUM('complaint', 'suggestion', 'compliment', 'general') NOT NULL,
        category ENUM('cleanliness', 'punctuality', 'staff_behavior', 'facilities', 'safety', 'ticketing', 'other') NOT NULL,
        subject VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        rating TINYINT CHECK (rating >= 1 AND rating <= 5),
        priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
        status ENUM('pending', 'in_progress', 'resolved', 'closed') DEFAULT 'pending',
        admin_response TEXT,
        responded_by INT,
        response_date TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (responded_by) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_user_id (user_id),
        INDEX idx_status (status),
        INDEX idx_category (category),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    // Found items table
    $foundItemsSQL = "
    CREATE TABLE IF NOT EXISTS found_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        item_name VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        category ENUM('electronics', 'clothing', 'documents', 'jewelry', 'bags', 'other') NOT NULL,
        color VARCHAR(50),
        brand VARCHAR(100),
        location_found VARCHAR(255) NOT NULL,
        date_found DATE NOT NULL,
        found_by VARCHAR(255),
        status ENUM('unclaimed', 'claimed') DEFAULT 'unclaimed',
        image LONGBLOB,
        additional_notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_status (status),
        INDEX idx_category (category),
        INDEX idx_date_found (date_found)
    ) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    // Lost items table
    $lostItemsSQL = "
    CREATE TABLE IF NOT EXISTS lost_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        item_name VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        category ENUM('electronics', 'clothing', 'documents', 'jewelry', 'bags', 'other') NOT NULL,
        color VARCHAR(50),
        brand VARCHAR(100),
        lost_location VARCHAR(255) NOT NULL,
        lost_date DATE NOT NULL,
        train_number VARCHAR(20),
        contact_phone VARCHAR(20) NOT NULL,
        contact_email VARCHAR(255) NOT NULL,
        reward_offered DECIMAL(10,2) DEFAULT 0.00,
        status ENUM('active', 'found', 'closed') DEFAULT 'active',
        reference_number VARCHAR(50),
        additional_notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user_id (user_id),
        INDEX idx_status (status),
        INDEX idx_category (category),
        INDEX idx_lost_date (lost_date)
    ) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    // Claims table
    $claimsSQL = "
    CREATE TABLE IF NOT EXISTS claims (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        item_id INT NOT NULL,
        name VARCHAR(255) NOT NULL,
        phone VARCHAR(20) NOT NULL,
        email VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        action ENUM('pending', 'approved', 'rejected', 'under_review') DEFAULT 'pending',
        claim_reference VARCHAR(50),
        admin_notes TEXT,
        processed_by INT,
        processed_date TIMESTAMP NULL,
        submission_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (item_id) REFERENCES found_items(id) ON DELETE CASCADE,
        FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_user_id (user_id),
        INDEX idx_item_id (item_id),
        INDEX idx_action (action),
        INDEX idx_submission_date (submission_date)
    ) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $tables = [
        'users' => $userTableSQL,
        'password_resets' => $passwordResetSQL,
        'verification_attempts' => $verificationSQL,
        'feedback' => $feedbackSQL,
        'found_items' => $foundItemsSQL,
        'lost_items' => $lostItemsSQL,
        'claims' => $claimsSQL
    ];
    
    $created = [];
    $errors = [];
    
    foreach ($tables as $tableName => $sql) {
        if ($conn->query($sql)) {
            $created[] = $tableName;
        } else {
            $errors[] = "Error creating $tableName: " . $conn->error;
            error_log("Error creating table $tableName: " . $conn->error);
        }
    }
    
    closeDatabaseConnection($conn);
    
    return [
        'success' => empty($errors),
        'created' => $created,
        'errors' => $errors
    ];
}
?>