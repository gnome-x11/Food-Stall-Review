<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once "../config/db_config.php";

if (!isset($_SESSION["super_admin_logged_in"])) {
    header("Location: superadmin_login.php");
    exit();
}

// Handle CRUD operations
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST["create_stall"])) {
        $stall_name = $_POST["stall_name"];
        $description = $_POST["description"];
        $username = $_POST["username"];
        $password = password_hash($_POST["password"], PASSWORD_DEFAULT);
        $filename = null;

        // Handle image upload if a file was uploaded
        if (isset($_FILES['imagePath']) && $_FILES['imagePath']['tmp_name'] != '') {
            $stall_img = '../stall_img/';
            $filename = time() . '_' . basename($_FILES['imagePath']['name']);
            $filepath = $stall_img . $filename;

            if (!move_uploaded_file($_FILES['imagePath']['tmp_name'], $filepath)) {
                die(json_encode(["status" => "error", "message" => "Failed to upload image."]));
            }
        }

        // Prepare and execute insert statement
        $stmt = $conn->prepare("INSERT INTO food_stalls (stall_name, description, username, password, imagePath) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $stall_name, $description, $username, $password, $filename);
        $stmt->execute();
        $new_stall_id = $stmt->insert_id;

        header("Location: superadmin_dashboard.php");
        exit();



    } elseif (isset($_POST["delete_stall"])) {
        // Handle delete
        $stall_id = $_POST["delete_id"];
        $stmt = $conn->prepare("DELETE FROM food_stalls WHERE id = ?");
        $stmt->bind_param("i", $stall_id);
        $stmt->execute();

        $image_path = "../stall_img/" . $stall_id . ".jpg";
        if (file_exists($image_path)) {
            unlink($image_path);
        }

        header("Location: superadmin_dashboard.php");
        exit();


    } elseif (isset($_POST["edit_stall"])) {
        // Handle edit
        $stall_id = $_POST["stall_id"];
        $stall_name = $_POST["stall_name"];
        $description = $_POST["description"];
        $username = $_POST["username"];
        $password = !empty($_POST["password"]) ? password_hash($_POST["password"], PASSWORD_DEFAULT) : null;

        $stmt = $conn->prepare("SELECT imagePath FROM food_stalls WHERE id = ?");
        $stmt->bind_param("i", $stall_id);
        $stmt->execute();
        $stmt->bind_result($current_image);
        $stmt->fetch();
        $stmt->close();

        $filename = $current_image;

        // If a new file is uploaded, process it
         if (isset($_FILES['imagePath']) && $_FILES['imagePath']['tmp_name'] !== '') {
             $stall_img = '../stall_img/';
             $filename = time() . '_' . basename($_FILES['imagePath']['name']);
             $filepath = $stall_img . $filename;

             if (move_uploaded_file($_FILES['imagePath']['tmp_name'], $filepath)) {
                 // Optional: Delete old image file
                 if (!empty($current_image) && file_exists('../stall_img/' . $current_image)) {
                     unlink('../stall_img/' . $current_image);
                 }
             } else {
                 die(json_encode(["status" => "error", "message" => "Image upload failed."]));
             }
         }

        $sql = "UPDATE food_stalls SET stall_name = ?, description = ?, username = ?, imagePath = ?";
        $params = [$stall_name, $description, $username, $filename];
        $types = "ssss";

        if ($password) {
            $sql .= ", password = ?";
            $params[] = $password;
            $types .= "s";
        }

        $sql .= " WHERE id = ?";
        $params[] = $stall_id;
        $types .= "i";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();


        header("Location: superadmin_dashboard.php");
        exit();
    }
}

// Fetch all stalls
$stalls = $conn->query("SELECT * FROM food_stalls");
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="theme-color" content="#FF6B6B">
        <title>Super Admin Dashboard</title>
        <link rel="shortcut icon" type="image/x-icon" href="../assets/img/favicon.ico">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <style>
            /* Include all the CSS variables and styles from the stall selection page */
            :root {
                --primary-color: #FF6B6B;
                --secondary-color: #4ECDC4;
                --dark-color: #2D3436;
                --light-color: #F9F9F9;
                --header-primary: #00674F;
            }

            body {
                font-family: 'Poppins', sans-serif;
                background: var(--dark-color);
                color: var(--dark-color);
            }

            .stall-card {
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                border: none;
                border-radius: 15px;
                overflow: hidden;
                box-shadow: 0 10px 20px rgba(0, 0, 0, 0.08);
                background: white;
                position: relative;
                cursor: pointer;
            }

            .stall-card::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                height: 4px;
                background: var(--header-primary);
            }

            .logo-container {
                height: 200px;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 1rem;
                overflow: hidden;
                background: rgba(0, 0, 0, 0.03);
            }

            .stall-logo {
                max-height: 100%;
                max-width: 100%;
                object-fit: cover;
                transition: transform 0.3s ease;
            }

            .management-actions {
                position: absolute;
                top: 10px;
                right: 10px;
                z-index: 2;
            }

            .btn-custom {
                padding: 0.25rem 0.75rem;
                margin: 2px;
                min-width: 70px;
            }
        </style>
    </head>
