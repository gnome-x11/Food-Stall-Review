<?php
session_start();
// Store that we've been to the thank you page
$_SESSION['thank_you_visited'] = true;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thank You</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #FF6B6B;
            --dark-color: #2D3436;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--dark-color);
            padding: 20px;
        }

        .thank-you-container {
            max-width: 800px;
            margin: 2rem auto;
            background: white;
            padding: 0;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .thank-you-header {
            background: var(--primary-color);
            color: white;
            padding: 3rem 2rem;
            text-align: center;
            border-radius: 15px 15px 0 0;
        }

        .thank-you-header h1 {
            font-weight: 700;
            margin-bottom: 1rem;
            font-size: 2.5rem;
        }

        .thank-you-header .icon {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            animation: bounce 1s infinite alternate;
        }

        .thank-you-content {
            padding: 3rem 2rem;
            text-align: center;
        }

        .thank-you-content p {
            font-size: 1.1rem;
            color: var(--dark-color);
            margin-bottom: 2rem;
        }

        .btn-return {
            background: var(--gradient-primary);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            margin-top: 1rem;
        }

        .btn-return:hover {
            opacity: 0.9;
            transform: translateY(-2px);
            color: white;
        }

        @keyframes bounce {
            from {
                transform: translateY(0);
            }

            to {
                transform: translateY(-10px);
            }
        }

        .rating-display {
            margin: 2rem 0;
            font-size: 1.2rem;
        }

        .stars {
            color: #ffc107;
            font-size: 2rem;
            margin: 1rem 0;
        }
    </style>
</head>

<body>
    <div class="thank-you-container">
        <div class="thank-you-header">
            <div class="icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h1>Thank You for Your Feedback!</h1>
        </div>


        <div class="thank-you-content">
            <p>We sincerely appreciate you taking the time to share your experience with us. Your feedback helps us
                improve our services.</p>
            <p> A copy of your feedback has been sent to your email.</p>

            <?php if (isset($_SESSION['average_rating'])): ?>
                <div class="rating-display">
                    <p>Your rating:</p>
                    <div class="stars">
                        <?php
                        $rating = $_SESSION['average_rating'];
                        $fullStars = floor($rating);
                        $hasHalfStar = ($rating - $fullStars) >= 0.5;

                        for ($i = 1; $i <= 5; $i++):
                            if ($i <= $fullStars): ?>
                                <i class="fas fa-star"></i>
                            <?php elseif ($i == $fullStars + 1 && $hasHalfStar): ?>
                                <i class="fas fa-star-half-alt"></i>
                            <?php else: ?>
                                <i class="far fa-star"></i>
                            <?php endif;
                        endfor; ?>
                        <span class="ms-2">(<?= number_format($rating, 1) ?>/5)</span>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Prevent form resubmission on refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }

        // Prevent going back to the form
        history.pushState(null, null, location.href);
        window.onpopstate = function () {
            history.go(1);
        };
    </script>
</body>

</html>