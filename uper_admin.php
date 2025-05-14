<?php
require_once "config/db_config.php";

// Define username and password
$username = "admin";
$plainPassword = "admin123";

// Hash the password
$hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);

// Prepare SQL query
$stmt = $conn->prepare("INSERT INTO super_admins (username, password, created_at) VALUES (?, ?, NOW())");
$stmt->bind_param("ss", $username, $hashedPassword);

// Execute
if ($stmt->execute()) {
    echo "Super admin account created successfully.";
} else {
    echo "Error: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>
