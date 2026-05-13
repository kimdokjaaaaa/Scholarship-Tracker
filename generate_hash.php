<?php
// Generate proper bcrypt hash for password123
$password = 'password123';
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
echo "Generated hash for 'password123':\n";
echo $hash . "\n\n";

// Verify it works
$verify = password_verify($password, $hash);
echo "Verification result: " . ($verify ? 'TRUE' : 'FALSE') . "\n";

// Test with another password to ensure it fails
$verify2 = password_verify('wrongpassword', $hash);
echo "Verification with wrong password: " . ($verify2 ? 'TRUE' : 'FALSE') . "\n";
?>
