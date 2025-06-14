<?php
// Replace 'admin_password' with the desired password for the admin account.
$plainPassword = 'aDmin123@';

// Generate the hashed password.
$hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);

// Display the hashed password.
echo "Hashed password: " . $hashedPassword;
?>