<body>
    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="text-light">Manage Food Stalls</h2>
            <a href="logout.php" class="btn btn-danger">Logout</a>
        </div>

        <!-- Create Stall Form -->
        <div class="card mb-4 stall-card">
            <div class="card-body">
                <h5 class="card-title">Add New Stall</h5>
                <form method="POST" enctype="multipart/form-data">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <input type="text" name="stall_name" class="form-control" placeholder="Stall Name" required>
                        </div>
                        <div class="col-md-3">
                            <input type="text" name="description" class="form-control" placeholder="Description">
                        </div>
                        <div class="col-md-2">
                            <input type="text" name="username" class="form-control" placeholder="Username" required>
                        </div>
                        <div class="col-md-2">
                            <input type="password" name="password" class="form-control" placeholder="Password" required>
                        </div>
                        <div class="col-md-2">
                            <input type="file" name="imagePath">
                        </div>
                        <div class="col-md-12">
                            <button type="submit" name="create_stall" class="btn btn-primary">Add Stall</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Stalls Grid -->
        <div class="row g-4">
            <?php while ($stall = $stalls->fetch_assoc()):
            $imagePath = $stall["imagePath"] ?? '';
            $filename = (!empty($imagePath) && file_exists("../stall_img/" . $imagePath)) ? "../stall_img/" . $imagePath : "../assets/img/default.jpg";


            ?>
            <div class="col-md-6 col-lg-4">
                <div class="card stall-card h-100">
                    <div class="management-actions">
                        <button class="btn btn-warning btn-custom edit-btn"
                            data-id="<?= $stall['id'] ?>"
                            data-name="<?= htmlspecialchars($stall['stall_name']) ?>"
                            data-description="<?= htmlspecialchars($stall['description']) ?>"
                            data-username="<?= htmlspecialchars($stall['username']) ?>">
                            Edit
                        </button>
                        <button class="btn btn-danger btn-custom delete-btn" data-id="<?= $stall['id'] ?>">Delete</button>
                    </div>
                    <div class="logo-container">
                        <img src="<?= $filename ?>" alt="<?= htmlspecialchars($stall["stall_name"]) ?>" class="stall-logo">
                    </div>
                    <div class="card-body">
                        <h5 class="card-title"><?= htmlspecialchars($stall["stall_name"]) ?></h5>
                        <p class="card-text"><?= htmlspecialchars($stall["description"]) ?></p>
                        <div class="mt-2">
                            <small class="text-muted">Username: <?= htmlspecialchars($stall["username"]) ?></small>
                        </div>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>

    <!-- Delete Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title">Confirm Delete</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        Are you sure you want to delete this stall?
                        <input type="hidden" name="delete_id" id="delete_id">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="delete_stall" class="btn btn-danger">Delete</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="stall_id" id="edit_stall_id">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Stall</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label>Stall Name</label>
                            <input type="text" name="stall_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Description</label>
                            <input type="text" name="description" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label>Username</label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Password (leave blank to keep current)</label>
                            <input type="password" name="password" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label>Logo</label>
                            <input type="file" name="logo" class="form-control" accept="image/*">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="edit_stall" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    $(document).ready(function() {
        // Delete Modal
        $('.delete-btn').click(function() {
            var stallId = $(this).data('id');
            $('#delete_id').val(stallId);
            $('#deleteModal').modal('show');
        });

        // Edit Modal
        $('.edit-btn').click(function() {
            var stallId = $(this).data('id');
            var stallName = $(this).data('name');
            var description = $(this).data('description');
            var username = $(this).data('username');

            $('#edit_stall_id').val(stallId);
            $('#editModal input[name="stall_name"]').val(stallName);
            $('#editModal input[name="description"]').val(description);
            $('#editModal input[name="username"]').val(username);
            $('#editModal').modal('show');
        });
    });
    </script>
</body>
</html>
