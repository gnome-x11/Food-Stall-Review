<?php
require_once 'db_config.php';

// The default password you want to set
$defaultPassword = 'admin123';

try {
    // Hash the default password
    $hashedPassword = password_hash($defaultPassword, PASSWORD_BCRYPT);

    // Update all records in the food_stalls table
    $stmt = $conn->prepare("UPDATE food_stalls SET password = ?");
    $stmt->bind_param("s", $hashedPassword);

    if ($stmt->execute()) {
        echo "Successfully updated all passwords to default (admin123)";
    } else {
        echo "Error updating passwords: " . $conn->error;
    }

    $stmt->close();
} catch (Exception $e) {
    echo "Database error: " . $e->getMessage();
}

$conn->close();
?>