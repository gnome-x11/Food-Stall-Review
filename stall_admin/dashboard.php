<?php

require_once('../header-control.php');

session_start();
require_once "../config/db_config.php";
require_once "../jwt_validator.php";

$decoded = validateToken("stall_admin_token", "../stall_admin/dashboard.php");
$id = $decoded->uid;
$username = $decoded->username;


// Fetch stall details
$stmt = $conn->prepare("SELECT stall_name FROM food_stalls WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$stall = $result->fetch_assoc();
$stmt->close();

if (!$stall) {
    die("Stall not found");
}

// Date ranges
$today = date("Y-m-d");
$week_start = date("Y-m-d", strtotime("monday this week"));
$week_end = date("Y-m-d", strtotime("sunday this week"));

// Get today's metrics
$stmt = $conn->prepare("SELECT
    COUNT(*) as total_today,
    AVG(rating_average) as avg_rating_today,
    SUM(CASE WHEN sentiment = 'positive' THEN 1 ELSE 0 END) as positive_today
    FROM responses
    WHERE food_stall_id = ?
    AND DATE(created_at) = ?");
$stmt->bind_param("is", $stall_id, $today);
$stmt->execute();
$today_metrics = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get weekly metrics
$stmt = $conn->prepare("SELECT
    COUNT(*) as total_week,
    AVG(rating_average) as avg_rating_week,
    SUM(CASE WHEN sentiment = 'positive' THEN 1 ELSE 0 END) as positive_week
    FROM responses
    WHERE food_stall_id = ?
    AND DATE(created_at) BETWEEN ? AND ?");
$stmt->bind_param("iss", $stall_id, $week_start, $week_end);
$stmt->execute();
$week_metrics = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Calculate recommendation rates
$recommendation_rate_today =
    $today_metrics["total_today"] > 0
        ? ($today_metrics["positive_today"] / $today_metrics["total_today"]) *
            100
        : 0;

$recommendation_rate_week =
    $week_metrics["total_week"] > 0
        ? ($week_metrics["positive_week"] / $week_metrics["total_week"]) * 100
        : 0;

// Get all responses with filtering
$filter_sentiment = $_GET["sentiment"] ?? "";
$filter_date = $_GET["date"] ?? "";
$filter_rating = $_GET["rating"] ?? "";

$query = "SELECT r.*, rf.form_title
    FROM responses r
    JOIN review_forms rf ON r.review_form_id = rf.id
    WHERE r.food_stall_id = ?";

$params = [$stall_id];
$types = "i";

if (!empty($filter_sentiment)) {
    $query .= " AND r.sentiment = ?";
    $params[] = $filter_sentiment;
    $types .= "s";
}

if (!empty($filter_date)) {
    $query .= " AND DATE(r.created_at) = ?";
    $params[] = $filter_date;
    $types .= "s";
}

if (!empty($filter_rating)) {
    $query .= " AND ROUND(r.rating_average) = ?";
    $params[] = $filter_rating;
    $types .= "i";
}

$query .= " ORDER BY r.created_at DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$all_responses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get today reviews for real-time section
$stmt = $conn->prepare("SELECT r.*, rf.form_title
    FROM responses r
    JOIN review_forms rf ON r.review_form_id = rf.id
    WHERE r.food_stall_id = ?
    AND DATE(r.created_at) = ?
    ORDER BY r.created_at DESC
    LIMIT 5");
$stmt->bind_param("is", $stall_id, $today);
$stmt->execute();
$today_reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$highest_rated_dish = null;
$lowest_rated_dish = null;

// Get highest rated dish
$highest_rated_dish_query = "
SELECT * FROM (
  SELECT
    JSON_UNQUOTE(JSON_EXTRACT(JSON_UNQUOTE(rf.form_structure), '$[0].fields[0].label')) AS `Dish Name`,
    ROUND(AVG((
      CAST(JSON_UNQUOTE(JSON_EXTRACT(r.response_data, '$[0][0].ratings[0]')) AS DECIMAL(3,1)) +
      CAST(JSON_UNQUOTE(JSON_EXTRACT(r.response_data, '$[0][0].ratings[1]')) AS DECIMAL(3,1)) +
      CAST(JSON_UNQUOTE(JSON_EXTRACT(r.response_data, '$[0][0].ratings[2]')) AS DECIMAL(3,1)) +
      CAST(JSON_UNQUOTE(JSON_EXTRACT(r.response_data, '$[0][0].ratings[3]')) AS DECIMAL(3,1)) +
      CAST(JSON_UNQUOTE(JSON_EXTRACT(r.response_data, '$[0][0].ratings[4]')) AS DECIMAL(3,1))
    ) / 5), 2) AS `Average Rating`
  FROM responses r
  JOIN review_forms rf ON r.review_form_id = rf.id
  WHERE r.food_stall_id = ?
  GROUP BY `Dish Name`

  UNION ALL

  SELECT
    JSON_UNQUOTE(JSON_EXTRACT(JSON_UNQUOTE(rf.form_structure), '$[0].fields[1].label')) AS `Dish Name`,
    ROUND(AVG((
      CAST(JSON_UNQUOTE(JSON_EXTRACT(r.response_data, '$[0][1].ratings[0]')) AS DECIMAL(3,1)) +
      CAST(JSON_UNQUOTE(JSON_EXTRACT(r.response_data, '$[0][1].ratings[1]')) AS DECIMAL(3,1)) +
      CAST(JSON_UNQUOTE(JSON_EXTRACT(r.response_data, '$[0][1].ratings[2]')) AS DECIMAL(3,1)) +
      CAST(JSON_UNQUOTE(JSON_EXTRACT(r.response_data, '$[0][1].ratings[3]')) AS DECIMAL(3,1)) +
      CAST(JSON_UNQUOTE(JSON_EXTRACT(r.response_data, '$[0][1].ratings[4]')) AS DECIMAL(3,1))
    ) / 5), 2) AS `Average Rating`
  FROM responses r
  JOIN review_forms rf ON r.review_form_id = rf.id
  WHERE r.food_stall_id = ?
  GROUP BY `Dish Name`

  UNION ALL

  SELECT
    JSON_UNQUOTE(JSON_EXTRACT(JSON_UNQUOTE(rf.form_structure), '$[0].fields[2].label')) AS `Dish Name`,
    ROUND(AVG((
      CAST(JSON_UNQUOTE(JSON_EXTRACT(r.response_data, '$[0][2].ratings[0]')) AS DECIMAL(3,1)) +
      CAST(JSON_UNQUOTE(JSON_EXTRACT(r.response_data, '$[0][2].ratings[1]')) AS DECIMAL(3,1)) +
      CAST(JSON_UNQUOTE(JSON_EXTRACT(r.response_data, '$[0][2].ratings[2]')) AS DECIMAL(3,1)) +
      CAST(JSON_UNQUOTE(JSON_EXTRACT(r.response_data, '$[0][2].ratings[3]')) AS DECIMAL(3,1)) +
      CAST(JSON_UNQUOTE(JSON_EXTRACT(r.response_data, '$[0][2].ratings[4]')) AS DECIMAL(3,1))
    ) / 5), 2) AS `Average Rating`
  FROM responses r
  JOIN review_forms rf ON r.review_form_id = rf.id
  WHERE r.food_stall_id = ?
  GROUP BY `Dish Name`

    UNION ALL

    SELECT
    JSON_UNQUOTE(JSON_EXTRACT(JSON_UNQUOTE(rf.form_structure), '$[0].fields[3].label')) AS `Dish Name`,
    ROUND(AVG((
      CAST(JSON_UNQUOTE(JSON_EXTRACT(r.response_data, '$[0][0].ratings[0]')) AS DECIMAL(3,1)) +
      CAST(JSON_UNQUOTE(JSON_EXTRACT(r.response_data, '$[0][0].ratings[1]')) AS DECIMAL(3,1)) +
      CAST(JSON_UNQUOTE(JSON_EXTRACT(r.response_data, '$[0][0].ratings[2]')) AS DECIMAL(3,1)) +
      CAST(JSON_UNQUOTE(JSON_EXTRACT(r.response_data, '$[0][0].ratings[3]')) AS DECIMAL(3,1)) +
      CAST(JSON_UNQUOTE(JSON_EXTRACT(r.response_data, '$[0][0].ratings[4]')) AS DECIMAL(3,1))
    ) / 5), 2) AS `Average Rating`
  FROM responses r
  JOIN review_forms rf ON r.review_form_id = rf.id
  WHERE r.food_stall_id = ?
  GROUP BY `Dish Name`

  UNION ALL
    SELECT
    JSON_UNQUOTE(JSON_EXTRACT(JSON_UNQUOTE(rf.form_structure), '$[0].fields[4].label')) AS `Dish Name`,
    ROUND(AVG((
      CAST(JSON_UNQUOTE(JSON_EXTRACT(r.response_data, '$[0][0].ratings[0]')) AS DECIMAL(3,1)) +
      CAST(JSON_UNQUOTE(JSON_EXTRACT(r.response_data, '$[0][0].ratings[1]')) AS DECIMAL(3,1)) +
      CAST(JSON_UNQUOTE(JSON_EXTRACT(r.response_data, '$[0][0].ratings[2]')) AS DECIMAL(3,1)) +
      CAST(JSON_UNQUOTE(JSON_EXTRACT(r.response_data, '$[0][0].ratings[3]')) AS DECIMAL(3,1)) +
      CAST(JSON_UNQUOTE(JSON_EXTRACT(r.response_data, '$[0][0].ratings[4]')) AS DECIMAL(3,1))
    ) / 5), 2) AS `Average Rating`
  FROM responses r
  JOIN review_forms rf ON r.review_form_id = rf.id
  WHERE r.food_stall_id = ?
  GROUP BY `Dish Name`
) AS dish_ratings
ORDER BY `Average Rating` DESC
LIMIT 1";

$stmt = $conn->prepare($highest_rated_dish_query);
$stmt->bind_param(
    "iiiii",
    $stall_id,
    $stall_id,
    $stall_id,
    $stall_id,
    $stall_id
);
$stmt->execute();
$highest_rated_dish = $stmt->get_result()->fetch_assoc();
$stmt->close();

$lowest_rated_dish_query = str_replace(
    "ORDER BY `Average Rating` DESC",
    "ORDER BY `Average Rating` ASC",
    $highest_rated_dish_query
);

$stmt = $conn->prepare($lowest_rated_dish_query);
$stmt->bind_param(
    "iiiii",
    $stall_id,
    $stall_id,
    $stall_id,
    $stall_id,
    $stall_id
);
$stmt->execute();
$lowest_rated_dish = $stmt->get_result()->fetch_assoc();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?= htmlspecialchars($stall["stall_name"]) ?></title>
    <link rel="shortcut icon" type="image/x-icon" href="../assets/img/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../components/index.css">
</head>

<body>

    <?php include "../includes/sidebar.php"; ?>
    <?php include "../includes/topbar.php"; ?>

    <div class="main-content">

        <div class="dashboard-card mb-4">
            <div class="dashboard-header d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="dashboard-title"><i class="fas fa-chart-line me-2"></i>Dashboard</h2>
                    <p class="mb-0"><?= htmlspecialchars(
                        $stall["stall_name"]
                    ) ?></p>
                </div>
                <div class="d-flex align-items-center">
                    <div class="position-relative me-3">
                        <span class="real-time-badge pulse"></span>
                        <span class="badge bg-light text-dark">Live Data</span>
                    </div>

                </div>
            </div>
        </div>

        <!-- Key Metrics -->
        <div class="row g-4 mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="metric-card">
                    <div class="metric-value text-primary"><?= $today_metrics[
                        "total_today"
                    ] ?? 0 ?></div>
                    <div class="metric-label">Today's Reviews</div>
                    <div class="mt-2">
                        <span class="badge bg-light text-dark">
                            <i class="fas fa-calendar-day me-1"></i> <?= date(
                                "M j, Y"
                            ) ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="metric-card">
                    <div class="metric-value text-info"><?= $week_metrics[
                        "total_week"
                    ] ?? 0 ?></div>
                    <div class="metric-label">This Week's Reviews</div>
                    <div class="mt-2">
                        <span class="badge bg-light text-dark">
                            <i class="fas fa-calendar-week me-1"></i> <?= date(
                                "M j",
                                strtotime($week_start)
                            ) ?> -
                            <?= date("M j", strtotime($week_end)) ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="metric-card">
                    <div class="metric-value text-warning">
                        <?= number_format(
                            $today_metrics["avg_rating_today"] ?? 0,
                            1
                        ) ?>
                    </div>
                    <div class="metric-label">Avg Rating Today</div>
                    <div class="mt-2 star-rating">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i
                                class="fas fa-star <?= $i <=
                                round($today_metrics["avg_rating_today"] ?? 0)
                                    ? "text-warning"
                                    : "text-muted" ?>"></i>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="metric-card">
                    <div class="metric-value text-success"><?= number_format(
                        $recommendation_rate_today,
                        1
                    ) ?>%</div>
                    <div class="metric-label">Recommendation Rate</div>
                    <div class="mt-2">
                        <div class="progress">
                            <div class="progress-bar bg-success" style="width: <?= $recommendation_rate_today ?>%">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-md-4">
                <div class="metric-card">
                    <?php if ($highest_rated_dish): ?>
                        <div class="metric-value text-danger"><?= htmlspecialchars(
                            $highest_rated_dish["Dish Name"]
                        ) ?>
                        </div>
                        <div class="metric-label">Highest Rated Dish</div>
                        <div class="mt-2 d-flex justify-content-between align-items-center">
                            <div class="star-rating">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i
                                        class="fas fa-star <?= $i <=
                                        round(
                                            $highest_rated_dish[
                                                "Average Rating"
                                            ]
                                        )
                                            ? "text-warning"
                                            : "text-muted" ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <span class="badge bg-danger">
                                <?= number_format(
                                    $highest_rated_dish["Average Rating"],
                                    1
                                ) ?> / 5.0
                            </span>
                        </div>
                    <?php else: ?>
                        <div class="metric-value text-muted">No dishes rated yet</div>
                        <div class="metric-label">Highest Rated Dish</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-md-6 col-md-4">
                <div class="metric-card">
                    <?php if ($lowest_rated_dish): ?>
                        <div class="metric-value text-primary"><?= htmlspecialchars(
                            $lowest_rated_dish["Dish Name"]
                        ) ?>
                        </div>
                        <div class="metric-label">Lowest Rated Dish</div>
                        <div class="mt-2 d-flex justify-content-between align-items-center">
                            <div class="star-rating">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i
                                        class="fas fa-star <?= $i <=
                                        round(
                                            $lowest_rated_dish["Average Rating"]
                                        )
                                            ? "text-warning"
                                            : "text-muted" ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <span class="badge bg-primary">
                                <?= number_format(
                                    $lowest_rated_dish["Average Rating"],
                                    1
                                ) ?> / 5.0
                            </span>
                        </div>
                    <?php else: ?>
                        <div class="metric-value text-muted">No dishes rated yet</div>
                        <div class="metric-label">Lowest Rated Dish</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Real-Time Reviews -->
        <div class="dashboard-card mb-4">
            <div class="p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="mb-0 d-flex align-items-center">
                        <i class="fas fa-bolt text-warning me-2"></i> Today's Recent Reviews
                    </h4>
                    <?php if (count($today_reviews) > 0): ?>
                        <span class="badge bg-danger">
                            <i class="fas fa-circle me-1"></i> Live Updates
                        </span>
                    <?php endif; ?>
                </div>

                <?php if (count($today_reviews) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Rating</th>
                                    <th>Sentiment</th>
                                    <th>Form</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($today_reviews as $review): ?>
                                    <tr class="<?= $review["sentiment"] ===
                                    "positive"
                                        ? "positive-bg"
                                        : ($review["sentiment"] === "negative"
                                            ? "negative-bg"
                                            : "neutral-bg") ?>">
                                        <td><?= date(
                                            "h:i A",
                                            strtotime($review["created_at"])
                                        ) ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <span
                                                    class="fw-bold me-2"><?= number_format(
                                                        $review[
                                                            "rating_average"
                                                        ],
                                                        1
                                                    ) ?></span>
                                                <div class="star-rating">
                                                    <?php for (
                                                        $i = 1;
                                                        $i <= 5;
                                                        $i++
                                                    ): ?>
                                                        <i class="fas fa-star <?= $i <=
                                                        round(
                                                            $review[
                                                                "rating_average"
                                                            ]
                                                        )
                                                            ? "text-warning"
                                                            : "text-muted" ?>"
                                                            style="font-size: 0.8rem;"></i>
                                                    <?php endfor; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $review[
                                                "sentiment"
                                            ] === "positive"
                                                ? "success"
                                                : ($review["sentiment"] ===
                                                "negative"
                                                    ? "danger"
                                                    : "warning") ?>">
                                                <?= ucfirst(
                                                    $review["sentiment"]
                                                ) ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars(
                                            $review["form_title"]
                                        ) ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary view-response"
                                                data-id="<?= $review["id"] ?>">
                                                <i class="fas fa-eye me-1"></i> View
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle me-2"></i> No reviews yet today. Check back later!
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- All Responses with Filters -->
        <div class="dashboard-card">
            <div class="p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="mb-0"><i class="fas fa-list me-2"></i> All Responses</h4>
                </div>

                <!-- Filter Section -->
                <div class="filter-section mb-4">
                    <form id="filter-form" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Sentiment</label>
                            <select name="sentiment" class="form-select">
                                <option value="">All Sentiments</option>
                                <option value="positive" <?= $filter_sentiment ===
                                "positive"
                                    ? "selected"
                                    : "" ?>>Positive
                                </option>
                                <option value="neutral" <?= $filter_sentiment ===
                                "neutral"
                                    ? "selected"
                                    : "" ?>>Neutral
                                </option>
                                <option value="negative" <?= $filter_sentiment ===
                                "negative"
                                    ? "selected"
                                    : "" ?>>Negative
                                </option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Date</label>
                            <input type="date" name="date" class="form-control" value="<?= $filter_date ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Rating</label>
                            <select name="rating" class="form-select">
                                <option value="">All Ratings</option>
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <option value="<?= $i ?>" <?= $filter_rating ==
$i
    ? "selected"
    : "" ?>>
                                        <?= $i ?> Star<?= $i > 1 ? "s" : "" ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-filter me-1"></i> Filter
                            </button>
                            <a href="dashboard.php" class="btn btn-outline-secondary">
                                <i class="fas fa-sync-alt me-1"></i> Reset
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Responses Table -->
                <div class="table-responsive">
                    <table id="responses-table" class="table table-hover align-middle" style="width:100%">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Rating</th>
                                <th>Sentiment</th>
                                <th>Form</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_responses as $response): ?>
                                <tr class="<?= $response["sentiment"] ===
                                "positive"
                                    ? "positive-bg"
                                    : ($response["sentiment"] === "negative"
                                        ? "negative-bg"
                                        : "neutral-bg") ?>">
                                    <td><?= date(
                                        "M j, Y h:i A",
                                        strtotime($response["created_at"])
                                    ) ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <span
                                                class="fw-bold me-2"><?= number_format(
                                                    $response["rating_average"],
                                                    1
                                                ) ?></span>
                                            <div class="star-rating">
                                                <?php for (
                                                    $i = 1;
                                                    $i <= 5;
                                                    $i++
                                                ): ?>
                                                    <i class="fas fa-star <?= $i <=
                                                    round(
                                                        $response[
                                                            "rating_average"
                                                        ]
                                                    )
                                                        ? "text-warning"
                                                        : "text-muted" ?>"
                                                        style="font-size: 0.8rem;"></i>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $response[
                                            "sentiment"
                                        ] === "positive"
                                            ? "success"
                                            : ($response["sentiment"] ===
                                            "negative"
                                                ? "danger"
                                                : "warning") ?>">
                                            <?= ucfirst(
                                                $response["sentiment"]
                                            ) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars(
                                        $response["form_title"]
                                    ) ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary view-response"
                                            data-id="<?= $response["id"] ?>">
                                            <i class="fas fa-eye me-1"></i> View
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

    <!-- Response Modal -->
    <div class="modal fade" id="responseModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Review Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="response-details">
                    <!-- Content will be loaded via AJAX -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>



    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>

    <!-- Previous PHP and HTML code remains the same until the JavaScript section -->
    <script>
        $(document).ready(function () {
            // Initialize DataTable
            $('#responses-table').DataTable({
                order: [[0, 'desc']],
                responsive: true,
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search responses...",
                },
                dom: '<"top"f>rt<"bottom"lip><"clear">'
            });

            // View response modal - fixed with better error handling
            $(document).on('click', '.view-response', function () {
                const responseId = $(this).data('id');
                $('#response-details').html('<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div><p class="mt-2">Loading review details...</p></div>');
                $('#responseModal').modal('show');

                $.ajax({
                    url: '../get_response.php',
                    type: 'GET',
                    data: { id: responseId },
                    success: function (data) {
                        $('#response-details').html(data);
                    },
                    error: function (xhr, status, error) {
                        $('#response-details').html(
                            '<div class="alert alert-danger">' +
                            '<i class="fas fa-exclamation-circle me-2"></i>' +
                            'Failed to load response details. Error: ' + error +
                            '</div>'
                        );
                        console.error("AJAX Error:", status, error);
                    }
                });
            });

            // Auto-refresh today's reviews every 60 seconds
            setInterval(function () {
                $.get('get_today_reviews.php?stall_id=<?= $stall_id ?>', function (data) {
                    if (data && data.length > 0) {
                        $('.real-time-badge').addClass('pulse');
                        setTimeout(function () {
                            $('.real-time-badge').removeClass('pulse');
                        }, 1000);
                    }
                }).fail(function () {
                    console.error("Failed to refresh today's reviews");
                });
            }, 30000);
        });


    </script>
</body>

</html>
