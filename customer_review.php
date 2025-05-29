<?php

require __DIR__ . "/vendor/autoload.php";

// Import necessary classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require "vendor/autoload.php";
require_once "config/db_config.php";

$error = null;
$email = "";

if (!isset($_GET["form"])) {
    die("Form parameter is missing");
}
$form_hash = $_GET["form"];

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

$json_string = $form["form_structure"];
$form_structure = json_decode($json_string, true);

if (is_string($form_structure)) {
    $form_structure = json_decode($form_structure, true);
}

if (json_last_error() !== JSON_ERROR_NONE) {
    die("Invalid JSON structure: " . json_last_error_msg());
}

if (!is_array($form_structure)) {
    die("Form structure is not an array");
}

function calculateAverageRating($responses)
{
    $totalRatings = 0;
    $ratingCount = 0;

    foreach ($responses as $section) {
        foreach ($section as $field) {
            if (isset($field["ratings"])) {
                foreach ($field["ratings"] as $rating) {
                    if (is_numeric($rating)) {
                        $totalRatings += $rating;
                        $ratingCount++;
                    }
                }
            } elseif (isset($field["value"]) && is_numeric($field["value"])) {
                $totalRatings += $field["value"];
                $ratingCount++;
            }
        }
    }

    return $ratingCount > 0 ? round($totalRatings / $ratingCount, 2) : null;
}

