<?php
include 'db_connection.php';

try {
    $conn = getDatabaseConnection();
    echo "<h2>✅ Database Connection Test</h2>";
    
    // Check if admin user exists
    $sql = "SELECT id, username, email, role FROM users WHERE username = 'admin'";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $admin = $result->fetch_assoc();
        echo "✅ Admin user found!<br>";
        echo "ID: " . $admin['id'] . "<br>";
        echo "Username: " . $admin['username'] . "<br>";
        echo "Email: " . $admin['email'] . "<br>";
        echo "Role: " . $admin['role'] . "<br><br>";
        
        // Test password verification
        $stored_password = '$2y$10$T5kbqKSFaBr2Ys246vKY3OuZp6zBzfqDqGDYiVnq8YrFljwKWF5p.';
        $test_password = 'aDmin123@';
        
        if (password_verify($test_password, $stored_password)) {
            echo "✅ Password verification works!<br>";
        } else {
            echo "❌ Password verification failed!<br>";
        }
        
    } else {
        echo "❌ Admin user not found!<br>";
    }
    
    // Test other tables
    $tables = ['password_resets', 'verification_attempts', 'found_items', 'claims'];
    echo "<h3>Table Status:</h3>";
    foreach ($tables as $table) {
        $result = $conn->query("SELECT COUNT(*) as count FROM $table");
        $count = $result->fetch_assoc()['count'];
        echo "✅ $table: $count records<br>";
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>