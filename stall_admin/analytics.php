<?php

error_reporting(E_ALL);
ini_set("display_errors", 1);

session_start();
require_once "../config/db_config.php";

if (!isset($_SESSION["stall_id"])) {
    header("Location: ../stall_admin/stall_selection.php");
    exit();
}

$stall_id = $_SESSION["stall_id"];

$stmt = $conn->prepare("SELECT stall_name FROM food_stalls WHERE id = ?");
$stmt->bind_param("i", $stall_id);
$stmt->execute();
$result = $stmt->get_result();
$stall = $result->fetch_assoc();
$stmt->close();

$query = "SELECT
            r.id,
            r.rating_average,
            r.sentiment,
            r.created_at,
            r.response_data,
            rf.form_title,
            rf.form_structure
          FROM responses r
          JOIN review_forms rf ON r.review_form_id = rf.id
          WHERE r.food_stall_id = ?
          ORDER BY r.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $stall_id);
$stmt->execute();
$result = $stmt->get_result();
$all_reviews = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Calculate statistics
$total_reviews = count($all_reviews);
$total_positive = 0;
$total_negative = 0;
$total_neutral = 0;
$sum_ratings = 0;
$item_ratings = [];

$rating_distribution = array_fill(1, 5, 0);
foreach ($all_reviews as $review) {
    $rounded_rating = round($review["rating_average"]);
    $rating_distribution[$rounded_rating]++;
}

// Prepare data for graphs
$weekly_visits = array_fill(0, 7, 0);
$monthly_trends = array_fill(0, 12, 0);
$text_responses = [];
$sentiment_counts = ["positive" => 0, "neutral" => 0, "negative" => 0];

foreach ($all_reviews as $review) {
    $sum_ratings += $review["rating_average"];

    // Count sentiments
    $sentiment_counts[$review["sentiment"]]++;

    // Weekly visits
    $day_of_week = date("w", strtotime($review["created_at"]));
    $weekly_visits[$day_of_week]++;

    // Monthly trends
    $month = date("n", strtotime($review["created_at"])) - 1;
    $monthly_trends[$month]++;

    // Process text responses
    $response_data = json_decode($review["response_data"], true);
    $form_structure = json_decode($review["form_structure"], true);

    // Skip if JSON decoding failed
    if (!is_array($response_data) || !is_array($form_structure)) {
        continue;
    }

    foreach ($form_structure as $section_index => $section) {
        if (!isset($section["fields"]) || !is_array($section["fields"])) {
            continue;
        }

        foreach ($section["fields"] as $field_index => $field) {
            if (isset($response_data[$section_index][$field_index]["value"])) {
                $response_text =
                    $response_data[$section_index][$field_index]["value"];
                if (is_string($response_text)) {
                    $text_responses[] = $response_text;
                }
            }
        }
    }
}

// Calculate percentages
$average_rating = $total_reviews > 0 ? $sum_ratings / $total_reviews : 0;
$positive_percent =
    $total_reviews > 0
        ? ($sentiment_counts["positive"] / $total_reviews) * 100
        : 0;
$negative_percent =
    $total_reviews > 0
        ? ($sentiment_counts["negative"] / $total_reviews) * 100
        : 0;
$neutral_percent =
    $total_reviews > 0
        ? ($sentiment_counts["neutral"] / $total_reviews) * 100
        : 0;

// Process text responses
$text_responses = [];
foreach ($all_reviews as $review) {
    $response_data = json_decode($review["response_data"], true);
    $form_structure = json_decode($review["form_structure"], true);

    if (!is_array($response_data)) {
        continue;
    }

    // Improved recursive text extraction
    array_walk_recursive($response_data, function ($value, $key) use (
        &$text_responses
    ) {
        if (is_string($value) && !empty(trim($value))) {
            $text_responses[] = strtolower(trim($value));
        }
    });
}

// Word frequency analysis with improved filtering
$word_counts = [];
$stop_words = array_merge(
    ["the", "and", "to", "of", "a", "in", "is", "it", "that", "with", "for"],
    ["ang", "ng", "sa", "na", "ay", "ako", "kami", "sila", "ito", "iyon"],
    ["very", "good", "bad", "nice", "like", "okay", "ok", "na", "po"]
);

foreach ($text_responses as $text) {
    // Better word splitting that handles punctuation
    $words = preg_split("/[\s,\.!\?\(\)]+/", $text);
    foreach ($words as $word) {
        $word = preg_replace('/[^a-z\']/', "", $word); // Keep letters and apostrophes
        if (
            !empty($word) &&
            strlen($word) > 2 &&
            !in_array($word, $stop_words)
        ) {
            $word_counts[$word] = ($word_counts[$word] ?? 0) + 1;
        }
    }
}

// Sort and get top words
arsort($word_counts);
$top_words = array_slice($word_counts, 0, 20);

// Get top 20 words
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - <?= htmlspecialchars($stall["stall_name"]) ?></title>
    <link rel="shortcut icon" type="image/x-icon" href="../assets/img/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../components/index.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

</head>

