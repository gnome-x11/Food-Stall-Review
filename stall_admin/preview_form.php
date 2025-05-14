<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once('../config/db_config.php');

// Check if form hash is provided
if (!isset($_GET["form"])) {
    die("Form parameter is missing");
}

$form_hash = $_GET["form"];

// Fetch form details
$stmt = $conn->prepare("
    SELECT rf.*, fs.stall_name 
    FROM review_forms rf
    JOIN food_stalls fs ON rf.food_stall_id = fs.id
    WHERE rf.form_hash = ?
");
$stmt->bind_param("s", $form_hash);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Form not found");
}

$form = $result->fetch_assoc();
$stmt->close();


// Decode the form structure
$json_string = $form["form_structure"];
$form_structure = json_decode($json_string, true);

// If first decode returns a string, decode again (for double-encoded JSON)
if (is_string($form_structure)) {
    $form_structure = json_decode($form_structure, true);
}

if (json_last_error() !== JSON_ERROR_NONE) {
    die("Invalid JSON structure: " . json_last_error_msg());
}


// Ensure we have an array
if (!is_array($form_structure)) {
    die("Form structure is not an array");
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preview: <?= htmlspecialchars($form["form_title"]) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #FF6B6B;
            --dark-color: #2D3436;
            --gradient-primary: linear-gradient(135deg, #FF6B6B 0%, #FF8E8E 100%);
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: #2D3436;
            padding: 20px;
        }

        .preview-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 0;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .form-header {
            background: var(--gradient-primary);
            color: white;
            padding: 2rem;
            border-radius: 15px 15px 0 0;
        }

        .form-header h2 {
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .form-section {
            padding: 2rem;
            border-bottom: 1px solid #eee;
        }

        .question-card {
            padding: 1.5rem;
            border: 2px dashed #eee;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            background: #f9f9f9;
        }

        .question-label {
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--dark-color);
        }

        .required-field::after {
            content: " *";
            color: var(--primary-color);
        }

        .star-rating {
            color: #ddd;
            font-size: 1.4rem;
        }

        .star-rating i {
            margin-right: 0.3rem;
        }

        textarea.form-control {
            border: 2px solid #eee;
            border-radius: 8px;
            padding: 1rem;
        }

        .btn-submit {
            background: var(--gradient-primary);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-submit:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }

        .star-rating {
            color: #ffc107;
            /* Gold color for filled stars */
            cursor: pointer;
            /* Show pointer cursor on hover */
            font-size: 1.4rem;
        }

        .fa-regular.fa-star {
            color: #ddd;
            /* Gray color for empty stars */
        }

        .fa-solid.fa-star {
            color: #ffc107;
            /* Gold color for filled stars */
        }

        .rating-question {
            margin-bottom: 10px;
        }

        .rating-question .question-label {
            margin-bottom: 10px;
        }
    </style>
</head>

<body>
    <div class="preview-container">
        <div class="form-header">
            <h2><?= htmlspecialchars($form["form_title"]) ?></h2>
            <p class="mb-0"><?= htmlspecialchars(
                $form["form_description"] ?? ""
            ) ?></p>
        </div>

        <div class="form-content">
            <?php if (empty($form_structure)): ?>
                <div class="alert alert-warning m-4">No questions found in this form</div>
            <?php else: ?>
                <?php foreach ($form_structure as $section): ?>
                    <div class="form-section">
                        <?php if (!empty($section["title"])): ?>
                            <h4 class="mb-3"><?= htmlspecialchars(
                                $section["title"]
                            ) ?></h4>
                        <?php endif; ?>

                        <?php if (!empty($section["description"])): ?>
                            <p class="text-muted mb-4"><?= htmlspecialchars(
                                $section["description"]
                            ) ?></p>
                        <?php endif; ?>

                        <?php foreach ($section["fields"] as $field): ?>
                            <div class="question-card">
                                <?php if ($field["type"] === "dish"): ?>
                                    <div class="dish-rating">
                                        <h5 class="question-label"><?= htmlspecialchars(
                                            $field["label"]
                                        ) ?></h5>

                                        <?php foreach ($field["ratings"] as $rating): ?>
                                            <div class="rating-category mb-3">
                                                <div class="question-label <?= $rating[
                                                    "required"
                                                ] ?? false
                                                    ? "required-field"
                                                    : "" ?>">
                                                    <?= htmlspecialchars(
                                                        $rating["label"]
                                                    ) ?>
                                                </div>
                                                <div class="star-rating">
                                                    <?php for (
                                                        $i = 0;
                                                        $i < 5;
                                                        $i++
                                                    ): ?>
                                                        <i class="fa-regular fa-star"></i>
                                                    <?php endfor; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>

                                <?php elseif (
                                    $field["type"] === "rating"
                                ): ?>
                                    <div class="rating-question">
                                        <div class="question-label <?= $field["required"] ?? false
                                            ? "required-field"
                                            : "" ?>">
                                            <?= htmlspecialchars($field["label"]) ?>
                                        </div>
                                        <div class="star-rating">
                                            <?php for ($i = 0; $i < 5; $i++): ?>
                                                <i class="fa-regular fa-star"></i>
                                            <?php endfor; ?>
                                        </div>
                                    </div>


                                <?php elseif ($field["type"] === "text"): ?>
                                    <div class="text-question">
                                        <div class="question-label <?= $field[
                                            "required"
                                        ] ?? false
                                            ? "required-field"
                                            : "" ?>">
                                            <?= htmlspecialchars(
                                                $field["label"]
                                            ) ?>
                                        </div>
                                        <textarea class="form-control" rows="3" placeholder="Your answer..."></textarea>
                                    </div>

                                <?php elseif (
                                    $field["type"] === "multiple_choice"
                                ): ?>
                                    <div class="choice-question">
                                        <div class="question-label <?= $field[
                                            "required"
                                        ] ?? false
                                            ? "required-field"
                                            : "" ?>">
                                            <?= htmlspecialchars(
                                                $field["label"]
                                            ) ?>
                                        </div>
                                        <?php foreach (explode("\n", $field["options"] ?? "") as $option): ?>
                                            <?php if (!empty(trim($option))): ?>
                                                <div class="form-check mb-2">
                                                    <input class="form-check-input" type="radio" name="choice_<?= $field[
                                                        "id"
                                                    ] ?? "" ?>" disabled>
                                                    <label class="form-check-label">
                                                        <?= htmlspecialchars(
                                                            trim($option)
                                                        ) ?>
                                                    </label>
                                                </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <div class="text-center p-4">
                <button class="btn btn-submit">
                    <i class="fas fa-paper-plane me-2"></i>Submit Review
                </button>
            </div>
        </div>
    </div>

    <script>
        // Unified star rating handler
        document.querySelectorAll('.star-rating').forEach(ratingContainer => {
            const stars = ratingContainer.querySelectorAll('.fa-star');

            stars.forEach((star, index) => {
                star.addEventListener('click', () => {
                    // Clear all stars first
                    stars.forEach(s => {
                        s.classList.remove('fa-solid');
                        s.classList.add('fa-regular');
                    });

                    // Fill stars up to clicked index
                    stars.forEach((s, i) => {
                        if (i <= index) {
                            s.classList.add('fa-solid');
                            s.classList.remove('fa-regular');
                        }
                    });
                });
            });
        });
        // Activate star ratings in preview
        document.querySelectorAll('.star-rating').forEach(ratingContainer => {
            const stars = ratingContainer.querySelectorAll('.fa-star');

            stars.forEach((star, index) => {
                star.addEventListener('click', () => {
                    // Toggle star states
                    stars.forEach((s, i) => {
                        s.classList.toggle('fa-solid', i <= index);
                        s.classList.toggle('fa-regular', i > index);
                    });
                });
            });
        });
    </script>
</body>

</html>