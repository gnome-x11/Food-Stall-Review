<?php

error_reporting(E_ALL);
ini_set("display_errors", 1);

session_start();
require_once "../config/db_config.php";

// Fetch food stalls from database
$stalls = [];
$query = "SELECT id, stall_name, description, imagePath FROM food_stalls"; //food_stalls ay table
$result = $conn->query($query);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $row["logo"] = "../stall_img" . $row["imagePath"] . ".jpg"; // Adjust path as needed
        $stalls[] = $row;
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#FF6B6B">
    <title>PLMUN FOOD STALL Reviews - Select Stall</title>
    <link rel="shortcut icon" type="image/x-icon" href="../assets/img/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #FF6B6B;
            --secondary-color: #4ECDC4;
            --dark-color: #2D3436;
            --light-color: #F9F9F9;
            --header-primary: #00674F

        }

        ::-webkit-scrollbar {
            display: none;
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

        .stall-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.12);
        }

        .card-title {
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 0.75rem;
        }

        .card-text {
            color: #666;
            font-size: 0.9rem;
            line-height: 1.5;
        }

        .hero-section {
            text-align: center;
            padding: 2rem 0;
            background: var(--primary-color);
            color: white;
            margin-bottom: 1rem;
            clip-path: polygon(0 0, 100% 0, 100% 90%, 0 100%);
        }

        .logo-container {
            height: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            overflow: hidden;
            background: rgba(0, 0, 0, 0.03);
        }

        .stall-logo {

            max-width: 100%;
            aspect-ratio: 16 / 9;
            object-fit: contain;
            transition: transform 0.3s ease;
        }

        .stall-card:hover .stall-logo {
            transform: scale(1.05);
        }

        .card-content {
            padding: 1.5rem;
            text-decoration: none !important;
            color: inherit;
        }

        a.card-link {
            text-decoration: none;
            color: inherit;
        }
    </style>
</head>

<body>
    <div class="hero-section">
        <div class="container">
            <h1 class="hero-title">PLMUN Food Stalls Review</h1>
            <p class="hero-subtitle">Manager Portal</p>
        </div>
    </div>

    <div class="container py-5">
        <div class="row g-4">
            <?php foreach ($stalls as $stall):
            $imagePath = $stall["imagePath"] ?? '';
            $filename = (!empty($imagePath) && file_exists("../stall_img/" . $imagePath)) ? "../stall_img/" . $imagePath : "../assets/img/default.jpg";
 ?>

                <a href="login.php?stall_id=<?= $stall[
                    "id"
                ] ?>" class="col-md-6 col-lg-4 card-link">
                    <div class="card stall-card h-100">
                        <div class="logo-container">
                            <img src="<?= $filename ?>" alt="<?= htmlspecialchars($stall["stall_name"]) ?>" class="stall-logo">
                        </div>
                        <div class="card-content">
                            <h5 class="card-title"><?= htmlspecialchars(
                                $stall["stall_name"]
                            ) ?></h5>
                            <p class="card-text"><?= htmlspecialchars(
                                $stall["description"]
                            ) ?></p>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>



    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