function sendFormCopy(
    $email,
    $form_title,
    $form_structure,
    $responses,
    $rating_average
) {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = "smtp.gmail.com";
        $mail->SMTPAuth = true;
        $mail->Username = "\";
        $mail->Password = "\";
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom("\", "PLMUN Food Stall");
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject =
            "Your Feedback Submission: " . htmlspecialchars($form_title);

        $message = "<html><body>";
        $message .= "<h2>" . htmlspecialchars($form_title) . "</h2>";
        $message .= "<p>Here's a copy of your responses:</p>";
        $message .= "<ul style='list-style-type: none; padding: 0;'>";

        foreach ($form_structure as $section_index => $section) {
            if (!empty($section["title"])) {
                $message .=
                    "<li><h3 style='margin-bottom: 5px;'>" .
                    htmlspecialchars($section["title"]) .
                    "</h3></li>";
            }

            foreach ($section["fields"] as $field_index => $field) {
                $response = $responses[$section_index][$field_index] ?? null;
                if (!$response) {
                    continue;
                }

                $message .= "<li style='margin-bottom: 15px;'>";
                $message .=
                    "<strong>" .
                    htmlspecialchars($field["label"]) .
                    "</strong><br>";

                if (isset($response["ratings"])) {
                    $message .= "<ul>";
                    foreach (
                        $field["ratings"]
                        as $rating_index => $rating_field
                    ) {
                        $rating_value =
                            $response["ratings"][$rating_index] ?? "N/A";
                        $message .=
                            "<li>" .
                            htmlspecialchars($rating_field["label"]) .
                            ": " .
                            $rating_value .
                            "/5</li>";
                    }
                    $message .= "</ul>";
                } elseif (isset($response["value"])) {
                    if (is_array($response["value"])) {
                        $message .= implode(
                            ", ",
                            array_map("htmlspecialchars", $response["value"])
                        );
                    } else {
                        $message .= htmlspecialchars($response["value"]);
                    }
                }
                $message .= "</li>";
            }
        }

        $message .= "</ul>";

        if ($rating_average !== null) {
            $message .=
                "<p><strong>Average Rating:</strong> " .
                number_format($rating_average, 1) .
                "/5</p>";
        }

        $message .= "<p><em>Thank you for your feedback!</em></p>";
        $message .= "</body></html>";

        $mail->Body = $message;
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email failed: " . $e->getMessage());
        return false;
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $review_form_id = $_POST["review_form_id"];
    $food_stall_id = $_POST["food_stall_id"];
    $email = $_POST["email"] ?? "";

    $isValid = true;

    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $isValid = false;
        $error = "Invalid email address or leave blank to remain anonymous";
    }

    // Validate responses
    if ($isValid && empty($_POST["responses"])) {
        $isValid = false;
        $error = "Please complete all required fields";
    } elseif ($isValid) {
        foreach ($form_structure as $section_index => $section) {
            foreach ($section["fields"] as $field_index => $field) {
                if (
                    ($field["required"] ?? false) &&
                    empty($_POST["responses"][$section_index][$field_index])
                ) {
                    $isValid = false;
                    $error = "Please complete all required fields";
                    break 2;
                }
            }
        }
    }

    if ($isValid) {
        $response_data = json_encode($_POST["responses"]);
        $rating_average = calculateAverageRating($_POST["responses"]);

        // Sentiment analysis
        $sentiment = "neutral";
        if ($rating_average !== null) {
            if ($rating_average >= 4) {
                $sentiment = "positive";
            } elseif ($rating_average <= 2) {
                $sentiment = "negative";
            }
        }

        // Insert into database
        $stmt = $conn->prepare(
            "INSERT INTO responses (review_form_id, food_stall_id, response_data, rating_average, sentiment) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->bind_param(
            "iisds",
            $review_form_id,
            $food_stall_id,
            $response_data,
            $rating_average,
            $sentiment
        );

        if ($stmt->execute()) {
            // Send email copy if provided
            if (!empty($email)) {
                $emailSent = sendFormCopy(
                    $email,
                    $form["form_title"],
                    $form_structure,
                    $_POST["responses"],
                    $rating_average
                );

                if (!$emailSent) {
                    error_log("Failed to send email to: $email");
                }
            }

            session_start();
            $_SESSION["form_submitted"] = true;
            $_SESSION["form_hash"] = $form_hash;

            header("Location: thank_you.php");
            exit();
        } else {
            $error = "Submission error. Please try again.";
        }
        $stmt->close();
    }
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
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
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
            cursor: pointer;
            font-size: 1.4rem;
        }

        .fa-regular.fa-star {
            color: #ddd;
        }

        .fa-solid.fa-star {
            color: #ffc107;
        }

        .rating-question {
            margin-bottom: 10px;
        }

        .rating-question .question-label {
            margin-bottom: 10px;
        }

        .validation-alert {
            position: fixed;
            top: 20px;
            right: 20px;
            max-width: 400px;
            background: white;
            border-left: 5px solid var(--error-color);
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            padding: 1.5rem;
            display: flex;
            align-items: center;
            transform: translateX(150%);
            opacity: 0;
            transition: all 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            z-index: 9999;
        }

        .validation-alert.show {
            transform: translateX(0);
            opacity: 1;
        }

        .alert-icon {
            font-size: 2rem;
            color: var(--error-color);
            margin-right: 1.5rem;
        }

        .alert-content h4 {
            color: var(--error-color);
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .alert-content p {
            color: var(--dark-color);
            margin-bottom: 0;
        }
        .email-field {
            padding: 1rem 2rem;
            background: #f8f9fa;
            border-bottom: 1px solid #eee;
        }
        .email-field label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            display: block;
        }
        .email-field input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #eee;
            border-radius: 8px;
        }
        .email-note {
            font-size: 0.8rem;
            color: #666;
            margin-top: 0.5rem;
        }
        .is-invalid {
    border-color: #ff4757 !important;
    animation: pulse 0.5s ease;
}

@keyframes pulse {
    0% { transform: translateX(0); }
    25% { transform: translateX(-5px); }
    50% { transform: translateX(5px); }
    75% { transform: translateX(-5px); }
    100% { transform: translateX(0); }
}

