<?php
include 'db_connect.php';

$username = 'admin';
$password = 'Madrasah2026';
$full_name = 'System Administrator';
$role = 'Admin';

// Generate a secure hash for Madrasah2026
$password_hash = password_hash($password, PASSWORD_DEFAULT);

// Insert or update the admin user
$stmt = $conn->prepare("INSERT INTO users (username, password_hash, full_name, role) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE password_hash = ?");
$stmt->bind_param("sssss", $username, $password_hash, $full_name, $role, $password_hash);

if ($stmt->execute()) {
    echo "Admin user successfully created/updated with password: <strong>Madrasah2026</strong>";
} else {
    echo "Error: " . $stmt->error;
}
$stmt->close();
?>