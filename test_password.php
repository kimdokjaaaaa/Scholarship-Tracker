<?php
$hash = '$2y$10$Dgm1QM0d.h/1lyJx2Vyd2.WvkCxVUhb/Bc.Fw2R/94blOQYe1zBNa';
$password = 'password123';
$result = password_verify($password, $hash);
echo "Password verify result: " . ($result ? 'TRUE' : 'FALSE') . "\n";
echo "Hash length: " . strlen($hash) . "\n";
echo "Hash: " . $hash . "\n";
?>