.alert-danger {
    background: #fff5f5;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1rem;
}

        .shake {
            animation: shake 0.5s cubic-bezier(.36,.07,.19,.97) both;
        }

        @keyframes shake {
            10%, 90% { transform: translate3d(-1px, 0, 0); }
            20%, 80% { transform: translate3d(2px, 0, 0); }
            30%, 50%, 70% { transform: translate3d(-4px, 0, 0); }
            40%, 60% { transform: translate3d(4px, 0, 0); }
        }

        .highlight-error {
            animation: pulse 1.5s infinite;
            border-color: var(--error-color) !important;
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(255, 71, 87, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(255, 71, 87, 0); }
            100% { box-shadow: 0 0 0 0 rgba(255, 71, 87, 0); }
        }
    </style>
</head>
<body>
    <div class="preview-container">
        <form id="reviewForm" method="POST">
            <input type="hidden" name="review_form_id" value="<?= $form[
                "id"
            ] ?>">
            <input type="hidden" name="food_stall_id" value="<?= $form[
                "food_stall_id"
            ] ?>">

            <div class="form-header">
                <h2><?= htmlspecialchars($form["form_title"]) ?></h2>
                <p class="mb-0"><?= htmlspecialchars(
                    $form["form_description"] ?? ""
                ) ?></p>
            </div>

            <div class="email-field">
                <label for="email">Email Address (optional - receive a copy of your responses)</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars(
                    $email
                ) ?>"
                    placeholder="your@email.com">
                <p class="email-note">Your email will only be used to send this copy and will not be stored with your responses.</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger mx-4 mt-3" style="border-left: 4px solid #ff4757;">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <div>
                            <strong>Validation Error</strong>
                            <p class="mb-0"><?= htmlspecialchars($error) ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="form-content">
                <?php if (empty($form_structure)): ?>
                    <div class="alert alert-warning m-4">No questions found in this form</div>
                <?php else: ?>
                    <?php foreach (
                        $form_structure
                        as $section_index => $section
                    ): ?>
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

                            <?php foreach (
                                $section["fields"]
                                as $field_index => $field
                            ): ?>
                                <div class="question-card">
                                    <?php if ($field["type"] === "dish"): ?>
                                        <div class="dish-rating">
                                            <h5 class="question-label"><?= htmlspecialchars(
                                                $field["label"]
                                            ) ?></h5>

                                            <?php foreach (
                                                $field["ratings"]
                                                as $rating_index => $rating
                                            ): ?>
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
                                                    <div class="star-rating" data-type="dish" data-section="<?= $section_index ?>" data-field="<?= $field_index ?>" data-rating="<?= $rating_index ?>">
                                                        <?php for (
                                                            $i = 1;
                                                            $i <= 5;
                                                            $i++
                                                        ): ?>
                                                            <i class="fa-regular fa-star" data-value="<?= $i ?>"></i>
                                                        <?php endfor; ?>
                                                        <input type="hidden" name="responses[<?= $section_index ?>][<?= $field_index ?>][ratings][<?= $rating_index ?>]" value="0">
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>

                                    <?php elseif (
                                        $field["type"] === "rating"
                                    ): ?>
                                        <div class="rating-question">
                                            <div class="question-label <?= $field[
                                                "required"
                                            ] ?? false
                                                ? "required-field"
                                                : "" ?>">
                                                <?= htmlspecialchars(
                                                    $field["label"]
                                                ) ?>
                                            </div>
                                            <div class="star-rating" data-type="rating" data-section="<?= $section_index ?>" data-field="<?= $field_index ?>">
                                                <?php for (
                                                    $i = 1;
                                                    $i <= 5;
                                                    $i++
                                                ): ?>
                                                    <i class="fa-regular fa-star" data-value="<?= $i ?>"></i>
                                                <?php endfor; ?>
                                                <input type="hidden" name="responses[<?= $section_index ?>][<?= $field_index ?>][value]" value="0">
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
                                            <textarea class="form-control" name="responses[<?= $section_index ?>][<?= $field_index ?>][value]" rows="3" placeholder="Your answer..."></textarea>
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
                                            <?php foreach (
                                                explode(
                                                    "\n",
                                                    $field["options"] ?? ""
                                                )
                                                as $option_index => $option
                                            ): ?>
                                                <?php if (
                                                    !empty(trim($option))
                                                ): ?>
                                                    <div class="form-check mb-2">
                                                        <input class="form-check-input" type="radio"
                                                            name="responses[<?= $section_index ?>][<?= $field_index ?>][value]"
                                                            value="<?= htmlspecialchars(
                                                                trim($option)
                                                            ) ?>"
                                                            id="option_<?= $section_index ?>_<?= $field_index ?>_<?= $option_index ?>">
                                                        <label class="form-check-label" for="option_<?= $section_index ?>_<?= $field_index ?>_<?= $option_index ?>">
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
    <button type="submit" class="btn btn-submit">
        <i class="fas fa-paper-plane me-2"></i>Submit Review
    </button>
    <?php if (isset($rating_average) && $rating_average !== null): ?>
        <div class="mt-3">
            <strong>Average Rating:</strong>
            <div class="star-rating d-inline-block ms-2">
                <?php
                $fullStars = floor($rating_average);
                $hasHalfStar = $rating_average - $fullStars >= 0.5;

                for ($i = 1; $i <= 5; $i++):
                    if ($i <= $fullStars): ?>
                        <i class="fas fa-star"></i>
                    <?php elseif ($i == $fullStars + 1 && $hasHalfStar): ?>
                        <i class="fas fa-star-half-alt"></i>
                    <?php else: ?>
                        <i class="far fa-star"></i>
                    <?php endif;
                endfor;
                ?>
                <span class="ms-2">(<?= number_format(
                    $rating_average,
                    1
                ) ?>/5)</span>
            </div>
        </div>
    <?php endif; ?>