<body>

    <!-- Include Sidebar -->
    <?php include "../includes/sidebar.php"; ?>
    <!-- Include Topbar -->
    <?php include "../includes/topbar.php"; ?>

    <div class="main-content">
        <div class="dashboard-card mb-4">
            <div class="dashboard-header d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="dashboard-title"><i class="fas fa-chart-line me-2"></i> Analytics Dashboard</h2>
                    <p class="mb-0"><?= htmlspecialchars(
                        $stall["stall_name"]
                    ) ?></p>
                </div>
            </div>

            <div class="p-4">
                <!-- Statistics Cards -->
                <div class="row g-4 mb-4">
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="stat-card h-100">
                            <div class="stat-value total"><?= number_format(
                                $total_reviews
                            ) ?></div>
                            <div class="text-muted">TOTAL RESPONSES</div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6 col-xl-3 mb-4 mb-xl-0">
                        <div class="stat-card h-100">
                            <div class="stat-value positive"><?= number_format(
                                $positive_percent,
                                1
                            ) ?>%</div>
                            <div class="text-muted">POSITIVE</div>
                        </div>
                    </div>

                    <div class="col-12 col-sm-6 col-xl-3 mb-4 mb-xl-0">
                        <div class="stat-card h-100">
                            <div class="stat-value negative"><?= number_format(
                                $negative_percent,
                                1
                            ) ?>%</div>
                            <div class="text-muted">NEGATIVE</div>
                        </div>
                    </div>

                    <div class="col-12 col-sm-6 col-xl-3 mb-4 mb-xl-0">
                        <div class="stat-card h-100">
                            <div class="stat-value neutral"><?= number_format(
                                $neutral_percent,
                                1
                            ) ?>%</div>
                            <div class="text-muted">NEUTRAL</div>
                        </div>
                    </div>
                </div>

                <!-- First Row - Weekly and Monthly Charts Side by Side -->
                <div class="row">
                    <!-- Visit by Week Graph -->
                    <div class="col-md-6">
                        <div class="graph-container">
                            <h4 class="graph-title"><i class="fas fa-calendar-week me-2"></i> Visits by Day of Week</h4>
                            <canvas id="weeklyVisitsChart"></canvas>
                        </div>
                    </div>

                    <!-- Monthly Trends Graph -->
                    <div class="col-md-6">
                        <div class="graph-container">
                            <h4 class="graph-title"><i class="fas fa-chart-bar me-2"></i> Monthly Trends</h4>
                            <canvas id="monthlyTrendsChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Second Row - Smaller Sentiment Analysis -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="graph-container" style="height: 400px;">
                            <h4 class="graph-title"><i class="fas fa-smile me-2"></i> Sentiment Analysis</h4>
                            <canvas id="sentimentChart"></canvas>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="graph-container">
                            <h4 class="graph-title"><i class="fas fa-star me-2"></i> Rating Distribution</h4>
                            <canvas id="ratingDistributionChart"></canvas>
                        </div>
                    </div>
                    <!-- Text Analysis Word Cloud -->
                    <div class="col-md-12">
                        <div class="graph-container" style="height: 300px; overflow-y: auto;">
                            <h4 class="graph-title"><i class="fas fa-comment-dots me-2"></i> Common Feedback Words</h4>
                            <?php if (!empty($top_words)): ?>
                                <div class="word-cloud">
                                    <?php foreach (
                                        $top_words
                                        as $word => $count
                                    ):

                                        $size = min(16 + log($count) * 6, 36); // Logarithmic scaling

                                        $title =
                                            "Used $count " .
                                            ($count === 1 ? "time" : "times");
                                        ?>
                                        <span class="word-item" style="font-size: <?= $size ?>px; color: <?= $color ?>"
                                            title="<?= $title ?>" data-bs-toggle="tooltip">
                                            <?= htmlspecialchars($word) ?>
                                        </span>
                                    <?php
                                    endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">No text responses available for analysis.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>

        // Weekly Visits Chart
        const weeklyCtx = document.getElementById('weeklyVisitsChart').getContext('2d');
        new Chart(weeklyCtx, {
            type: 'bar',
            data: {
                labels: ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
                datasets: [{
                    label: 'Number of Visits',
                    data: <?= json_encode($weekly_visits) ?>,
                    backgroundColor: '#FF6B6B',
                    borderColor: '#FF6B6B',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Number of Visits'
                        }
                    }
                }
            }
        });

        // Monthly Trends Chart
        const monthlyCtx = document.getElementById('monthlyTrendsChart').getContext('2d');
        new Chart(monthlyCtx, {
            type: 'line',
            data: {
                labels: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
                datasets: [{
                    label: 'Reviews per Month',
                    data: <?= json_encode($monthly_trends) ?>,
                    backgroundColor: 'rgba(255, 107, 107, 0.2)',
                    borderColor: '#FF6B6B',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Number of Reviews'
                        }
                    }
                }
            }
        });

        // Sentiment Analysis Chart
        const sentimentCtx = document.getElementById('sentimentChart').getContext('2d');
        new Chart(sentimentCtx, {
            type: 'doughnut',
            data: {
                labels: ['Positive', 'Neutral', 'Negative'],
                datasets: [{
                    data: <?= json_encode(array_values($sentiment_counts)) ?>,
                    backgroundColor: [
                        '#28a745',
                        '#ffc107',
                        '#dc3545'
                    ],
                    borderWidth: 10
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'left',
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                const total = <?= $total_reviews ?>;
                                const value = context.raw;
                                const percentage = Math.round((value / total) * 100);
                                return `${context.label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });

        const ratingCtx = document.getElementById('ratingDistributionChart').getContext('2d');
        new Chart(ratingCtx, {
            type: 'bar',
            data: {
                labels: ['1 Star', '2 Stars', '3 Stars', '4 Stars', '5 Stars'],
                datasets: [{
                    label: 'Number of Reviews',
                    data: <?= json_encode(
                        array_values($rating_distribution)
                    ) ?>,
                    backgroundColor: '#FF6B6B',
                    borderColor: '#FF6B6B',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: { display: true, text: 'Number of Reviews' }
                    }
                }
            }
        });
    </script>
</body>
</html>
