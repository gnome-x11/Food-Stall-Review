<?php
session_start();
require_once('../config/db_config.php');


if (!isset($_SESSION["stall_id"])) {
    header("Location:../stall_admin/stall_selection.php");
    exit();
}

$stall_id = $_SESSION["stall_id"];

// Fetch stall details including account info
$stmt = $conn->prepare("SELECT stall_name, description, username, password FROM food_stalls WHERE id = ?");
$stmt->bind_param("i", $stall_id);
$stmt->execute();
$result = $stmt->get_result();
$stall = $result->fetch_assoc();
$stmt->close();

// Generate default username (stall name without spaces)
$default_username = strtolower(str_replace(' ', '', $stall["stall_name"]));
$default_password = "admin123";

// Handle form submission
$error_message = "";
$success_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["update_account"])) {
    $new_username = trim($_POST["username"]);
    $current_password = $_POST["current_password"];
    $new_password = $_POST["new_password"];
    $confirm_password = $_POST["confirm_password"];

    // Validate inputs
    if (empty($new_username)) {
        $error_message = "Username cannot be empty";
    } elseif (!empty($new_password) && $new_password !== $confirm_password) {
        $error_message = "New passwords do not match";
    } else {
        // Verify current password
        if (!password_verify($current_password, $stall["password"])) {
            $error_message = "Current password is incorrect";
        } else {
            // Prepare update query
            $update_fields = ["username = ?"];
            $params = [$new_username];
            $param_types = "s";

            if (!empty($new_password)) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_fields[] = "password = ?";
                $params[] = $hashed_password;
                $param_types .= "s";
            }

            $params[] = $stall_id;
            $param_types .= "i";

            $sql = "UPDATE food_stalls SET " . implode(", ", $update_fields) . " WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($param_types, ...$params);

            if ($stmt->execute()) {
                $success_message = "Account updated successfully!";
                // Refresh stall data
                $stmt = $conn->prepare("SELECT stall_name, description, username, password FROM food_stalls WHERE id = ?");
                $stmt->bind_param("i", $stall_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $stall = $result->fetch_assoc();
                $stmt->close();
            } else {
                $error_message = "Error updating account: " . $conn->error;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Management - <?= htmlspecialchars($stall["stall_name"]) ?></title>
    <link rel="shortcut icon" type="image/x-icon" href="../assets/img/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../components/index.css">


</head>

<body>
    <!-- Include Sidebar -->
    <?php include('../includes/sidebar.php'); ?>
    <!-- Include Topbar -->
    <?php include('../includes/topbar.php'); ?>

    <div class="main-content">
        <div class="dashboard-card">
            <div class="dashboard-header">
                <h2 class="dashboard-title"><i class="fas fa-user-cog me-2"></i> Account Management</h2>
            </div>

            <div class="p-4">
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger"><?= $error_message ?></div>
                <?php endif; ?>

                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success"><?= $success_message ?></div>
                <?php endif; ?>

                <div class="account-info-card mb-4">
                    <h4><i class="fas fa-info-circle me-2"></i>Account Information</h4>
                    <hr>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="info-label">Stall Name</label>
                                <p class="info-value"><?= htmlspecialchars($stall["stall_name"]) ?></p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="info-label">Default Username</label>
                                <p class="info-value"><?= $default_username ?></p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="info-label">Default Password</label>
                                <p class="info-value"><?= $default_password ?></p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="info-label">Current Username</label>
                                <p class="info-value"><?= htmlspecialchars($stall["username"]) ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="account-info-card">
                    <h4><i class="fas fa-edit me-2"></i>Update Account</h4>
                    <hr>
                    <form method="POST" action="account_management.php">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="username">New Username</label>
                                    <input type="text" class="form-control" id="username" name="username"
                                        value="<?= htmlspecialchars($stall["username"]) ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="new_password">New Password (leave blank to keep current)</label>
                                    <div class="position-relative">
                                        <input type="password" class="form-control" id="new_password"
                                            name="new_password">
                                        <i class="fas fa-eye password-toggle"
                                            onclick="togglePassword('new_password')"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="current_password">Current Password</label>
                                    <div class="position-relative">
                                        <input type="password" class="form-control" id="current_password"
                                            name="current_password" required>
                                        <i class="fas fa-eye password-toggle"
                                            onclick="togglePassword('current_password')"></i>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="confirm_password">Confirm New Password</label>
                                    <div class="position-relative">
                                        <input type="password" class="form-control" id="confirm_password"
                                            name="confirm_password">
                                        <i class="fas fa-eye password-toggle"
                                            onclick="togglePassword('confirm_password')"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <button type="submit" name="update_account" class="btn btn-update mt-3">
                            <i class="fas fa-save me-2"></i>Update Account
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.nextElementSibling;

            if (input.type === "password") {
                input.type = "text";
                icon.classList.remove("fa-eye");
                icon.classList.add("fa-eye-slash");
            } else {
                input.type = "password";
                icon.classList.remove("fa-eye-slash");
                icon.classList.add("fa-eye");
            }
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>