</div>
            </div>
        </form>
    </div>

    <script>
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }
        document.querySelectorAll('.star-rating').forEach(ratingContainer => {
            const stars = ratingContainer.querySelectorAll('.fa-star');
            const hiddenInput = ratingContainer.querySelector('input[type="hidden"]');

            stars.forEach(star => {
                star.addEventListener('click', () => {
                    const value = parseInt(star.getAttribute('data-value'));

                    stars.forEach((s, i) => {
                        if (i < value) {
                            s.classList.add('fa-solid');
                            s.classList.remove('fa-regular');
                        } else {
                            s.classList.add('fa-regular');
                            s.classList.remove('fa-solid');
                        }
                    });

                    hiddenInput.value = value;
                });
            });
        });

        // Form validation
        document.getElementById('reviewForm').addEventListener('submit', function(e) {
    let isValid = true;
    const invalidFields = [];

    // Clear previous error highlights
    document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));

    // Check required fields
    document.querySelectorAll('.required-field').forEach(label => {
        const questionCard = label.closest('.question-card');

        if (questionCard) {
            // For star ratings
            const starRating = questionCard.querySelector('.star-rating');
            if (starRating) {
                const hiddenInput = starRating.querySelector('input[type="hidden"]');
                if (hiddenInput && hiddenInput.value === '0') {
                    isValid = false;
                    starRating.classList.add('is-invalid');
                    invalidFields.push(starRating);
                }
            }

            // For text areas
            const textarea = questionCard.querySelector('textarea');
            if (textarea && textarea.required && !textarea.value.trim()) {
                isValid = false;
                textarea.classList.add('is-invalid');
                invalidFields.push(textarea);
            }

            // For radio buttons
            const radioGroup = questionCard.querySelectorAll('input[type="radio"]');
            if (radioGroup.length > 0) {
                const isChecked = Array.from(radioGroup).some(radio => radio.checked);
                if (!isChecked) {
                    isValid = false;
                    radioGroup[0].closest('.choice-question').classList.add('is-invalid');
                    invalidFields.push(radioGroup[0].closest('.choice-question'));
                }
            }
        }
    });

    if (!isValid) {
        e.preventDefault();
        if (invalidFields.length > 0) {
            invalidFields[0].scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });
        }
        return false;
    }
});
    </script>
</body>
</html>
